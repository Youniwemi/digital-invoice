<?php

namespace DigitalInvoice\Tests;

require_once __DIR__.'/FnfeRules.php';

use DigitalInvoice\CurrencyCode;
use DigitalInvoice\FacturX;
use DigitalInvoice\Invoice;
use DigitalInvoice\Ubl;
use DigitalInvoice\VatDueDateTypeCode;
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

        $invoice = self::createInvoice($profile, 'F202600001', '380');
        $this->assertValid($invoice, $profile, $stylesheets);
    }

    /**
     * @dataProvider profilesProvider
     */
    public function testFrenchRulesCreditNote(string $profile, array $stylesheets): void
    {
        if (!self::fnfeAvailable()) {
            $this->markTestSkipped('Run `make fnfe` to download Saxon and the FNFE rules');
        }

        $creditNote = self::createInvoice($profile, 'A202600001', '381');
        $creditNote->addPrecedingInvoiceReference('F202600001', new \Datetime('2026-09-01'));
        $this->assertTrue($creditNote->isCreditNote());
        $this->assertValid($creditNote, $profile, $stylesheets);

        // Bundled Factur-X schematron, EU ITB validator for UBL
        $xml = $creditNote->getXml();
        $result = $creditNote->validate($xml, true);
        $this->assertEmpty($result, print_r($result, true)."\n".$xml);
    }

    /**
     * @dataProvider profilesProvider
     */
    public function testFrenchRulesAllowance(string $profile, array $stylesheets): void
    {
        if (!self::fnfeAvailable()) {
            $this->markTestSkipped('Run `make fnfe` to download Saxon and the FNFE rules');
        }

        $invoice = self::createInvoice($profile, 'F202600002', '380');
        $invoice->addAllowance(50, 20);
        $this->assertValid($invoice, $profile, $stylesheets);

        // 750 - 50 = 700 HT, 140 TVA
        $xml = $invoice->getXml();
        $this->assertMatchesRegularExpression('/>700(\.00)?</', $xml);
        $this->assertMatchesRegularExpression('/>840(\.00)?</', $xml);

        $result = $invoice->validate($xml, true);
        $this->assertEmpty($result, print_r($result, true)."\n".$xml);
    }

    /**
     * @dataProvider profilesProvider
     */
    public function testFrenchRulesVatOnDebits(string $profile, array $stylesheets): void
    {
        if ($profile === Ubl::PEPPOL) {
            $this->markTestSkipped('BT-8 is not implemented for UBL');
        }
        if (!self::fnfeAvailable()) {
            $this->markTestSkipped('Run `make fnfe` to download Saxon and the FNFE rules');
        }

        // Option pour le paiement de la taxe d'après les débits
        $invoice = self::createInvoice($profile, 'F202600003', '380');
        $invoice->setVatDueDateTypeCode(VatDueDateTypeCode::INVOICE_DATE);
        $this->assertValid($invoice, $profile, $stylesheets);
        $xml = $invoice->getXml();
        $this->assertStringContainsString('<ram:DueDateTypeCode>5</ram:DueDateTypeCode>', $xml);
        // Kept for external validation
        $file = __DIR__.'/examples/french-vat-on-debits-'.strtolower($stylesheets[0]);
        file_put_contents($file.'.xml', $xml);
        file_put_contents($file.'.pdf', $invoice->getPdf(file_get_contents(__DIR__.'/examples/basic.pdf'), true));
    }

    private function assertValid(Invoice $invoice, string $profile, array $stylesheets): void
    {
        $xml = $invoice->getXml();
        // UBL is checked with EN16931-UBL, the PEPPOL preset rules allow a single note while BR-FR-05 requires three
        if ($profile !== Ubl::PEPPOL) {
            // The added elements must keep the XSD order
            $result = $invoice->validate($xml);
            $this->assertNull($result, print_r($result, true)."\n".$xml);
        }

        $this->assertFnfeRules($xml, $stylesheets);
    }

    private static function createInvoice(string $profile, string $id, string $type): Invoice
    {
        $invoice = new Invoice($id, new \Datetime('2026-09-01'), null, CurrencyCode::EURO, $profile, $type);
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

        return $invoice;
    }
}
