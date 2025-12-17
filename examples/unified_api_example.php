<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DigitalInvoice\Invoice;
use DigitalInvoice\Submission\Submitter;

/**
 * Unified API Example - Same pattern as Invoice generation
 *
 * Just like Invoice class provides one API for all formats:
 *   $invoice = new Invoice(..., profile: Invoice::UBL_PEPPOL);
 *   $invoice = new Invoice(..., profile: Invoice::FACTURX_BASIC);
 *
 * Submitter provides one API for all providers:
 *   $submitter = new Submitter('superpdp');  // France SuperPDP
 *   $submitter = new Submitter('SA');        // Saudi Arabia
 *
 * OR even simpler - submit directly from Invoice:
 *   $result = $invoice->submit('superpdp', $credentials);
 */

echo "=== Unified API Example ===\n\n";

// =============================================================================
// STEP 1: Create Invoice (Same as always)
// =============================================================================

$invoice = new Invoice(
    invoiceId: 'UNIFIED-001',
    issueDate: new DateTime('2025-01-15'),
    currency: 'EUR',
    profile: Invoice::UBL_PEPPOL  // One class, different profiles
);

// Configure invoice
$invoice->setSeller('12345678901234', '0002', 'My Company SARL');
$invoice->setSellerAddress('123 Rue de Paris', '75001', 'Paris', 'FR');
$invoice->setSellerTaxRegistration('FR12345678901', 'VAT');

$invoice->setBuyer('98765432109876', 'Customer SAS');
$invoice->setBuyerAddress('456 Avenue Victor Hugo', '75016', 'Paris', 'FR');
$invoice->setBuyerIdentifier('98765432109876', '0002', 'Legal');

$invoice->addItem('Consulting Services', 1000.00, 20.0, 10, 'HUR');
$invoice->setPrice(10000.00, 2000.00);

echo "✓ Invoice created with profile: UBL_PEPPOL\n\n";

// =============================================================================
// APPROACH 1: Direct submission from Invoice (Simplest!)
// =============================================================================

echo "=== Approach 1: Direct Submission from Invoice ===\n";
echo "Just like \$invoice->getXml() or \$invoice->getPdf()\n\n";

try {
    // Submit directly from invoice - ONE LINE!
    // Provider can be: 'superpdp', 'basware', 'SA', 'IT', etc.
    $result = $invoice->submit(
        'superpdp',              // Provider identifier
        [                        // Credentials
            'client_id' => getenv('SUPERPDP_CLIENT_ID') ?: 'demo_client',
            'client_secret' => getenv('SUPERPDP_CLIENT_SECRET') ?: 'demo_secret',
        ],
        'sandbox'                // Environment
    );

    if ($result->success) {
        echo "✓ Invoice submitted via direct method!\n";
        echo "  ID: {$result->governmentId}\n";
        echo "  Status: {$result->status}\n\n";
    }

} catch (\Exception $e) {
    echo "Note: Demo credentials don't work (expected)\n";
    echo "Error: {$e->getMessage()}\n\n";
}

// =============================================================================
// APPROACH 2: Using Submitter class (Reusable for multiple invoices)
// =============================================================================

echo "=== Approach 2: Using Submitter Class (Reusable) ===\n";
echo "One class for all providers - just like Invoice class\n\n";

try {
    // Create submitter - ONE class for ALL providers
    $submitter = new Submitter('superpdp', 'sandbox');

    // Authenticate once
    $submitter->authenticate([
        'client_id' => getenv('SUPERPDP_CLIENT_ID') ?: 'demo_client',
        'client_secret' => getenv('SUPERPDP_CLIENT_SECRET') ?: 'demo_secret',
    ]);

    // Submit multiple invoices with same submitter
    $result = $submitter->submit($invoice);

    if ($result->success) {
        echo "✓ Invoice submitted via Submitter class!\n";
        echo "  Provider: {$submitter->getProvider()}\n";
        echo "  Country: {$submitter->getCountryCode()}\n";
        echo "  ID: {$result->governmentId}\n\n";
    }

} catch (\Exception $e) {
    echo "Note: Demo credentials don't work (expected)\n";
    echo "Error: {$e->getMessage()}\n\n";
}

// =============================================================================
// APPROACH 3: Using Constants (Type-safe, IDE-friendly)
// =============================================================================

echo "=== Approach 3: Using Constants (Type-Safe) ===\n";
echo "Constants like Invoice::FACTURX_BASIC\n\n";

try {
    // Use constants for type-safety and autocomplete
    $submitter = new Submitter(Submitter::SUPERPDP, 'sandbox');

    $submitter->authenticate([
        'client_id' => getenv('SUPERPDP_CLIENT_ID') ?: 'demo_client',
        'client_secret' => getenv('SUPERPDP_CLIENT_SECRET') ?: 'demo_secret',
    ]);

    $result = $submitter->submit($invoice);

    if ($result->success) {
        echo "✓ Invoice submitted using constant!\n";
        echo "  Constant used: Submitter::SUPERPDP\n";
        echo "  ID: {$result->governmentId}\n\n";
    }

} catch (\Exception $e) {
    echo "Note: Demo credentials don't work (expected)\n";
    echo "Error: {$e->getMessage()}\n\n";
}

// =============================================================================
// MULTI-COUNTRY EXAMPLE: Same API for all providers
// =============================================================================

echo "=== Multi-Country Example: Same API Works Everywhere ===\n\n";

$configurations = [
    [
        'name' => 'France SuperPDP',
        'provider' => 'superpdp',  // or Submitter::SUPERPDP
        'credentials' => [
            'client_id' => 'xxx',
            'client_secret' => 'yyy',
        ],
    ],
    [
        'name' => 'France Basware',
        'provider' => 'basware',   // or Submitter::BASWARE
        'credentials' => [
            'api_key' => 'xxx',
        ],
    ],
    [
        'name' => 'Saudi Arabia',
        'provider' => 'sa',        // or Submitter::SAUDI_ZATCA
        'credentials' => [
            'certificate' => '/path/to/cert.pem',
            'private_key' => '/path/to/key.pem',
            'secret' => 'otp_secret',
        ],
    ],
];

foreach ($configurations as $config) {
    echo "Provider: {$config['name']}\n";
    echo "  Using: new Submitter('{$config['provider']}')\n";

    try {
        // Same constructor for ALL providers!
        $submitter = new Submitter($config['provider'], 'sandbox');
        $submitter->authenticate($config['credentials']);

        // Same submit() method for ALL providers!
        // $result = $submitter->submit($invoice);

        echo "  ✓ Initialized successfully\n";
        echo "  Country: {$submitter->getCountryCode()}\n";
        echo "  Model: {$submitter->getSubmissionModel()}\n\n";

    } catch (\Exception $e) {
        echo "  ✗ Error: {$e->getMessage()}\n\n";
    }
}

// =============================================================================
// PROVIDER DISCOVERY (for WooCommerce plugins, etc.)
// =============================================================================

echo "=== Provider Discovery (for WooCommerce plugins) ===\n\n";

// Get all available providers
$providers = Submitter::getAvailableProviders();

echo "All Available Providers:\n";
foreach ($providers as $key => $info) {
    if (isset($info['status']) && $info['status'] === 'coming_soon') {
        continue; // Skip coming soon
    }

    $free = $info['free_tier'] ? ' [FREE]' : '';
    echo "  - {$key}: {$info['name']} ({$info['country_name']}){$free}\n";
}
echo "\n";

// Get providers by country (for grouped dropdowns)
$byCountry = Submitter::getProvidersByCountry();

echo "Providers by Country:\n";
foreach ($byCountry as $countryCode => $data) {
    echo "  {$data['country_name']} ({$countryCode}):\n";
    foreach ($data['providers'] as $provKey => $provInfo) {
        echo "    - {$provKey}: {$provInfo['name']}\n";
    }
}
echo "\n";

// Get required credentials for a provider
$requiredCreds = Submitter::getRequiredCredentials('superpdp');
echo "Required credentials for SuperPDP: " . implode(', ', $requiredCreds) . "\n";

$requiredCreds = Submitter::getRequiredCredentials('sa');
echo "Required credentials for Saudi ZATCA: " . implode(', ', $requiredCreds) . "\n\n";

// =============================================================================
// COMPARISON: Old way vs New way
// =============================================================================

echo "=== Comparison: Before & After ===\n\n";

echo "❌ OLD WAY (if we had kept the complex design):\n";
echo "   \$francePdp = new FrancePdpSubmitter('superpdp', 'sandbox');\n";
echo "   \$superpdp = new SuperPdpSubmitter('sandbox');\n";
echo "   \$zatca = new SaudiZatcaSubmitter('sandbox');\n";
echo "   // Different classes to learn!\n\n";

echo "✅ NEW WAY (unified):\n";
echo "   \$submitter = new Submitter('superpdp', 'sandbox');\n";
echo "   \$submitter = new Submitter('basware', 'sandbox');\n";
echo "   \$submitter = new Submitter('sa', 'sandbox');\n";
echo "   // ONE class for everything!\n\n";

echo "Just like Invoice generation:\n";
echo "   \$invoice = new Invoice(..., profile: Invoice::FACTURX_BASIC);\n";
echo "   \$invoice = new Invoice(..., profile: Invoice::UBL_PEPPOL);\n";
echo "   // ONE class for all formats!\n\n";

// =============================================================================
// AVAILABLE CONSTANTS
// =============================================================================

echo "=== Available Constants ===\n\n";

echo "French PDPs:\n";
echo "  - Submitter::SUPERPDP      = '" . Submitter::SUPERPDP . "'\n";
echo "  - Submitter::BASWARE       = '" . Submitter::BASWARE . "'\n";
echo "  - Submitter::TRADESHIFT    = '" . Submitter::TRADESHIFT . "'\n";
echo "  - Submitter::PAGERO        = '" . Submitter::PAGERO . "'\n\n";

echo "Centralized Systems:\n";
echo "  - Submitter::SAUDI_ZATCA   = '" . Submitter::SAUDI_ZATCA . "'\n";
echo "  - Submitter::ITALY_SDI     = '" . Submitter::ITALY_SDI . "' (coming soon)\n";
echo "  - Submitter::MALAYSIA_MYINVOIS = '" . Submitter::MALAYSIA_MYINVOIS . "' (coming soon)\n\n";

// =============================================================================
// SUMMARY
// =============================================================================

echo "=== Summary ===\n\n";

echo "Three ways to submit (all equivalent):\n\n";

echo "1. Direct (simplest):\n";
echo "   \$result = \$invoice->submit('superpdp', \$credentials);\n\n";

echo "2. Submitter class (reusable):\n";
echo "   \$submitter = new Submitter('superpdp');\n";
echo "   \$result = \$submitter->submit(\$invoice);\n\n";

echo "3. Using constants (type-safe):\n";
echo "   \$submitter = new Submitter(Submitter::SUPERPDP);\n";
echo "   \$result = \$submitter->submit(\$invoice);\n\n";

echo "Key Points:\n";
echo "  ✓ Provider IS the identifier ('superpdp' for France, 'SA' for Saudi)\n";
echo "  ✓ Same class (Submitter) for all countries\n";
echo "  ✓ Same pattern as Invoice generation\n";
echo "  ✓ Easy discovery for plugins (getAvailableProviders())\n";
echo "  ✓ Constants for type-safety (Submitter::SUPERPDP)\n\n";

echo "✓ One class to learn, works everywhere!\n";
