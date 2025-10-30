# E-Invoice Submission Abstraction Layer

## What is This?

A **unified abstraction layer** for submitting e-invoices to tax authorities worldwide. One API, multiple countries.

## Structure

```
src/Submission/
├── SubmitterInterface.php          # Main interface all adapters implement
├── AbstractSubmitter.php            # Base class with common functionality
├── SubmitterFactory.php             # Factory to create country-specific submitters
│
├── Adapters/                        # Country-specific implementations
│   ├── FrancePdpSubmitter.php      # France (PDP/Y-model)
│   ├── SaudiZatcaSubmitter.php     # Saudi Arabia (ZATCA clearance)
│   └── [More countries to come]
│
├── Result/                          # Value objects for results
│   ├── SubmissionResult.php        # Result from submit()
│   ├── InvoiceStatus.php           # Result from getStatus()
│   └── ValidationResult.php        # Result from validateInvoice()
│
└── Exception/                       # Custom exceptions
    ├── SubmissionException.php     # Base exception
    ├── AuthenticationException.php # Auth failures
    └── ValidationException.php     # Validation failures
```

## Quick Example

```php
use DigitalInvoice\Submission\SubmitterFactory;

// Create invoice (as normal)
$invoice = new Invoice(...);

// Create submitter for any country
$submitter = SubmitterFactory::create('FR', [
    'environment' => 'sandbox',
    'pdp_provider' => 'basware',
]);

// Authenticate and submit
$submitter->authenticate(['api_key' => 'xxx']);
$result = $submitter->submit($invoice);

// Same API works for Saudi Arabia, Italy, Malaysia, etc.
```

## Supported Countries

| Code | Country | Model | Status |
|------|---------|-------|--------|
| FR | France | Intermediary (PDP) | ✅ Ready |
| SA | Saudi Arabia | CTC Clearance (ZATCA) | ✅ Ready |
| IT | Italy | CTC Clearance (SDI) | 🚧 Coming |
| MY | Malaysia | CTC Clearance (MyInvois) | 🚧 Coming |
| BE/SG/NL | Various | Peppol Network | 🚧 Coming |

## Key Features

### 1. **Unified Interface**
All countries use the same methods:
- `submit()` - Submit invoice
- `getStatus()` - Check status
- `authenticate()` - Authenticate
- `validateInvoice()` - Pre-validate

### 2. **Three Submission Models**
- **CTC Clearance**: Real-time approval (Saudi, Italy, Malaysia)
- **Intermediary**: Via certified platforms (France PDP)
- **Peppol Network**: Peer-to-peer (Belgium, Singapore)

### 3. **Extensible**
Add new countries by:
1. Create adapter in `Adapters/`
2. Implement `SubmitterInterface`
3. Extend `AbstractSubmitter`
4. Register in `SubmitterFactory`

### 4. **Type-Safe Results**
- `SubmissionResult` - Rich result object
- `InvoiceStatus` - Status with history
- `ValidationResult` - Errors and warnings

### 5. **Proper Error Handling**
- Custom exceptions with context
- Validation errors separate from submission errors
- Authentication errors clearly identified

## Documentation

See `SUBMISSION_GUIDE.md` in the root directory for:
- Complete usage examples
- Country-specific guides
- Configuration details
- Error handling patterns
- Best practices

## Adding New Countries

Template:

```php
namespace DigitalInvoice\Submission\Adapters;

class CountrySubmitter extends AbstractSubmitter
{
    protected function getApiBaseUrl(): string { }
    protected function transformInvoice(Invoice $invoice): string { }
    public function submit(Invoice $invoice): SubmissionResult { }
    public function getStatus(string $referenceId): InvoiceStatus { }
    public function getCountryCode(): string { return 'XX'; }
    public function getSubmissionModel(): string { return 'ctc_clearance'; }
    public function requiresClearance(): bool { return true; }
}
```

## Philosophy

**"Generate once, submit anywhere"**

This package already generates invoices in multiple formats (UBL, Factur-X, ZUGFeRD). Now it can also submit them to any country's tax authority using a single, consistent API.

## License

MIT - Same as parent library
