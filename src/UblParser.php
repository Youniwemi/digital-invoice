<?php

namespace DigitalInvoice;

use Einvoicing\Party;
use Einvoicing\Readers\UblReader;

/**
 * Reads UBL 2.1 XML invoices (Peppol, NLCIUS, CIUS-*, Malaysia…).
 *
 * Uses josemmo/einvoicing UblReader which returns the same Einvoicing\Invoice
 * object graph used by Ubl.php for writing, with the CIUS preset auto-detected
 * from the CustomizationID element.
 */
class UblParser extends XmlParser
{
    public function parse(string $xml): InvoiceData
    {
        $reader  = new UblReader();
        $invoice = $reader->import($xml);
        $data    = new InvoiceData();

        // Header
        $data->invoiceId   = (string) $invoice->getNumber();
        $data->invoiceType = (string) $invoice->getType();
        $data->currency    = (string) $invoice->getCurrency();
        $data->profile     = (string) $invoice->getSpecification();

        $data->issueDate  = $invoice->getIssueDate();
        $data->dueDate    = $invoice->getDueDate();
        $data->buyerReference = $invoice->getBuyerReference();

        // Notes
        foreach ($invoice->getNotes() as $note) {
            $data->notes[] = [
                'content'     => (string) $note,
                'subjectCode' => null,
                'contentCode' => null,
            ];
        }

        // Seller
        $seller = $invoice->getSeller();
        if ($seller) {
            $data->seller = $this->extractParty($seller);
        }

        // Buyer
        $buyer = $invoice->getBuyer();
        if ($buyer) {
            $data->buyer = $this->extractParty($buyer);
        }

        // Line items
        foreach ($invoice->getLines() as $line) {
            $item              = new InvoiceItemData();
            $item->name        = (string) $line->getName();
            $item->description = $line->getDescription();
            $item->quantity    = (float) $line->getQuantity();
            $item->unit        = (string) $line->getUnit();
            $item->price       = (float) $line->getPrice();
            $item->taxRate     = (float) $line->getVatRate();
            $item->globalID    = $line->getId();

            $std = $line->getStandardIdentifier();
            if ($std) {
                $item->globalID     = $std->getValue();
                $item->globalIDCode = $std->getScheme();
            }

            if ($item->taxRate === 0.0) {
                $data->taxExemptionCategory = $line->getVatCategory() ?? null;
                $data->taxExemptionReason   = $line->getVatExemptionReason() ?? null;
            }

            $data->items[] = $item;
        }

        // Totals (calculated from the Invoice object)
        $totals              = $invoice->getTotals();
        $data->taxBasisTotal = $totals->taxExclusiveAmount;
        $data->taxTotal      = $totals->vatAmount;
        $data->grandTotal    = $totals->taxInclusiveAmount;
        $data->duePayable    = $totals->payableAmount;

        // Tax breakdown
        foreach ($totals->vatBreakdown as $vat) {
            $tb = new TaxBreakdownData();
            $tb->rate             = (float) $vat->rate;
            $tb->basisAmount      = (float) $vat->taxableAmount;
            $tb->calculatedAmount = (float) $vat->taxAmount;
            $tb->categoryCode     = $vat->category ?? null;
            $tb->exemptionReason  = $vat->exemptionReason ?? null;
            $data->taxBreakdown[] = $tb;
        }

        // Payment means
        foreach ($invoice->getPayments() as $payment) {
            $pm           = new PaymentMeanData();
            $pm->typeCode = (string) $payment->getMeansCode();
            foreach ($payment->getTransfers() as $transfer) {
                $pm->iban        = $transfer->getAccountId();
                $pm->accountName = $transfer->getAccountName();
                break; // take first transfer only
            }
            $data->paymentMeans[] = $pm;
        }

        // Payment terms (stored as a description string in UBL)
        $data->paymentTermsDescription = $invoice->getPaymentTerms();

        return $data;
    }

    private function extractParty(Party $party): PartyData
    {
        $p              = new PartyData();
        $p->name        = (string) $party->getName();
        $p->tradingName = $party->getTradingName();

        $companyId = $party->getCompanyId();
        if ($companyId) {
            $p->id     = $companyId->getValue();
            $p->idType = $companyId->getScheme();
        }

        $vatNumber = $party->getVatNumber();
        if ($vatNumber) {
            $p->taxRegistrations[] = [
                'id'       => $vatNumber,
                'schemeID' => 'VA',
            ];
        }

        $taxRegId = $party->getTaxRegistrationId();
        if ($taxRegId) {
            $p->taxRegistrations[] = [
                'id'       => $taxRegId->getValue(),
                'schemeID' => $taxRegId->getScheme() ?? '',
            ];
        }

        // Address
        $addressLines = $party->getAddress();
        $p->address = $this->buildAddress(
            $addressLines[0] ?? null,
            $party->getPostalCode(),
            $party->getCity(),
            $party->getCountry(),
            $addressLines[1] ?? null,
            $addressLines[2] ?? null,
            $party->getSubdivision(),
        );

        // Contact
        $p->contact = $this->buildContact(
            $party->getContactName(),
            $party->getContactPhone(),
            $party->getContactEmail(),
        );

        return $p;
    }
}
