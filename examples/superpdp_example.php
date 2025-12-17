<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DigitalInvoice\Invoice;
use DigitalInvoice\Submission\Adapters\SuperPdpSubmitter;
use DigitalInvoice\Submission\Exception\SubmissionException;

/**
 * Example: Using SuperPDP for French e-invoicing
 *
 * SuperPDP is a certified French PDP (Plateforme Agréée #0111)
 * - Free for up to 1,000 invoices/month
 * - €0.0025 per invoice for API usage
 * - ISO 27001 certified
 * - Peppol certified
 */

echo "=== SuperPDP Example ===\n\n";

// =============================================================================
// STEP 1: Create Invoice
// =============================================================================

$invoice = new Invoice(
    invoiceId: 'SUPERPDP-TEST-001',
    issueDate: new DateTime('2025-01-15'),
    currency: 'EUR',
    profile: Invoice::UBL_PEPOOL
);

// Configure seller
$invoice->setSeller('12345678901234', '0002', 'Ma Société SARL');
$invoice->setSellerAddress('123 Rue de Paris', '75001', 'Paris', 'FR');
$invoice->setSellerTaxRegistration('FR12345678901', 'VAT');
$invoice->setSellerContact('Jean Dupont', '0123456789', 'contact@masociete.fr');

// Configure buyer
$invoice->setBuyer('98765432109876', 'Client SAS');
$invoice->setBuyerAddress('456 Avenue Victor Hugo', '75016', 'Paris', 'FR');
$invoice->setBuyerIdentifier('98765432109876', '0002', 'Legal'); // SIRET

// Add items
$invoice->addItem('Consulting Services', 1000.00, 20.0, 10, 'HUR', null, '0160', 'Software consulting');
$invoice->addItem('License Fee', 500.00, 20.0, 1, 'C62', null, '0160', 'Annual license');

// Payment details
$invoice->addPaymentMean('30', 'FR7612345678901234567890123', 'Ma Société', 'BNPAFRPP');
$invoice->setPaymentTerms(new DateTime('+30 days'), 'Payable within 30 days');

// Set totals
$invoice->setPrice(11000.00, 2200.00); // 11000 + 20% VAT = 13200

echo "✓ Invoice created: SUPERPDP-TEST-001\n\n";

// =============================================================================
// STEP 2: Initialize SuperPDP Submitter
// =============================================================================

$submitter = new SuperPdpSubmitter('production'); // or 'sandbox'

// Authenticate with OAuth2 credentials
// Get these from https://www.superpdp.tech after creating an account
try {
    $submitter->authenticate([
        'client_id' => getenv('SUPERPDP_CLIENT_ID') ?: 'your_client_id',
        'client_secret' => getenv('SUPERPDP_CLIENT_SECRET') ?: 'your_client_secret',
    ]);

    echo "✓ Authenticated with SuperPDP via OAuth2\n\n";
} catch (\Exception $e) {
    echo "✗ Authentication failed: {$e->getMessage()}\n";
    echo "Please set SUPERPDP_CLIENT_ID and SUPERPDP_CLIENT_SECRET environment variables\n";
    exit(1);
}

// =============================================================================
// STEP 3: Verify Company Information (optional)
// =============================================================================

try {
    $companyInfo = $submitter->getCompanyInfo();
    echo "Company Information:\n";
    echo "  Name: " . ($companyInfo['name'] ?? 'N/A') . "\n";
    echo "  SIREN: " . ($companyInfo['siren'] ?? 'N/A') . "\n";
    echo "  Registration: " . ($companyInfo['pdp_registration'] ?? '0111') . "\n\n";
} catch (SubmissionException $e) {
    echo "Warning: Could not fetch company info: {$e->getMessage()}\n\n";
}

// =============================================================================
// STEP 4: Validate Invoice (optional pre-check)
// =============================================================================

echo "Validating invoice...\n";
$validation = $submitter->validateInvoice($invoice);

if (!$validation->valid) {
    echo "✗ Validation errors found:\n";
    foreach ($validation->errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}

if (!empty($validation->warnings)) {
    echo "⚠ Validation warnings:\n";
    foreach ($validation->warnings as $warning) {
        echo "  - $warning\n";
    }
}

echo "✓ Invoice passed validation\n\n";

// =============================================================================
// STEP 5: Submit Invoice to SuperPDP
// =============================================================================

echo "Submitting invoice to SuperPDP...\n";

try {
    $result = $submitter->submit($invoice);

    if ($result->success) {
        echo "✓ Invoice submitted successfully!\n";
        echo "  Reference ID: {$result->referenceId}\n";
        echo "  Government ID: {$result->governmentId}\n";
        echo "  Status: {$result->status}\n";
        echo "  Message: {$result->getStatusMessage()}\n";

        if (!empty($result->metadata)) {
            echo "  Metadata:\n";
            foreach ($result->metadata as $key => $value) {
                echo "    - $key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
            }
        }

        echo "\n";

        // =============================================================================
        // STEP 6: Check Status
        // =============================================================================

        echo "Checking invoice status...\n";
        $status = $submitter->getStatus($result->referenceId);

        echo "  Current status: {$status->status}\n";
        echo "  Status date: {$status->statusDate}\n";

        if ($status->isAccepted()) {
            echo "  ✓ Invoice has been accepted!\n";
        } elseif ($status->isPending()) {
            echo "  ⏳ Invoice is still being processed\n";
        } elseif ($status->isRejected()) {
            echo "  ✗ Invoice was rejected\n";
            if ($status->rejectionReason) {
                echo "  Reason: {$status->rejectionReason}\n";
            }
        }

        echo "\n";

        // =============================================================================
        // STEP 7: List Recent Invoices
        // =============================================================================

        echo "Listing recent invoices...\n";
        $invoices = $submitter->listInvoices(null, 'desc', 5);

        if (isset($invoices['data']) && !empty($invoices['data'])) {
            echo "Recent invoices:\n";
            foreach ($invoices['data'] as $inv) {
                echo "  - ID: {$inv['id']} | Status: {$inv['status']} | Created: {$inv['created_at']}\n";
            }
        } else {
            echo "  No invoices found\n";
        }

    } else {
        echo "✗ Submission failed: {$result->error}\n";
        if (!empty($result->rawResponse)) {
            echo "Raw response:\n";
            print_r($result->rawResponse);
        }
    }

} catch (SubmissionException $e) {
    echo "✗ Exception during submission: {$e->getMessage()}\n";
    echo "Context: " . json_encode($e->getContext()) . "\n";

    // Check if it's an authentication error
    if ($e instanceof \DigitalInvoice\Submission\Exception\AuthenticationException) {
        echo "\nAuthentication failed. Please check your credentials.\n";
    }
}

echo "\n=== Example Complete ===\n";

// =============================================================================
// BONUS: Generate and Submit Test Invoice
// =============================================================================

echo "\n=== Testing with SuperPDP Test Invoice ===\n";

try {
    echo "Generating test invoice from SuperPDP...\n";
    $testInvoiceXml = $submitter->generateTestInvoice();

    echo "✓ Test invoice generated\n";
    echo "  Length: " . strlen($testInvoiceXml) . " bytes\n";

    // You could submit this test invoice
    // file_put_contents('/tmp/superpdp_test_invoice.xml', $testInvoiceXml);
    // echo "  Saved to /tmp/superpdp_test_invoice.xml\n";

} catch (SubmissionException $e) {
    echo "✗ Could not generate test invoice: {$e->getMessage()}\n";
}

echo "\n=== All Tests Complete ===\n";
