<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DigitalInvoice\Invoice;
use DigitalInvoice\Submission\Submitter;

/**
 * Unified API Example - Same pattern as Invoice generation
 *
 * Just like Invoice class provides one API for all formats:
 *   $invoice = new Invoice(..., profile: Invoice::UBL_PEPOOL);
 *   $invoice = new Invoice(..., profile: Invoice::FACTURX_BASIC);
 *
 * Submitter provides one API for all countries:
 *   $submitter = new Submitter('FR', 'superpdp');
 *   $submitter = new Submitter('SA');
 *
 * OR even simpler - submit directly from Invoice:
 *   $result = $invoice->submit('FR', 'superpdp', $credentials);
 */

echo "=== Unified API Example ===\n\n";

// =============================================================================
// STEP 1: Create Invoice (Same as always)
// =============================================================================

$invoice = new Invoice(
    invoiceId: 'UNIFIED-001',
    issueDate: new DateTime('2025-01-15'),
    currency: 'EUR',
    profile: Invoice::UBL_PEPOOL  // One class, different profiles
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

echo "✓ Invoice created with profile: UBL_PEPOOL\n\n";

// =============================================================================
// APPROACH 1: Direct submission from Invoice (Simplest!)
// =============================================================================

echo "=== Approach 1: Direct Submission from Invoice ===\n";
echo "Just like \$invoice->getXml() or \$invoice->getPdf()\n\n";

try {
    // Submit directly from invoice - ONE LINE!
    $result = $invoice->submit(
        'FR',                    // Country
        'superpdp',              // Provider
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
// APPROACH 2: Using Submitter class (Reusable)
// =============================================================================

echo "=== Approach 2: Using Submitter Class ===\n";
echo "One Submitter class for all countries (like Invoice class)\n\n";

// France - SuperPDP
echo "France (SuperPDP):\n";
$submitterFR = new Submitter('FR', 'superpdp', 'sandbox');
$submitterFR->authenticate([
    'client_id' => 'xxx',
    'client_secret' => 'yyy',
]);
echo "  Created: Submitter('FR', 'superpdp')\n";
echo "  Country: {$submitterFR->getCountryCode()}\n";
echo "  Provider: {$submitterFR->getProvider()}\n";
echo "  Model: {$submitterFR->getSubmissionModel()}\n\n";

// France - Basware (same class, different provider!)
echo "France (Basware):\n";
$submitterBasware = new Submitter('FR', 'basware', 'sandbox');
$submitterBasware->authenticate(['api_key' => 'xxx']);
echo "  Created: Submitter('FR', 'basware')\n";
echo "  Country: {$submitterBasware->getCountryCode()}\n";
echo "  Provider: {$submitterBasware->getProvider()}\n\n";

// Saudi Arabia (same class, different country!)
echo "Saudi Arabia (ZATCA):\n";
$submitterSA = new Submitter('SA', null, 'sandbox');
$submitterSA->authenticate([
    'certificate' => '/path/cert.pem',
    'private_key' => '/path/key.pem',
    'secret' => 'xxx',
]);
echo "  Created: Submitter('SA')\n";
echo "  Country: {$submitterSA->getCountryCode()}\n";
echo "  Requires clearance: " . ($submitterSA->requiresClearance() ? 'Yes' : 'No') . "\n\n";

// =============================================================================
// APPROACH 3: Using Constants (Type-safe)
// =============================================================================

echo "=== Approach 3: Using Constants ===\n";
echo "Type-safe profiles (like Invoice::FACTURX_BASIC)\n\n";

$submitter = new Submitter(Submitter::FRANCE_SUPERPDP);
echo "✓ Created: new Submitter(Submitter::FRANCE_SUPERPDP)\n";
echo "  Equivalent to: new Submitter('FR', 'superpdp')\n\n";

// Available constants:
echo "Available constants:\n";
echo "  - Submitter::FRANCE_SUPERPDP\n";
echo "  - Submitter::FRANCE_BASWARE\n";
echo "  - Submitter::FRANCE_TRADESHIFT\n";
echo "  - Submitter::FRANCE_PAGERO\n";
echo "  - Submitter::SAUDI_ZATCA\n";
echo "  (More added as countries are implemented)\n\n";

// =============================================================================
// APPROACH 4: Multi-Country Loop (Same API!)
// =============================================================================

echo "=== Approach 4: Multi-Country Loop ===\n";
echo "Same API works for ALL countries\n\n";

$configurations = [
    ['country' => 'FR', 'provider' => 'superpdp', 'name' => 'France (SuperPDP)'],
    ['country' => 'FR', 'provider' => 'basware', 'name' => 'France (Basware)'],
    ['country' => 'SA', 'provider' => null, 'name' => 'Saudi Arabia (ZATCA)'],
];

foreach ($configurations as $config) {
    try {
        $submitter = new Submitter($config['country'], $config['provider'], 'sandbox');
        echo "✓ {$config['name']}\n";
        echo "  - Same constructor signature\n";
        echo "  - Same authenticate() method\n";
        echo "  - Same submit() method\n";
        echo "  - Same getStatus() method\n";

        // $result = $submitter->submit($invoice);
        // ALL use identical API!

    } catch (\Exception $e) {
        echo "✗ {$config['name']}: {$e->getMessage()}\n";
    }
    echo "\n";
}

// =============================================================================
// COMPARISON: Old Way vs New Way
// =============================================================================

echo "=== API Comparison ===\n\n";

echo "OLD WAY (Different classes):\n";
echo "❌ \$superpdp = new SuperPdpSubmitter();\n";
echo "❌ \$basware = new FrancePdpSubmitter('basware');\n";
echo "❌ \$zatca = new SaudiZatcaSubmitter();\n";
echo "   Different classes to learn!\n\n";

echo "NEW WAY (One class, like Invoice):\n";
echo "✅ \$submitter = new Submitter('FR', 'superpdp');\n";
echo "✅ \$submitter = new Submitter('FR', 'basware');\n";
echo "✅ \$submitter = new Submitter('SA');\n";
echo "   One class to learn!\n\n";

echo "EVEN SIMPLER (Direct from Invoice):\n";
echo "✅ \$result = \$invoice->submit('FR', 'superpdp', \$creds);\n";
echo "✅ \$result = \$invoice->submit('SA', null, \$creds);\n";
echo "   Just like \$invoice->getXml() or \$invoice->getPdf()!\n\n";

// =============================================================================
// HELPER METHODS
// =============================================================================

echo "=== Helper Methods ===\n\n";

// Check if supported
echo "Check support:\n";
echo "  Submitter::isSupported('FR'): " . (Submitter::isSupported('FR') ? 'true' : 'false') . "\n";
echo "  Submitter::isSupported('IT'): " . (Submitter::isSupported('IT') ? 'true' : 'false') . "\n\n";

// Get required credentials
echo "Get required credentials:\n";
$required = Submitter::getRequiredCredentials('FR', 'superpdp');
echo "  FR + superpdp: " . implode(', ', $required) . "\n";

$required = Submitter::getRequiredCredentials('SA');
echo "  SA: " . implode(', ', $required) . "\n\n";

// List all supported
echo "Supported countries:\n";
$countries = Submitter::getSupportedCountries();
foreach ($countries as $code => $info) {
    echo "  {$code} - {$info['name']} ({$info['model']})\n";
}

echo "\n=== Complete ===\n";

// =============================================================================
// SUMMARY
// =============================================================================

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              UNIFIED API - KEY BENEFITS                        ║\n";
echo "╠════════════════════════════════════════════════════════════════╣\n";
echo "║                                                                ║\n";
echo "║  1. ONE CLASS to learn (Submitter)                            ║\n";
echo "║     Just like Invoice has one class for all formats           ║\n";
echo "║                                                                ║\n";
echo "║  2. CONSISTENT API across all countries                       ║\n";
echo "║     new Submitter('FR', 'superpdp')                           ║\n";
echo "║     new Submitter('SA')                                       ║\n";
echo "║     new Submitter('IT')                                       ║\n";
echo "║                                                                ║\n";
echo "║  3. DIRECT submission from Invoice                            ║\n";
echo "║     \$invoice->submit('FR', 'superpdp', \$creds)               ║\n";
echo "║     Just like \$invoice->getXml()                             ║\n";
echo "║                                                                ║\n";
echo "║  4. TYPE-SAFE with constants                                  ║\n";
echo "║     Submitter::FRANCE_SUPERPDP                                ║\n";
echo "║     Like Invoice::FACTURX_BASIC                               ║\n";
echo "║                                                                ║\n";
echo "║  5. EASY to switch providers/countries                        ║\n";
echo "║     Change one parameter, that's it!                          ║\n";
echo "║                                                                ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
