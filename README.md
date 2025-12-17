# Digital Invoice

Digital Invoice offers an effortless and unified interface for generating E-Invoices across various formats. It serves as a simplified wrapper around key libraries such as `easybill/zugferd-php`, `josemmo/einvoicing`, and `atgp/factur-x`. Our objective is to streamline the process of E-Invoice generation by bringing together the capabilities of these established libraries into one easy-to-use package.

We extend our sincere appreciation to the developers of these libraries for their foundational contributions to the field.

## Supported Formats

Digital Invoice supports a range of formats, catering to diverse invoicing needs:

- **Factur-X PDF Invoice**:
  - Profiles: `MINIMUM`, `BASIC_WL`, `BASIC`, `EN16931`, `EXTENDED`

- **ZUGFeRD PDF Invoice**:
  - Profiles: `CONFORT`, `BASIC`, `EXTENDED`

- **XRechnung XML Invoice**:
  - A widely-used German XML format

- **UBL XML Invoices**:
    - Profiles: `Peppol`, `Nlcius`, `CiusRo`, `CiusIt`, `CiusEsFace`, `CiusAtNat`, `CiusAtGov`, `Malaysia`

## Installation

```bash
composer require youniwemi/digital-invoice
```

## Quick Start Example

```php
use DigitalInvoice\Invoice;

// 1. Create invoice (one class, different profiles)
$invoice = new Invoice(
    invoiceId: 'INV-2025-001',
    issueDate: new DateTime('2025-01-15'),
    currency: 'EUR',
    profile: Invoice::UBL_PEPPOL  // Or FACTURX_BASIC, UBL_MALAYSIA, etc.
);

// 2. Configure invoice
$invoice->setSeller('12345678901234', '0002', 'My Company');
$invoice->setBuyer('98765432109876', 'Customer');
$invoice->addItem('Consulting Services', 1000.00, 20.0, 10, 'HUR');
$invoice->setPrice(10000.00, 2000.00);

// 3. Generate invoice files
$xml = $invoice->getXml();
$pdf = $invoice->getPdf($existingPdf);

// 4. Submit to tax authority (NEW!)
// Same unified API - just like creating invoices
$result = $invoice->submit('superpdp', [  // Provider identifier
    'client_id' => 'xxx',
    'client_secret' => 'yyy',
]);

if ($result->success) {
    echo "✓ Invoice submitted! ID: {$result->governmentId}";
}
```

**One API for everything:**
- **Invoice generation**: `new Invoice(..., profile: Invoice::FACTURX_BASIC)`
- **Invoice submission**: `$invoice->submit('superpdp', $credentials)`

Same pattern, just specify the provider. Easy!

## Getting Started

To integrate Digital Invoice into your PHP project:

1. Install via Composer (see Installation section above)
2. See the Quick Start Example above
3. Explore our test cases and examples for more usage guidelines

## Key Features

- **Unified Invoice Generation**: One class (`Invoice`) for all formats - just change the profile parameter
- **Unified E-Invoice Submission**: One class (`Submitter`) for all countries - just change the country parameter
- **Direct Tax Authority Submission**: Submit invoices to tax authorities and e-invoicing platforms worldwide
- **Multi-Country Support**: France (PDP), Saudi Arabia (ZATCA), and more coming soon
- **Enhanced Buyer Identification**: Support for multiple identification types (TAX, VAT, LEGAL, ELECTRONIC)
- **Multi-Currency Support**: Including Malaysian Ringgit (MYR) for Malaysia UBL invoices
- **Comprehensive Format Coverage**: Support for all major European e-invoicing standards plus Malaysia

Digital Invoice enables you to generate E-Invoices in the following formats with ease:

- **Factur-X PDF Invoice**:
  - Profiles: `MINIMUM`, `BASIC_WL`, `BASIC`, `EN16931`, `EXTENDED`

- **ZUGFeRD PDF Invoice**:
  - Profiles: `CONFORT`, `BASIC`, `EXTENDED`

- **XRechnung XML Invoice**:
  - A standard German XML format

- **UBL XML Invoices**:
    - Profiles: `Peppol`, `Nlcius`, `CiusRo`, `CiusIt`, `CiusEsFace`, `CiusAtNat`, `CiusAtGov`, `Malaysia`

## E-Invoice Submission

Digital Invoice now supports **direct submission to tax authorities and e-invoicing platforms** using the same unified API pattern as invoice generation.

### Supported Countries

| Country | Status | Submission Model | Notes |
|---------|--------|------------------|-------|
| 🇫🇷 **France** | ✅ Ready | PDP Intermediary | SuperPDP, Basware, Tradeshift, Pagero |
| 🇸🇦 **Saudi Arabia** | ✅ Ready | CTC Clearance | ZATCA (Fatoora) Phase 2 |
| 🇮🇹 **Italy** | 🚧 Coming Soon | SDI Clearance | Sistema di Interscambio |
| 🇲🇾 **Malaysia** | 🚧 Coming Soon | MyInvois Clearance | LHDN MyInvois |
| 🇧🇪🇸🇬🇳🇱 **Peppol** | 🚧 Coming Soon | Peppol Network | Belgium, Singapore, Netherlands |

### Unified Submission API

**Three ways to submit - choose what you prefer:**

```php
// 1. Simplest: Direct from Invoice (Recommended)
$result = $invoice->submit('superpdp', $credentials);

// 2. Using Submitter class (Reusable)
$submitter = new Submitter('superpdp');
$submitter->authenticate($credentials);
$result = $submitter->submit($invoice);

// 3. Using constants (Type-safe)
$submitter = new Submitter(Submitter::SUPERPDP);
$result = $submitter->submit($invoice);
```

**Same class, different providers:**

```php
// France SuperPDP
$submitter = new Submitter('superpdp');

// France Basware
$submitter = new Submitter('basware');

// Saudi Arabia
$submitter = new Submitter('sa');  // or Submitter::SAUDI_ZATCA

// Same API for all providers!
```

### Why This Matters

Many countries now **mandate** e-invoice submission to tax authorities:
- 🇫🇷 France: Mandatory September 2026 (large companies) / September 2027 (SMEs)
- 🇸🇦 Saudi Arabia: Mandatory since December 2021 (Phase 2)
- 🇮🇹 Italy: Mandatory since January 2019
- 🇲🇾 Malaysia: Mandatory rollout 2024-2025

Digital Invoice handles all the complexity - authentication, submission, status tracking, and error handling - with **one consistent API**.

### Learn More

- 📚 **Complete Guide**: See [SUBMISSION_GUIDE.md](SUBMISSION_GUIDE.md) for full documentation
- 🚀 **SuperPDP Guide**: See [SUPERPDP_GUIDE.md](SUPERPDP_GUIDE.md) for French e-invoicing
- 💡 **Examples**: Check `examples/unified_api_example.php` and `examples/superpdp_example.php`

## Development Status and Collaboration

**Active Development**: Please note that Digital Invoice is currently under active development. As such, the API and functionalities may undergo changes. We encourage users to use this package with caution and to stay updated with the latest versions for the most stable experience.

**Collaboration and Support**: We welcome collaboration and are eager to support specific use cases that enhance the utility of Digital Invoice. If you have unique invoicing needs or suggestions for improvements, we would be delighted to hear from you and work together to expand the capabilities of this package. Your contributions and feedback are invaluable in shaping a more versatile and robust tool.

## Contributors

@yassiNebeL : Adding support to UBL Format using josemmo/einvoicing

## Credits and Acknowledgements

We are grateful to the following projects and teams for their contributions:

- [ZUGFeRD PHP SDK (Factur-X, XRechnung)](https://github.com/easybill/zugferd-php) - For enabling the generation of Factur-X, XRechnung and Zugferd.
- [European Invoicing (eInvoicing)](https://github.com/josemmo/einvoicing) - For enabling the generation of UBL invoices.
- [PHP Factur-X Library](https://github.com/atgp/factur-x) - Providing foundational tools for Factur-X invoicing.
- [Tiime Software Team](https://github.com/Tiime-Software/EN-16931) - For their work on structured data types in E-Invoicing.