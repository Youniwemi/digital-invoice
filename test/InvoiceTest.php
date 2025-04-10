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
        $invoice = new Invoice('123', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, FacturX::BASIC);
        // add some tax lines
        $invoice->xmlGenerator->addTaxLine(20, 200);
        // at this point, it would be considered as 10%
        $invoice->xmlGenerator->addTaxLine(9.999, 200);
        $xml = $invoice->getXml();
        //Ensure the total is correctly calculated
        $this->assertEquals($invoice->xmlGenerator->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->specifiedTradeSettlementHeaderMonetarySummation->duePayableAmount->value, "460.00");
    }

    public function testFacturXCalculateTotalsInMinimum()
    {
        $invoice = new Invoice('123', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, FacturX::MINIMUM);
        // add some tax lines
        $invoice->xmlGenerator->addTaxLine(20, 200);
        // at this point, it would be considered as 10%
        $invoice->xmlGenerator->addTaxLine(9.999, 200);
        $xml = $invoice->getXml();
        //Ensure the total is correctly calculated
        $this->assertEquals($invoice->xmlGenerator->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->specifiedTradeSettlementHeaderMonetarySummation->duePayableAmount->value, "460.00");
    }

    public function testFacturXCalculateVATRate()
    {
        $invoice = new Invoice('123', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, FacturX::BASIC_WL);
        // add some tax lines
        $invoice->setPrice(1100, 220);
        $xml = $invoice->getXml();
        //Ensure the total is correctly calculated
        $this->assertEquals($invoice->xmlGenerator->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->specifiedTradeSettlementHeaderMonetarySummation->duePayableAmount->value, "1320.00");
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

    public function testUblMalaysiaCodes()
    {
        $invoice = new Invoice('123', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, Ubl::PEPPOL);
        // add some tax lines
        $invoice->addItem('service a la demande', 750, 10, 1, 'DAY', 'xxxx') ;

        $invoice->setSeller(
            '123456789012',
            'BRN',
            'Seller'
        );

        $invoice->addSellerIdentifier(
            '12344QWE',
            'TIN'
        );

        $xml = $invoice->getXml();

        $this->assertStringContainsString('<cbc:CompanyID schemeID="BRN">123456789012</cbc:CompanyID>', $xml);
        $this->assertStringContainsString('<cbc:ID schemeID="TIN">12344QWE</cbc:ID>', $xml);
    }

    public static function profilesProvider()
    {
        // PROFILE/Type , isPdf
        return [
            [FacturX::MINIMUM , true , false ] ,
            [FacturX::BASIC_WL , true, false ],
            //[FacturX::BASIC_WL , true, false , 0 ],
            [FacturX::BASIC , true, false  ],
            [FacturX::BASIC , true, false , 0 ],
            [FacturX::EN16931 , true, false ],
            [FacturX::EXTENDED , true , false],
            [Zugferd::ZUGFERD_BASIC, true, false],
            [Zugferd::ZUGFERD_CONFORT, true, false],
            [Zugferd::ZUGFERD_EXTENDED, true, false],
            [Zugferd::ZUGFERD_EXTENDED, true, false, 0],
            [FacturX::XRECHNUNG, false, false],
            [Ubl::PEPPOL, false , true],
            //[Ubl::PEPPOL, false , true, 0],
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
    public function testInvoiceXml($profile, $isPdf, $embedPdf = false, $taxRate = 20): void
    {
        
        $invoice = new Invoice('123', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, $profile);

        
        $invoice->addNote("My Document note");

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
        if ($taxRate == 0) {
            $invoice->setTaxExemption(Invoice::EXEMPT_FROM_TAX, 'Assujeti') ;
        }
        
        

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
            $taxAmount = ( 750 * $taxRate ) / 100;
            $invoice->setPrice(750, $taxAmount);
        } else {
            // Item 1
            $invoice->addItem('service a la demande', 750, $taxRate, 1, 'DAY', 'xxxx') ;
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
