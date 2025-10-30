<?php

namespace DigitalInvoice\Submission;

use DigitalInvoice\Invoice;
use DigitalInvoice\Submission\Result\SubmissionResult;
use DigitalInvoice\Submission\Result\InvoiceStatus;
use DigitalInvoice\Submission\Result\ValidationResult;

/**
 * Interface for e-invoice submission to tax authorities and platforms
 *
 * This interface provides a unified API for submitting invoices to various
 * countries' tax systems, regardless of their specific implementation details.
 */
interface SubmitterInterface
{
    /**
     * Submit an invoice to the tax authority/platform
     *
     * @param Invoice $invoice The invoice to submit
     * @return SubmissionResult Result containing reference IDs, status, and any errors
     * @throws SubmissionException If submission fails
     */
    public function submit(Invoice $invoice): SubmissionResult;

    /**
     * Get the current status of a previously submitted invoice
     *
     * @param string $referenceId The reference ID returned from submit()
     * @return InvoiceStatus Current status of the invoice
     * @throws SubmissionException If status query fails
     */
    public function getStatus(string $referenceId): InvoiceStatus;

    /**
     * Authenticate with the tax authority/platform
     *
     * @param array $credentials Authentication credentials (API keys, certificates, etc.)
     * @return void
     * @throws AuthenticationException If authentication fails
     */
    public function authenticate(array $credentials): void;

    /**
     * Validate an invoice before submission (optional pre-check)
     *
     * @param Invoice $invoice The invoice to validate
     * @return ValidationResult Validation result with errors/warnings
     */
    public function validateInvoice(Invoice $invoice): ValidationResult;

    /**
     * Get the country code this submitter handles
     *
     * @return string ISO 3166-1 alpha-2 country code (e.g., 'IT', 'FR', 'SA')
     */
    public function getCountryCode(): string;

    /**
     * Get the submission model type
     *
     * @return string One of: 'ctc_clearance', 'peppol', 'intermediary', 'reporting'
     */
    public function getSubmissionModel(): string;

    /**
     * Check if this submitter requires real-time clearance
     *
     * @return bool True if invoice must be cleared before sending to customer
     */
    public function requiresClearance(): bool;

    /**
     * Set the environment (sandbox or production)
     *
     * @param string $environment 'sandbox' or 'production'
     * @return void
     */
    public function setEnvironment(string $environment): void;
}
