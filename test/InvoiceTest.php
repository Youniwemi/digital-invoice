<?php

namespace DigitalInvoice\Tests;

require_once __DIR__.'/FnfeRules.php';

use DigitalInvoice\AttachmentDescription;
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
    use FnfeRules;

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
        // FNFE rules (make fnfe), the Factur-X profiles used by the French CTC also get the French rules
        $fnfe = match ($profile) {
            FacturX::BASIC_WL => ['FACTUR-X_BASIC-WL', 'BR-FR-CII'],
            FacturX::EN16931 => ['FACTUR-X_EN16931', 'BR-FR-CII'],
            FacturX::EXTENDED => ['FACTUR-X_EXTENDED', 'BR-FR-CII'],
            FacturX::BASIC, FacturX::XRECHNUNG => ['EN16931-CII'],
            Ubl::PEPPOL, Ubl::NLCIUS, Ubl::CIUS_RO, Ubl::CIUS_IT, Ubl::CIUS_ES_FACE, Ubl::CIUS_AT_GOV, Ubl::CIUS_AT_NAT => ['EN16931-UBL'],
            default => [],
        };
        $french = in_array('BR-FR-CII', $fnfe);
        $invoice = new Invoice('123', new \Datetime('2023-11-07'), null, $currency , $profile);


        $invoice->addNote("My Document note");
        if ($french) {
            $invoice->setBillingMode('S1');
            $invoice->addNote('Indemnité forfaitaire pour frais de recouvrement : 40 €', 'PMT');
            $invoice->addNote('Pénalités de retard : 3 fois le taux d\'intérêt légal', 'PMD');
            $invoice->addNote('Pas d\'escompte pour paiement anticipé', 'AAB');
        }


        $invoice->setSeller(
            $profile === Ubl::MALAYSIA ? '12344' : '732829320',
            $identificationDesignator,
            'Seller'
        );
        if ($french) {
            $invoice->setSellerElectronicAddress('732829320', '0225');
        }
        
        // Add TIN for Malaysian invoices (required by validation rules)
        if ($profile === Ubl::MALAYSIA) {
            $invoice->addSellerIdentifier('MY123456789', 'TIN');
        }
        $invoice->setSellerContact(
            'Contact Seller',
            '+2129999999999',
            'seller@email.com'
        );
        $invoice->setSellerTaxRegistration('FR44732829320', 'VA') ;
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
            $profile === Ubl::MALAYSIA ? '12344' : '552100554',
            $identificationDesignator,
        );
        if ($french) {
            $invoice->setBuyerElectronicAddress('buyer@example.fr', 'EM');
        }
        
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
            // ZUGFeRD 1.0 profiles (BASIC, COMFORT, EXTENDED) would overwrite the Factur-X ones
            $profile = explode(":", $profile);
            $short = count($profile) > 1 ? array_pop($profile) : 'zugferd-'.strtolower($profile[0]);
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

        $this->assertFnfeRules($xml, $fnfe);
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
            'Jalan Ampang',  // Additional street name
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
            'Jalan Bukit Bintang',  // Additional street name
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
        $this->assertStringContainsString('Jalan Ampang', $xml); // Seller additional street name
        $this->assertStringContainsString('Jalan Bukit Bintang', $xml); // Buyer additional street name
        
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
        
        // Verify AddressLine elements are created for both StreetName and AdditionalStreetName
        $sellerAddressLines = $xpath->query('/ubl:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cac:AddressLine/cbc:Line');
        $this->assertGreaterThan(0, $sellerAddressLines->length, 'Seller should have AddressLine elements');
        
        $buyerAddressLines = $xpath->query('/ubl:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cac:AddressLine/cbc:Line');
        $this->assertGreaterThan(0, $buyerAddressLines->length, 'Buyer should have AddressLine elements');
        
        // Verify specific AddressLine content
        $foundSellerStreet = false;
        $foundSellerAdditional = false;
        foreach ($sellerAddressLines as $line) {
            if (strpos($line->textContent, 'Lot 66, Bangunan Merdeka') !== false) {
                $foundSellerStreet = true;
            }
            if (strpos($line->textContent, 'Jalan Ampang') !== false) {
                $foundSellerAdditional = true;
            }
        }
        $this->assertTrue($foundSellerStreet, 'Seller StreetName should be converted to AddressLine');
        $this->assertTrue($foundSellerAdditional, 'Seller AdditionalStreetName should be converted to AddressLine');
        
        $foundBuyerStreet = false;
        $foundBuyerAdditional = false;
        foreach ($buyerAddressLines as $line) {
            if (strpos($line->textContent, 'Lot 66, Bangunan Merdeka') !== false) {
                $foundBuyerStreet = true;
            }
            if (strpos($line->textContent, 'Jalan Bukit Bintang') !== false) {
                $foundBuyerAdditional = true;
            }
        }
        $this->assertTrue($foundBuyerStreet, 'Buyer StreetName should be converted to AddressLine');
        $this->assertTrue($foundBuyerAdditional, 'Buyer AdditionalStreetName should be converted to AddressLine');
        
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
        $this->assertEquals('Rice milling', $msicCodes["10611"]);
        
        // Test Item Classification codes
        $classificationCodes = \DigitalInvoice\Presets\Malaysia::getItemClassificationCodes();
        $this->assertIsArray($classificationCodes);
        $this->assertArrayHasKey('001', $classificationCodes);
        $this->assertEquals('Breastfeeding equipment ', $classificationCodes['001']);
        $this->assertArrayHasKey('022', $classificationCodes);
        $this->assertEquals('Others', $classificationCodes['022']);
    }

    /**
     * ShipToTradeParty must not contain SpecifiedTaxRegistration (CII schematron rule).
     * @dataProvider ciiProfilesProvider
     */
    public function testShipToTradePartyHasNoTaxRegistration(string $profile): void
    {
        $invoice = new Invoice('TEST-SHIPTO', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, $profile);
        $invoice->setSeller('12344', '0002', 'Seller');
        $invoice->setSellerTaxRegistration('FR1231344', 'VA');
        $invoice->setSellerAddress('1 rue test', '90000', 'Paris', 'FR');
        $invoice->setBuyer('', 'Buyer');
        $invoice->setBuyerAddress('2 rue test', '90000', 'Paris', 'FR');

        if (in_array($profile, [FacturX::MINIMUM, FacturX::BASIC_WL])) {
            $invoice->setPrice(100, 20);
        } else {
            $invoice->addItem('item', 100, 20, 1, 'DAY');
        }
        $invoice->addPaymentMean('58', 'FR7630001007941234567890185', 'Test');
        $invoice->setPaymentTerms(new \Datetime('2023-12-07'));

        $xml = $invoice->getXml();

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xpath->registerNamespace('ram10', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:12');

        $nodes = $xpath->query('//ram:ShipToTradeParty/ram:SpecifiedTaxRegistration | //ram10:ShipToTradeParty/ram10:SpecifiedTaxRegistration');
        $this->assertEquals(0, $nodes->length, "ShipToTradeParty must not contain SpecifiedTaxRegistration in $profile\n$xml");
    }

    /**
     * BT-13 purchase order reference is written, validated and read back.
     * @dataProvider buyerOrderReferenceProvider
     */
    public function testBuyerOrderReference(string $profile, string $query, string $file): void
    {
        // The Factur-X invoice is French CTC compliant, UBL PEPPOL allows a single note so it only gets EN16931 rules
        $french = $profile === FacturX::EN16931;
        $invoice = new Invoice('TEST-BT13', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, $profile);
        if ($french) {
            $invoice->setBillingMode('S1');
            $invoice->addNote('Indemnité forfaitaire pour frais de recouvrement : 40 €', 'PMT');
            $invoice->addNote('Pénalités de retard : 3 fois le taux d\'intérêt légal', 'PMD');
            $invoice->addNote('Pas d\'escompte pour paiement anticipé', 'AAB');
        }
        $invoice->setSeller('732829320', '0002', 'Seller');
        $invoice->setSellerTaxRegistration('FR44732829320', 'VA');
        $invoice->setSellerAddress('1 rue test', '90000', 'Paris', 'FR');
        $invoice->setBuyer('REF-ACHETEUR', 'Buyer');
        if ($french) {
            $invoice->setSellerElectronicAddress('732829320', '0225');
            $invoice->setBuyerIdentifier('552100554', '0002');
            $invoice->setBuyerElectronicAddress('buyer@example.fr', 'EM');
        }
        $invoice->setBuyerOrderReference('BC-42');
        $invoice->setBuyerAddress('2 rue test', '90000', 'Paris', 'FR');
        $invoice->addItem('item', 100, 20, 1, 'DAY', 'xxxx');
        $invoice->addPaymentMean('58', 'FR7630001007941234567890185', 'Test');
        $invoice->setPaymentTerms(new \Datetime('2023-12-07'));

        $xml = $invoice->getXml();
        // Kept for external validation
        file_put_contents(__DIR__.'/examples/'.$file.'.xml', $xml);
        if ($profile === FacturX::EN16931) {
            file_put_contents(__DIR__.'/examples/'.$file.'.pdf', $invoice->getPdf(file_get_contents(__DIR__.'/examples/basic.pdf'), true));
        }

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xpath->registerNamespace('ram10', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:12');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $this->assertSame('BC-42', $xpath->evaluate("string($query)"), $xml);

        // BT-10 is left untouched
        $this->assertStringContainsString('REF-ACHETEUR', $xml);

        $result = $invoice->validate($xml);
        $this->assertEmpty($result, $result ? print_r($result, true)."\n".$xml : '');
        $result = $invoice->validate($xml, true);
        $this->assertEmpty($result, $result ? print_r($result, true)."\n".$xml : '');
        $this->assertFnfeRules($xml, match ($profile) {
            FacturX::EN16931 => ['FACTUR-X_EN16931', 'BR-FR-CII'],
            Ubl::PEPPOL => ['EN16931-UBL'],
            default => [],
        });

        $data = \DigitalInvoice\InvoiceReader::fromXml($xml);
        $this->assertSame('BC-42', $data->buyerOrderReference);
    }

    /**
     * BG-24 supporting document, embedded in the XML and in the PDF.
     */
    public function testAttachment(): void
    {
        $invoice = new Invoice('TEST-BG24', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, FacturX::EN16931);
        $invoice->setBillingMode('S1');
        $invoice->addNote('Indemnité forfaitaire pour frais de recouvrement : 40 €', 'PMT');
        $invoice->addNote('Pénalités de retard : 3 fois le taux d\'intérêt légal', 'PMD');
        $invoice->addNote('Pas d\'escompte pour paiement anticipé', 'AAB');
        $invoice->setSeller('732829320', '0002', 'Seller');
        $invoice->setSellerTaxRegistration('FR44732829320', 'VA');
        $invoice->setSellerAddress('1 rue test', '90000', 'Paris', 'FR');
        $invoice->setSellerElectronicAddress('732829320', '0225');
        $invoice->setBuyer('REF-ACHETEUR', 'Buyer');
        $invoice->setBuyerIdentifier('552100554', '0002');
        $invoice->setBuyerElectronicAddress('buyer@example.fr', 'EM');
        $invoice->setBuyerAddress('2 rue test', '90000', 'Paris', 'FR');
        $invoice->addItem('item', 100, 20, 1, 'DAY', 'xxxx');
        $invoice->addPaymentMean('58', 'FR7630001007941234567890185', 'Test');
        $invoice->setPaymentTerms(new \Datetime('2023-12-07'));

        // A distinct document, to check the right file is extracted
        $note = new \FPDF();
        $note->AddPage();
        $note->SetFont('Helvetica', 'B', 24);
        $note->Cell(0, 20, 'BON DE LIVRAISON BL-42');
        $note->Ln();
        $note->SetFont('Helvetica', '', 14);
        $note->Cell(0, 10, '1 x item, livre le 07/11/2023');
        $attachment = $note->Output('S');
        $invoice->addEmbeddedAttachment('BL-42', null, 'bon-de-livraison.pdf', $attachment, 'application/pdf', 'BON_LIVRAISON');

        $xml = $invoice->getXml();
        // Kept for external validation
        file_put_contents(__DIR__.'/examples/attachment.xml', $xml);
        $pdf = $invoice->getPdf(file_get_contents(__DIR__.'/examples/basic.pdf'), true, [], true);
        file_put_contents(__DIR__.'/examples/attachment.pdf', $pdf);

        // The delivery note is appended after the invoice page
        $pageCount = (new \setasign\Fpdi\Fpdi())->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($pdf));
        $this->assertSame(2, $pageCount);
        $this->assertSame(1, (new \setasign\Fpdi\Fpdi())->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($invoice->getPdf(file_get_contents(__DIR__.'/examples/basic.pdf')))));

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $node = $xpath->query('//ram:ApplicableHeaderTradeAgreement/ram:AdditionalReferencedDocument/ram:AttachmentBinaryObject')->item(0);
        $this->assertNotNull($node, $xml);
        $this->assertSame($attachment, base64_decode($node->nodeValue));

        $result = $invoice->validate($xml);
        $this->assertEmpty($result, $result ? print_r($result, true)."\n".$xml : '');
        $result = $invoice->validate($xml, true);
        $this->assertEmpty($result, $result ? print_r($result, true)."\n".$xml : '');
        $this->assertFnfeRules($xml, ['FACTUR-X_EN16931', 'BR-FR-CII']);

        // Visible in the attachment pane of PDF readers
        $this->assertStringContainsString('/F (bon-de-livraison.pdf)', $pdf);
        $this->assertStringContainsString('/AFRelationship /Supplement', $pdf);

        // Read back from the PDF and offered for download by the viewer
        $data = \DigitalInvoice\InvoiceReader::read($pdf);
        $this->assertCount(1, $data->attachments);
        $this->assertSame('BL-42', $data->attachments[0]->id);
        $this->assertSame('BON_LIVRAISON', $data->attachments[0]->description);
        $this->assertSame('bon-de-livraison.pdf', $data->attachments[0]->filename);
        $this->assertSame('application/pdf', $data->attachments[0]->mimeCode);
        $this->assertSame($attachment, $data->attachments[0]->contents);
        $html = (new \DigitalInvoice\InvoiceRenderer())->render($data);
        $this->assertStringContainsString('href="data:application/pdf;base64,'.base64_encode($attachment).'" download="bon-de-livraison.pdf"', $html);
    }

    /**
     * A PDF attachment FPDI can not read is not appended, but is still embedded.
     */
    public function testAttachmentNotAppendable(): void
    {
        $invoice = new Invoice('TEST-BG24', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, FacturX::EN16931);
        $invoice->setSeller('732829320', '0002', 'Seller');
        $invoice->setSellerTaxRegistration('FR44732829320', 'VA');
        $invoice->setSellerAddress('1 rue test', '90000', 'Paris', 'FR');
        $invoice->setBuyer('REF-ACHETEUR', 'Buyer');
        $invoice->setBuyerAddress('2 rue test', '90000', 'Paris', 'FR');
        $invoice->addItem('item', 100, 20, 1, 'DAY', 'xxxx');
        $invoice->addEmbeddedAttachment('BL-42', null, 'bon-de-livraison.pdf', 'not a pdf', 'application/pdf', 'BON_LIVRAISON');

        $pdf = $invoice->getPdf(file_get_contents(__DIR__.'/examples/basic.pdf'), false, [], true);

        $this->assertSame(1, (new \setasign\Fpdi\Fpdi())->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($pdf)));
        $this->assertStringContainsString('/F (bon-de-livraison.pdf)', $pdf);
    }

    public function testAttachmentUnsupported(): void
    {
        $invoice = new Invoice('TEST-BG24', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, FacturX::BASIC);
        $this->expectException(\Exception::class);
        $invoice->addEmbeddedAttachment('BL-42', null, 'bl.pdf', 'x', 'application/pdf', 'Bon de livraison');
    }

    public function testAttachmentInvalidMimeCode(): void
    {
        $invoice = new Invoice('TEST-BG24', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, FacturX::EN16931);
        $this->expectExceptionMessage('Attachment mime code must be one of application/pdf');
        $invoice->addEmbeddedAttachment('BL-42', null, 'bl.docx', 'x', 'application/msword', AttachmentDescription::BON_LIVRAISON->value);
    }

    public static function buyerOrderReferenceProvider(): array
    {
        return [
            'FacturX EN16931' => [FacturX::EN16931, '//ram:ApplicableHeaderTradeAgreement/ram:BuyerOrderReferencedDocument/ram:IssuerAssignedID', 'basic-bt13-facturx-en16931'],
            'Zugferd COMFORT' => [Zugferd::ZUGFERD_CONFORT, '//ram10:ApplicableSupplyChainTradeAgreement/ram10:BuyerOrderReferencedDocument/ram10:ID', 'basic-bt13-zugferd-comfort'],
            'UBL PEPPOL' => [Ubl::PEPPOL, '/*/cac:OrderReference/cbc:ID', 'basic-bt13-ubl-peppol'],
        ];
    }

    /**
     * Document level allowances (BG-20) on two VAT rates, written, validated and read back.
     * @dataProvider allowanceProvider
     */
    public function testAllowance(string $profile, array $queries, string $file): void
    {
        // The Factur-X invoice is French CTC compliant, UBL PEPPOL allows a single note so it only gets EN16931 rules
        $french = $profile === FacturX::EN16931;
        $invoice = new Invoice('TEST-ALLOWANCE', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, $profile);
        if ($french) {
            $invoice->setBillingMode('S1');
            $invoice->addNote('Indemnité forfaitaire pour frais de recouvrement : 40 €', 'PMT');
            $invoice->addNote('Pénalités de retard : 3 fois le taux d\'intérêt légal', 'PMD');
            $invoice->addNote('Pas d\'escompte pour paiement anticipé', 'AAB');
        }
        $invoice->setSeller('732829320', '0002', 'Seller');
        $invoice->setSellerTaxRegistration('FR44732829320', 'VA');
        $invoice->setSellerAddress('1 rue test', '90000', 'Paris', 'FR');
        $invoice->setBuyer('REF-ACHETEUR', 'Buyer');
        if ($french) {
            $invoice->setSellerElectronicAddress('732829320', '0225');
            $invoice->setBuyerIdentifier('552100554', '0002');
            $invoice->setBuyerElectronicAddress('buyer@example.fr', 'EM');
        }
        $invoice->setBuyerAddress('2 rue test', '90000', 'Paris', 'FR');
        $invoice->addItem('Abonnement', 100, 20, 3, 'H87', 'A1');
        $invoice->addItem('Formation', 75, 20, 1, 'DAY', 'F1');
        $invoice->addItem('Livre', 50, 5.5, 1, 'H87', 'L1');
        $invoice->addAllowance(7, 20);
        $invoice->addAllowance(18, 20, 'Remise fidélité', '95');
        $invoice->addAllowance(10, 5.5);
        $invoice->addPaymentMean('58', 'FR7630001007941234567890185', 'Test');
        $invoice->setPaymentTerms(new \Datetime('2023-12-07'));

        $xml = $invoice->getXml();
        // Kept for external validation
        file_put_contents(__DIR__.'/examples/'.$file.'.xml', $xml);
        if ($profile === FacturX::EN16931) {
            file_put_contents(__DIR__.'/examples/'.$file.'.pdf', $invoice->getPdf(file_get_contents(__DIR__.'/examples/basic.pdf'), true));
        }

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        // Lines 300 + 75 at 20 % and 50 at 5.5 %, allowances 25 at 20 % and 10 at 5.5 %
        $expected = [
            'allowances' => 3,
            'lineTotal' => 425,    // BT-106
            'allowanceTotal' => 35, // BT-107
            'taxBasisTotal' => 390, // BT-109
            'taxTotal' => 72.20,    // BT-110: 350 * 20 % + 40 * 5.5 %
            'grandTotal' => 462.20, // BT-112
        ];
        foreach ($expected as $name => $value) {
            $this->assertEquals($value, $xpath->evaluate("number({$queries[$name]})"), "$name\n$xml");
        }

        $result = $invoice->validate($xml);
        $this->assertEmpty($result, $result ? print_r($result, true)."\n".$xml : '');
        $result = $invoice->validate($xml, true);
        $this->assertEmpty($result, $result ? print_r($result, true)."\n".$xml : '');
        $this->assertFnfeRules($xml, match ($profile) {
            FacturX::EN16931 => ['FACTUR-X_EN16931', 'BR-FR-CII'],
            Ubl::PEPPOL => ['EN16931-UBL'],
        });

        $data = \DigitalInvoice\InvoiceReader::fromXml($xml);
        $this->assertEquals(390, $data->taxBasisTotal);
        $this->assertEquals(72.20, $data->taxTotal);
        $this->assertEquals(462.20, $data->grandTotal);
        $basis = [];
        foreach ($data->taxBreakdown as $tax) {
            $basis[(string) $tax->rate] = $tax->basisAmount;
        }
        $this->assertEquals(['20' => 350, '5.5' => 40], $basis);
    }

    public static function allowanceProvider(): array
    {
        return [
            'FacturX EN16931' => [FacturX::EN16931, [
                'allowances' => 'count(//ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeAllowanceCharge)',
                'lineTotal' => '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:LineTotalAmount',
                'allowanceTotal' => '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:AllowanceTotalAmount',
                'taxBasisTotal' => '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxBasisTotalAmount',
                'taxTotal' => '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount',
                'grandTotal' => '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount',
            ], 'basic-allowance-facturx-en16931'],
            'UBL PEPPOL' => [Ubl::PEPPOL, [
                'allowances' => 'count(/*/cac:AllowanceCharge)',
                'lineTotal' => '/*/cac:LegalMonetaryTotal/cbc:LineExtensionAmount',
                'allowanceTotal' => '/*/cac:LegalMonetaryTotal/cbc:AllowanceTotalAmount',
                'taxBasisTotal' => '/*/cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount',
                'taxTotal' => '/*/cac:TaxTotal/cbc:TaxAmount',
                'grandTotal' => '/*/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount',
            ], 'basic-allowance-ubl-peppol'],
        ];
    }

    /**
     * @dataProvider allowanceUnsupportedProvider
     */
    public function testAllowanceUnsupported(string $profile): void
    {
        $invoice = new Invoice('TEST-ALLOWANCE', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, $profile);
        $this->expectException(\Exception::class);
        $invoice->addAllowance(10, 20);
    }

    public static function allowanceUnsupportedProvider(): array
    {
        return [
            'FacturX MINIMUM' => [FacturX::MINIMUM],
            'Zugferd COMFORT' => [Zugferd::ZUGFERD_CONFORT],
        ];
    }

    public function testAllowanceAmountMustBePositive(): void
    {
        $invoice = new Invoice('TEST-ALLOWANCE', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, FacturX::EN16931);
        $this->expectExceptionMessage('The allowance amount should be positive');
        $invoice->addAllowance(-10, 20);
    }

    public function testFacturXSellerContact(): void
    {
        $invoice = new Invoice('TEST-CONTACT', new \Datetime('2023-11-07'), null, CurrencyCode::EURO, FacturX::EN16931);
        $invoice->setSeller('12344', '0002', 'Seller');
        $invoice->setSellerContact('Contact Seller', '+33100000000', 'seller@email.com');
        $invoice->setBuyer('', 'Buyer');
        $invoice->addItem('item', 100, 20, 1, 'DAY');

        $doc = new \DOMDocument();
        $doc->loadXML($invoice->getXml());
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $contact = '//ram:SellerTradeParty/ram:DefinedTradeContact';
        $this->assertSame('Contact Seller', $xpath->evaluate("string($contact/ram:PersonName)"));
        $this->assertSame('+33100000000', $xpath->evaluate("string($contact/ram:TelephoneUniversalCommunication/ram:CompleteNumber)"));
        $this->assertSame('seller@email.com', $xpath->evaluate("string($contact/ram:EmailURIUniversalCommunication/ram:URIID)"));
        $this->assertSame(0, $xpath->query("$contact/ram:DepartmentName")->length);
    }

    public static function ciiProfilesProvider(): array
    {
        return [
            'FacturX BASIC_WL' => [FacturX::BASIC_WL],
            'FacturX BASIC' => [FacturX::BASIC],
            'FacturX EN16931' => [FacturX::EN16931],
            'Zugferd BASIC' => [Zugferd::ZUGFERD_BASIC],
            'Zugferd COMFORT' => [Zugferd::ZUGFERD_CONFORT],
        ];
    }
}
