<?php

namespace DigitalInvoice;

use Easybill\ZUGFeRD2\Model\TradeParty;
use Easybill\ZUGFeRD2\Model\TradeContact;
use Easybill\ZUGFeRD2\Model\TradeAddress;
use Easybill\ZUGFeRD2\Reader;

/**
 * Reads any Cross Industry Invoice (CII) XML: FacturX, ZUGFeRD 2.1.1, XRechnung.
 *
 * Uses easybill/zugferd-php ZUGFeRD2 Reader which deserializes via JMS Serializer
 * into the CrossIndustryInvoice object graph. Can be used directly for generic CII
 * parsing or extended by format-specific subclasses (e.g. FacturXParser).
 *
 * Also handles PDF input: FacturX / ZUGFeRD PDFs embed their CII XML as an attachment,
 * so PDF extraction naturally belongs here rather than in the generic InvoiceReader.
 */
class CiiParser extends XmlParser
{
    /**
     * Extract the embedded CII XML from a FacturX / ZUGFeRD PDF, then parse it.
     * Accepts a file path or raw PDF binary content.
     */
    public function parsePdf(string $pdfPathOrContent): InvoiceData
    {
        $xml = (new PdfWriter())->getFacturxXmlFromPdf($pdfPathOrContent);
        self::assertNoDoctype($xml);
        return $this->parse($xml);
    }

    public function parse(string $xml): InvoiceData
    {
        $cii = Reader::create()->transform($xml);
        $data = new InvoiceData();

        $doc   = $cii->exchangedDocument;
        $ctx   = $cii->exchangedDocumentContext;
        $tx    = $cii->supplyChainTradeTransaction;
        $agr   = $tx->applicableHeaderTradeAgreement;
        $set   = $tx->applicableHeaderTradeSettlement;

        // Header
        $data->invoiceId   = $doc->id ?? '';
        $data->invoiceType = $doc->typeCode ?? '380';
        $data->profile     = $ctx->documentContextParameter->id ?? '';
        $data->currency    = $set->invoiceCurrencyCode ?? 'EUR';

        if (isset($doc->issueDateTime->dateTimeString)) {
            $data->issueDate = $this->parseDate($doc->issueDateTime->dateTimeString->value, 'Ymd');
        }

        // Delivery date
        $delivery = $tx->applicableHeaderTradeDelivery ?? null;
        if ($delivery && isset($delivery->actualDeliverySupplyChainEvent->occurrenceDateTime->dateTimeString)) {
            $data->deliveryDate = $this->parseDate($delivery->actualDeliverySupplyChainEvent->occurrenceDateTime->dateTimeString->value, 'Ymd');
        }

        // Preceding invoice references (BG-3)
        foreach ($set->invoiceReferencedDocument as $reference) {
            $data->precedingInvoices[] = [
                'id'        => $reference->issuerAssignedID->value ?? '',
                'issueDate' => isset($reference->formattedIssueDateTime->dateTimeString)
                    ? $this->parseDate($reference->formattedIssueDateTime->dateTimeString->value, 'Ymd')
                    : null,
            ];
        }

        // Notes
        foreach ($doc->notes as $note) {
            $data->notes[] = [
                'content'     => $note->content ?? '',
                'subjectCode' => $note->subjectCode ?? null,
                'contentCode' => $note->contentCode ?? null,
            ];
        }

        // Seller
        if (isset($agr->sellerTradeParty)) {
            $data->seller = $this->extractParty($agr->sellerTradeParty);
        }

        // Buyer
        if (isset($agr->buyerTradeParty)) {
            $data->buyer          = $this->extractParty($agr->buyerTradeParty);
            $data->buyerReference = $agr->buyerReference ?? null;
        }
        $data->buyerOrderReference = $agr->buyerOrderReferencedDocument?->issuerAssignedID->value ?? null;

        // Electronic addresses (BT-34/BT-49): easybill's TradeParty model does not map
        // URIUniversalCommunication, so read them straight from the XML.
        $this->fillElectronicAddresses($xml, $data);

        // Line items
        foreach ($tx->lineItems as $lineItem) {
            $item = new InvoiceItemData();

            $product = $lineItem->specifiedTradeProduct ?? null;
            if ($product) {
                $item->name        = $product->name ?? '';
                $item->description = $product->description ?? null;
                if (isset($product->globalID)) {
                    $item->globalID     = $product->globalID->value ?? null;
                    $item->globalIDCode = $product->globalID->schemeID ?? null;
                }
            }

            $netPrice = $lineItem->tradeAgreement->netPrice ?? null;
            if ($netPrice) {
                $item->price = (float) ($netPrice->chargeAmount->value ?? 0);
            }

            $billedQty = $lineItem->delivery->billedQuantity ?? null;
            if ($billedQty) {
                $item->quantity = (float) ($billedQty->value ?? 1);
                $item->unit     = $billedQty->unitCode ?? 'H87';
            }

            $taxes = $lineItem->specifiedLineTradeSettlement->tradeTax ?? [];
            if (!empty($taxes)) {
                $tax = $taxes[0];
                $item->taxRate = (float) ($tax->rateApplicablePercent ?? 0);
                if ($item->taxRate === 0.0 && isset($tax->categoryCode)) {
                    $data->taxExemptionCategory = $tax->categoryCode;
                    $data->taxExemptionReason   = $tax->exemptionReason ?? null;
                }
            }

            $monetarySummation = $lineItem->specifiedLineTradeSettlement->monetarySummation ?? null;
            if ($monetarySummation && isset($monetarySummation->totalAmount)) {
                $item->lineTotal = (float) $monetarySummation->totalAmount->value;
            }

            $data->items[] = $item;
        }

        // Tax breakdown (document level)
        foreach ($set->tradeTaxes as $tax) {
            $tb = new TaxBreakdownData();
            $tb->rate             = (float) ($tax->rateApplicablePercent ?? 0);
            $tb->basisAmount      = isset($tax->basisAmount) ? (float) $tax->basisAmount->value : null;
            $tb->calculatedAmount = isset($tax->calculatedAmount) ? (float) $tax->calculatedAmount->value : null;
            $tb->categoryCode     = $tax->categoryCode ?? null;
            $tb->exemptionReason  = $tax->exemptionReason ?? null;
            $data->taxBreakdown[] = $tb;
        }

        // Document level allowances (BG-20), charges are skipped
        foreach ($set->specifiedTradeAllowanceCharge as $charge) {
            if ($charge->indicator?->indicator !== false) {
                continue;
            }
            $allowance               = new AllowanceData();
            $allowance->amount       = (float) $charge->actualAmount->value;
            $allowance->reason       = $charge->reason;
            $allowance->reasonCode   = $charge->reasonCode;
            $tax                     = $charge->tradeTax[0] ?? null;
            $allowance->taxRate      = isset($tax->rateApplicablePercent) ? (float) $tax->rateApplicablePercent : null;
            $allowance->categoryCode = $tax->categoryCode ?? null;
            $data->allowances[]      = $allowance;
        }

        // Totals
        $summation = $set->specifiedTradeSettlementHeaderMonetarySummation ?? null;
        if ($summation) {
            $data->lineTotal      = isset($summation->lineTotalAmount) ? (float) $summation->lineTotalAmount->value : null;
            $data->allowanceTotal = isset($summation->allowanceTotalAmount) ? (float) $summation->allowanceTotalAmount->value : null;
            $data->taxBasisTotal = isset($summation->taxBasisTotalAmount[0]) ? (float) $summation->taxBasisTotalAmount[0]->value : null;
            $data->taxTotal      = isset($summation->taxTotalAmount[0]) ? (float) $summation->taxTotalAmount[0]->value : null;
            $data->grandTotal    = isset($summation->grandTotalAmount[0]) ? (float) $summation->grandTotalAmount[0]->value : null;
            $data->prepaidAmount = isset($summation->totalPrepaidAmount) ? (float) $summation->totalPrepaidAmount->value : null;
            $data->duePayable    = isset($summation->duePayableAmount) ? (float) $summation->duePayableAmount->value : null;
        }

        // Payment means
        foreach ($set->specifiedTradeSettlementPaymentMeans as $mean) {
            $pm           = new PaymentMeanData();
            $pm->typeCode = $mean->typeCode ?? '';
            $account      = $mean->payeePartyCreditorFinancialAccount ?? null;
            if ($account) {
                $pm->iban        = $account->ibanId->value ?? null;
                $pm->accountName = $account->AccountName ?? null;
            }
            $institution = $mean->payeeSpecifiedCreditorFinancialInstitution ?? null;
            if ($institution) {
                $pm->bic = $institution->bicId->value ?? null;
            }
            $data->paymentMeans[] = $pm;
        }

        // Payment terms
        $terms = $set->specifiedTradePaymentTerms[0] ?? null;
        if ($terms) {
            $data->paymentTermsDescription = $terms->description ?? null;
            if (isset($terms->dueDateDateTime->dateTimeString)) {
                $data->dueDate = $this->parseDate($terms->dueDateDateTime->dateTimeString->value, 'Ymd');
            }
        }

        return $data;
    }

    /**
     * BT-34 (seller) / BT-49 (buyer): URIUniversalCommunication/URIID on each
     * trade party. Namespace-agnostic XPath so FacturX, ZUGFeRD and XRechnung
     * prefixes all match.
     */
    private function fillElectronicAddresses(string $xml, InvoiceData $data): void
    {
        $doc = $this->getDoc($xml);
        if (!$doc) {
            return;
        }
        $xpath = new \DOMXPath($doc);

        foreach (['Seller' => $data->seller, 'Buyer' => $data->buyer] as $role => $party) {
            if (!$party) {
                continue;
            }
            $nodes = $xpath->query(
                "//*[local-name()='{$role}TradeParty']/*[local-name()='URIUniversalCommunication']/*[local-name()='URIID']"
            );
            if ($nodes && $nodes->length > 0) {
                $node = $nodes->item(0);
                $party->electronicAddress       = trim($node->textContent) ?: null;
                $party->electronicAddressScheme = $node instanceof \DOMElement
                    ? ($node->getAttribute('schemeID') ?: null)
                    : null;
            }
        }
    }

    private function extractParty(TradeParty $party): PartyData
    {
        $p       = new PartyData();
        $p->name = $party->name ?? '';

        if (isset($party->specifiedLegalOrganization)) {
            $lo        = $party->specifiedLegalOrganization;
            $p->id     = $lo->id->value ?? null;
            $p->idType = $lo->id->schemeID ?? null;
            $p->tradingName = $lo->tradingBusinessName ?? null;
        }

        if (isset($party->postalTradeAddress)) {
            $p->address = $this->extractAddress($party->postalTradeAddress);
        }

        if (!empty($party->definedTradeContact)) {
            $p->contact = $this->extractContact($party->definedTradeContact[0]);
        }

        foreach ($party->taxRegistrations as $reg) {
            $id = $reg->id->value ?? '';
            if ($id !== '') {
                $p->taxRegistrations[] = [
                    'id'       => $id,
                    'schemeID' => $reg->id->schemeID ?? '',
                ];
            }
        }

        foreach ($party->globalID as $gid) {
            $id = $gid->value ?? '';
            if ($id !== '') {
                $p->identifiers[] = [
                    'id'     => $id,
                    'idType' => $gid->schemeID ?? '',
                ];
            }
        }

        return $p;
    }

    private function extractAddress(TradeAddress $addr): AddressData
    {
        return $this->buildAddress(
            $addr->lineOne ?? null,
            $addr->postcodeCode ?? null,
            $addr->cityName ?? null,
            $addr->countryID ?? null,
            $addr->lineTwo ?? null,
            $addr->lineThree ?? null,
            $addr->countrySubDivisionName ?? null,
        );
    }

    private function extractContact(TradeContact $contact): ?ContactData
    {
        return $this->buildContact(
            $contact->personName ?? null,
            $contact->telephoneUniversalCommunication->completeNumber ?? null,
            $contact->emailURIUniversalCommunication->uriid->value ?? null,
            $contact->departmentName ?? null,
        );
    }
}
