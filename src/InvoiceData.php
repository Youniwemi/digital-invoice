<?php

namespace DigitalInvoice;

require_once __DIR__ . '/Types.php';

/**
 * Address data extracted from an invoice party.
 */
class AddressData
{
    public ?string $lineOne = null;
    public ?string $lineTwo = null;
    public ?string $lineThree = null;
    public ?string $postCode = null;
    public ?string $city = null;
    public ?string $countryCode = null;
    public ?string $stateCode = null;
}

/**
 * Contact data extracted from an invoice party.
 */
class ContactData
{
    public ?string $name = null;
    public ?string $phone = null;
    public ?string $email = null;
    public ?string $department = null;
}

/**
 * Party (seller or buyer) data extracted from an invoice.
 */
class PartyData
{
    public string $name = '';
    public ?string $id = null;
    public ?string $idType = null;
    public ?string $tradingName = null;
    public ?AddressData $address = null;
    public ?ContactData $contact = null;

    /**
     * Electronic routing address (EN16931 BT-34/BT-49):
     * CII URIUniversalCommunication/URIID, UBL EndpointID.
     */
    public ?string $electronicAddress = null;
    public ?string $electronicAddressScheme = null;

    /** @var array<array{id: string, schemeID: string}> */
    public array $taxRegistrations = [];

    /** @var array<array{id: string, idType: string}> */
    public array $identifiers = [];
}

/**
 * A single invoice line item.
 */
class InvoiceItemData
{
    public string $name = '';
    public float $price = 0.0;
    public float $taxRate = 0.0;
    public float $quantity = 1.0;
    public string $unit = 'H87';
    public ?string $globalID = null;
    public ?string $globalIDCode = null;
    public ?string $description = null;
    public ?float $lineTotal = null;

    public function getCalculatedTotal(): float
    {
        return $this->lineTotal ?? round($this->price * $this->quantity, 2);
    }
}

/**
 * A single tax breakdown entry (one row per rate/category combination).
 */
class TaxBreakdownData
{
    public float $rate = 0.0;
    public ?float $basisAmount = null;
    public ?float $calculatedAmount = null;
    public ?string $categoryCode = null;
    public ?string $exemptionReason = null;
}

/**
 * Document level allowance (BG-20), e.g. a global discount.
 */
class AllowanceData
{
    public float $amount = 0.0;
    public ?float $taxRate = null;
    public ?string $categoryCode = null;
    public ?string $reason = null;
    public ?string $reasonCode = null;
}

/**
 * Supporting document (BG-24), e.g. a delivery note.
 */
class AttachmentData
{
    public string $id = '';
    /** BT-123, a code in France (BON_LIVRAISON, DOCUMENT_ANNEXE…) */
    public ?string $description = null;
    public ?string $filename = null;
    public ?string $mimeCode = null;
    /** Decoded file contents, null when the document is only referenced */
    public ?string $contents = null;
}

/**
 * Payment means extracted from an invoice.
 */
class PaymentMeanData
{
    public string $typeCode = '';
    public ?string $iban = null;
    public ?string $accountName = null;
    public ?string $bic = null;
}

/**
 * Normalized invoice data returned by InvoiceReader parsers.
 *
 * Field names and semantics mirror the Invoice setter API so that a parsed
 * invoice can be round-tripped into a new Invoice object if needed.
 */
class InvoiceData
{
    public string $invoiceId = '';
    public ?\DateTime $issueDate = null;
    public ?\DateTime $deliveryDate = null;
    public string $currency = 'EUR';

    /** Profile URN or class name identifying the format/level. */
    public string $profile = '';

    /** Numeric invoice type code (e.g. '380' = commercial invoice). */
    public string $invoiceType = '380';

    /** @var array<array{id: string, issueDate: ?\DateTime}> Preceding invoice references (BG-3), e.g. the invoice a credit note credits */
    public array $precedingInvoices = [];

    public ?PartyData $seller = null;
    public ?PartyData $buyer = null;
    public ?string $buyerReference = null;

    /** Purchase order reference (BT-13). */
    public ?string $buyerOrderReference = null;

    /** @var InvoiceItemData[] */
    public array $items = [];

    /** @var PaymentMeanData[] */
    public array $paymentMeans = [];

    public ?\DateTime $dueDate = null;
    public ?string $paymentTermsDescription = null;

    /** @var array<array{content: string, subjectCode: ?string, contentCode: ?string}> */
    public array $notes = [];

    /** @var TaxBreakdownData[] */
    public array $taxBreakdown = [];

    /** @var AllowanceData[] Document level allowances (BG-20) */
    public array $allowances = [];

    /** @var AttachmentData[] Supporting documents (BG-24) */
    public array $attachments = [];

    // Monetary totals
    public ?float $lineTotal = null;      // BT-106
    public ?float $allowanceTotal = null; // BT-107
    public ?float $taxBasisTotal = null;
    public ?float $taxTotal = null;
    public ?float $grandTotal = null;
    public ?float $prepaidAmount = null;
    public ?float $duePayable = null;

    // Tax exemption (when tax rate is 0)
    public ?string $taxExemptionCategory = null;
    public ?string $taxExemptionReason = null;

    public function isCreditNote(): bool
    {
        return InvoiceTypeCode::tryFrom($this->invoiceType)?->isCreditNote() ?? false;
    }
}
