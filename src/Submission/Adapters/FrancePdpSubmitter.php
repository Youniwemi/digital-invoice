<?php

namespace DigitalInvoice\Submission\Adapters;

use DigitalInvoice\Invoice;
use DigitalInvoice\Submission\AbstractSubmitter;
use DigitalInvoice\Submission\Result\SubmissionResult;
use DigitalInvoice\Submission\Result\InvoiceStatus;
use DigitalInvoice\Submission\Exception\SubmissionException;
use DigitalInvoice\Submission\Exception\AuthenticationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Submitter for France's PDP (Plateforme Agréée) system
 *
 * Implements the AFNOR XP Z12-013 standard for B2B e-invoicing via certified platforms.
 * Supports any PDP provider (Basware, Tradeshift, Pagero, etc.)
 */
class FrancePdpSubmitter extends AbstractSubmitter
{
    private string $pdpProvider;
    private ?Client $httpClient = null;

    private const PDP_URLS = [
        'basware' => [
            'sandbox' => 'https://sandbox-api.basware.fr/pdp',
            'production' => 'https://api.basware.fr/pdp',
        ],
        'tradeshift' => [
            'sandbox' => 'https://sandbox-api.tradeshift.com/pdp',
            'production' => 'https://api.tradeshift.com/pdp',
        ],
        'pagero' => [
            'sandbox' => 'https://sandbox-api.pagero.com/pdp',
            'production' => 'https://api.pagero.com/pdp',
        ],
        // Add more PDPs as needed
    ];

    /**
     * @param string $pdpProvider PDP provider name ('basware', 'tradeshift', etc.)
     * @param string $environment 'sandbox' or 'production'
     */
    public function __construct(string $pdpProvider, string $environment = 'production')
    {
        parent::__construct($environment);
        $this->pdpProvider = strtolower($pdpProvider);

        if (!isset(self::PDP_URLS[$this->pdpProvider])) {
            throw new \InvalidArgumentException(
                "Unknown PDP provider: {$pdpProvider}. Supported: " . implode(', ', array_keys(self::PDP_URLS))
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getApiBaseUrl(): string
    {
        return self::PDP_URLS[$this->pdpProvider][$this->environment];
    }

    /**
     * {@inheritDoc}
     */
    protected function performAuthentication(): void
    {
        $this->ensureAuthenticated();

        // For most PDPs, authentication is via Bearer token (API key)
        // Some may require OAuth2 - implement as needed per PDP
        $apiKey = $this->credentials['api_key'] ?? null;

        if (!$apiKey) {
            throw new AuthenticationException('API key is required for PDP authentication');
        }

        // Initialize HTTP client with authentication
        $this->httpClient = new Client([
            'base_uri' => $this->getApiBaseUrl(),
            'timeout' => 60,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ],
        ]);

        $this->log('info', 'Authenticated with PDP', ['provider' => $this->pdpProvider]);
    }

    /**
     * {@inheritDoc}
     */
    protected function transformInvoice(Invoice $invoice): string
    {
        // France accepts UBL 2.1, Factur-X, or CII
        // We'll use UBL as it's the most common for PDP submissions
        return $invoice->getXml();
    }

    /**
     * {@inheritDoc}
     */
    public function submit(Invoice $invoice): SubmissionResult
    {
        $this->refreshTokenIfNeeded();

        try {
            $invoiceXml = $this->transformInvoice($invoice);

            // Query Annuaire for recipient routing (optional but recommended)
            $recipientSiret = $this->extractRecipientSiret($invoice);

            // Submit invoice using XP Z12-013 Flow 2 (invoice emission)
            $response = $this->httpClient->post('/api/v1/flows/invoices', [
                'json' => [
                    'format' => 'UBL',
                    'version' => '2.1',
                    'content' => base64_encode($invoiceXml),
                    'recipient' => [
                        'siret' => $recipientSiret,
                    ],
                    'metadata' => [
                        'invoice_number' => $this->extractInvoiceNumber($invoice),
                        'issue_date' => $this->extractIssueDate($invoice),
                    ],
                ],
            ]);

            $responseBody = json_decode($response->getBody()->getContents(), true);
            return $this->parseSubmissionResponse($responseBody);

        } catch (GuzzleException $e) {
            $this->log('error', 'PDP submission failed', ['error' => $e->getMessage()]);

            throw new SubmissionException(
                'Failed to submit invoice to PDP: ' . $e->getMessage(),
                $e->getCode(),
                $e,
                ['provider' => $this->pdpProvider]
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(string $referenceId): InvoiceStatus
    {
        $this->refreshTokenIfNeeded();

        try {
            // XP Z12-013 Flow 6 (lifecycle status)
            $response = $this->httpClient->get("/api/v1/flows/{$referenceId}/status");
            $responseBody = json_decode($response->getBody()->getContents(), true);

            return $this->parseStatusResponse($responseBody);

        } catch (GuzzleException $e) {
            throw new SubmissionException(
                'Failed to get invoice status: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Query the Annuaire directory for recipient information
     *
     * @param string $siret Recipient SIRET number
     * @return array Recipient information including PDP routing
     */
    public function queryAnnuaire(string $siret): array
    {
        $this->refreshTokenIfNeeded();

        try {
            // XP Z12-013 Flow 11 (directory consultation)
            $response = $this->httpClient->get("/api/v1/annuaire/{$siret}");
            return json_decode($response->getBody()->getContents(), true);

        } catch (GuzzleException $e) {
            throw new SubmissionException(
                'Failed to query Annuaire: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function parseSubmissionResponse($response): SubmissionResult
    {
        return new SubmissionResult(
            success: $response['success'] ?? true,
            referenceId: $response['flow_id'] ?? $response['reference_id'] ?? null,
            governmentId: $response['government_id'] ?? $response['flow_id'] ?? null,
            status: $response['status'] ?? 'submitted',
            error: $response['error'] ?? null,
            rawResponse: $response,
            metadata: [
                'pdp_provider' => $this->pdpProvider,
                'transmission_id' => $response['transmission_id'] ?? null,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function parseStatusResponse($response): InvoiceStatus
    {
        return new InvoiceStatus(
            status: $response['status'] ?? 'unknown',
            statusDate: $response['status_date'] ?? $response['updated_at'] ?? null,
            rejectionReason: $response['rejection_reason'] ?? null,
            statusHistory: $response['status_history'] ?? [],
            rawResponse: $response,
            metadata: [
                'pdp_provider' => $this->pdpProvider,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function performCountrySpecificValidation(Invoice $invoice): array
    {
        $errors = [];
        $warnings = [];

        // Validate SIRET format (14 digits)
        $recipientSiret = $this->extractRecipientSiret($invoice);
        if (empty($recipientSiret)) {
            $errors[] = 'Recipient SIRET is required for French e-invoicing';
        } elseif (!preg_match('/^\d{14}$/', $recipientSiret)) {
            $errors[] = 'Recipient SIRET must be exactly 14 digits';
        }

        // Check if invoice is UBL format
        if (!$invoice->isUbl()) {
            $warnings[] = 'Invoice is not in UBL format. Consider using UBL for better PDP compatibility.';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Extract recipient SIRET from invoice
     *
     * @param Invoice $invoice
     * @return string|null
     */
    private function extractRecipientSiret(Invoice $invoice): ?string
    {
        // This would need to extract from the invoice XML
        // For now, return a placeholder - implement based on your Invoice structure
        $xml = $invoice->getXml();

        // Parse XML to find SIRET in buyer identification
        // Implementation depends on where SIRET is stored in your Invoice class

        return null; // TODO: Implement SIRET extraction
    }

    /**
     * Extract invoice number from invoice
     *
     * @param Invoice $invoice
     * @return string
     */
    private function extractInvoiceNumber(Invoice $invoice): string
    {
        // Access invoice number from Invoice class
        // This is a placeholder - adjust based on your Invoice structure
        return ''; // TODO: Implement invoice number extraction
    }

    /**
     * Extract issue date from invoice
     *
     * @param Invoice $invoice
     * @return string
     */
    private function extractIssueDate(Invoice $invoice): string
    {
        // Access issue date from Invoice class
        return date('Y-m-d'); // TODO: Implement date extraction
    }

    /**
     * {@inheritDoc}
     */
    public function getCountryCode(): string
    {
        return 'FR';
    }

    /**
     * {@inheritDoc}
     */
    public function getSubmissionModel(): string
    {
        return 'intermediary';
    }

    /**
     * {@inheritDoc}
     */
    public function requiresClearance(): bool
    {
        return false; // France Y-model doesn't require clearance before sending
    }
}
