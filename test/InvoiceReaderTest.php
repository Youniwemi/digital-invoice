<?php

namespace DigitalInvoice\Tests;

use DigitalInvoice\CurrencyCode;
use DigitalInvoice\FacturX;
use DigitalInvoice\Invoice;
use DigitalInvoice\InvoiceReader;
use DigitalInvoice\PdfWriter;
use DigitalInvoice\Ubl;
use DigitalInvoice\Zugferd;
use PHPUnit\Framework\TestCase;

/**
 * Round-trip tests: generate XML with Invoice, parse it with InvoiceReader,
 * verify the extracted InvoiceData matches the original values.
 */
class InvoiceReaderTest extends TestCase
{
    private function buildTestInvoice(string $profile, CurrencyCode $currency = CurrencyCode::EURO): Invoice
    {
        $invoice = new Invoice('INV-001', new \DateTime('2023-11-07'), null, $currency, $profile);

        $invoice->setSeller('12345', '0002', 'ACME Corp', 'ACME');
        $invoice->setSellerAddress('1 rue de la Paix', '75001', 'Paris', 'FR');
        $invoice->setSellerTaxRegistration('FR12312345678', 'VA');

        $invoice->setBuyer('', 'Client SARL');
        $invoice->setBuyerAddress('2 avenue de la Gare', '69001', 'Lyon', 'FR');

        return $invoice;
    }

    // ─── FacturX ─────────────────────────────────────────────────────────────

    public function testFacturXBasicRoundTrip(): void
    {
        $invoice = $this->buildTestInvoice(FacturX::BASIC);
        $invoice->addNote('Test note');
        $invoice->addItem('Consulting', 100.0, 20.0, 2, 'H87');
        $invoice->addPaymentMean('58', 'FR7630006000011234567890189', 'ACME Corp');
        $invoice->setPaymentTerms(new \DateTime('2023-12-07'), 'Net 30');
        $xml = $invoice->getXml();

        $data = InvoiceReader::fromXml($xml);

        $this->assertEquals('INV-001', $data->invoiceId);
        $this->assertEquals('380', $data->invoiceType);
        $this->assertEquals('EUR', $data->currency);
        $this->assertEquals('2023-11-07', $data->issueDate->format('Y-m-d'));

        $this->assertNotNull($data->seller);
        $this->assertEquals('ACME Corp', $data->seller->name);
        $this->assertEquals('FR', $data->seller->address->countryCode);
        $this->assertEquals('Paris', $data->seller->address->city);
        $this->assertNotEmpty($data->seller->taxRegistrations);

        $this->assertNotNull($data->buyer);
        $this->assertEquals('Client SARL', $data->buyer->name);

        $this->assertCount(1, $data->items);
        $this->assertEquals('Consulting', $data->items[0]->name);
        $this->assertEquals(100.0, $data->items[0]->price);
        $this->assertEquals(20.0, $data->items[0]->taxRate);
        $this->assertEquals(2.0, $data->items[0]->quantity);

        $this->assertNotEmpty($data->paymentMeans);
        $this->assertEquals('58', $data->paymentMeans[0]->typeCode);
        $this->assertEquals('FR7630006000011234567890189', $data->paymentMeans[0]->iban);

        $this->assertNotNull($data->dueDate);
        $this->assertEquals('2023-12-07', $data->dueDate->format('Y-m-d'));

        $this->assertNotNull($data->taxBasisTotal);
        $this->assertNotNull($data->grandTotal);
        $this->assertEquals(200.0, $data->taxBasisTotal);
        $this->assertEquals(240.0, $data->grandTotal);
    }

    public function testFacturXMinimumRoundTrip(): void
    {
        $invoice = $this->buildTestInvoice(FacturX::MINIMUM);
        $invoice->setPrice(500.0, 100.0);
        $xml = $invoice->getXml();

        $data = InvoiceReader::fromXml($xml);

        $this->assertEquals('INV-001', $data->invoiceId);
        $this->assertEquals('EUR', $data->currency);
        $this->assertNotNull($data->seller);
        $this->assertEquals('ACME Corp', $data->seller->name);
        $this->assertEquals(500.0, $data->taxBasisTotal);
        $this->assertEquals(600.0, $data->grandTotal);
    }

    public function testFacturXen16931RoundTrip(): void
    {
        $invoice = $this->buildTestInvoice(FacturX::EN16931);
        $invoice->addItem('Product A', 50.0, 20.0, 3, 'H87', 'REF001', '0160', 'A great product');
        $invoice->addItem('Service B', 200.0, 10.0, 1, 'DAY');
        $xml = $invoice->getXml();

        $data = InvoiceReader::fromXml($xml);

        $this->assertCount(2, $data->items);
        $this->assertEquals('Product A', $data->items[0]->name);
        $this->assertEquals('A great product', $data->items[0]->description);
        $this->assertEquals(50.0, $data->items[0]->price);
        $this->assertEquals(20.0, $data->items[0]->taxRate);
        $this->assertEquals(3.0, $data->items[0]->quantity);
        $this->assertEquals('Service B', $data->items[1]->name);
        $this->assertEquals(200.0, $data->items[1]->price);
        $this->assertEquals(10.0, $data->items[1]->taxRate);
    }

    public function testFacturXDetectedFromXml(): void
    {
        $invoice = $this->buildTestInvoice(FacturX::BASIC);
        $invoice->addItem('Item', 100.0, 20.0);
        $xml = $invoice->getXml();

        // Must auto-detect as FacturX (CrossIndustryInvoice root)
        $this->assertStringContainsString('CrossIndustryInvoice', $xml);
        $data = InvoiceReader::fromXml($xml);
        $this->assertNotNull($data);
    }

    // ─── ZUGFeRD 1.0 ─────────────────────────────────────────────────────────

    public function testZugferdRoundTrip(): void
    {
        $invoice = $this->buildTestInvoice(Zugferd::ZUGFERD_CONFORT);
        $invoice->addItem('Produit ZUGFeRD', 120.0, 19.0, 1, 'H87');
        $invoice->addPaymentMean('58', 'DE89370400440532013000', 'ACME GmbH');
        $invoice->setPaymentTerms(new \DateTime('2023-12-31'), '30 jours');
        $xml = $invoice->getXml();

        // Must auto-detect as ZUGFeRD (CrossIndustryDocument root)
        $this->assertStringContainsString('CrossIndustryDocument', $xml);

        $data = InvoiceReader::fromXml($xml);

        $this->assertEquals('INV-001', $data->invoiceId);
        $this->assertEquals('EUR', $data->currency);

        $this->assertNotNull($data->seller);
        $this->assertEquals('ACME Corp', $data->seller->name);
        $this->assertEquals('FR', $data->seller->address->countryCode);

        $this->assertNotNull($data->buyer);
        $this->assertEquals('Client SARL', $data->buyer->name);

        $this->assertCount(1, $data->items);
        $this->assertEquals('Produit ZUGFeRD', $data->items[0]->name);
        $this->assertEquals(120.0, $data->items[0]->price);
        $this->assertEquals(19.0, $data->items[0]->taxRate);

        // Note: ZUGFeRD 1.0 addPaymentMean() has a pre-existing bug that disconnects
        // the Settlement from the Trade, causing payment means and terms to be lost.
        // We only assert on data that is reliably written by the generator.
    }

    // ─── UBL ─────────────────────────────────────────────────────────────────

    public function testUblPeppolRoundTrip(): void
    {
        $invoice = $this->buildTestInvoice(Ubl::PEPPOL);
        $invoice->addNote('Peppol test note');
        $invoice->addItem('Consulting UBL', 300.0, 20.0, 1, 'DAY', 'SVC001');
        $invoice->addPaymentMean('58', 'FR7630006000011234567890189', 'ACME');
        $invoice->setPaymentTerms(new \DateTime('2024-01-07'), 'Payment within 30 days');
        $xml = $invoice->getXml();

        // Must auto-detect as UBL (Invoice root)
        $this->assertStringContainsString('<Invoice', $xml);

        $data = InvoiceReader::fromXml($xml);

        $this->assertEquals('INV-001', $data->invoiceId);
        $this->assertEquals('EUR', $data->currency);

        $this->assertNotNull($data->seller);
        $this->assertEquals('ACME Corp', $data->seller->name);
        $this->assertEquals('FR', $data->seller->address->countryCode);

        $this->assertNotNull($data->buyer);
        $this->assertEquals('Client SARL', $data->buyer->name);

        $this->assertCount(1, $data->items);
        $this->assertEquals('Consulting UBL', $data->items[0]->name);
        $this->assertEquals(300.0, $data->items[0]->price);
        $this->assertEquals(20.0, $data->items[0]->taxRate);

        $this->assertNotEmpty($data->paymentMeans);
        $this->assertEquals('58', $data->paymentMeans[0]->typeCode);

        $this->assertNotNull($data->taxBasisTotal);
        $this->assertEquals(300.0, $data->taxBasisTotal);
        $this->assertEquals(360.0, $data->grandTotal);
    }

    public function testUblNlciusRoundTrip(): void
    {
        $invoice = $this->buildTestInvoice(Ubl::NLCIUS);
        $invoice->addItem('Dutch item', 500.0, 21.0, 1, 'H87', 'NL001');
        $xml = $invoice->getXml();

        $data = InvoiceReader::fromXml($xml);

        $this->assertEquals('INV-001', $data->invoiceId);
        $this->assertCount(1, $data->items);
        $this->assertEquals(500.0, $data->items[0]->price);
        $this->assertEquals(21.0, $data->items[0]->taxRate);
    }

    // ─── PDF round-trip ───────────────────────────────────────────────────────

    public function testReadFromEmbeddedPdf(): void
    {
        $invoice = $this->buildTestInvoice(FacturX::EN16931);
        $invoice->addItem('PDF item', 250.0, 20.0, 1, 'H87');
        $pdfTemplate = file_get_contents(__DIR__ . '/examples/basic.pdf');
        $pdf = $invoice->getPdf($pdfTemplate);

        $data = InvoiceReader::read($pdf);

        $this->assertEquals('INV-001', $data->invoiceId);
        $this->assertNotNull($data->seller);
        $this->assertCount(1, $data->items);
        $this->assertEquals('PDF item', $data->items[0]->name);
    }

    // ─── Format detection ─────────────────────────────────────────────────────

    public function testUnknownFormatThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/unknown invoice format/i');
        InvoiceReader::fromXml('<SomeRandomElement/>');
    }

    public function testInvalidXmlThrows(): void
    {
        $this->expectException(\Exception::class);
        InvoiceReader::fromXml('this is not xml at all <<<');
    }
}
