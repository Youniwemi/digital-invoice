# SuperPDP Integration Guide

## What is SuperPDP?

**SuperPDP** is "La Plateforme Agréée (PDP) la plus simple et la moins chère" - France's simplest and most affordable approved e-invoicing platform.

- **Registration Number**: 0111
- **Certifications**: ISO 27001, Peppol AP & SMP certified
- **Pricing**:
  - Free for up to 1,000 invoices/month
  - €0.0025 per invoice for API usage (volume-based)
- **Launch**: Autumn 2025
- **Website**: https://www.superpdp.tech
- **API**: https://api.superpdp.tech

---

## Quick Start

### 1. Create Account

Sign up at https://www.superpdp.tech to get:
- OAuth2 Client ID
- OAuth2 Client Secret

### 2. Install Package

```bash
composer require youniwemi/digital-invoice
```

### 3. Create and Submit Invoice

```php
use DigitalInvoice\Invoice;
use DigitalInvoice\Submission\Adapters\SuperPdpSubmitter;

// Create invoice
$invoice = new Invoice(
    'INV-2025-001',
    new DateTime('2025-01-15'),
    currency: 'EUR',
    profile: Invoice::UBL_PEPOOL
);

// Configure invoice (seller, buyer, items, etc.)
// ... see examples/superpdp_example.php

// Create SuperPDP submitter
$submitter = new SuperPdpSubmitter('production');

// Authenticate with OAuth2
$submitter->authenticate([
    'client_id' => 'your_client_id',
    'client_secret' => 'your_client_secret',
]);

// Submit invoice
$result = $submitter->submit($invoice);

if ($result->success) {
    echo "Invoice submitted! ID: {$result->governmentId}";
}
```

---

## Authentication

SuperPDP uses **OAuth2 Client Credentials** flow (different from other PDPs).

### OAuth2 Flow

```php
// The submitter handles OAuth2 automatically
$submitter->authenticate([
    'client_id' => 'your_client_id',
    'client_secret' => 'your_client_secret',
]);

// Token is automatically:
// - Requested from /oauth2/token
// - Cached and reused
// - Refreshed when expired
```

### Environment Variables

```bash
export SUPERPDP_CLIENT_ID="your_client_id"
export SUPERPDP_CLIENT_SECRET="your_client_secret"
```

```php
$submitter->authenticate([
    'client_id' => getenv('SUPERPDP_CLIENT_ID'),
    'client_secret' => getenv('SUPERPDP_CLIENT_SECRET'),
]);
```

---

## API Endpoints

SuperPDP provides these endpoints:

### Company Information

```php
$companyInfo = $submitter->getCompanyInfo();
// Returns: ['name' => '...', 'siren' => '...', ...]
```

### Submit Invoice

```php
$result = $submitter->submit($invoice);
// Internally calls: POST /v1.beta/invoices
```

### Get Invoice Status

```php
$status = $submitter->getStatus($invoiceId);
// Internally calls: GET /v1.beta/invoices/{id}
```

### Validate Invoice

```php
$validation = $submitter->validateInvoice($invoice);
// Pre-validates before submission
// Internally calls: POST /v1.beta/validation_reports
```

### List Invoices

```php
$invoices = $submitter->listInvoices(
    startingAfterId: null,  // For pagination
    order: 'desc',          // 'asc' or 'desc'
    limit: 50               // Number of results
);
```

### Generate Test Invoice

```php
$testXml = $submitter->generateTestInvoice();
// Get a sample invoice for testing
```

---

## Invoice Requirements

### Format

SuperPDP requires **UBL 2.1** format:

```php
$invoice = new Invoice(
    'INV-001',
    new DateTime(),
    currency: 'EUR',
    profile: Invoice::UBL_PEPOOL  // ✓ Correct
);

// Not: Invoice::FACTURX_BASIC  // ✗ Wrong
```

### Required Fields

#### Seller (Your Company)
```php
$invoice->setSeller(
    '12345678901234',  // SIRET (14 digits)
    '0002',            // Identifier scheme
    'My Company SARL'  // Company name
);

$invoice->setSellerAddress(
    '123 Rue de Paris',
    '75001',
    'Paris',
    'FR'
);

$invoice->setSellerTaxRegistration(
    'FR12345678901',  // VAT number
    'VAT'
);
```

#### Buyer (Customer)
```php
$invoice->setBuyer('98765432109876', 'Customer SAS');

$invoice->setBuyerAddress(
    '456 Avenue Victor Hugo',
    '75016',
    'Paris',
    'FR'
);

$invoice->setBuyerIdentifier(
    '98765432109876',  // Customer SIRET
    '0002',
    'Legal'
);
```

#### Items
```php
$invoice->addItem(
    'Consulting Services',  // Description
    1000.00,                // Unit price
    20.0,                   // VAT rate (%)
    10,                     // Quantity
    'HUR'                   // Unit code (hours)
);
```

#### Payment
```php
$invoice->addPaymentMean(
    '30',                               // Payment means code (30 = bank transfer)
    'FR7612345678901234567890123',     // IBAN
    'My Company',                       // Account name
    'BNPAFRPP'                          // BIC
);

$invoice->setPaymentTerms(
    new DateTime('+30 days'),
    'Payable within 30 days'
);
```

#### Totals
```php
$invoice->setPrice(
    11000.00,  // Net total
    2200.00    // VAT amount
);
// Total to pay: 13200.00
```

---

## Status Codes

SuperPDP returns these statuses:

| SuperPDP Status | Mapped To | Meaning |
|----------------|-----------|---------|
| `submitted` | `pending` | Invoice submitted, awaiting processing |
| `pending` | `pending` | Being processed |
| `validated` | `accepted` | Invoice validated and accepted |
| `accepted` | `accepted` | Invoice accepted by recipient |
| `delivered` | `accepted` | Invoice delivered to recipient |
| `rejected` | `rejected` | Invoice rejected |
| `failed` | `rejected` | Processing failed |

Check status:

```php
$status = $submitter->getStatus($invoiceId);

if ($status->isAccepted()) {
    echo "✓ Invoice accepted!";
} elseif ($status->isPending()) {
    echo "⏳ Still processing...";
} elseif ($status->isRejected()) {
    echo "✗ Invoice rejected: " . $status->rejectionReason;
}
```

---

## Error Handling

```php
use DigitalInvoice\Submission\Exception\SubmissionException;
use DigitalInvoice\Submission\Exception\AuthenticationException;
use DigitalInvoice\Submission\Exception\ValidationException;

try {
    $result = $submitter->submit($invoice);
} catch (AuthenticationException $e) {
    // OAuth2 authentication failed
    echo "Auth error: {$e->getMessage()}";
    // Check client_id and client_secret
} catch (ValidationException $e) {
    // Invoice validation failed
    foreach ($e->getValidationErrors() as $error) {
        echo "Validation: $error\n";
    }
} catch (SubmissionException $e) {
    // General submission error
    echo "Error: {$e->getMessage()}";
    $context = $e->getContext();
    // Check $context for details
}
```

---

## Using via Factory

You can also use SuperPDP through the factory:

```php
use DigitalInvoice\Submission\SubmitterFactory;

$submitter = SubmitterFactory::create('FR', [
    'pdp_provider' => 'superpdp',
    'environment' => 'production',
]);

$submitter->authenticate([
    'client_id' => 'xxx',
    'client_secret' => 'yyy',
]);

$result = $submitter->submit($invoice);
```

This is useful for multi-country applications where you want to switch between countries easily.

---

## Pagination

List all invoices with pagination:

```php
$allInvoices = [];
$startingAfterId = null;

do {
    $batch = $submitter->listInvoices(
        startingAfterId: $startingAfterId,
        order: 'desc',
        limit: 100
    );

    $allInvoices = array_merge($allInvoices, $batch['data'] ?? []);

    // Get last ID for next page
    if (!empty($batch['data'])) {
        $lastInvoice = end($batch['data']);
        $startingAfterId = $lastInvoice['id'];
    }

} while (!empty($batch['data']) && count($batch['data']) === 100);

echo "Total invoices: " . count($allInvoices);
```

---

## Testing

### 1. Use Test Invoice Generator

```php
$testXml = $submitter->generateTestInvoice();
file_put_contents('/tmp/test_invoice.xml', $testXml);

// Parse and modify if needed
// Then submit
```

### 2. Validate Before Submitting

```php
$validation = $submitter->validateInvoice($invoice);

if (!$validation->valid) {
    echo "Errors found:\n";
    foreach ($validation->errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}

// Validation passed, safe to submit
$result = $submitter->submit($invoice);
```

### 3. Check Company Info

```php
$info = $submitter->getCompanyInfo();
echo "Connected as: {$info['name']}\n";
echo "SIREN: {$info['siren']}\n";
```

---

## Comparison with Other PDPs

| Feature | SuperPDP | Basware | Tradeshift | Pagero |
|---------|----------|---------|------------|--------|
| **Authentication** | OAuth2 | API Key | API Key | API Key |
| **Free Tier** | ✅ 1,000/month | ❌ | ❌ | ❌ |
| **Pricing** | €0.0025/invoice | Higher | Higher | Higher |
| **Peppol Certified** | ✅ | ✅ | ✅ | ✅ |
| **ISO 27001** | ✅ | ✅ | ✅ | ✅ |
| **Test Invoice Generator** | ✅ | ❌ | ❌ | ❌ |
| **API Version** | v1.beta | Various | Various | Various |

### Why Choose SuperPDP?

✅ **Cheapest option** - Free for small businesses
✅ **Simplest** - Easy OAuth2, clear API
✅ **Certified** - ISO 27001, Peppol AP/SMP
✅ **Developer-friendly** - Test invoice generator, clear docs
✅ **Made in France** - "Conçu avec amour à Paris, France"

---

## Complete Example

See `examples/superpdp_example.php` for a complete working example that demonstrates:

1. ✅ Creating an invoice
2. ✅ OAuth2 authentication
3. ✅ Company info verification
4. ✅ Invoice validation
5. ✅ Invoice submission
6. ✅ Status checking
7. ✅ Listing invoices
8. ✅ Test invoice generation

Run it:

```bash
export SUPERPDP_CLIENT_ID="your_id"
export SUPERPDP_CLIENT_SECRET="your_secret"
php examples/superpdp_example.php
```

---

## Support

- **SuperPDP Website**: https://www.superpdp.tech
- **API Docs**: https://www.superpdp.tech/documentation
- **Newsletter**: Subscribe on website for updates
- **Human Support**: Available via contact page
- **Launch**: Autumn 2025

---

## Links

- 📚 Main Submission Guide: `SUBMISSION_GUIDE.md`
- 🔧 Generic France PDP: `FrancePdpSubmitter.php`
- 🚀 SuperPDP Adapter: `SuperPdpSubmitter.php`
- 💡 Example Code: `examples/superpdp_example.php`

---

**Made with ❤️ for French e-invoicing**
