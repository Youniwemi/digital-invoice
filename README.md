# Digital Invoice

Digital Invoice offers a unified interface for **generating and reading** e-invoices across all major formats. It wraps `easybill/zugferd-php`, `josemmo/einvoicing`, and `atgp/factur-x` into a single, consistent API.

## Supported Formats

| Format | Profiles | Generate | Read |
|--------|----------|:--------:|:----:|
| **Factur-X / ZUGFeRD 2.x** | `MINIMUM`, `BASIC_WL`, `BASIC`, `EN16931`, `EXTENDED` | ✓ | ✓ |
| **ZUGFeRD 1.0** | `CONFORT`, `BASIC`, `EXTENDED` | ✓ | ✓ |
| **XRechnung** | — | ✓ | ✓ |
| **UBL** | `Peppol`, `NLCIUS`, `CIUS-RO`, `CIUS-IT`, `CIUS-ES-FACE`, `CIUS-AT-NAT`, `CIUS-AT-GOV`, `Malaysia` | ✓ | ✓ |

## Installation

```bash
composer require youniwemi/digital-invoice
```

## Generating an invoice

```php
use DigitalInvoice\Invoice;
use DigitalInvoice\CurrencyCode;
use DigitalInvoice\FacturX;

$invoice = new Invoice('INV-2024-001', new DateTime(), null, CurrencyCode::EURO, FacturX::BASIC);

$invoice->setSeller('12345', '0002', 'ACME Corp', 'ACME');
$invoice->setSellerAddress('1 rue de la Paix', '75001', 'Paris', 'FR');
$invoice->setSellerTaxRegistration('FR12312345678', 'VA');

$invoice->setBuyer('', 'Client SARL');
$invoice->setBuyerAddress('2 avenue de la Gare', '69001', 'Lyon', 'FR');

$invoice->addItem('Consulting', 200.0, 20.0, 2);
$invoice->addPaymentMean('58', 'FR7630006000011234567890189', 'ACME Corp');
$invoice->setPaymentTerms(new DateTime('+30 days'), 'Net 30');

// XML only
$xml = $invoice->getXml();

// PDF with embedded XML (requires a blank PDF template)
$pdf = $invoice->getPdf(file_get_contents('template.pdf'));
```

For UBL:

```php
use DigitalInvoice\Ubl;

$invoice = new Invoice('INV-2024-001', new DateTime(), null, CurrencyCode::EURO, Ubl::PEPPOL);
// same setter API …
$xml = $invoice->getXml();
```

## Reading an invoice

`InvoiceReader` auto-detects the format (CII/FacturX, ZUGFeRD 1.0, UBL) and returns a normalised `InvoiceData` object.

```php
use DigitalInvoice\InvoiceReader;

// From XML string
$data = InvoiceReader::fromXml($xml);

// From PDF (extracts embedded CII/FacturX XML automatically)
$data = InvoiceReader::read(file_get_contents('invoice.pdf'));
```

### InvoiceData structure

```php
$data->invoiceId;               // string
$data->issueDate;               // ?DateTime
$data->dueDate;                 // ?DateTime
$data->currency;                // string  e.g. 'EUR'
$data->profile;                 // string  URN or format identifier
$data->invoiceType;             // string  e.g. '380'

$data->seller;                  // ?PartyData
$data->buyer;                   // ?PartyData
$data->buyerReference;          // ?string

$data->notes;                   // array  [{content, subjectCode, contentCode}]
$data->items;                   // InvoiceItemData[]
$data->paymentMeans;            // PaymentMeanData[]
$data->paymentTermsDescription; // ?string

// Monetary totals
$data->taxBasisTotal;           // ?float  net amount (excl. VAT)
$data->taxTotal;                // ?float  total VAT amount
$data->grandTotal;              // ?float  total incl. VAT
$data->duePayable;              // ?float

// Tax breakdown — one entry per rate/category
$data->taxBreakdown;            // TaxBreakdownData[]
```

#### TaxBreakdownData

```php
$tb->rate;             // float   e.g. 20.0
$tb->basisAmount;      // ?float  taxable base for this rate
$tb->calculatedAmount; // ?float  VAT amount for this rate
$tb->categoryCode;     // ?string S, Z, E, AE, K …
$tb->exemptionReason;  // ?string
```

#### PartyData

```php
$party->name;             // string
$party->tradingName;      // ?string
$party->id;               // ?string  legal/company ID value
$party->idType;           // ?string  ISO 6523 scheme code e.g. '0002'
$party->address;          // ?AddressData  (lineOne, postCode, city, countryCode …)
$party->contact;          // ?ContactData  (name, phone, email)
$party->taxRegistrations; // array  [{id, schemeID}]  e.g. VAT number
$party->identifiers;      // array  [{id, idType}]    additional IDs
```

## Rendering an invoice to HTML

`InvoiceRenderer` turns any parsed `InvoiceData` into a self-contained HTML fragment with inlined CSS. It supports custom templates, custom CSS, and three UI languages out of the box.

```php
use DigitalInvoice\InvoiceRenderer;

$data = InvoiceReader::fromXml($xml);

// Default (English)
$html = (new InvoiceRenderer())->render($data);

// French labels
$html = (new InvoiceRenderer(lang: 'fr'))->render($data);

// German labels
$html = (new InvoiceRenderer(lang: 'de'))->render($data);

// Custom template and/or CSS
$html = (new InvoiceRenderer('/path/to/template.php', '/path/to/styles.css'))->render($data);
```

The default template (`src/templates/invoice.html.php`) and stylesheet (`src/templates/invoice.css`) can be replaced entirely. The template receives these variables:

| Variable | Type | Description |
|----------|------|-------------|
| `$invoice` | `InvoiceData` | The parsed invoice |
| `$cur` | `string` | Currency code |
| `$labels` | `array` | Translated UI strings |
| `$esc` | `Closure` | `fn(?string): string` — HTML-safe output |
| `$fmt` | `Closure` | `fn(?float, string): string` — formatted amount |
| `$date` | `Closure` | `fn(?\DateTime): string` — formatted date |
| `$schemeLabel` | `Closure` | `fn(string): string` — human label for ISO 6523 / tax scheme |
| `$formatLabel` | `Closure` | `fn(string): string` — human label for invoice format/profile |

## Interactive viewer

`viewer.php` provides a browser-based upload-and-preview page: upload any invoice file on the left, see the rendered HTML on the right. No files are stored server-side.

```bash
php -S localhost:8000
# open http://localhost:8000/viewer.php
```

## Key Features

- **Read & Generate** — round-trip support for all formats
- **Tax breakdown** — per-rate base amount, VAT amount, and category code from any parser
- **HTML rendering** — self-contained fragment with inlined CSS, i18n (EN/FR/DE), format badge, SIRET/VAT labels
- **Identifier support** — SIRET, SIREN, DUNS, LEI, VAT, and 60+ ISO 6523 codes
- **Multi-currency** — including MYR for Malaysian e-invoices
- **Secure by default** — DOCTYPE guard, LIBXML_NONET, upload MIME validation, XSS-safe renderer

## Development Status

Active development — API may change between minor versions.

Collaboration and contributions are welcome. Open an issue or PR for specific use cases or format requests.

## Contributors

- @yassiNebeL — UBL format support via josemmo/einvoicing

## Credits

- [easybill/zugferd-php](https://github.com/easybill/zugferd-php) — Factur-X, XRechnung, ZUGFeRD generation and reading
- [josemmo/einvoicing](https://github.com/josemmo/einvoicing) — UBL invoice generation and reading
- [atgp/factur-x](https://github.com/atgp/factur-x) — PDF embedding/extraction for Factur-X
- [Tiime-Software/EN-16931](https://github.com/Tiime-Software/EN-16931) — Structured data types for e-invoicing
