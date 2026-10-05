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

Requires PHP 8.3+ (easybill/zugferd-php 6).

### Upgrading to 0.4

- PHP 8.3 or later is required.
- easybill/zugferd-php 6 replaces the `Youniwemi/zugferd-php` fork: the Factur-X models moved from `Easybill\ZUGFeRD211\Model` to `Easybill\ZUGFeRD2\Model`. Only code using `$invoice->xmlGenerator->invoice` directly is affected.
- Use `addAllowance()` for discounts, negative prices are rejected by EN16931 (BR-27) and the French rules (BR-FR-DEC-03).

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
$invoice->setBuyerOrderReference('BC-42'); // purchase order (BT-13)
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

### French e-invoicing (CTC reform)

The French rules (BR-FR, XP Z12-012) require a billing mode, electronic addresses and the legal notes:

```php
$invoice->setBillingMode('S1');                          // BT-23: B1, S1, M1, B2, S2, M2, S3, B4 …
$invoice->setSellerElectronicAddress('123456789', '0225'); // BT-34, after setSeller
$invoice->setBuyerElectronicAddress('ap@client.fr', 'EM'); // BT-49, after setBuyer

$invoice->addNote('Indemnité forfaitaire pour frais de recouvrement : 40 €', 'PMT');
$invoice->addNote('Pénalités de retard : 3 fois le taux d\'intérêt légal', 'PMD');
$invoice->addNote('Pas d\'escompte pour paiement anticipé', 'AAB');
```

In UBL, note subject codes are written as a `#PMT#…` prefix.

### Credit notes

Pass a credit note type code (381, 396, 261…) and reference the credited invoice (BG-3, required by BR-FR-CO-05). Amounts stay positive; UBL output switches to a `<CreditNote>` document.

```php
$creditNote = new Invoice('AV-2024-001', new DateTime(), null, CurrencyCode::EURO, FacturX::EN16931, InvoiceTypeCode::CREDIT_NOTE);
$creditNote->addPrecedingInvoiceReference('INV-2024-001', new DateTime('2024-01-15')); // BT-25, BT-26
$creditNote->isCreditNote(); // true
```

When read back, `InvoiceData::isCreditNote()` and `InvoiceData::$precedingInvoices` expose them.

### Discounts

Add a document level allowance (BG-20) instead of a negative line, prices must stay positive (BR-27, BR-FR-DEC-03). The amount is deducted from the VAT basis of the given rate. Supported by Factur-X (BASIC WL and above) and UBL.

```php
$invoice->addAllowance(7, 20);                          // 7 € excl. VAT at 20 %, reason "Remise", code 95 (Discount)
$invoice->addAllowance(18, 20, 'Remise fidélité', '95'); // reason (BT-97) and UNTDID 5189 code (BT-98)
```

### Supporting documents

Attach a supporting document (BG-24), e.g. a delivery note. It is embedded in the XML (base64) and, for PDF output, also attached to the PDF (`AFRelationship /Supplement`), visible in the attachment pane of Acrobat or Firefox. Supported by Factur-X (EN16931 and EXTENDED) and UBL.

```php
$invoice->addEmbeddedAttachment(
    'BL-42',                // BT-122 document reference
    null,                   // scheme, UBL only
    'bon-de-livraison.pdf', // filename
    file_get_contents('bon-de-livraison.pdf'),
    AttachmentMimeCode::PDF->value,
    AttachmentDescription::BON_LIVRAISON->value // BT-123
);
```

The mime code must be one of `AttachmentMimeCode` (BR-CL-24): pdf, png, jpeg, csv, xlsx or ods, any other value throws an exception. The id is required, and so are the filename and the mime code when contents are given.

In France, BT-123 must be one of `AttachmentDescription` (BR-FR-17): `BON_LIVRAISON`, `BON_COMMANDE`, `DOCUMENT_ANNEXE`, `RIB`, `PJA`, `BORDEREAU_SUIVI`, `BORDEREAU_SUIVI_VALIDATION`, `ETAT_ACOMPTE`, `FACTURE_PAIEMENT_DIRECT`, `RECAPITULATIF_COTRAITANCE`, `FEUILLE_DE_STYLE` or `LISIBLE` (readable copy of the invoice, once at most). It is not checked, as it is free text outside France.

Some readers (macOS Preview, Chrome) do not show PDF attachments. To make PDF documents readable everywhere, append their pages after the invoice:

```php
$pdf = $invoice->getPdf(file_get_contents('template.pdf'), false, [], true);
```

A PDF the free FPDI parser can not read (e.g. PDF 1.5+ with a compressed xref) is silently not appended, the document is still embedded in the XML. Appended pages are copied as is, a document that is not PDF/A may break the PDF/A-3 conformance of the invoice.

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
$data->invoiceType;             // string  e.g. '380', '381'
$data->isCreditNote();          // bool
$data->precedingInvoices;       // array  [{id, issueDate}] (BG-3)

$data->seller;                  // ?PartyData
$data->buyer;                   // ?PartyData
$data->buyerReference;          // ?string
$data->buyerOrderReference;     // ?string  purchase order (BT-13)

$data->notes;                   // array  [{content, subjectCode, contentCode}]
$data->items;                   // InvoiceItemData[]
$data->paymentMeans;            // PaymentMeanData[]
$data->paymentTermsDescription; // ?string

// Monetary totals
$data->lineTotal;               // ?float  sum of line net amounts (BT-106)
$data->allowanceTotal;          // ?float  sum of document level allowances (BT-107)
$data->taxBasisTotal;           // ?float  net amount (excl. VAT)
$data->taxTotal;                // ?float  total VAT amount
$data->grandTotal;              // ?float  total incl. VAT
$data->duePayable;              // ?float

// Tax breakdown — one entry per rate/category
$data->taxBreakdown;            // TaxBreakdownData[]

// Document level allowances (BG-20), e.g. global discounts
$data->allowances;              // AllowanceData[] {amount, taxRate, categoryCode, reason, reasonCode}

// Supporting documents (BG-24), contents decoded
$data->attachments;             // AttachmentData[] {id, description, filename, mimeCode, contents}
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

`viewer.php` provides a browser-based upload-and-preview page: upload any invoice file on the left, see the rendered HTML on the right, supporting documents can be downloaded. No files are stored server-side.

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

## Testing

```bash
make test
```

Generated Factur-X and UBL invoices are also validated with the official [FNFE artefacts](https://github.com/fnfempe/France_RFE): Factur-X / EN16931 profile rules, and French BR-FR rules for the French CTC profiles (Factur-X BASIC-WL, EN16931, EXTENDED and `test/FrenchRulesTest.php`). The rules are XSLT 2.0 and run with Saxon-HE, so Java is required. Download them once, these checks are skipped otherwise:

```bash
make fnfe
```

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
