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

        // Parse LineExtensionAmount values from raw XML
        $lineExtAmounts = $this->parseLineExtensionAmounts($xml);

        // Line items
        $lineIndex = 0;
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

            if (isset($lineExtAmounts[$lineIndex])) {
                $item->lineTotal = $lineExtAmounts[$lineIndex];
            }

            if ($item->taxRate === 0.0) {
                $data->taxExemptionCategory = $line->getVatCategory() ?? null;
                $data->taxExemptionReason   = $line->getVatExemptionReason() ?? null;
            }

            $data->items[] = $item;
            $lineIndex++;
        }

        // Totals — read from XML to preserve original values
        $xmlTotals = $this->parseMonetaryTotals($xml);
        if ($xmlTotals) {
            $data->taxBasisTotal  = $xmlTotals['taxExclusive'];
            $data->taxTotal       = $xmlTotals['taxAmount'];
            $data->grandTotal     = $xmlTotals['taxInclusive'];
            $data->prepaidAmount  = $xmlTotals['prepaid'];
            $data->duePayable     = $xmlTotals['payable'];
        } else {
            $totals              = $invoice->getTotals();
            $data->taxBasisTotal = $totals->taxExclusiveAmount;
            $data->taxTotal      = $totals->vatAmount;
            $data->grandTotal    = $totals->taxInclusiveAmount;
            $data->prepaidAmount = $totals->paidAmount != 0 ? $totals->paidAmount : null;
            $data->duePayable    = $totals->payableAmount;
        }

        // Tax breakdown
        $totals = $totals ?? $invoice->getTotals();
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

    private function parseMonetaryTotals(string $xml): ?array
    {
        $doc = $this->getDoc($xml);
        if (!$doc) {
            return null;
        }
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $getVal = function (string $element) use ($xpath): ?float {
            $nodes = $xpath->query("//cac:LegalMonetaryTotal/cbc:$element");
            return ($nodes && $nodes->length > 0) ? (float) $nodes->item(0)->textContent : null;
        };

        $taxAmountNodes = $xpath->query('//cac:TaxTotal/cbc:TaxAmount');
        $taxAmount = ($taxAmountNodes && $taxAmountNodes->length > 0) ? (float) $taxAmountNodes->item(0)->textContent : null;

        return [
            'taxExclusive' => $getVal('TaxExclusiveAmount'),
            'taxAmount'    => $taxAmount,
            'taxInclusive' => $getVal('TaxInclusiveAmount'),
            'prepaid'      => $getVal('PrepaidAmount'),
            'payable'      => $getVal('PayableAmount'),
        ];
    }

    /**
     * @return array<int, float>
     */
    private function parseLineExtensionAmounts(string $xml): array
    {
        $doc = $this->getDoc($xml);
        if (!$doc) {
            return [];
        }
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $amounts = [];
        $nodes = $xpath->query('//cac:InvoiceLine/cbc:LineExtensionAmount | //cac:CreditNoteLine/cbc:LineExtensionAmount');
        if ($nodes) {
            foreach ($nodes as $i => $node) {
                $amounts[$i] = (float) $node->textContent;
            }
        }
        return $amounts;
    }

    private function extractParty(Party $party): PartyData
    {
        $p              = new PartyData();
        $p->name        = (string) $party->getName();
        $p->tradingName = $party->getTradingName();

        // BT-34/BT-49: UBL EndpointID
        $endpoint = $party->getElectronicAddress();
        if ($endpoint) {
            $p->electronicAddress       = $endpoint->getValue();
            $p->electronicAddressScheme = $endpoint->getScheme();
        }

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
