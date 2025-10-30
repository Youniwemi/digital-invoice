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
 * Submitter for Saudi Arabia's ZATCA (Fatoora) e-invoicing system
 *
 * Implements Phase 2 integration with real-time clearance (B2B) and reporting (B2C).
 * Requires digital signature and cryptographic stamp.
 */
class SaudiZatcaSubmitter extends AbstractSubmitter
{
    private ?Client $httpClient = null;
    private ?string $certificateSerialNumber = null;

    private const API_URLS = [
        'sandbox' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal',
        'production' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation',
    ];

    /**
     * {@inheritDoc}
     */
    protected function getApiBaseUrl(): string
    {
        return self::API_URLS[$this->environment];
    }

    /**
     * {@inheritDoc}
     */
    protected function performAuthentication(): void
    {
        $this->ensureAuthenticated();

        $certificate = $this->credentials['certificate'] ?? null;
        $privateKey = $this->credentials['private_key'] ?? null;
        $secret = $this->credentials['secret'] ?? null; // OTP or secret for compliance CSID

        if (!$certificate || !$privateKey || !$secret) {
            throw new AuthenticationException(
                'Certificate, private key, and secret are required for ZATCA authentication'
            );
        }

        // Extract certificate serial number
        $certData = openssl_x509_parse($certificate);
        $this->certificateSerialNumber = $certData['serialNumberHex'] ?? null;

        // Initialize HTTP client with certificate authentication
        $this->httpClient = new Client([
            'base_uri' => $this->getApiBaseUrl(),
            'timeout' => 60,
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'en',
                'Accept-Version' => 'V2',
                'Content-Type' => 'application/json',
            ],
            // ZATCA uses certificate-based authentication
            'cert' => is_file($certificate) ? $certificate : null,
            'ssl_key' => is_file($privateKey) ? $privateKey : null,
        ]);

        $this->log('info', 'Authenticated with ZATCA', [
            'environment' => $this->environment,
            'certificate_serial' => $this->certificateSerialNumber,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    protected function transformInvoice(Invoice $invoice): string
    {
        // ZATCA requires specific XML format with cryptographic stamp
        // This is a simplified version - you'd need to use php-zatca-xml library
        $invoiceXml = $invoice->getXml();

        // Add ZATCA-specific elements:
        // - UUID (unique invoice identifier)
        // - QR code
        // - Cryptographic stamp
        // - Digital signature

        // TODO: Integrate with Saleh7/php-zatca-xml or similar library

        return $invoiceXml;
    }

    /**
     * {@inheritDoc}
     */
    public function submit(Invoice $invoice): SubmissionResult
    {
        $this->refreshTokenIfNeeded();

        try {
            $invoiceXml = $this->transformInvoice($invoice);

            // Determine if this is B2B (clearance) or B2C (reporting)
            $isClearance = $this->isClearanceInvoice($invoice);

            $endpoint = $isClearance
                ? '/invoices/clearance/single'  // B2B - real-time clearance
                : '/invoices/reporting/single';  // B2C - reporting

            // Submit to ZATCA
            $response = $this->httpClient->post($endpoint, [
                'json' => [
                    'invoiceHash' => $this->calculateInvoiceHash($invoiceXml),
                    'uuid' => $this->generateUuid(),
                    'invoice' => base64_encode($invoiceXml),
                ],
            ]);

            $responseBody = json_decode($response->getBody()->getContents(), true);
            return $this->parseSubmissionResponse($responseBody);

        } catch (GuzzleException $e) {
            $this->log('error', 'ZATCA submission failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            throw new SubmissionException(
                'Failed to submit invoice to ZATCA: ' . $e->getMessage(),
                $e->getCode(),
                $e
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
            // ZATCA doesn't have a specific status endpoint
            // Status is typically returned in the clearance/reporting response
            // This is a placeholder implementation

            return new InvoiceStatus(
                status: 'unknown',
                statusDate: date('c'),
                rawResponse: [],
                metadata: ['note' => 'ZATCA status must be checked via original submission response']
            );

        } catch (GuzzleException $e) {
            throw new SubmissionException(
                'Failed to get invoice status: ' . $e->getMessage(),
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
        $validationResults = $response['validationResults'] ?? [];
        $clearanceStatus = $response['clearanceStatus'] ?? null;
        $reportingStatus = $response['reportingStatus'] ?? null;

        $isSuccess = ($clearanceStatus === 'CLEARED' || $reportingStatus === 'REPORTED');
        $status = $clearanceStatus ?? $reportingStatus ?? 'unknown';

        return new SubmissionResult(
            success: $isSuccess,
            referenceId: $response['uuid'] ?? null,
            governmentId: $response['uuid'] ?? null,
            status: strtolower($status),
            qrCode: $response['qrCode'] ?? null,
            signedXml: isset($response['clearedInvoice']) ? base64_decode($response['clearedInvoice']) : null,
            error: $isSuccess ? null : $this->formatValidationErrors($validationResults),
            rawResponse: $response,
            metadata: [
                'validation_results' => $validationResults,
                'certificate_serial' => $this->certificateSerialNumber,
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
            statusDate: $response['timestamp'] ?? date('c'),
            rawResponse: $response
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function performCountrySpecificValidation(Invoice $invoice): array
    {
        $errors = [];
        $warnings = [];

        // Validate VAT number format (15 digits starting with 3 and ending with 3)
        // Check if cryptographic stamp is present
        // Validate QR code generation

        // TODO: Implement ZATCA-specific validations

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Determine if invoice requires clearance (B2B) or reporting (B2C)
     *
     * @param Invoice $invoice
     * @return bool True if B2B (clearance required)
     */
    private function isClearanceInvoice(Invoice $invoice): bool
    {
        // Simplified: Check invoice type
        // Standard tax invoices (B2B) require clearance
        // Simplified tax invoices (B2C) require reporting only

        // TODO: Implement based on invoice type code
        return true; // Default to clearance
    }

    /**
     * Calculate invoice hash for ZATCA
     *
     * @param string $invoiceXml
     * @return string
     */
    private function calculateInvoiceHash(string $invoiceXml): string
    {
        // ZATCA requires SHA-256 hash of the invoice
        return hash('sha256', $invoiceXml);
    }

    /**
     * Generate UUID v4 for invoice
     *
     * @return string
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Format validation errors from ZATCA response
     *
     * @param array $validationResults
     * @return string
     */
    private function formatValidationErrors(array $validationResults): string
    {
        if (empty($validationResults['errorMessages'])) {
            return '';
        }

        return implode('; ', array_map(
            fn($error) => $error['message'] ?? 'Unknown error',
            $validationResults['errorMessages']
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function getCountryCode(): string
    {
        return 'SA';
    }

    /**
     * {@inheritDoc}
     */
    public function getSubmissionModel(): string
    {
        return 'ctc_clearance';
    }

    /**
     * {@inheritDoc}
     */
    public function requiresClearance(): bool
    {
        return true; // B2B invoices require clearance before sending to customer
    }
}
