<?php

namespace DigitalInvoice\Submission\Adapters;

use DigitalInvoice\Invoice;
use DigitalInvoice\Submission\Result\SubmissionResult;
use DigitalInvoice\Submission\Result\InvoiceStatus;
use DigitalInvoice\Submission\Exception\SubmissionException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Dedicated submitter for SuperPDP (France PDP #0111)
 *
 * SuperPDP is "La Plateforme Agréée la plus simple et la moins chère"
 * - Free for up to 1,000 invoices/month
 * - €0.0025 per invoice for API usage (volume-based)
 * - ISO 27001 certified
 * - Peppol AP and SMP certified
 * - Registration number: 0111
 *
 * @see https://www.superpdp.tech
 * @see https://api.superpdp.tech
 */
class SuperPdpSubmitter extends FrancePdpSubmitter
{
    /**
     * @param string $environment 'sandbox' or 'production'
     */
    public function __construct(string $environment = 'production')
    {
        // Always use 'superpdp' as provider
        parent::__construct('superpdp', $environment);
    }

    /**
     * {@inheritDoc}
     *
     * SuperPDP-specific submission implementation
     */
    public function submit(Invoice $invoice): SubmissionResult
    {
        $this->refreshTokenIfNeeded();

        try {
            $invoiceXml = $this->transformInvoice($invoice);

            // Step 1: Validate invoice (optional but recommended)
            $this->validateInvoiceViaSuperPdp($invoiceXml);

            // Step 2: Submit invoice to SuperPDP
            // SuperPDP uses multipart/form-data for invoice upload
            $response = $this->httpClient->post('/v1.beta/invoices', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $invoiceXml,
                        'filename' => 'invoice.xml',
                        'headers' => [
                            'Content-Type' => 'application/xml',
                        ],
                    ],
                ],
            ]);

            $responseBody = json_decode($response->getBody()->getContents(), true);
            return $this->parseSubmissionResponse($responseBody);

        } catch (GuzzleException $e) {
            $this->log('error', 'SuperPDP submission failed', ['error' => $e->getMessage()]);

            throw new SubmissionException(
                'Failed to submit invoice to SuperPDP: ' . $e->getMessage(),
                $e->getCode(),
                $e,
                ['provider' => 'superpdp']
            );
        }
    }

    /**
     * Validate invoice via SuperPDP API (optional pre-check)
     *
     * @param string $invoiceXml
     * @return array Validation result
     * @throws SubmissionException
     */
    private function validateInvoiceViaSuperPdp(string $invoiceXml): array
    {
        try {
            $response = $this->httpClient->post('/v1.beta/validation_reports', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $invoiceXml,
                        'filename' => 'invoice.xml',
                        'headers' => [
                            'Content-Type' => 'application/xml',
                        ],
                    ],
                ],
            ]);

            $validationResult = json_decode($response->getBody()->getContents(), true);

            if (!empty($validationResult['errors'])) {
                $this->log('warning', 'Invoice validation warnings', [
                    'errors' => $validationResult['errors'],
                ]);
            }

            return $validationResult;

        } catch (GuzzleException $e) {
            // Validation failure is not fatal, just log it
            $this->log('warning', 'Invoice validation failed', [
                'error' => $e->getMessage(),
            ]);

            return ['errors' => [], 'warnings' => ['Validation check failed']];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(string $referenceId): InvoiceStatus
    {
        $this->refreshTokenIfNeeded();

        try {
            // Get invoice details by ID
            $response = $this->httpClient->get("/v1.beta/invoices/{$referenceId}");
            $invoiceData = json_decode($response->getBody()->getContents(), true);

            return $this->parseStatusResponse($invoiceData);

        } catch (GuzzleException $e) {
            throw new SubmissionException(
                'Failed to get invoice status from SuperPDP: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get company information (useful for verification)
     *
     * @return array Company details
     * @throws SubmissionException
     */
    public function getCompanyInfo(): array
    {
        $this->refreshTokenIfNeeded();

        try {
            $response = $this->httpClient->get('/v1.beta/companies/me');
            return json_decode($response->getBody()->getContents(), true);

        } catch (GuzzleException $e) {
            throw new SubmissionException(
                'Failed to get company info from SuperPDP: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Generate a test invoice (useful for testing)
     *
     * @return string Invoice XML
     * @throws SubmissionException
     */
    public function generateTestInvoice(): string
    {
        $this->refreshTokenIfNeeded();

        try {
            $response = $this->httpClient->get('/v1.beta/invoices/generate_test_invoice');
            return $response->getBody()->getContents();

        } catch (GuzzleException $e) {
            throw new SubmissionException(
                'Failed to generate test invoice from SuperPDP: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * List invoices with pagination
     *
     * @param string|null $startingAfterId Starting after this invoice ID (for pagination)
     * @param string $order 'asc' or 'desc'
     * @param int $limit Number of invoices to return
     * @return array List of invoices
     * @throws SubmissionException
     */
    public function listInvoices(?string $startingAfterId = null, string $order = 'desc', int $limit = 50): array
    {
        $this->refreshTokenIfNeeded();

        try {
            $queryParams = [
                'order' => $order,
                'limit' => $limit,
            ];

            if ($startingAfterId) {
                $queryParams['starting_after_id'] = $startingAfterId;
            }

            $response = $this->httpClient->get('/v1.beta/invoices', [
                'query' => $queryParams,
            ]);

            return json_decode($response->getBody()->getContents(), true);

        } catch (GuzzleException $e) {
            throw new SubmissionException(
                'Failed to list invoices from SuperPDP: ' . $e->getMessage(),
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
        // SuperPDP returns invoice data with status
        $invoiceId = $response['id'] ?? null;
        $status = $response['status'] ?? 'unknown';

        // Map SuperPDP status to our standard statuses
        $standardStatus = $this->mapSuperPdpStatus($status);

        return new SubmissionResult(
            success: !empty($invoiceId),
            referenceId: $invoiceId,
            governmentId: $invoiceId,
            status: $standardStatus,
            error: $response['error'] ?? null,
            rawResponse: $response,
            metadata: [
                'pdp_provider' => 'superpdp',
                'pdp_registration' => '0111',
                'created_at' => $response['created_at'] ?? null,
                'updated_at' => $response['updated_at'] ?? null,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function parseStatusResponse($response): InvoiceStatus
    {
        $status = $response['status'] ?? 'unknown';
        $standardStatus = $this->mapSuperPdpStatus($status);

        return new InvoiceStatus(
            status: $standardStatus,
            statusDate: $response['updated_at'] ?? $response['created_at'] ?? null,
            rejectionReason: $response['rejection_reason'] ?? null,
            rawResponse: $response,
            metadata: [
                'pdp_provider' => 'superpdp',
                'invoice_id' => $response['id'] ?? null,
            ]
        );
    }

    /**
     * Map SuperPDP status to standard status
     *
     * @param string $superpdpStatus
     * @return string
     */
    private function mapSuperPdpStatus(string $superpdpStatus): string
    {
        return match (strtolower($superpdpStatus)) {
            'submitted', 'pending' => 'pending',
            'validated', 'accepted', 'delivered' => 'accepted',
            'rejected', 'failed' => 'rejected',
            default => $superpdpStatus,
        };
    }

    /**
     * {@inheritDoc}
     */
    protected function performCountrySpecificValidation(Invoice $invoice): array
    {
        $errors = [];
        $warnings = [];

        // SuperPDP requires UBL format
        if (!$invoice->isUbl()) {
            $errors[] = 'SuperPDP requires UBL format invoices';
        }

        // Add France-specific validation
        $franceValidation = parent::performCountrySpecificValidation($invoice);
        $errors = array_merge($errors, $franceValidation['errors'] ?? []);
        $warnings = array_merge($warnings, $franceValidation['warnings'] ?? []);

        return ['errors' => $errors, 'warnings' => $warnings];
    }
}
