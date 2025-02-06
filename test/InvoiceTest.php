<?php

namespace DigitalInvoice\Tests;

use DigitalInvoice\CurrencyCode;
use DigitalInvoice\FacturX;
use DigitalInvoice\Invoice;
use DigitalInvoice\PdfWriter;
use DigitalInvoice\Ubl;
use DigitalInvoice\Zugferd;
use PHPUnit\Framework\TestCase;

class InvoiceTest extends TestCase
{
    public function testFormatingDecimals()
    {
        $this->assertEquals(FacturX::decimalFormat(10), "10.00");
        $this->assertEquals(FacturX::decimalFormat(9.5, 3), "9.500");
        $this->assertEquals(FacturX::decimalFormat(9.999999999, 3), "10.000");
        $this->assertEquals(FacturX::decimalFormat(9.999999999, 2), "10.00");
    }

    public function testFacturXCalculateTotals()
    {
        $invoice = new Invoice('123', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, FacturX::BASIC_WL);
        // add some tax lines
        $invoice->xmlGenerator->addTaxLine(20, 200);
        $invoice->xmlGenerator->addTaxLine(9.5, 200);
        $xml = $invoice->getXml();
        $this->assertNotEmpty($xml);
        //Ensure the total is correctly calculated
        $this->assertEquals($invoice->xmlGenerator->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->specifiedTradeSettlementHeaderMonetarySummation->duePayableAmount->value, "459.00");
    }

    public function testFacturXCalculateTotalsWithFloatsShouldRound()
    {
        $invoice = new Invoice('123', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, FacturX::BASIC_WL);
        // add some tax lines
        $invoice->xmlGenerator->addTaxLine(20, 200);
        // at this point, it would be considered as 10%
        $invoice->xmlGenerator->addTaxLine(9.999, 200);
        $xml = $invoice->getXml();
        //Ensure the total is correctly calculated
        $this->assertEquals($invoice->xmlGenerator->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->specifiedTradeSettlementHeaderMonetarySummation->duePayableAmount->value, "460.00");
    }

    public function testUblCalculateTotals()
    {
        $invoice = new Invoice('123', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, Ubl::PEPPOL);
        // add some tax lines
        $invoice->addItem('service a la demande', 750, 10, 1, 'DAY', 'xxxx') ;
        $xml = $invoice->getXml();

        $this->assertStringContainsString('<cbc:TaxInclusiveAmount currencyID="EUR">825</cbc:TaxInclusiveAmount>', $xml);
        $this->assertStringContainsString('<cbc:PayableAmount currencyID="EUR">825</cbc:PayableAmount>', $xml);
    }

    public function profilesProvider()
    {
        // PROFILE/Type , isPdf
        return [
            [FacturX::MINIMUM , true ] ,
            [FacturX::BASIC_WL , true ],
            [FacturX::BASIC , true ],
            [FacturX::EN16931 , true ],
            [FacturX::EXTENDED , true ],
            [Zugferd::ZUGFERD_BASIC, true],
            [Zugferd::ZUGFERD_CONFORT, true],
            [Zugferd::ZUGFERD_EXTENDED, true],
            [FacturX::XRECHNUNG, false, false],
            [Ubl::PEPPOL, false , true],
            [Ubl::NLCIUS, false, true],
            [Ubl::CIUS_RO, false, true],
            [Ubl::CIUS_IT, false, true],
            [Ubl::CIUS_ES_FACE, false, true],
            [Ubl::CIUS_AT_GOV, false, true],
            [Ubl::CIUS_AT_NAT, false, true],
        ];
    }

    /**
     * @dataProvider profilesProvider
     */
    public function testInvoiceXml($profile, $isPdf, $embedPdf = false): void
    {
        $invoice = new Invoice('123', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, $profile);

        $invoice->setSeller(
            '12344',
            '0002',
            'Seller'
        );
        $invoice->setSellerContact(
            'Contact Seller',
            $departmentName = null,
            '+2129999999999',
            'seller@email.com'
        );

        $invoice->setSellerTaxRegistration('FR1231344', 'VA') ;

        $invoice->setSellerAddress(
            '1 rue de la paie',
            '90000',
            'Paris',
            'FR'
        );

        $invoice->setBuyer(
            '12344',
            'buyer'
        );
        $invoice->setBuyerAddress(
            '2 rue de la paie',
            '90000',
            'Paris',
            'FR'
        );


        if (in_array($profile, [FacturX::MINIMUM ,FacturX::BASIC_WL ])) {
            $invoice->setPrice(750, 20);
        } else {
            // Item 1
            $invoice->addItem('service a la demande', 750, 20, 1, 'DAY', 'xxxx') ;
        }


        // add payment
        $invoice->addPaymentMean('58', 'MA2120300000000202051', 'Youniwemi');

        // set payment terms
        $invoice->setPaymentTerms(new \Datetime('2023-12-07'), 'After A Month');

        // Embedding pdf
        if ($embedPdf) {
            $pdfFile = file_get_contents(__DIR__.'/examples/basic.pdf');
            $invoice->addEmbeddedAttachment('123', null, 'basic', $pdfFile, 'application/pdf', 'The pdf invoice');
        }

        $xml = $invoice->getXml();
        self::assertNotEmpty($xml);


        // An easy xml validation
        $result = $invoice->validate($xml);
        self::assertNull($result, $result ? $result."\nIN\n".$xml : '');

        if ($isPdf) {
            // This will for a more thorough validation
            $pdfFile = file_get_contents(__DIR__.'/examples/basic.pdf');
            $addLogo = in_array($profile, [FacturX::MINIMUM ,FacturX::BASIC_WL, FacturX::BASIC,  FacturX::EN16931, FacturX::EXTENDED]);
            $result = $invoice->getPdf($pdfFile, $addLogo);
            $profile = explode(":", $profile);
            $short = array_pop($profile);
            file_put_contents(__DIR__.'/examples/basic-'.$short.'.pdf', $result);
            // Check xml again
            $facturX = new PdfWriter();

            try {
                $xml = $facturX->getFacturxXmlFromPdf($result);
                $this->assertTrue(true);
            } catch (\Exception $e) {
                $this->fail('Error extractiong xml '. $e->getMessage());
            }
        }

        // A complete validation using schematron
        $result = $invoice->validate($xml, true);
        $this->assertEmpty($result, $result ? print_r($result, true) ."\n".$xml : '');
    }
}
