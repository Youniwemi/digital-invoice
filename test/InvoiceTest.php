<?php

namespace DigitalInvoice\Tests;

use DigitalInvoice\CurrencyCode;
use DigitalInvoice\FacturX;
use DigitalInvoice\Invoice;
use DigitalInvoice\InvoiceTypeCode;
use DigitalInvoice\IdentificationType;
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
            // //[Ubl::PEPPOL, false , true, 0],
            [Ubl::NLCIUS, false, true],
            [Ubl::CIUS_RO, false, true],
            [Ubl::CIUS_IT, false, true],
            [Ubl::CIUS_ES_FACE, false, true],
            [Ubl::CIUS_AT_GOV, false, true],
            [Ubl::CIUS_AT_NAT, false, true],
            [Ubl::MALAYSIA, false, false],
        ];
    }

    /**
     * @dataProvider profilesProvider
     */
    public function testInvoiceXml($profile, $isPdf, $embedPdf = false, $taxRate = 20): void
    {
        if ($profile===Ubl::MALAYSIA){
            $identificationDesignator = 'BRN';  // Use BRN as primary identifier for Malaysia
            $currency =  CurrencyCode::MALAYSIAN_RINGGIT;
            $validate = false;
        } else {
            $identificationDesignator = '0002';
            $currency =  CurrencyCode::EURO;
            $validate = true;
        }
        $invoice = new Invoice('123', new \Datetime('2023-11-07'), null, $currency , $profile);

        
        $invoice->addNote("My Document note");


        $invoice->setSeller(
            '12344',
            $identificationDesignator,
            'Seller'
        );
        
        // Add TIN for Malaysian invoices (required by validation rules)
        if ($profile === Ubl::MALAYSIA) {
            $invoice->addSellerIdentifier('MY123456789', 'TIN');
        }
        $invoice->setSellerContact(
            'Contact Seller',
            '+2129999999999',
            'seller@email.com'
        );
        $invoice->setSellerTaxRegistration('FR1231344', 'VA') ;
        if ($taxRate == 0) {
            $invoice->setTaxExemption(Invoice::EXEMPT_FROM_TAX, 'Assujeti') ;
        }
        
        

        if ($profile === Ubl::MALAYSIA) {
            $invoice->setSellerAddress(
                'Lot 1, Jalan Test',
                '50480',
                'Kuala Lumpur',
                'MYS',
                null,
                null,
                '14'  // State code for Kuala Lumpur
            );
        } else {
            $invoice->setSellerAddress(
                '1 rue de la paie',
                '90000',
                'Paris',
                'FR'
            );
        }

        $invoice->setBuyer(
            '',
            'buyer'
        );

        $invoice->setBuyerIdentifier(
            '12344',
            $identificationDesignator, 
        );
        
        // Add TIN for Malaysian invoices (required by validation rules)
        if ($profile === Ubl::MALAYSIA) {
            $invoice->setBuyerIdentifier('MY987654321', 'TIN');
        }
        
        // Add buyer contact for Malaysian invoices (required by validation rules)
        if ($profile === Ubl::MALAYSIA) {
            $invoice->setBuyerContact(
                'Buyer Contact',
                '+60123456789',
                'buyer@example.com'
            );
        }

        if ($profile === Ubl::MALAYSIA) {
            $invoice->setBuyerAddress(
                'Lot 2, Jalan Buyer',
                '50480',
                'Kuala Lumpur',
                'MYS',
                null,
                null,
                '14'  // State code for Kuala Lumpur
            );
        } else {
            $invoice->setBuyerAddress(
                '2 rue de la paie',
                '90000',
                'Paris',
                'FR'
            );
        }


        if (in_array($profile, [FacturX::MINIMUM ,FacturX::BASIC_WL ])) {
            $taxAmount = ( 750 * $taxRate ) / 100;
            $invoice->setPrice(750, $taxAmount);
        } else {
            // Item 1 - add description for Malaysian invoices
            if ($profile === Ubl::MALAYSIA) {
                $invoice->addItem('service a la demande', 750, $taxRate, 1, 'DAY', 'xxxx', '0160', 'Professional consulting services on demand');
            } else {
                $invoice->addItem('service a la demande', 750, $taxRate, 1, 'DAY', 'xxxx');
            }
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
        self::assertNull($result, $result ? (is_array($result) ? print_r($result, true) : $result)."\nIN\n".$xml : '');

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
        $result = $invoice->validate($xml, $validate);
        $this->assertEmpty($result, $result ? print_r($result, true) ."\n".$xml : '');
    }

    public function testMalaysiaValidation()
    {
        // Test creating a complete Malaysian invoice following the existing pattern
        $profile = Ubl::MALAYSIA;
        $identificationDesignator = 'TIN';
        $currency = CurrencyCode::MALAYSIAN_RINGGIT;
        
        // Use yesterday's date with a specific time to avoid "too old" validation errors
        $yesterday = new \DateTime('yesterday 15:30:00');
        $invoice = new Invoice('INV-MY-001', $yesterday, null, $currency, $profile);
        
        $invoice->addNote("Malaysian e-invoice test with all required fields");

        // Set seller with Malaysian 'NRIC',

        $invoice->setSeller(
            '850125105019',  
            'NRIC',
            'AMS Setia Jaya Sdn. Bhd.'
        );
        
        // Add TIN for seller
        $invoice->addSellerIdentifier('IG21136626090', 'TIN');
        $invoice->addSellerIdentifier('850125105019', 'NRIC');
        
        // Set MSIC code for seller industry classification
        $invoice->setSellerIndustryClassification('26201', 'Manufacture of computers');
        
        $invoice->setSellerContact(
            'Ahmad Hassan',
            '+60123456789', 
            'general.ams@supplier.com'
        );
        
        $invoice->setSellerAddress(
            'Lot 66, Bangunan Merdeka, Persiaran Jaya',
            '50480',
            'Kuala Lumpur',
            'MYS',
            null,
            null,
            '14'  // State code for Kuala Lumpur
        );

        // Set buyer with Malaysian BRN  
        $invoice->setBuyer(
            '202301234567',  // Valid Malaysian BRN format: YYYYMMXXXXXX
            'Hebat Group'
        );
        
        // Add TIN for buyer - valid Malaysian TIN format
        $invoice->setBuyerIdentifier('C12345678901', 'TIN', IdentificationType::OTHER->value);

        $invoice->setBuyerIdentifier('201901234567', 'BRN', IdentificationType::OTHER->value);

        $invoice->setBuyerContact(
            'Fatimah Ali',
            '+60987654321',
            'buyer@hebatgroup.com'
        );

        $invoice->setBuyerAddress(
            'Lot 66, Bangunan Merdeka, Persiaran Jaya',
            '50480',
            'Kuala Lumpur', 
            'MYS',
            null,
            null,
            '14'  // State code for Kuala Lumpur
        );

        // Add invoice line item with description and classification
        $item = $invoice->addItem('Laptop Peripherals', 1436.50, 0, 1, 'C62', '1234', '0160', 'High-quality laptop peripherals including mouse, keyboard, and USB hub');
        
        // Add Malaysian commodity classification 
        $invoice->addItemClassification($item, '001', 'CLASS');
        
        // Set tax exemption
        $invoice->setTaxExemption(Invoice::EXEMPT_FROM_TAX, 'Exempt New Means of Transport');

        // Add payment method
        $invoice->addPaymentMean('58', '1234567890123', 'Bank Transfer');

        // Set payment terms (30 days from invoice date)
        $dueDate = clone $yesterday;
        $dueDate->add(new \DateInterval('P30D'));
        $invoice->setPaymentTerms($dueDate, 'Payment method is cash');

        // Generate and validate XML
        $xml = $invoice->getXml();
        $this->assertNotEmpty($xml);
        
        // Check that Malaysian-specific elements are present
        $this->assertStringContainsString('MYR', $xml);
        $this->assertStringContainsString('AMS Setia Jaya Sdn. Bhd.', $xml);
        $this->assertStringContainsString('Hebat Group', $xml);
        $this->assertStringContainsString('IG21136626090', $xml); // Supplier TIN
        $this->assertStringContainsString('C12345678901', $xml); // Buyer TIN  
        $this->assertStringContainsString('850125105019', $xml); // Supplier NRIC
        $this->assertStringContainsString('202301234567', $xml); // Buyer BRN
        $this->assertStringContainsString('Laptop Peripherals', $xml);
        $this->assertStringContainsString('Kuala Lumpur', $xml);
        $this->assertStringContainsString('+60987654321', $xml); // Buyer phone
        $this->assertStringContainsString('buyer@hebatgroup.com', $xml); // Buyer email
        
        // Basic XML validation including Malaysian preset validation rules
        $result = $invoice->validate($xml);
        if ($result) {
            $resultStr = is_array($result) ? print_r($result, true) : $result;
            $this->fail("Validation failed: " . $resultStr . "\nGenerated XML:\n" . $xml);
        }
        
        // Test that we can parse key Malaysian UBL elements
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ubl', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        
        // Verify mandatory Malaysian fields are present in XML structure
        $supplierName = $xpath->query('/ubl:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName');
        $this->assertEquals(1, $supplierName->length, 'Supplier name should be present');
        
        $currency = $xpath->query('/ubl:Invoice/cbc:DocumentCurrencyCode');
        $this->assertEquals(1, $currency->length, 'Currency should be present');
        $this->assertEquals('MYR', $currency->item(0)->textContent);
        
        $invoiceLines = $xpath->query('/ubl:Invoice/cac:InvoiceLine');
        $this->assertGreaterThan(0, $invoiceLines->length, 'At least one invoice line should be present');
        
        // Save the generated XML to examples directory for reference
        $xmlFilePath = __DIR__ . '/examples/malaysian-ubl-invoice.xml';
        file_put_contents($xmlFilePath, $xml);
        
    }

    public function testMalaysiaCodeArrays()
    {
        // Test MSIC codes
        $msicCodes = \DigitalInvoice\Presets\Malaysia::getMsicCodes();
        $this->assertIsArray($msicCodes);
        $this->assertArrayHasKey('00000', $msicCodes);
        $this->assertEquals('NOT APPLICABLE', $msicCodes['00000']);
        
        // Test Item Classification codes
        $classificationCodes = \DigitalInvoice\Presets\Malaysia::getItemClassificationCodes();
        $this->assertIsArray($classificationCodes);
        $this->assertArrayHasKey('001', $classificationCodes);
        $this->assertEquals('Breastfeeding equipment ', $classificationCodes['001']);
        $this->assertArrayHasKey('022', $classificationCodes);
        $this->assertEquals('Others', $classificationCodes['022']);
    }
}
