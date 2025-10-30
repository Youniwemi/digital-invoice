# E-Invoice Submission Guide

## Overview

The Digital Invoice library now includes a **unified submission abstraction layer** that allows you to submit invoices directly to tax authorities and e-invoicing platforms worldwide using a **single, consistent API**.

## Why Use This?

Instead of learning different APIs for each country:

```php
// ❌ Without abstraction - different API for each country
$italySdi->submitToSDI($invoice, $certificate);
$zatca->clearInvoice($xml, $uuid, $hash);
$pdp->sendFlowToPlateforme($facturx, $siret);
```

You get **one unified interface**:

```php
// ✅ With abstraction - same API for all countries
$submitter = SubmitterFactory::create('IT'); // or 'FR', 'SA', 'MY', etc.
$submitter->authenticate($credentials);
$result = $submitter->submit($invoice);
```

## Supported Countries

| Country | Code | Model | Status | Required Config |
|---------|------|-------|--------|----------------|
| **France** | `FR` | PDP Intermediary | ✅ Ready | `pdp_provider`, `api_key` |
| **Saudi Arabia** | `SA` | CTC Clearance | ✅ Ready | `certificate`, `private_key`, `secret` |
| **Italy** | `IT` | SDI Clearance | 🚧 Coming Soon | `certificate`, `private_key` |
| **Malaysia** | `MY` | MyInvois Clearance | 🚧 Coming Soon | `api_key`, `tin` |
| **Peppol (BE, SG, NL)** | Various | Peppol Network | 🚧 Coming Soon | `access_point_url`, `api_key` |

> 💡 **Extensible**: Add new countries by implementing the `SubmitterInterface`

---

## Architecture

### Three Submission Models

#### 1. **CTC Clearance Model**
**Countries**: Saudi Arabia, Italy, Malaysia, Brazil, Mexico, Chile, India

**Flow**:
```
Invoice → Tax Authority (clearance) → Approved ✓ → Send to Customer
```

**Characteristics**:
- Real-time approval required before sending
- Government assigns unique ID/hash
- Invoice can be rejected
- Example: ZATCA, SDI, MyInvois

#### 2. **Intermediary Model**
**Countries**: France

**Flow**:
```
Invoice → Certified Platform (PDP) → Tax Authority + Customer
```

**Characteristics**:
- Uses certified intermediaries
- Real-time tax reporting
- Platform handles routing
- Example: France's PDP system

#### 3. **Peppol Network Model**
**Countries**: Belgium, Singapore, Netherlands, UAE (planned)

**Flow**:
```
Sender → Access Point → Peppol Network → Access Point → Recipient
```

**Characteristics**:
- Decentralized peer-to-peer
- Cross-border interoperability
- Requires certified Access Point
- Example: InvoiceNow, Peppol BIS

---

## Quick Start

### Step 1: Install Dependencies

```bash
composer require guzzlehttp/guzzle
composer install
```

### Step 2: Create Invoice (as usual)

```php
use DigitalInvoice\Invoice;

$invoice = new Invoice(
    invoiceId: 'INV-2025-001',
    issueDate: new DateTime('2025-01-15'),
    currency: 'EUR',
    profile: Invoice::UBL_PEPOOL
);

// Configure invoice as normal
$invoice->setSeller('12345678901234', '0002', 'My Company');
$invoice->setBuyer('98765432109876', 'Customer Name');
// ... add items, etc.
```

### Step 3: Submit to Tax Authority

```php
use DigitalInvoice\Submission\SubmitterFactory;

// Create submitter for France
$submitter = SubmitterFactory::create('FR', [
    'environment' => 'sandbox',
    'pdp_provider' => 'basware',
]);

// Authenticate
$submitter->authenticate([
    'api_key' => 'your_api_key_here',
]);

// Submit
$result = $submitter->submit($invoice);

if ($result->success) {
    echo "✓ Submitted! ID: {$result->governmentId}";
} else {
    echo "✗ Failed: {$result->error}";
}
```

---

## Country-Specific Examples

### France (PDP)

```php
$submitter = SubmitterFactory::create('FR', [
    'environment' => 'production',
    'pdp_provider' => 'basware', // or 'tradeshift', 'pagero'
]);

$submitter->authenticate([
    'api_key' => 'your_pdp_api_key',
]);

// Optional: Query Annuaire directory
$recipientInfo = $submitter->queryAnnuaire('12345678901234'); // SIRET
echo "Recipient PDP: " . $recipientInfo['pdp_name'];

// Submit invoice
$result = $submitter->submit($invoice);

// Check status later
$status = $submitter->getStatus($result->referenceId);
echo "Status: {$status->status}";
```

**France-Specific Notes**:
- Buyer must have valid 14-digit SIRET
- Supports UBL 2.1, Factur-X, or CII formats
- XP Z12-013 standard API
- All PDPs use same API (interchangeable)

---

### Saudi Arabia (ZATCA)

```php
$submitter = SubmitterFactory::create('SA', [
    'environment' => 'sandbox',
]);

$submitter->authenticate([
    'certificate' => '/path/to/zatca_cert.pem',
    'private_key' => '/path/to/private_key.pem',
    'secret' => 'your_otp_or_secret',
]);

$result = $submitter->submit($invoice);

if ($result->success) {
    echo "UUID: {$result->governmentId}\n";
    echo "QR Code: {$result->qrCode}\n";

    // Save cleared invoice with stamp
    file_put_contents('cleared.xml', $result->signedXml);
}
```

**ZATCA-Specific Notes**:
- B2B invoices require **clearance** before sending to customer
- B2C invoices require **reporting** within 24 hours
- Returns QR code and signed XML
- VAT number must be 15 digits (starts/ends with 3)

---

## Advanced Usage

### Validation Before Submission

```php
$validation = $submitter->validateInvoice($invoice);

if (!$validation->valid) {
    echo "Errors:\n";
    foreach ($validation->errors as $error) {
        echo "  ✗ $error\n";
    }

    echo "Warnings:\n";
    foreach ($validation->warnings as $warning) {
        echo "  ⚠ $warning\n";
    }

    exit(1);
}

// Proceed with submission
$result = $submitter->submit($invoice);
```

### Error Handling

```php
use DigitalInvoice\Submission\Exception\SubmissionException;
use DigitalInvoice\Submission\Exception\AuthenticationException;
use DigitalInvoice\Submission\Exception\ValidationException;

try {
    $result = $submitter->submit($invoice);
} catch (AuthenticationException $e) {
    // Authentication failed
    echo "Auth failed: {$e->getMessage()}";
    // Re-authenticate or check credentials
} catch (ValidationException $e) {
    // Invoice validation failed
    foreach ($e->getValidationErrors() as $error) {
        echo "Validation: $error\n";
    }
} catch (SubmissionException $e) {
    // General submission error
    echo "Error: {$e->getMessage()}";
    $context = $e->getContext();
    var_dump($context); // Additional error details
}
```

### Multi-Country Support

```php
$invoices = [
    ['invoice' => $frenchInvoice, 'country' => 'FR', 'config' => [...]],
    ['invoice' => $saudiInvoice, 'country' => 'SA', 'config' => [...]],
];

foreach ($invoices as $item) {
    try {
        $submitter = SubmitterFactory::create($item['country'], $item['config']);
        $submitter->authenticate($item['config']);

        $result = $submitter->submit($item['invoice']);

        echo "{$item['country']}: " . ($result->success ? '✓' : '✗') . "\n";
    } catch (\Exception $e) {
        echo "{$item['country']}: Error - {$e->getMessage()}\n";
    }
}
```

### Check Supported Countries

```php
// Check if supported
if (SubmitterFactory::isSupported('IT')) {
    echo "Italy is supported!";
}

// Get all supported
$countries = SubmitterFactory::getSupportedCountries();
foreach ($countries as $code => $info) {
    echo "{$code} - {$info['name']} ({$info['model']})\n";
}

// Get required config for a country
$required = SubmitterFactory::getRequiredConfig('FR');
// Returns: ['pdp_provider', 'api_key']
```

---

## Submission Result

All submissions return a `SubmissionResult` object:

```php
$result = $submitter->submit($invoice);

// Properties
$result->success;        // bool - Was submission successful?
$result->referenceId;    // string - Internal reference for tracking
$result->governmentId;   // string - ID from tax authority (UUID, IRN, etc.)
$result->status;         // string - Status: 'submitted', 'accepted', 'pending', etc.
$result->qrCode;         // string|null - QR code (if applicable)
$result->signedXml;      // string|null - Signed/stamped XML (if applicable)
$result->error;          // string|null - Error message if failed
$result->rawResponse;    // array - Complete API response
$result->metadata;       // array - Country-specific metadata

// Helper methods
$result->isAccepted();   // bool - Invoice accepted/cleared?
$result->isRejected();   // bool - Invoice rejected?
$result->isPending();    // bool - Still processing?
$result->getStatusMessage(); // string - Human-readable status

// Convert to array
$array = $result->toArray();
```

---

## Invoice Status

Check status of previously submitted invoices:

```php
$status = $submitter->getStatus($result->referenceId);

// Properties
$status->status;          // string - Current status code
$status->statusDate;      // string|null - Last update timestamp
$status->rejectionReason; // string|null - Why rejected (if applicable)
$status->statusHistory;   // array - History of status changes
$status->metadata;        // array - Country-specific data

// Helper methods
$status->isAccepted();
$status->isRejected();
$status->isPending();

// Convert to array
$array = $status->toArray();
```

---

## Adding New Countries

Want to add support for a new country? Follow this pattern:

### 1. Create Adapter Class

```php
// src/Submission/Adapters/ItalySdiSubmitter.php

namespace DigitalInvoice\Submission\Adapters;

use DigitalInvoice\Submission\AbstractSubmitter;

class ItalySdiSubmitter extends AbstractSubmitter
{
    protected function getApiBaseUrl(): string
    {
        return $this->environment === 'sandbox'
            ? 'https://testservizi.fatturapa.it/ricevifatture'
            : 'https://sdi.fatturapa.it/ricevifatture';
    }

    protected function transformInvoice(Invoice $invoice): string
    {
        // Transform to FatturaPA XML
        return $invoice->getXml();
    }

    public function submit(Invoice $invoice): SubmissionResult
    {
        // Implement submission to SDI
    }

    public function getStatus(string $referenceId): InvoiceStatus
    {
        // Implement status query
    }

    // ... implement other methods

    public function getCountryCode(): string { return 'IT'; }
    public function getSubmissionModel(): string { return 'ctc_clearance'; }
    public function requiresClearance(): bool { return true; }
}
```

### 2. Register in Factory

```php
// src/Submission/SubmitterFactory.php

return match ($countryCode) {
    // ... existing countries
    'IT' => new ItalySdiSubmitter($environment),
    // ...
};
```

### 3. Add to Supported List

```php
public static function getSupportedCountries(): array
{
    return [
        // ...
        'IT' => [
            'name' => 'Italy',
            'model' => 'ctc_clearance',
            'requires_clearance' => true,
            'description' => 'SDI (Sistema di Interscambio) clearance',
            'required_config' => ['certificate', 'private_key'],
        ],
    ];
}
```

---

## Testing

### Sandbox Environments

All adapters support sandbox/test environments:

```php
$submitter = SubmitterFactory::create('FR', [
    'environment' => 'sandbox', // Use test environment
    'pdp_provider' => 'basware',
]);
```

### Mock Submitter for Unit Tests

```php
use DigitalInvoice\Submission\SubmitterInterface;
use DigitalInvoice\Submission\Result\SubmissionResult;

class MockSubmitter implements SubmitterInterface
{
    public function submit(Invoice $invoice): SubmissionResult
    {
        return new SubmissionResult(
            success: true,
            referenceId: 'mock-ref-123',
            governmentId: 'mock-gov-456',
            status: 'accepted'
        );
    }

    // ... implement other methods
}

// Use in tests
$submitter = new MockSubmitter();
$result = $submitter->submit($invoice);
assert($result->success === true);
```

---

## Configuration Examples

### France PDP Configuration

```php
[
    'environment' => 'production',
    'pdp_provider' => 'basware',  // Choose: basware, tradeshift, pagero, etc.
    'api_key' => 'your_pdp_api_key',
]
```

### Saudi ZATCA Configuration

```php
[
    'environment' => 'sandbox',
    'certificate' => '/path/to/zatca_cert.pem',
    'private_key' => '/path/to/private_key.pem',
    'secret' => 'your_otp_secret',
]
```

### Peppol Configuration (Future)

```php
[
    'environment' => 'production',
    'access_point_url' => 'https://ap.example.com',
    'api_key' => 'your_ap_api_key',
    'participant_id' => '0088:1234567890',
]
```

---

## Best Practices

### 1. Always Validate First

```php
$validation = $submitter->validateInvoice($invoice);
if ($validation->valid) {
    $result = $submitter->submit($invoice);
}
```

### 2. Handle Errors Gracefully

```php
try {
    $result = $submitter->submit($invoice);
} catch (SubmissionException $e) {
    // Log error
    error_log("Submission failed: " . $e->getMessage());

    // Notify user
    // Queue for retry
}
```

### 3. Store Reference IDs

```php
$result = $submitter->submit($invoice);

// Store in database
DB::table('invoices')->where('id', $invoiceId)->update([
    'government_id' => $result->governmentId,
    'submission_reference' => $result->referenceId,
    'submission_status' => $result->status,
    'submitted_at' => now(),
]);
```

### 4. For CTC Clearance Models

```php
// Get clearance BEFORE sending to customer
$result = $submitter->submit($invoice);

if ($submitter->requiresClearance() && $result->isAccepted()) {
    // Use the CLEARED invoice (with government stamp)
    $clearedInvoice = $result->signedXml;

    // Send THIS to customer, not the original
    sendToCustomer($clearedInvoice);
}
```

### 5. Monitor Status for Async Processing

```php
$result = $submitter->submit($invoice);

if ($result->isPending()) {
    // Poll status until complete
    do {
        sleep(5);
        $status = $submitter->getStatus($result->referenceId);
    } while ($status->isPending());

    echo "Final status: {$status->status}";
}
```

---

## Troubleshooting

### Authentication Failures

```php
// Check credentials are correct
$requiredKeys = SubmitterFactory::getRequiredConfig('FR');
// Returns: ['pdp_provider', 'api_key']

// Ensure all required keys are provided
foreach ($requiredKeys as $key) {
    if (empty($credentials[$key])) {
        throw new \Exception("Missing required config: $key");
    }
}
```

### Validation Errors

```php
$validation = $submitter->validateInvoice($invoice);

foreach ($validation->getAllIssues() as $issue) {
    echo "{$issue['type']}: {$issue['message']}\n";
}
```

### Network Timeouts

```php
// Increase timeout (for slow APIs)
// This would need to be implemented in the adapter
// Currently uses default 60 seconds
```

---

## Roadmap

### Phase 1 (Complete)
- ✅ Abstraction layer interfaces
- ✅ France PDP adapter
- ✅ Saudi ZATCA adapter
- ✅ Factory pattern
- ✅ Result objects
- ✅ Exception handling

### Phase 2 (Next)
- 🚧 Italy SDI adapter
- 🚧 Malaysia MyInvois adapter
- 🚧 Peppol network adapter
- 🚧 Unit tests

### Phase 3 (Future)
- ⏳ Brazil NFe adapter
- ⏳ Mexico CFDI adapter
- ⏳ India IRP adapter
- ⏳ Spain SII adapter
- ⏳ Async/queue support
- ⏳ Webhook handling

---

## Support

- **GitHub Issues**: https://github.com/Youniwemi/digital-invoice/issues
- **Documentation**: See examples/ directory
- **Contributing**: PRs welcome for new country adapters!

---

## License

MIT License - Same as the main Digital Invoice library
