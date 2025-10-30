<?php

namespace DigitalInvoice\Submission;

use DigitalInvoice\Invoice;
use DigitalInvoice\Submission\Result\SubmissionResult;
use DigitalInvoice\Submission\Result\InvoiceStatus;
use DigitalInvoice\Submission\Result\ValidationResult;
use DigitalInvoice\Submission\Exception\SubmissionException;
use DigitalInvoice\Submission\Exception\AuthenticationException;

/**
 * Abstract base class for all e-invoice submitters
 *
 * Provides common functionality and enforces the contract for country-specific implementations
 */
abstract class AbstractSubmitter implements SubmitterInterface
{
    protected string $environment = 'production';
    protected array $credentials = [];
    protected ?string $accessToken = null;
    protected ?int $tokenExpiry = null;

    /**
     * @param string $environment 'sandbox' or 'production'
     */
    public function __construct(string $environment = 'production')
    {
        $this->setEnvironment($environment);
    }

    /**
     * Get the API base URL for the current environment
     *
     * @return string Base URL for API calls
     */
    abstract protected function getApiBaseUrl(): string;

    /**
     * Transform invoice to the format required by this country
     *
     * @param Invoice $invoice
     * @return string Transformed invoice data (XML, JSON, etc.)
     */
    abstract protected function transformInvoice(Invoice $invoice): string;

    /**
     * Parse the API response into a standard SubmissionResult
     *
     * @param mixed $response Raw API response
     * @return SubmissionResult
     */
    abstract protected function parseSubmissionResponse($response): SubmissionResult;

    /**
     * Parse the status response into a standard InvoiceStatus
     *
     * @param mixed $response Raw status response
     * @return InvoiceStatus
     */
    abstract protected function parseStatusResponse($response): InvoiceStatus;

    /**
     * {@inheritDoc}
     */
    public function setEnvironment(string $environment): void
    {
        if (!in_array($environment, ['sandbox', 'production'])) {
            throw new \InvalidArgumentException("Environment must be 'sandbox' or 'production'");
        }
        $this->environment = $environment;
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(array $credentials): void
    {
        $this->credentials = $credentials;

        // Perform authentication (to be implemented by child classes if needed)
        $this->performAuthentication();
    }

    /**
     * Perform the actual authentication (override in child classes)
     *
     * @return void
     * @throws AuthenticationException
     */
    protected function performAuthentication(): void
    {
        // Default: no authentication needed
        // Override in subclasses that require OAuth, tokens, etc.
    }

    /**
     * Check if authentication token is still valid
     *
     * @return bool
     */
    protected function isTokenValid(): bool
    {
        if ($this->accessToken === null) {
            return false;
        }

        if ($this->tokenExpiry === null) {
            return true;
        }

        return time() < $this->tokenExpiry;
    }

    /**
     * Refresh authentication token if needed
     *
     * @return void
     */
    protected function refreshTokenIfNeeded(): void
    {
        if (!$this->isTokenValid()) {
            $this->performAuthentication();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function validateInvoice(Invoice $invoice): ValidationResult
    {
        $errors = [];
        $warnings = [];

        // Basic validation that applies to all countries
        if (empty($this->credentials)) {
            $warnings[] = 'No authentication credentials provided';
        }

        // Country-specific validation (override in child classes)
        $countryValidation = $this->performCountrySpecificValidation($invoice);
        $errors = array_merge($errors, $countryValidation['errors'] ?? []);
        $warnings = array_merge($warnings, $countryValidation['warnings'] ?? []);

        return new ValidationResult(
            valid: empty($errors),
            errors: $errors,
            warnings: $warnings
        );
    }

    /**
     * Perform country-specific validation (override in child classes)
     *
     * @param Invoice $invoice
     * @return array ['errors' => [], 'warnings' => []]
     */
    protected function performCountrySpecificValidation(Invoice $invoice): array
    {
        return ['errors' => [], 'warnings' => []];
    }

    /**
     * Log submission activity (override for custom logging)
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        // Default: no logging
        // Override in subclasses or inject a logger
    }

    /**
     * Check if credentials are set
     *
     * @return void
     * @throws AuthenticationException
     */
    protected function ensureAuthenticated(): void
    {
        if (empty($this->credentials)) {
            throw new AuthenticationException('No credentials provided. Call authenticate() first.');
        }
    }
}
