<?php

namespace DigitalInvoice;

use Easybill\ZUGFeRD211\Model\TradeParty;
use Easybill\ZUGFeRD211\Model\TradeContact;
use Easybill\ZUGFeRD211\Model\TradeAddress;
use Easybill\ZUGFeRD211\Reader;

/**
 * Reads any Cross Industry Invoice (CII) XML: FacturX, ZUGFeRD 2.1.1, XRechnung.
 *
 * Uses easybill/zugferd-php ZUGFeRD211 Reader which deserializes via JMS Serializer
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
        $data->currency    = $set->currency ?? 'EUR';

        if (isset($doc->issueDateTime->dateTimeString)) {
            $data->issueDate = $this->parseDate($doc->issueDateTime->dateTimeString->value, 'Ymd');
        }

        // Delivery date
        $delivery = $tx->applicableHeaderTradeDelivery ?? null;
        if ($delivery && isset($delivery->chainEvent->date->dateTimeString)) {
            $data->deliveryDate = $this->parseDate($delivery->chainEvent->date->dateTimeString->value, 'Ymd');
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

            $data->items[] = $item;
        }

        // Totals
        $summation = $set->specifiedTradeSettlementHeaderMonetarySummation ?? null;
        if ($summation) {
            $data->taxBasisTotal = isset($summation->taxBasisTotalAmount[0]) ? (float) $summation->taxBasisTotalAmount[0]->value : null;
            $data->taxTotal      = isset($summation->taxTotalAmount[0]) ? (float) $summation->taxTotalAmount[0]->value : null;
            $data->grandTotal    = isset($summation->grandTotalAmount[0]) ? (float) $summation->grandTotalAmount[0]->value : null;
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
            if (isset($terms->dueDate->dateTimeString)) {
                $data->dueDate = $this->parseDate($terms->dueDate->dateTimeString->value, 'Ymd');
            }
        }

        return $data;
    }

    private function extractParty(TradeParty $party): PartyData
    {
        $p       = new PartyData();
        $p->name = $party->name ?? '';

        if (isset($party->legalOrganization)) {
            $lo        = $party->legalOrganization;
            $p->id     = $lo->id->value ?? null;
            $p->idType = $lo->id->schemeID ?? null;
            $p->tradingName = $lo->tradingBusinessName ?? null;
        }

        if (isset($party->postalTradeAddress)) {
            $p->address = $this->extractAddress($party->postalTradeAddress);
        }

        if (isset($party->definedTradeContact)) {
            $p->contact = $this->extractContact($party->definedTradeContact);
        }

        foreach ($party->taxRegistrations as $reg) {
            $p->taxRegistrations[] = [
                'id'       => $reg->id->value ?? '',
                'schemeID' => $reg->id->schemeID ?? '',
            ];
        }

        foreach ($party->globalID as $gid) {
            $p->identifiers[] = [
                'id'     => $gid->value ?? '',
                'idType' => $gid->schemeID ?? '',
            ];
        }

        return $p;
    }

    private function extractAddress(TradeAddress $addr): AddressData
    {
        return $this->buildAddress(
            $addr->lineOne ?? null,
            $addr->postcode ?? null,
            $addr->city ?? null,
            $addr->countryCode ?? null,
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
