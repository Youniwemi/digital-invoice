<?php

namespace DigitalInvoice\Tests;

use DigitalInvoice\Invoice;
use DigitalInvoice\Zugferd;
use DigitalInvoice\FacturX;
use DigitalInvoice\PdfWriter;
use DigitalInvoice\CurrencyCode;
use DigitalInvoice\Ubl;
use PHPUnit\Framework\TestCase;

class InvoiceTest extends TestCase
{
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
            [Ubl::CIUS_AT_NAT, false, true]
        ];
    }

    /**
     * @dataProvider profilesProvider
     */
    public function testInvoiceXml($profile, $isPdf, $embedPdf = false ): void
    {
        $invoice = new Invoice('123', new \Datetime('2023-11-07'), null ,  CurrencyCode::EURO , $profile);

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

        $invoice->setPrice(100, 20);

        // Item 1
        $invoice->addItem('service a la demande', 750, 10, 0, 'DAY', 'xxxx') ;

        // add payment
        $invoice->addPaymentMean('58','MA2120300000000202051' , 'Youniwemi');
        
        // Embedding pdf
        if ($embedPdf){
            $pdfFile = file_get_contents(__DIR__.'/examples/basic.pdf');
            $invoice->addEmbeddedAttachment('123', null, 'basic', $pdfFile, 'application/pdf', 'The pdf invoice');
        }

        $xml = $invoice->getXml();
        self::assertNotEmpty($xml);


        // An easy xml validation
        $result = $invoice->validate($xml);
        self::assertNull($result, $result ? $result."\nIN\n".$xml : '');

        if ($isPdf){
            // This will for a more thorough validation
            $pdfFile = file_get_contents(__DIR__.'/examples/basic.pdf');
            $result = $invoice->getPdf($pdfFile, true);

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
        $this->assertEmpty($result, $result ? print_r($result,true) ."\n".$xml : '');
    }
}
