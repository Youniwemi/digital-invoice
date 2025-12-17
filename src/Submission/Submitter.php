<?php

namespace DigitalInvoice\Submission;

use DigitalInvoice\Invoice;
use DigitalInvoice\Submission\Result\SubmissionResult;
use DigitalInvoice\Submission\Result\InvoiceStatus;
use DigitalInvoice\Submission\Result\ValidationResult;
use DigitalInvoice\Submission\Exception\SubmissionException;

/**
 * Unified Submitter for all countries - Single API entry point
 *
 * This class provides a consistent API for submitting invoices to any country,
 * similar to how the Invoice class provides a unified API for generating invoices.
 *
 * Usage:
 *   $submitter = new Submitter('FR', 'superpdp');
 *   $submitter->authenticate(['client_id' => 'xxx', 'client_secret' => 'yyy']);
 *   $result = $submitter->submit($invoice);
 *
 * Just like Invoice class:
 *   $invoice = new Invoice(..., profile: Invoice::UBL_PEPOOL);
 *   $xml = $invoice->getXml();
 *
 * One class, multiple countries/providers via parameters.
 */
class Submitter implements SubmitterInterface
{
    // Country constants (similar to Invoice::FACTURX_BASIC, etc.)
    public const FRANCE_SUPERPDP = 'FR:superpdp';
    public const FRANCE_BASWARE = 'FR:basware';
    public const FRANCE_TRADESHIFT = 'FR:tradeshift';
    public const FRANCE_PAGERO = 'FR:pagero';
    public const SAUDI_ZATCA = 'SA:zatca';
    // public const ITALY_SDI = 'IT:sdi';
    // public const MALAYSIA_MYINVOIS = 'MY:myinvois';

    private SubmitterInterface $adapter;
    private string $countryCode;
    private ?string $provider;

    /**
     * Create a new Submitter for any country
     *
     * @param string $countryOrProfile Country code ('FR', 'SA') or profile constant (Submitter::FRANCE_SUPERPDP)
     * @param string|null $provider Provider name for countries with multiple options ('superpdp', 'basware', etc.)
     * @param string $environment 'sandbox' or 'production'
     *
     * Examples:
     *   new Submitter('FR', 'superpdp')              // France with SuperPDP
     *   new Submitter('SA')                          // Saudi Arabia (only one provider)
     *   new Submitter(Submitter::FRANCE_SUPERPDP)   // Using constant
     *   new Submitter('FR', 'basware', 'sandbox')   // Sandbox mode
     */
    public function __construct(
        string $countryOrProfile,
        ?string $provider = null,
        string $environment = 'production'
    ) {
        // Support both 'FR:superpdp' and ('FR', 'superpdp') formats
        if (str_contains($countryOrProfile, ':')) {
            [$this->countryCode, $this->provider] = explode(':', $countryOrProfile, 2);
        } else {
            $this->countryCode = strtoupper($countryOrProfile);
            $this->provider = $provider ? strtolower($provider) : null;
        }

        // Use factory internally to create appropriate adapter
        $config = array_filter([
            'pdp_provider' => $this->provider,
            'environment' => $environment,
        ]);

        $this->adapter = SubmitterFactory::create($this->countryCode, $config);
    }

    /**
     * {@inheritDoc}
     */
    public function submit(Invoice $invoice): SubmissionResult
    {
        return $this->adapter->submit($invoice);
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(string $referenceId): InvoiceStatus
    {
        return $this->adapter->getStatus($referenceId);
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(array $credentials): void
    {
        $this->adapter->authenticate($credentials);
    }

    /**
     * {@inheritDoc}
     */
    public function validateInvoice(Invoice $invoice): ValidationResult
    {
        return $this->adapter->validateInvoice($invoice);
    }

    /**
     * {@inheritDoc}
     */
    public function getCountryCode(): string
    {
        return $this->adapter->getCountryCode();
    }

    /**
     * {@inheritDoc}
     */
    public function getSubmissionModel(): string
    {
        return $this->adapter->getSubmissionModel();
    }

    /**
     * {@inheritDoc}
     */
    public function requiresClearance(): bool
    {
        return $this->adapter->requiresClearance();
    }

    /**
     * {@inheritDoc}
     */
    public function setEnvironment(string $environment): void
    {
        $this->adapter->setEnvironment($environment);
    }

    /**
     * Get the provider name (if applicable)
     *
     * @return string|null
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * Get the underlying adapter (for advanced usage)
     *
     * @return SubmitterInterface
     */
    public function getAdapter(): SubmitterInterface
    {
        return $this->adapter;
    }

    /**
     * Check if a country/provider combination is supported
     *
     * @param string $country Country code
     * @param string|null $provider Provider name
     * @return bool
     */
    public static function isSupported(string $country, ?string $provider = null): bool
    {
        return SubmitterFactory::isSupported($country);
    }

    /**
     * Get list of supported countries and providers
     *
     * @return array
     */
    public static function getSupportedCountries(): array
    {
        return SubmitterFactory::getSupportedCountries();
    }

    /**
     * Get required credentials for a country/provider
     *
     * @param string $country Country code
     * @param string|null $provider Provider name
     * @return array
     */
    public static function getRequiredCredentials(string $country, ?string $provider = null): array
    {
        $countries = self::getSupportedCountries();
        $country = strtoupper($country);

        if (!isset($countries[$country])) {
            return [];
        }

        // If provider-specific requirements exist
        if ($provider && isset($countries[$country]['supported_providers'][$provider]['required'])) {
            return $countries[$country]['supported_providers'][$provider]['required'];
        }

        // Otherwise return general requirements
        return $countries[$country]['required_config'] ?? [];
    }
}
