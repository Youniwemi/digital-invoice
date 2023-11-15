<?php

namespace DigitalInvoice\Tests;

use DigitalInvoice\Invoice;
use Atgp\FacturX\Facturx;
use PHPUnit\Framework\TestCase;

class InvoiceTest extends TestCase
{
    public function testMinimumInvoiceXml(): void
    {
        $invoice = new Invoice('123', new \Datetime('2023-11-07'));

        $invoice->setSeller(
            '12344',
            '0002',
            'Seller',
            'Contact Seller',
            $departmentName = null,
            '+2129999999999',
            'seller@email.com'
        );

        $invoice->setSellerTaxRegistration('!!!!!!', 'VAT') ;

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
        //$invoice->addItem('service a la demande', '750', 10, 0, 'DAY', 'xxxx') ;


        $xml = $invoice->getXml();
        self::assertNotEmpty($xml);


        // An easy xml validation
        $result = $invoice->validate($xml);
        self::assertNull($result, $result ? $result."\nIN\n".$xml : '');

        // This will for a more thorough validation
        $pdfFile = file_get_contents(__DIR__.'/examples/basic.pdf');
        $result = $invoice->getPdf($pdfFile);

        // Check xml again
        $facturX = new Facturx();
        try {
            $xml = $facturX->getFacturxXmlFromPdf($result);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail('Error extractiong xml '. $e->getMessage());
        }

        /* In progress, schematron validation library seems of..
        // A complete validation using schematron
        $result = $invoice->validate($xml, true);
        $this->assertEmpty($result, $result ? implode("\n", $result) ."\nIN\n".$xml : '');
        */

    }

}
