<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DigitalInvoice\Invoice;
use DigitalInvoice\Submission\SubmitterFactory;
use DigitalInvoice\Submission\Exception\SubmissionException;

/**
 * Example: Submitting invoices to different countries
 *
 * This example demonstrates how to use the submission abstraction layer
 * to submit e-invoices to various tax authorities.
 */

// =============================================================================
// EXAMPLE 1: France - Submit via PDP (Basware)
// =============================================================================

echo "=== France PDP Submission ===\n";

// Create invoice
$invoice = new Invoice(
    invoiceId: 'FR-2025-001',
    issueDate: new DateTime('2025-01-15'),
    currency: 'EUR',
    profile: Invoice::UBL_PEPOOL
);

// Configure invoice
$invoice->setSeller('12345678901234', '0002', 'My French Company SAS');
$invoice->setSellerAddress('123 Rue de Paris', '75001', 'Paris', 'FR');
$invoice->setSellerTaxRegistration('FR12345678901', 'VAT');

$invoice->setBuyer('98765432109876', 'Acme Corp France');
$invoice->setBuyerAddress('456 Avenue des Champs', '75008', 'Paris', 'FR');
$invoice->setBuyerIdentifier('98765432109876', '0002', 'Legal'); // SIRET

$invoice->addItem('Web Development Services', 1000.00, 20.0, 10, 'HUR');
$invoice->addPaymentMean('30', 'FR7612345678901234567890123', 'My Company', 'BNPAFRPP');
$invoice->setPrice(10000.00, 2000.00);

// Create France PDP submitter
$franceSubmitter = SubmitterFactory::create('FR', [
    'environment' => 'sandbox',
    'pdp_provider' => 'basware', // or 'tradeshift', 'pagero', etc.
]);

// Authenticate
$franceSubmitter->authenticate([
    'api_key' => 'your_pdp_api_key_here',
]);

// Validate before submitting (optional)
$validation = $franceSubmitter->validateInvoice($invoice);
if (!$validation->valid) {
    echo "Validation errors:\n";
    foreach ($validation->errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}

// Submit invoice
try {
    $result = $franceSubmitter->submit($invoice);

    if ($result->success) {
        echo "✓ Invoice submitted successfully!\n";
        echo "  Reference ID: {$result->referenceId}\n";
        echo "  Government ID: {$result->governmentId}\n";
        echo "  Status: {$result->status}\n";

        // Check status later
        sleep(5);
        $status = $franceSubmitter->getStatus($result->referenceId);
        echo "  Current status: {$status->status}\n";
    } else {
        echo "✗ Submission failed: {$result->error}\n";
    }
} catch (SubmissionException $e) {
    echo "✗ Exception: {$e->getMessage()}\n";
}

echo "\n";

// =============================================================================
// EXAMPLE 2: Saudi Arabia - ZATCA Clearance
// =============================================================================

echo "=== Saudi Arabia ZATCA Submission ===\n";

// Create invoice for Saudi Arabia
$invoiceSA = new Invoice(
    invoiceId: 'SA-2025-001',
    issueDate: new DateTime('2025-01-15'),
    currency: 'SAR',
    profile: Invoice::UBL_PEPOOL // Use appropriate profile
);

// Configure invoice
$invoiceSA->setSeller('300123456700003', '0088', 'My Saudi Company LLC');
$invoiceSA->setSellerAddress('King Fahd Road', '12345', 'Riyadh', 'SA');
$invoiceSA->setSellerTaxRegistration('300123456700003', 'VAT');

$invoiceSA->setBuyer('300987654300003', 'Saudi Customer Co.');
$invoiceSA->setBuyerAddress('Al Madinah Road', '54321', 'Jeddah', 'SA');

$invoiceSA->addItem('Consulting Services', 5000.00, 15.0, 1, 'HUR');
$invoiceSA->setPrice(5000.00, 750.00);

// Create ZATCA submitter
$zatcaSubmitter = SubmitterFactory::create('SA', [
    'environment' => 'sandbox',
]);

// Authenticate with certificate
$zatcaSubmitter->authenticate([
    'certificate' => '/path/to/zatca_certificate.pem',
    'private_key' => '/path/to/private_key.pem',
    'secret' => 'your_secret_otp',
]);

// Submit for clearance (B2B) or reporting (B2C)
try {
    $result = $zatcaSubmitter->submit($invoiceSA);

    if ($result->success) {
        echo "✓ Invoice cleared by ZATCA!\n";
        echo "  UUID: {$result->governmentId}\n";
        echo "  Status: {$result->status}\n";
        echo "  QR Code: {$result->qrCode}\n";

        // Save the signed/cleared invoice
        if ($result->signedXml) {
            file_put_contents('/tmp/cleared_invoice.xml', $result->signedXml);
            echo "  Cleared invoice saved to /tmp/cleared_invoice.xml\n";
        }
    } else {
        echo "✗ Clearance failed: {$result->error}\n";
    }
} catch (SubmissionException $e) {
    echo "✗ Exception: {$e->getMessage()}\n";
}

echo "\n";

// =============================================================================
// EXAMPLE 3: Check what countries are supported
// =============================================================================

echo "=== Supported Countries ===\n";

$supported = SubmitterFactory::getSupportedCountries();
foreach ($supported as $code => $info) {
    echo "{$code} - {$info['name']}\n";
    echo "  Model: {$info['model']}\n";
    echo "  Requires clearance: " . ($info['requires_clearance'] ? 'Yes' : 'No') . "\n";
    echo "  Required config: " . implode(', ', $info['required_config']) . "\n";
    echo "\n";
}

// =============================================================================
// EXAMPLE 4: Handling multiple countries in a loop
// =============================================================================

echo "=== Multi-Country Submission ===\n";

$countries = [
    ['code' => 'FR', 'config' => ['pdp_provider' => 'basware', 'api_key' => 'key1']],
    ['code' => 'SA', 'config' => ['certificate' => '/path/cert.pem', 'private_key' => '/path/key.pem', 'secret' => 'secret']],
];

foreach ($countries as $country) {
    if (!SubmitterFactory::isSupported($country['code'])) {
        echo "✗ {$country['code']} is not yet supported\n";
        continue;
    }

    echo "Processing invoice for {$country['code']}...\n";

    try {
        $submitter = SubmitterFactory::create($country['code'], [
            'environment' => 'sandbox',
            ...$country['config']
        ]);

        // Authenticate and submit...
        // (implementation details omitted for brevity)

        echo "✓ {$country['code']} processed successfully\n";
    } catch (\Exception $e) {
        echo "✗ {$country['code']} failed: {$e->getMessage()}\n";
    }

    echo "\n";
}

echo "=== Complete ===\n";
