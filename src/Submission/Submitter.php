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
 *   $submitter = new Submitter('superpdp');  // France SuperPDP
 *   $submitter = new Submitter('SA');        // Saudi Arabia
 *   $submitter->authenticate($credentials);
 *   $result = $submitter->submit($invoice);
 *
 * Or using constants:
 *   $submitter = new Submitter(Submitter::SUPERPDP);
 *   $submitter = new Submitter(Submitter::SAUDI_ZATCA);
 *
 * Just like Invoice class constants:
 *   $invoice = new Invoice(..., profile: Invoice::UBL_PEPPOL);
 *   $invoice = new Invoice(..., profile: Invoice::FACTURX_BASIC);
 */
class Submitter implements SubmitterInterface
{
    // Provider constants - discoverable like Invoice::FACTURX_BASIC
    // French PDPs
    public const SUPERPDP = 'superpdp';
    public const BASWARE = 'basware';
    public const TRADESHIFT = 'tradeshift';
    public const PAGERO = 'pagero';

    // Country codes for centralized systems
    public const SAUDI_ZATCA = 'SA';
    public const ITALY_SDI = 'IT';
    public const MALAYSIA_MYINVOIS = 'MY';

    private SubmitterInterface $adapter;
    private string $provider;
    private string $countryCode;

    /**
     * Create a new Submitter
     *
     * @param string $provider Provider name or country code:
     *                        - French PDPs: 'superpdp', 'basware', 'tradeshift', 'pagero'
     *                        - Centralized: 'SA' (Saudi), 'IT' (Italy), 'MY' (Malaysia)
     *                        Or use constants: Submitter::SUPERPDP, Submitter::SAUDI_ZATCA
     * @param string $environment 'sandbox' or 'production'
     *
     * Examples:
     *   new Submitter('superpdp')                    // France SuperPDP
     *   new Submitter('SA')                          // Saudi Arabia
     *   new Submitter(Submitter::SUPERPDP)          // Using constant
     *   new Submitter('basware', 'sandbox')         // Sandbox mode
     */
    public function __construct(
        string $provider,
        string $environment = 'production'
    ) {
        $this->provider = strtolower($provider);

        // Map provider to country and configuration
        $mapping = self::getProviderMapping();

        if (!isset($mapping[$this->provider])) {
            throw new SubmissionException("Unknown provider: {$provider}. Use Submitter::getAvailableProviders() to see available providers.");
        }

        $providerInfo = $mapping[$this->provider];
        $this->countryCode = $providerInfo['country'];

        // Build config for factory
        $config = ['environment' => $environment];

        // If it's a PDP provider, add pdp_provider to config
        if (isset($providerInfo['pdp_provider'])) {
            $config['pdp_provider'] = $providerInfo['pdp_provider'];
        }

        // Create adapter using factory
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
     * Get the provider identifier
     *
     * @return string
     */
    public function getProvider(): string
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
     * Get all available providers (for WooCommerce plugin dropdown, etc.)
     *
     * Returns array of providers with their metadata:
     * [
     *   'superpdp' => [
     *     'name' => 'SuperPDP',
     *     'country' => 'FR',
     *     'country_name' => 'France',
     *     'description' => 'SuperPDP - French e-invoicing platform',
     *     'model' => 'pdp_intermediary',
     *     'requires_clearance' => false,
     *     'required_credentials' => ['client_id', 'client_secret'],
     *     'free_tier' => true,
     *   ],
     *   'SA' => [...],
     * ]
     *
     * @return array
     */
    public static function getAvailableProviders(): array
    {
        return [
            // French PDPs
            'superpdp' => [
                'name' => 'SuperPDP',
                'country' => 'FR',
                'country_name' => 'France',
                'description' => 'SuperPDP - French certified e-invoicing platform (PDP #0111)',
                'model' => 'pdp_intermediary',
                'requires_clearance' => false,
                'required_credentials' => ['client_id', 'client_secret'],
                'optional_credentials' => [],
                'free_tier' => true,
                'free_tier_limit' => '1,000 invoices/month',
                'website' => 'https://www.superpdp.tech',
                'documentation' => 'https://www.superpdp.tech/docs',
            ],
            'basware' => [
                'name' => 'Basware',
                'country' => 'FR',
                'country_name' => 'France',
                'description' => 'Basware - French certified e-invoicing platform',
                'model' => 'pdp_intermediary',
                'requires_clearance' => false,
                'required_credentials' => ['api_key'],
                'optional_credentials' => [],
                'free_tier' => false,
                'website' => 'https://www.basware.com',
            ],
            'tradeshift' => [
                'name' => 'Tradeshift',
                'country' => 'FR',
                'country_name' => 'France',
                'description' => 'Tradeshift - French certified e-invoicing platform',
                'model' => 'pdp_intermediary',
                'requires_clearance' => false,
                'required_credentials' => ['api_key'],
                'optional_credentials' => [],
                'free_tier' => false,
                'website' => 'https://tradeshift.com',
            ],
            'pagero' => [
                'name' => 'Pagero',
                'country' => 'FR',
                'country_name' => 'France',
                'description' => 'Pagero - French certified e-invoicing platform',
                'model' => 'pdp_intermediary',
                'requires_clearance' => false,
                'required_credentials' => ['api_key'],
                'optional_credentials' => [],
                'free_tier' => false,
                'website' => 'https://www.pagero.com',
            ],

            // Centralized systems
            'sa' => [
                'name' => 'ZATCA (Fatoora)',
                'country' => 'SA',
                'country_name' => 'Saudi Arabia',
                'description' => 'ZATCA Phase 2 - Saudi Arabian e-invoicing clearance system',
                'model' => 'ctc_clearance',
                'requires_clearance' => true,
                'required_credentials' => ['certificate', 'private_key', 'secret'],
                'optional_credentials' => [],
                'free_tier' => true,
                'free_tier_limit' => 'Government-operated (free)',
                'website' => 'https://zatca.gov.sa',
                'documentation' => 'https://zatca.gov.sa/en/E-Invoicing/Pages/default.aspx',
            ],

            // Coming soon
            'it' => [
                'name' => 'SDI (Sistema di Interscambio)',
                'country' => 'IT',
                'country_name' => 'Italy',
                'description' => 'Italian e-invoicing clearance system',
                'model' => 'ctc_clearance',
                'requires_clearance' => true,
                'required_credentials' => ['certificate', 'private_key'],
                'optional_credentials' => [],
                'free_tier' => true,
                'free_tier_limit' => 'Government-operated (free)',
                'website' => 'https://www.fatturapa.gov.it',
                'status' => 'coming_soon',
            ],
            'my' => [
                'name' => 'MyInvois',
                'country' => 'MY',
                'country_name' => 'Malaysia',
                'description' => 'Malaysian e-invoicing clearance system',
                'model' => 'ctc_clearance',
                'requires_clearance' => true,
                'required_credentials' => ['api_key', 'tin'],
                'optional_credentials' => [],
                'free_tier' => true,
                'free_tier_limit' => 'Government-operated (free)',
                'website' => 'https://myinvois.hasil.gov.my',
                'status' => 'coming_soon',
            ],
        ];
    }

    /**
     * Get information about a specific provider
     *
     * @param string $provider Provider identifier
     * @return array|null Provider info or null if not found
     */
    public static function getProviderInfo(string $provider): ?array
    {
        $providers = self::getAvailableProviders();
        $provider = strtolower($provider);

        return $providers[$provider] ?? null;
    }

    /**
     * Check if a provider is supported
     *
     * @param string $provider Provider identifier
     * @return bool
     */
    public static function isSupported(string $provider): bool
    {
        $info = self::getProviderInfo($provider);
        return $info !== null && !isset($info['status']);
    }

    /**
     * Get providers grouped by country (useful for UI dropdowns)
     *
     * Returns:
     * [
     *   'FR' => [
     *     'country_name' => 'France',
     *     'providers' => ['superpdp' => [...], 'basware' => [...], ...]
     *   ],
     *   'SA' => [...]
     * ]
     *
     * @param bool $includeComingSoon Include providers marked as coming_soon
     * @return array
     */
    public static function getProvidersByCountry(bool $includeComingSoon = false): array
    {
        $providers = self::getAvailableProviders();
        $grouped = [];

        foreach ($providers as $key => $info) {
            if (!$includeComingSoon && isset($info['status']) && $info['status'] === 'coming_soon') {
                continue;
            }

            $country = $info['country'];

            if (!isset($grouped[$country])) {
                $grouped[$country] = [
                    'country_name' => $info['country_name'],
                    'providers' => [],
                ];
            }

            $grouped[$country]['providers'][$key] = $info;
        }

        return $grouped;
    }

    /**
     * Get required credentials for a provider
     *
     * @param string $provider Provider identifier
     * @return array Array of required credential keys
     */
    public static function getRequiredCredentials(string $provider): array
    {
        $info = self::getProviderInfo($provider);
        return $info['required_credentials'] ?? [];
    }

    /**
     * Internal: Get provider to country mapping
     *
     * @return array
     */
    private static function getProviderMapping(): array
    {
        return [
            // French PDPs
            'superpdp' => ['country' => 'FR', 'pdp_provider' => 'superpdp'],
            'basware' => ['country' => 'FR', 'pdp_provider' => 'basware'],
            'tradeshift' => ['country' => 'FR', 'pdp_provider' => 'tradeshift'],
            'pagero' => ['country' => 'FR', 'pdp_provider' => 'pagero'],

            // Centralized systems (country code IS the provider)
            'sa' => ['country' => 'SA'],
            'it' => ['country' => 'IT'],
            'my' => ['country' => 'MY'],
        ];
    }

    /**
     * Legacy support: Get supported countries
     * Use getAvailableProviders() or getProvidersByCountry() instead
     *
     * @return array
     * @deprecated Use getAvailableProviders() or getProvidersByCountry() instead
     */
    public static function getSupportedCountries(): array
    {
        return SubmitterFactory::getSupportedCountries();
    }
}
