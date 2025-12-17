<?php

/**
 * WooCommerce E-Invoicing Plugin Integration Example
 *
 * This example demonstrates how a WooCommerce plugin would:
 * 1. Discover available e-invoicing providers
 * 2. Display them in admin settings (dropdown)
 * 3. Collect credentials based on selected provider
 * 4. Submit invoices after order completion
 *
 * The Submitter class provides discovery methods similar to Invoice profiles,
 * making it easy to build UI and integrate with plugins.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DigitalInvoice\Invoice;
use DigitalInvoice\Submission\Submitter;

// ============================================================================
// Example 1: Building a Settings Page Dropdown
// ============================================================================

echo "=== Provider Discovery for WooCommerce Settings ===\n\n";

// Get all available providers (like getting Invoice profiles)
$providers = Submitter::getAvailableProviders();

echo "Building settings dropdown:\n";
echo "<select name='einvoice_provider'>\n";
echo "  <option value=''>-- Select E-Invoicing Provider --</option>\n";

foreach ($providers as $key => $info) {
    // Skip coming soon providers in production
    if (isset($info['status']) && $info['status'] === 'coming_soon') {
        continue;
    }

    $label = "{$info['name']} ({$info['country_name']})";
    if ($info['free_tier']) {
        $label .= " - FREE";
    }

    echo "  <option value='{$key}'>{$label}</option>\n";
}
echo "</select>\n\n";

// ============================================================================
// Example 2: Grouped by Country (Better UX)
// ============================================================================

echo "=== Providers Grouped by Country ===\n\n";

$byCountry = Submitter::getProvidersByCountry();

echo "<select name='einvoice_provider'>\n";
echo "  <option value=''>-- Select E-Invoicing Provider --</option>\n";

foreach ($byCountry as $countryCode => $countryData) {
    echo "  <optgroup label='{$countryData['country_name']}'>\n";

    foreach ($countryData['providers'] as $providerKey => $providerInfo) {
        $label = $providerInfo['name'];
        if ($providerInfo['free_tier']) {
            $label .= " (FREE)";
        }

        echo "    <option value='{$providerKey}'>{$label}</option>\n";
    }

    echo "  </optgroup>\n";
}
echo "</select>\n\n";

// ============================================================================
// Example 3: Dynamic Credential Fields Based on Selected Provider
// ============================================================================

echo "=== Dynamic Credential Fields ===\n\n";

// When user selects a provider, show appropriate credential fields
$selectedProvider = 'superpdp'; // From form POST

$providerInfo = Submitter::getProviderInfo($selectedProvider);
$requiredCreds = $providerInfo['required_credentials'];

echo "Selected Provider: {$providerInfo['name']}\n";
echo "Required Credentials:\n";

$fieldLabels = [
    'client_id' => 'OAuth Client ID',
    'client_secret' => 'OAuth Client Secret',
    'api_key' => 'API Key',
    'certificate' => 'Certificate Path',
    'private_key' => 'Private Key Path',
    'secret' => 'OTP Secret',
    'tin' => 'Tax Identification Number',
];

foreach ($requiredCreds as $credKey) {
    $label = $fieldLabels[$credKey] ?? ucwords(str_replace('_', ' ', $credKey));
    echo "  <input type='text' name='{$credKey}' placeholder='{$label}' required />\n";
}
echo "\n";

// ============================================================================
// Example 4: Using Constants (Type-Safe, Autocomplete-Friendly)
// ============================================================================

echo "=== Using Provider Constants ===\n\n";

// WooCommerce plugin can use constants for validation
$allowedProviders = [
    Submitter::SUPERPDP,
    Submitter::BASWARE,
    Submitter::TRADESHIFT,
    Submitter::SAUDI_ZATCA,
];

echo "Available constants:\n";
foreach ($allowedProviders as $provider) {
    $info = Submitter::getProviderInfo($provider);
    echo "  Submitter::" . strtoupper(str_replace(['superpdp', 'basware', 'tradeshift', 'sa'], ['SUPERPDP', 'BASWARE', 'TRADESHIFT', 'SAUDI_ZATCA'], $provider)) . " = '{$provider}' ({$info['name']})\n";
}
echo "\n";

// ============================================================================
// Example 5: Complete WooCommerce Order Hook Integration
// ============================================================================

echo "=== Complete WooCommerce Integration ===\n\n";

/**
 * Simulate WooCommerce order completion hook
 */
function woocommerce_order_completed_hook($orderId) {
    // Get plugin settings from WordPress options
    $settings = [
        'provider' => get_option('einvoice_provider', 'superpdp'),
        'environment' => get_option('einvoice_environment', 'sandbox'),
        'credentials' => [
            'client_id' => get_option('einvoice_client_id'),
            'client_secret' => get_option('einvoice_client_secret'),
            // ... other credentials based on provider
        ],
    ];

    // Check if provider is supported
    if (!Submitter::isSupported($settings['provider'])) {
        error_log("E-Invoicing: Provider {$settings['provider']} not supported");
        return;
    }

    // Get order data
    $order = wc_get_order($orderId);

    // Create invoice from order
    $invoice = new Invoice(
        invoiceId: $order->get_order_number(),
        issueDate: new DateTime($order->get_date_created()),
        currency: $order->get_currency(),
        profile: Invoice::UBL_PEPPOL // or determine based on country
    );

    // Configure invoice from order
    $invoice->setSeller(
        get_option('company_tax_id'),
        '0002', // Scheme
        get_option('company_name')
    );

    $invoice->setBuyer(
        $order->get_meta('_billing_tax_id'),
        $order->get_billing_company() ?: $order->get_billing_first_name() . ' ' . $order->get_billing_last_name()
    );

    // Add line items
    foreach ($order->get_items() as $item) {
        $invoice->addItem(
            $item->get_name(),
            $item->get_total(),
            $item->get_total_tax(),
            $item->get_quantity(),
            'C62' // Unit code
        );
    }

    $invoice->setPrice(
        $order->get_total() - $order->get_total_tax(),
        $order->get_total_tax()
    );

    // Submit to tax authority
    try {
        $result = $invoice->submit(
            $settings['provider'],
            $settings['credentials'],
            $settings['environment']
        );

        if ($result->success) {
            // Store submission result in order meta
            $order->update_meta_data('_einvoice_submitted', true);
            $order->update_meta_data('_einvoice_reference_id', $result->referenceId);
            $order->update_meta_data('_einvoice_government_id', $result->governmentId);
            $order->update_meta_data('_einvoice_status', $result->status);
            $order->save();

            // Add order note
            $order->add_order_note(
                sprintf(
                    'E-Invoice submitted successfully to %s. Reference: %s',
                    Submitter::getProviderInfo($settings['provider'])['name'],
                    $result->governmentId
                )
            );

            error_log("E-Invoice submitted for order {$orderId}: {$result->governmentId}");
        } else {
            throw new Exception($result->error);
        }
    } catch (Exception $e) {
        // Log error and add order note
        error_log("E-Invoice submission failed for order {$orderId}: " . $e->getMessage());

        $order->update_meta_data('_einvoice_error', $e->getMessage());
        $order->save();

        $order->add_order_note(
            'E-Invoice submission failed: ' . $e->getMessage()
        );

        // Optionally: Queue for retry
        // wp_schedule_single_event(time() + 300, 'retry_einvoice_submission', [$orderId]);
    }
}

// Mock WordPress functions for example
function get_option($key, $default = null) {
    return match($key) {
        'einvoice_provider' => 'superpdp',
        'einvoice_environment' => 'sandbox',
        'einvoice_client_id' => 'demo_client_id',
        'einvoice_client_secret' => 'demo_client_secret',
        'company_tax_id' => '12345678901234',
        'company_name' => 'My WooCommerce Store',
        default => $default,
    };
}

function wc_get_order($id) {
    return new class {
        public function get_order_number() { return 'WC-001'; }
        public function get_date_created() { return '2025-01-15'; }
        public function get_currency() { return 'EUR'; }
        public function get_meta($key) { return '98765432109876'; }
        public function get_billing_company() { return 'Customer Company'; }
        public function get_billing_first_name() { return 'John'; }
        public function get_billing_last_name() { return 'Doe'; }
        public function get_items() { return []; }
        public function get_total() { return 120.00; }
        public function get_total_tax() { return 20.00; }
        public function update_meta_data($key, $value) {}
        public function save() {}
        public function add_order_note($note) { echo "Order Note: {$note}\n"; }
    };
}

// Simulate order completion
echo "Simulating WooCommerce order completion...\n";
// woocommerce_order_completed_hook(12345);
echo "(Commented out to avoid actual submission in example)\n\n";

// ============================================================================
// Example 6: Admin Settings Page JSON Output for JavaScript
// ============================================================================

echo "=== JSON for JavaScript (AJAX Settings Page) ===\n\n";

// This is useful for building dynamic settings pages with React/Vue
$jsonData = [
    'providers' => Submitter::getAvailableProviders(),
    'byCountry' => Submitter::getProvidersByCountry(),
    'constants' => [
        'SUPERPDP' => Submitter::SUPERPDP,
        'BASWARE' => Submitter::BASWARE,
        'TRADESHIFT' => Submitter::TRADESHIFT,
        'PAGERO' => Submitter::PAGERO,
        'SAUDI_ZATCA' => Submitter::SAUDI_ZATCA,
    ],
];

echo json_encode($jsonData, JSON_PRETTY_PRINT) . "\n\n";

// ============================================================================
// Example 7: Validation Before Saving Settings
// ============================================================================

echo "=== Settings Validation ===\n\n";

function validate_einvoice_settings($provider, $credentials) {
    // Check if provider exists
    if (!Submitter::isSupported($provider)) {
        return "Invalid provider: {$provider}";
    }

    // Get required credentials for provider
    $required = Submitter::getRequiredCredentials($provider);

    // Validate all required credentials are provided
    foreach ($required as $credKey) {
        if (empty($credentials[$credKey])) {
            $info = Submitter::getProviderInfo($provider);
            return "Missing required credential '{$credKey}' for {$info['name']}";
        }
    }

    return true; // Valid
}

// Test validation
$testProvider = 'superpdp';
$testCredentials = ['client_id' => 'xxx', 'client_secret' => 'yyy'];

$validationResult = validate_einvoice_settings($testProvider, $testCredentials);
echo "Validation result: " . ($validationResult === true ? "✓ Valid" : "✗ {$validationResult}") . "\n";

// Test with missing credentials
$invalidCredentials = ['client_id' => 'xxx'];
$validationResult = validate_einvoice_settings($testProvider, $invalidCredentials);
echo "Validation result (missing secret): " . ($validationResult === true ? "✓ Valid" : "✗ {$validationResult}") . "\n\n";

// ============================================================================
// Summary
// ============================================================================

echo "=== Summary ===\n\n";
echo "Key Methods for WooCommerce Plugin:\n";
echo "1. Submitter::getAvailableProviders()     - Get all providers with metadata\n";
echo "2. Submitter::getProvidersByCountry()     - Get providers grouped by country\n";
echo "3. Submitter::getProviderInfo(\$provider)  - Get info about specific provider\n";
echo "4. Submitter::getRequiredCredentials()    - Get required credential fields\n";
echo "5. Submitter::isSupported(\$provider)      - Check if provider is available\n";
echo "6. \$invoice->submit(\$provider, \$creds)   - Submit invoice (simple!)\n\n";

echo "Constants for type-safe code:\n";
echo "- Submitter::SUPERPDP\n";
echo "- Submitter::BASWARE\n";
echo "- Submitter::TRADESHIFT\n";
echo "- Submitter::PAGERO\n";
echo "- Submitter::SAUDI_ZATCA\n";
echo "- Submitter::ITALY_SDI (coming soon)\n";
echo "- Submitter::MALAYSIA_MYINVOIS (coming soon)\n\n";

echo "Just like Invoice profiles:\n";
echo "- Invoice::FACTURX_BASIC\n";
echo "- Invoice::UBL_PEPPOL\n";
echo "- Invoice::UBL_MALAYSIA\n";
echo "etc.\n\n";

echo "✓ Same discovery pattern throughout the library!\n";
