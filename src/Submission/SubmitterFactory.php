<?php

namespace DigitalInvoice\Submission;

use DigitalInvoice\Submission\Adapters\FrancePdpSubmitter;
use DigitalInvoice\Submission\Adapters\SuperPdpSubmitter;
use DigitalInvoice\Submission\Adapters\SaudiZatcaSubmitter;

/**
 * Factory for creating country-specific invoice submitters
 */
class SubmitterFactory
{
    /**
     * Create a submitter for the specified country
     *
     * @param string $countryCode ISO 3166-1 alpha-2 country code (e.g., 'FR', 'SA', 'IT')
     * @param array $config Configuration array with keys like:
     *                      - 'environment': 'sandbox' or 'production'
     *                      - 'pdp_provider': (for France) PDP provider name
     *                      - 'access_point_url': (for Peppol) Access Point URL
     *                      - Other country-specific settings
     * @return SubmitterInterface
     * @throws \InvalidArgumentException If country is not supported
     */
    public static function create(string $countryCode, array $config = []): SubmitterInterface
    {
        $countryCode = strtoupper($countryCode);
        $environment = $config['environment'] ?? 'production';

        return match ($countryCode) {
            // France - PDP intermediary model
            'FR' => self::createFranceSubmitter($config, $environment),

            // Saudi Arabia - CTC clearance model
            'SA' => new SaudiZatcaSubmitter($environment),

            // Italy - SDI clearance model
            // 'IT' => new ItalySdiSubmitter($environment),

            // Malaysia - MyInvois clearance model
            // 'MY' => new MalaysiaMyInvoisSubmitter($environment),

            // Peppol countries (Belgium, Singapore, Netherlands, etc.)
            // 'BE', 'SG', 'NL', 'AU' => new PeppolSubmitter(
            //     accessPointUrl: $config['access_point_url'] ?? throw new \InvalidArgumentException('access_point_url required for Peppol'),
            //     environment: $environment
            // ),

            // Add more countries as you implement them...

            default => throw new \InvalidArgumentException(
                "Country code '{$countryCode}' is not yet supported. " .
                "Supported countries: FR, SA. " .
                "To add support for {$countryCode}, implement a new adapter in src/Submission/Adapters/"
            ),
        };
    }

    /**
     * Create France submitter based on PDP provider
     *
     * @param array $config
     * @param string $environment
     * @return SubmitterInterface
     */
    private static function createFranceSubmitter(array $config, string $environment): SubmitterInterface
    {
        $pdpProvider = strtolower($config['pdp_provider'] ?? 'basware');

        // SuperPDP has its own dedicated submitter
        if ($pdpProvider === 'superpdp') {
            return new SuperPdpSubmitter($environment);
        }

        // Generic PDP submitter for other providers
        return new FrancePdpSubmitter($pdpProvider, $environment);
    }

    /**
     * Check if a country is supported
     *
     * @param string $countryCode
     * @return bool
     */
    public static function isSupported(string $countryCode): bool
    {
        $supported = ['FR', 'SA'];
        return in_array(strtoupper($countryCode), $supported);
    }

    /**
     * Get list of all supported countries
     *
     * @return array Array of country codes with details
     */
    public static function getSupportedCountries(): array
    {
        return [
            'FR' => [
                'name' => 'France',
                'model' => 'intermediary',
                'requires_clearance' => false,
                'description' => 'PDP (Plateforme Agréée) via certified platforms',
                'required_config' => ['pdp_provider', 'api_key_or_oauth2'],
                'supported_providers' => [
                    'superpdp' => [
                        'name' => 'SuperPDP (#0111)',
                        'auth' => 'oauth2',
                        'required' => ['client_id', 'client_secret'],
                        'pricing' => 'Free up to 1,000 invoices/month, €0.0025 per invoice after',
                        'url' => 'https://www.superpdp.tech',
                    ],
                    'basware' => [
                        'name' => 'Basware',
                        'auth' => 'api_key',
                        'required' => ['api_key'],
                    ],
                    'tradeshift' => [
                        'name' => 'Tradeshift',
                        'auth' => 'api_key',
                        'required' => ['api_key'],
                    ],
                    'pagero' => [
                        'name' => 'Pagero',
                        'auth' => 'api_key',
                        'required' => ['api_key'],
                    ],
                ],
            ],
            'SA' => [
                'name' => 'Saudi Arabia',
                'model' => 'ctc_clearance',
                'requires_clearance' => true,
                'description' => 'ZATCA (Fatoora) Phase 2 integration',
                'required_config' => ['certificate', 'private_key', 'secret'],
            ],
            // Add more as implemented...
        ];
    }

    /**
     * Get required configuration keys for a country
     *
     * @param string $countryCode
     * @return array
     */
    public static function getRequiredConfig(string $countryCode): array
    {
        $countries = self::getSupportedCountries();
        $countryCode = strtoupper($countryCode);

        return $countries[$countryCode]['required_config'] ?? [];
    }
}
