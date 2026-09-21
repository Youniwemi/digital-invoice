<?php

namespace DigitalInvoice\Tests;

use DigitalInvoice\CurrencyCode;
use DigitalInvoice\FacturX;
use DigitalInvoice\Invoice;
use DigitalInvoice\InvoiceReader;
use DigitalInvoice\InvoiceRenderer;
use DigitalInvoice\Ubl;
use PHPUnit\Framework\TestCase;

class InvoiceRendererTest extends TestCase
{
    private function buildAndParse(string $profile): \DigitalInvoice\InvoiceData
    {
        $invoice = new Invoice('INV-RENDER-01', new \DateTime('2024-03-15'), null, CurrencyCode::EURO, $profile);
        $invoice->setSeller('12345', '0002', 'ACME Corp', 'ACME');
        $invoice->setSellerAddress('1 rue de la Paix', '75001', 'Paris', 'FR');
        $invoice->setSellerTaxRegistration('FR12312345678', 'VA');
        $invoice->setBuyer('', 'Client SARL');
        $invoice->setBuyerAddress('2 avenue de la Gare', '69001', 'Lyon', 'FR');
        $invoice->addNote('Rendered invoice note');
        $invoice->addItem('Consulting', 200.0, 20.0, 2, 'H87', 'SVC-001');
        $invoice->addPaymentMean('58', 'FR7630006000011234567890189', 'ACME Corp');
        $invoice->setPaymentTerms(new \DateTime('2024-04-15'), 'Net 30');

        return InvoiceReader::fromXml($invoice->getXml());
    }

    // ─── Happy path ──────────────────────────────────────────────────────────

    public function testRendersInvoiceIdAndDates(): void
    {
        $data = $this->buildAndParse(FacturX::BASIC);
        $html = (new InvoiceRenderer())->render($data);

        $this->assertStringContainsString('INV-RENDER-01', $html);
        $this->assertStringContainsString('2024-03-15', $html);
        $this->assertStringContainsString('2024-04-15', $html);
    }

    public function testRendersSellerAndBuyer(): void
    {
        $data = $this->buildAndParse(FacturX::BASIC);
        $html = (new InvoiceRenderer())->render($data);

        $this->assertStringContainsString('ACME Corp', $html);
        $this->assertStringContainsString('Client SARL', $html);
        $this->assertStringContainsString('Paris', $html);
        $this->assertStringContainsString('Lyon', $html);
        $this->assertStringContainsString('FR', $html);
    }

    public function testRendersLineItems(): void
    {
        $data = $this->buildAndParse(FacturX::BASIC);
        $html = (new InvoiceRenderer())->render($data);

        $this->assertStringContainsString('Consulting', $html);
        $this->assertStringContainsString('200.00', $html);
        $this->assertStringContainsString('20.00%', $html);
    }

    public function testRendersTotals(): void
    {
        $data = $this->buildAndParse(FacturX::BASIC);
        $html = (new InvoiceRenderer())->render($data);

        // taxBasisTotal = 2 × 200 = 400, grandTotal = 480
        $this->assertStringContainsString('400.00', $html);
        $this->assertStringContainsString('480.00', $html);
    }

    public function testRendersPaymentAndNote(): void
    {
        $data = $this->buildAndParse(FacturX::BASIC);
        $html = (new InvoiceRenderer())->render($data);

        $this->assertStringContainsString('FR7630006000011234567890189', $html);
        $this->assertStringContainsString('Rendered invoice note', $html);
    }

    public function testRendersUblInvoice(): void
    {
        $data = $this->buildAndParse(Ubl::PEPPOL);
        $html = (new InvoiceRenderer())->render($data);

        $this->assertStringContainsString('INV-RENDER-01', $html);
        $this->assertStringContainsString('ACME Corp', $html);
        $this->assertStringContainsString('Consulting', $html);
    }

    public function testCustomTemplateIsUsed(): void
    {
        $tmpTemplate = tempnam(sys_get_temp_dir(), 'di_tmpl_') . '.php';
        file_put_contents($tmpTemplate, '<span>CUSTOM:<?= $invoice->invoiceId ?></span>');

        $data = $this->buildAndParse(FacturX::BASIC);
        $html = (new InvoiceRenderer($tmpTemplate))->render($data);

        $this->assertStringContainsString('CUSTOM:INV-RENDER-01', $html);

        unlink($tmpTemplate);
    }

    public function testOutputIsProperlyEscaped(): void
    {
        $invoice = new Invoice('INV-<XSS>', new \DateTime('2024-01-01'), null, CurrencyCode::EURO, FacturX::BASIC);
        $invoice->setSeller('1', '0002', 'ACME & Co <test>', 'ACME');
        $invoice->setSellerAddress('1 rue', '75001', 'Paris', 'FR');
        $invoice->setBuyer('', 'Buyer');
        $invoice->addItem('Item', 100.0, 20.0);

        $data = InvoiceReader::fromXml($invoice->getXml());
        $html = (new InvoiceRenderer())->render($data);

        $this->assertStringNotContainsString('<XSS>', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function testRenderInlinesCss(): void
    {
        $data = $this->buildAndParse(FacturX::BASIC);
        $html = (new InvoiceRenderer())->render($data);

        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('.di-invoice', $html);
        $this->assertStringContainsString('.di-table', $html);
    }

    public function testCustomCssIsInlinedInRender(): void
    {
        $tmpCss = tempnam(sys_get_temp_dir(), 'di_css_') . '.css';
        file_put_contents($tmpCss, '.custom { color: red; }');

        $data = $this->buildAndParse(FacturX::BASIC);
        $html = (new InvoiceRenderer(null, $tmpCss))->render($data);

        $this->assertStringContainsString('.custom { color: red; }', $html);

        unlink($tmpCss);
    }

    public function testMissingCssProducesNoStyleTag(): void
    {
        $data = $this->buildAndParse(FacturX::BASIC);
        $html = (new InvoiceRenderer(null, '/nonexistent/styles.css'))->render($data);

        $this->assertStringNotContainsString('<style>', $html);
        $this->assertStringContainsString('di-invoice', $html);
    }

    public function testGetStylesReturnsDefaultCss(): void
    {
        $css = (new InvoiceRenderer())->getStyles();

        $this->assertNotEmpty($css);
        $this->assertStringContainsString('.di-invoice', $css);
    }

    public function testFormatBadgeIsDisplayed(): void
    {
        $data = $this->buildAndParse(FacturX::BASIC);
        $html = (new InvoiceRenderer())->render($data);

        $this->assertStringContainsString('di-format-badge', $html);
        $this->assertStringContainsString('FacturX Basic', $html);
    }

    public function testFormatBadgePeppol(): void
    {
        $data = $this->buildAndParse(Ubl::PEPPOL);
        $html = (new InvoiceRenderer())->render($data);

        $this->assertStringContainsString('Peppol BIS 3.0', $html);
    }

    // ─── i18n ────────────────────────────────────────────────────────────────

    public function testDefaultLangIsEnglish(): void
    {
        $data = $this->buildAndParse(FacturX::BASIC);
        $html = (new InvoiceRenderer())->render($data);

        $this->assertStringContainsString('Seller', $html);
        $this->assertStringContainsString('Tax basis', $html);
    }

    public function testFrenchLabels(): void
    {
        $data = $this->buildAndParse(FacturX::BASIC);
        $html = (new InvoiceRenderer(lang: 'fr'))->render($data);

        $this->assertStringContainsString('Vendeur', $html);
        $this->assertStringContainsString('Base HT', $html);
        $this->assertStringContainsString('TVA', $html);
    }

    public function testGermanLabels(): void
    {
        $data = $this->buildAndParse(FacturX::BASIC);
        $html = (new InvoiceRenderer(lang: 'de'))->render($data);

        $this->assertStringContainsString('Verkäufer', $html);
        $this->assertStringContainsString('Nettobetrag', $html);
    }

    public function testUnknownLangFallsBackToEnglish(): void
    {
        $data = $this->buildAndParse(FacturX::BASIC);
        $html = (new InvoiceRenderer(lang: 'zz'))->render($data);

        $this->assertStringContainsString('Seller', $html);
    }

    public function testSiretDisplayed(): void
    {
        $data = $this->buildAndParse(FacturX::BASIC);
        $html = (new InvoiceRenderer())->render($data);

        // Seller has SIRET (schemeID 0002) and VAT registration
        $this->assertStringContainsString('SIRET', $html);
        $this->assertStringContainsString('TVA / VAT', $html);
        $this->assertStringContainsString('FR12312345678', $html);
    }

    // ─── lineTotal / prepaid / discount ─────────────────────────────────────

    public function testNegativeBaseQuantityRendersNegativeTotal(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
    xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0</cbc:CustomizationID>
    <cbc:ID>DISC-001</cbc:ID>
    <cbc:IssueDate>2024-01-15</cbc:IssueDate>
    <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>
    <cbc:BuyerReference>ref</cbc:BuyerReference>
    <cac:AccountingSupplierParty>
        <cac:Party><cac:PartyLegalEntity><cbc:RegistrationName>Seller</cbc:RegistrationName></cac:PartyLegalEntity></cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
        <cac:Party><cac:PartyLegalEntity><cbc:RegistrationName>Buyer</cbc:RegistrationName></cac:PartyLegalEntity></cac:Party>
    </cac:AccountingCustomerParty>
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="EUR">0.00</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="EUR">68.68</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="EUR">0.00</cbc:TaxAmount>
            <cac:TaxCategory><cbc:ID>E</cbc:ID><cbc:Percent>0</cbc:Percent><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="EUR">68.68</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="EUR">68.68</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="EUR">68.68</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="EUR">68.68</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    <cac:InvoiceLine>
        <cbc:ID>1</cbc:ID>
        <cbc:InvoicedQuantity unitCode="C62">1</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="EUR">69.05</cbc:LineExtensionAmount>
        <cac:Item><cbc:Name>Product</cbc:Name>
            <cac:ClassifiedTaxCategory><cbc:ID>E</cbc:ID><cbc:Percent>0</cbc:Percent><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:ClassifiedTaxCategory>
        </cac:Item>
        <cac:Price><cbc:PriceAmount currencyID="EUR">69.05</cbc:PriceAmount></cac:Price>
    </cac:InvoiceLine>
    <cac:InvoiceLine>
        <cbc:ID>2</cbc:ID>
        <cbc:InvoicedQuantity unitCode="C62">1</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="EUR">-0.37</cbc:LineExtensionAmount>
        <cac:Item><cbc:Name>Discount</cbc:Name>
            <cac:ClassifiedTaxCategory><cbc:ID>E</cbc:ID><cbc:Percent>0</cbc:Percent><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:ClassifiedTaxCategory>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="EUR">0.37</cbc:PriceAmount>
            <cbc:BaseQuantity unitCode="C62">-1</cbc:BaseQuantity>
        </cac:Price>
    </cac:InvoiceLine>
</Invoice>
XML;

        $data = InvoiceReader::fromXml($xml);
        $html = (new InvoiceRenderer())->render($data);

        // Discount line must show -0.37, not 0.37
        $this->assertEquals(-0.37, $data->items[1]->lineTotal);
        $this->assertStringContainsString('-0.37', $html);
        // Totals should match the XML values
        $this->assertEquals(68.68, $data->duePayable);
    }

    public function testPrepaidAmountShowsPaymentsOnAccount(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
    xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0</cbc:CustomizationID>
    <cbc:ID>PREPAID-001</cbc:ID>
    <cbc:IssueDate>2024-01-15</cbc:IssueDate>
    <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>
    <cbc:BuyerReference>ref</cbc:BuyerReference>
    <cac:AccountingSupplierParty>
        <cac:Party><cac:PartyLegalEntity><cbc:RegistrationName>Seller</cbc:RegistrationName></cac:PartyLegalEntity></cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
        <cac:Party><cac:PartyLegalEntity><cbc:RegistrationName>Buyer</cbc:RegistrationName></cac:PartyLegalEntity></cac:Party>
    </cac:AccountingCustomerParty>
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="EUR">3.58</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="EUR">69.05</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="EUR">3.58</cbc:TaxAmount>
            <cac:TaxCategory><cbc:ID>S</cbc:ID><cbc:Percent>5.19</cbc:Percent><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="EUR">69.05</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="EUR">69.05</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="EUR">72.63</cbc:TaxInclusiveAmount>
        <cbc:PrepaidAmount currencyID="EUR">72.63</cbc:PrepaidAmount>
        <cbc:PayableAmount currencyID="EUR">0.00</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    <cac:InvoiceLine>
        <cbc:ID>1</cbc:ID>
        <cbc:InvoicedQuantity unitCode="C62">1</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="EUR">69.05</cbc:LineExtensionAmount>
        <cac:Item><cbc:Name>Service</cbc:Name>
            <cac:ClassifiedTaxCategory><cbc:ID>S</cbc:ID><cbc:Percent>5.19</cbc:Percent><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:ClassifiedTaxCategory>
        </cac:Item>
        <cac:Price><cbc:PriceAmount currencyID="EUR">69.05</cbc:PriceAmount></cac:Price>
    </cac:InvoiceLine>
</Invoice>
XML;

        $data = InvoiceReader::fromXml($xml);
        $html = (new InvoiceRenderer())->render($data);

        // Prepaid and payable amounts from XML
        $this->assertEquals(72.63, $data->prepaidAmount);
        $this->assertEquals(0.0, $data->duePayable);

        // "Payments on account" must appear between Total and Payable amount
        $this->assertStringContainsString('Payments on account', $html);
        $totalPos = strpos($html, '>Total<');
        $prepaidPos = strpos($html, 'Payments on account');
        $payablePos = strpos($html, 'Payable amount');
        $this->assertGreaterThan($totalPos, $prepaidPos);
        $this->assertGreaterThan($prepaidPos, $payablePos);
    }

    public function testLineTotalUsesXmlValueNotCalculation(): void
    {
        $data = $this->buildAndParse(Ubl::PEPPOL);

        // lineTotal should come from the XML's LineExtensionAmount, not price*qty
        $this->assertNotNull($data->items[0]->lineTotal);
        $this->assertEquals(400.0, $data->items[0]->getCalculatedTotal());
    }

    public function testNoPrepaidHidesPaymentsOnAccount(): void
    {
        $data = $this->buildAndParse(FacturX::BASIC);
        $html = (new InvoiceRenderer())->render($data);

        $this->assertStringNotContainsString('Payments on account', $html);
    }

    // ─── Sad path ────────────────────────────────────────────────────────────

    public function testMissingTemplateThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/template file not found/i');

        $data = $this->buildAndParse(FacturX::BASIC);
        (new InvoiceRenderer('/nonexistent/path/template.php'))->render($data);
    }
}
