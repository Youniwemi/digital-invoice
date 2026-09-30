<?php

namespace DigitalInvoice\Tests;

require_once __DIR__.'/FnfeRules.php';

use DigitalInvoice\CurrencyCode;
use DigitalInvoice\FacturX;
use DigitalInvoice\Invoice;
use DigitalInvoice\Ubl;
use PHPUnit\Framework\TestCase;

/**
 * French CTC invoices validated with the FNFE profile rules and the French rules (BR-FR, XP Z12-012).
 * Run `make fnfe` first, the test is skipped otherwise.
 */
class FrenchRulesTest extends TestCase
{
    use FnfeRules;

    public static function profilesProvider(): array
    {
        return [
            'FacturX BASIC_WL' => [FacturX::BASIC_WL, ['FACTUR-X_BASIC-WL', 'BR-FR-CII']],
            'FacturX EN16931' => [FacturX::EN16931, ['FACTUR-X_EN16931', 'BR-FR-CII']],
            'FacturX EXTENDED' => [FacturX::EXTENDED, ['FACTUR-X_EXTENDED', 'BR-FR-CII']],
            'UBL PEPPOL' => [Ubl::PEPPOL, ['EN16931-UBL', 'BR-FR-UBL']],
        ];
    }

    /**
     * @dataProvider profilesProvider
     */
    public function testFrenchRules(string $profile, array $stylesheets): void
    {
        if (!self::fnfeAvailable()) {
            $this->markTestSkipped('Run `make fnfe` to download Saxon and the FNFE rules');
        }

        $invoice = new Invoice('F202600001', new \Datetime('2026-09-01'), null, CurrencyCode::EURO, $profile);
        $invoice->setBillingMode('S1');
        $invoice->addNote('Pénalités de retard : 3 fois le taux d\'intérêt légal', 'PMD');
        $invoice->addNote('Indemnité forfaitaire pour frais de recouvrement : 40 €', 'PMT');
        $invoice->addNote('Pas d\'escompte pour paiement anticipé', 'AAB');
        $invoice->setSeller('732829320', '0002', 'Seller');
        $invoice->setSellerTaxRegistration('FR44732829320', 'VA');
        $invoice->setSellerAddress('1 rue de la paie', '75001', 'Paris', 'FR');
        $invoice->setSellerElectronicAddress('732829320', '0225');
        $invoice->setBuyer('REF-ACHETEUR', 'Buyer');
        $invoice->setBuyerIdentifier('552100554', '0002');
        $invoice->setBuyerElectronicAddress('buyer@example.fr', 'EM');
        $invoice->setBuyerAddress('2 rue de la paie', '75002', 'Paris', 'FR');
        if ($profile === FacturX::BASIC_WL) {
            $invoice->setPrice(750, 150);
        } else {
            $invoice->addItem('service a la demande', 750, 20, 1, 'DAY', 'xxxx');
        }
        $invoice->addPaymentMean('58', 'FR7630001007941234567890185', 'Seller');
        $invoice->setPaymentTerms(new \Datetime('2026-10-01'));

        $xml = $invoice->getXml();
        // UBL is checked with EN16931-UBL, the PEPPOL preset rules allow a single note while BR-FR-05 requires three
        if ($profile !== Ubl::PEPPOL) {
            // The added elements must keep the XSD order
            $result = $invoice->validate($xml);
            $this->assertNull($result, print_r($result, true)."\n".$xml);
        }

        $this->assertFnfeRules($xml, $stylesheets);
    }
}
