<?php

namespace DigitalInvoice;

use Easybill\ZUGFeRD\Model\Trade\TradeParty;
use Easybill\ZUGFeRD\Reader;

/**
 * Reads ZUGFeRD 1.0 XML (CrossIndustryDocument format).
 *
 * Uses easybill/zugferd-php ZUGFeRD 1.0 Reader which deserializes via JMS Serializer
 * into the Document object graph used by Zugferd.php for writing.
 */
class ZugferdParser extends XmlParser
{
    public function parse(string $xml): InvoiceData
    {
        $doc  = Reader::create()->getDocument($xml);
        $data = new InvoiceData();

        $header     = $doc->getHeader();
        $trade      = $doc->getTrade();
        $agreement  = $trade->getAgreement();
        $settlement = $trade->getSettlement();

        // Header
        $data->invoiceId   = (string) $header->getId();
        $data->invoiceType = (string) $header->getTypeCode();
        $data->currency    = (string) $settlement->getCurrency();
        $data->profile     = Zugferd::ZUGFERD_CONFORT; // ZUGFeRD 1.0 has no profile URN; use CONFORT as default

        $headerDate = $header->getDate();
        if ($headerDate) {
            $raw = $headerDate->getDate();
            $data->issueDate = ($raw instanceof \DateTime) ? $raw : $this->parseDate((string) $raw, 'Ymd');
        }

        // Notes
        foreach ((array) $header->getNotes() as $note) {
            $data->notes[] = [
                'content'     => (string) $note->getContent(),
                'subjectCode' => $note->getSubjectCode() ? (string) $note->getSubjectCode() : null,
                'contentCode' => null,
            ];
        }

        // Seller
        $seller = $agreement->getSeller();
        if ($seller) {
            $data->seller = $this->extractParty($seller);
        }

        // Buyer
        $buyer = $agreement->getBuyer();
        if ($buyer) {
            $data->buyer = $this->extractParty($buyer);
            // getBuyerReference() has a bug in ZUGFeRD 1.0 lib (references $this->name)
            try {
                $ref = $agreement->getBuyerReference();
                $data->buyerReference = $ref ? (string) $ref : null;
            } catch (\Throwable $e) {
                $data->buyerReference = null;
            }
        }

        // Line items
        foreach ((array) $trade->getLineItems() as $lineItem) {
            $item    = new InvoiceItemData();
            $product = $lineItem->getProduct();
            if ($product) {
                $item->name        = (string) $product->getName();
                $item->description = $product->getDescription() ? (string) $product->getDescription() : null;
                $item->globalID    = $product->getSellerAssignedID() ? (string) $product->getSellerAssignedID() : null;
            }

            $netPrice = $lineItem->getTradeAgreement()->getNetPrice() ?? null;
            if ($netPrice && $netPrice->getAmount()) {
                $item->price = (float) $netPrice->getAmount()->getValue();
            }

            $delivery = $lineItem->getDelivery();
            if ($delivery) {
                $qty          = $delivery->getBilledQuantity();
                $item->quantity = $qty ? (float) $qty->getValue() : 1.0;
                $item->unit   = $qty ? (string) $qty->getUnitCode() : 'H87';
            }

            $itemSettlement = $lineItem->getSettlement();
            if ($itemSettlement) {
                $tax = $itemSettlement->getTradeTax();
                if ($tax) {
                    $item->taxRate = (float) $tax->getPercent();
                    if ($item->taxRate === 0.0 && $tax->getCategory()) {
                        $data->taxExemptionCategory = (string) $tax->getCategory();
                        $data->taxExemptionReason   = $tax->getExemptionReason() ? (string) $tax->getExemptionReason() : null;
                    }
                }
            }

            $data->items[] = $item;
        }

        // Totals (MonetarySummation fields are Amount objects with ->getValue())
        $summation = $settlement->getMonetarySummation();
        if ($summation) {
            $data->taxBasisTotal = $summation->getTaxBasisTotal() !== null ? (float) $summation->getTaxBasisTotal()->getValue() : null;
            $data->taxTotal      = $summation->getTaxTotal() !== null ? (float) $summation->getTaxTotal()->getValue() : null;
            $data->grandTotal    = $summation->getGrandTotal() !== null ? (float) $summation->getGrandTotal()->getValue() : null;
            $data->duePayable    = $summation->getDuePayableAmount() !== null ? (float) $summation->getDuePayableAmount()->getValue() : null;
        }

        // Payment means
        $means = $settlement->getPaymentMeans();
        if ($means) {
            $pm           = new PaymentMeanData();
            $pm->typeCode = $means->getCode() ? (string) $means->getCode() : '';
            $account      = $means->getPayeeAccount();
            if ($account) {
                $pm->iban        = $account->getIban() ? (string) $account->getIban() : null;
                $pm->accountName = $account->getAccountName() ? (string) $account->getAccountName() : null;
            }
            $institution = $means->getPayeeInstitution();
            if ($institution) {
                $pm->bic = $institution->getBic() ? (string) $institution->getBic() : null;
            }
            $data->paymentMeans[] = $pm;
        }

        // Payment terms
        $terms = $settlement->getPaymentTerms();
        if ($terms) {
            $data->paymentTermsDescription = $terms->getDescription() ? (string) $terms->getDescription() : null;
            $dueDate = $terms->getDueDate();
            if ($dueDate) {
                $raw = $dueDate->getDate();
                $data->dueDate = ($raw instanceof \DateTime) ? $raw : $this->parseDate((string) $raw, 'Ymd');
            }
        }

        return $data;
    }

    private function extractParty(TradeParty $party): PartyData
    {
        $p       = new PartyData();
        $p->name = (string) $party->getName();
        $p->id   = $party->getId() ? (string) $party->getId() : null;

        $globalId = $party->getGlobalId();
        if ($globalId) {
            $p->idType = (string) $globalId->getSchemeID();
            // also add to identifiers list for completeness
            $p->identifiers[] = [
                'id'     => (string) $globalId->getValue(),
                'idType' => (string) $globalId->getSchemeID(),
            ];
        }

        $address = $party->getAddress();
        if ($address) {
            $p->address = $this->buildAddress(
                $address->getLineOne() ? (string) $address->getLineOne() : null,
                $address->getPostcode() ? (string) $address->getPostcode() : null,
                $address->getCity() ? (string) $address->getCity() : null,
                $address->getCountryCode() ? (string) $address->getCountryCode() : null,
                $address->getLineTwo() ? (string) $address->getLineTwo() : null,
            );
        }

        $contact = $party->getDefinedTradeContact();
        if ($contact) {
            $phone = null;
            $email = null;
            $tel   = $contact->getTelephoneUniversalCommunication();
            if ($tel) {
                $phone = $tel->getCompleteNumber() ? (string) $tel->getCompleteNumber() : null;
            }
            $emailComm = $contact->getEmailURIUniversalCommunication();
            if ($emailComm) {
                $email = $emailComm->getUriid() ? (string) $emailComm->getUriid() : null;
            }
            $p->contact = $this->buildContact(
                $contact->getPersonName() ? (string) $contact->getPersonName() : null,
                $phone,
                $email,
                $contact->getDepartmentName() ? (string) $contact->getDepartmentName() : null,
            );
        }

        foreach ((array) $party->getTaxRegistrations() as $reg) {
            $registration = $reg->getRegistration();
            if ($registration) {
                $p->taxRegistrations[] = [
                    'id'       => (string) $registration->getValue(),
                    'schemeID' => (string) $registration->getSchemeID(),
                ];
            }
        }

        return $p;
    }
}
