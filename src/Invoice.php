<?php

namespace DigitalInvoice;

// Factur-X Xml Stuff
use Easybill\ZUGFeRD211\Builder;
use Easybill\ZUGFeRD211\Model\Amount;
use Easybill\ZUGFeRD211\Model\CreditorFinancialAccount;
use Easybill\ZUGFeRD211\Model\CreditorFinancialInstitution;
use Easybill\ZUGFeRD211\Model\CrossIndustryInvoice;
use Easybill\ZUGFeRD211\Model\DateTime;
use Easybill\ZUGFeRD211\Model\DateTimeString;
use Easybill\ZUGFeRD211\Model\DocumentContextParameter;
use Easybill\ZUGFeRD211\Model\DocumentLineDocument;
use Easybill\ZUGFeRD211\Model\ExchangedDocument;
use Easybill\ZUGFeRD211\Model\ExchangedDocumentContext;
use Easybill\ZUGFeRD211\Model\HeaderTradeAgreement;
use Easybill\ZUGFeRD211\Model\HeaderTradeDelivery;
use Easybill\ZUGFeRD211\Model\HeaderTradeSettlement;
use Easybill\ZUGFeRD211\Model\Id;
use Easybill\ZUGFeRD211\Model\LineTradeAgreement;
use Easybill\ZUGFeRD211\Model\LineTradeDelivery;
use Easybill\ZUGFeRD211\Model\LineTradeSettlement;
use Easybill\ZUGFeRD211\Model\LegalOrganization;
use Easybill\ZUGFeRD211\Model\Note;
use Easybill\ZUGFeRD211\Model\Quantity;
use Easybill\ZUGFeRD211\Model\FormattedDateTime;
use Easybill\ZUGFeRD211\Model\Indicator;
use Easybill\ZUGFeRD211\Model\ReferencedDocument;
use Easybill\ZUGFeRD211\Model\ProcuringProject;
use Easybill\ZUGFeRD211\Model\SupplyChainEvent;
use Easybill\ZUGFeRD211\Model\SupplyChainTradeLineItem;
use Easybill\ZUGFeRD211\Model\SupplyChainTradeTransaction;
use Easybill\ZUGFeRD211\Model\TaxRegistration;
use Easybill\ZUGFeRD211\Model\TradeAddress;
use Easybill\ZUGFeRD211\Model\TradeAllowanceCharge;
use Easybill\ZUGFeRD211\Model\TradeContact;
use Easybill\ZUGFeRD211\Model\TradeCountry;
use Easybill\ZUGFeRD211\Model\TradeParty;
use Easybill\ZUGFeRD211\Model\TradePaymentTerms;
use Easybill\ZUGFeRD211\Model\TradePrice;
use Easybill\ZUGFeRD211\Model\TradeProduct;
use Easybill\ZUGFeRD211\Model\TradeSettlementHeaderMonetarySummation;
use Easybill\ZUGFeRD211\Model\TradeSettlementLineMonetarySummation;
use Easybill\ZUGFeRD211\Model\TradeSettlementPaymentMeans;
use Easybill\ZUGFeRD211\Model\TradeTax;
use Easybill\ZUGFeRD211\Model\UniversalCommunication;


use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\XmlElement;
use JMS\Serializer\Annotation\XmlList;
use JMS\Serializer\Annotation\XmlValue;


use Easybill\ZUGFeRD211\Validator;



use Atgp\FacturX\Facturx;

// Factur-X conversion functions
require __DIR__ . '/Types.php';



class Invoice
{
    public const XRECHNUNG = 'urn:cen.eu:en16931:2017#compliant#urn:xoev-de:kosit:standard:xrechnung_1.2';
    public const BASIC = 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic';


    protected $profile;
    protected CrossIndustryInvoice $invoice;
    protected $taxes = [];

    public function __construct(
        string $invoiceId,
        \DateTime $issueDateTime,
        \DateTime $deliveryDate = null,
        string|CurrencyCode $currency = CurrencyCode::EURO,
        $profile = self::BASIC,
        string|InvoiceTypeCode $invoiceType = InvoiceTypeCode::COMMERCIAL_INVOICE
    ) {
        if(is_string($currency)) {
            try {
                $currency = CurrencyCode::from($currency);
            } catch (\ValueError $e) {
                throw new \Exception("$currency is not a valid Currency");
            }
        }
        if(is_string($invoiceType)) {
            try {
                $invoiceType = InvoiceTypeCode::from($invoiceType);
            } catch (\ValueError $e) {
                throw new \Exception("$invoiceType is not a valid Invoice Type");
            }
        }


        $this->invoice = new CrossIndustryInvoice();
        $this->invoice->exchangedDocumentContext = new ExchangedDocumentContext();
        $this->invoice->exchangedDocumentContext->documentContextParameter = new DocumentContextParameter();
        $this->invoice->exchangedDocumentContext->documentContextParameter->id = $this->profile = $profile ;

        $this->invoice->exchangedDocument = new ExchangedDocument();
        $this->invoice->exchangedDocument->id = $invoiceId;
        $this->invoice->exchangedDocument->issueDateTime = self::convertDate($issueDateTime);
        $this->invoice->exchangedDocument->typeCode = $invoiceType->value ;

        $this->invoice->supplyChainTradeTransaction = new SupplyChainTradeTransaction();

        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement = new HeaderTradeAgreement();

        //$this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->specifiedProcuringProject = ProcuringProject::create('1234', 'Projekt');

        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery = new HeaderTradeDelivery();

        if ($deliveryDate) {
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery->chainEvent = new SupplyChainEvent();
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery->chainEvent->date =  self::convertDate($deliveryDate);
        }

        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement = new HeaderTradeSettlement();

        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->currency = $currency->value ;

    }

    protected function calculateTotals()
    {
        $totalBasis = 0;
        $tax = 0;
        $grand = 0;
        foreach ($this->taxes as $rate => $items) {
            $sum = array_sum($items);
            $tradeTax = new TradeTax();
            $tradeTax->typeCode = TaxTypeCodeContent::VAT->value;
            $tradeTax->categoryCode = VatCategory::STANDARD->value;
            $totalBasis += $sum;
            $tradeTax->basisAmount = Amount::create(number_format($sum));
            $tradeTax->rateApplicablePercent = number_format($rate) ;
            $tax += $calculated = $sum * $rate / 100;
            $tradeTax->calculatedAmount = Amount::create(number_format($calculated));
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->tradeTaxes[] =$tradeTax;
        }

        $grand = $totalBasis + $tax  ;

        $summation = new TradeSettlementHeaderMonetarySummation();
        $summation->lineTotalAmount = Amount::create(number_format($totalBasis));
        //$summation->chargeTotalAmount = Amount::create('0.00');
        //$summation->allowanceTotalAmount = Amount::create('0.00');
        $summation->taxBasisTotalAmount[] = Amount::create(number_format($totalBasis));
        $summation->taxTotalAmount[] = Amount::create(number_format($tax));
        $summation->grandTotalAmount[] = Amount::create(number_format($grand));
        //$summation->totalPrepaidAmount = Amount::create('0.00');
        $summation->duePayableAmount = Amount::create(number_format($grand));
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->specifiedTradeSettlementHeaderMonetarySummation = $summation;

    }

    public function getXml()
    {
        // calculate tradeTaxes
        $this->calculateTotals();
        return Builder::create()->transform($this->invoice);
    }

    protected static function convertDate(\DateTime $date)
    {
        return DateTime::create(102, $date->format('Ymd'));
    }




    public function setTradeDelivery()
    {

        // $invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery->deliveryNoteReferencedDocument = ReferencedDocument::create('123456');
        // $invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery->deliveryNoteReferencedDocument->formattedIssueDateTime = FormattedDateTime::create('102', '20180305');
    }



    public function setBuyer(string $buyerReference, string $id, string $name)
    {
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->buyerReference = $buyerReference;
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->buyerTradeParty = $buyerTradeParty = new TradeParty();
        $buyerTradeParty->id = Id::create($id);
        $buyerTradeParty->name =  $name ;
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery->shipToTradeParty = $buyerTradeParty;
        return $this;
    }

    public function createAddress(string $postCode, string $city, string $countryCode, string $lineOne, ?string $lineTwo = null, ?string $lineThree = null)
    {
        $address = new TradeAddress();
        $address->postcode =  $postCode;
        $address->lineOne = $lineOne ;
        $address->city = $city;
        $address->countryCode = $countryCode;
        $address->lineTwo = $lineTwo ;
        $address->lineThree = $lineThree;
        return $address;
    }


    public function setBuyerAddress(string $lineOne, string $postCode, string $city, string $countryCode, ?string $lineTwo = null, ?string $lineThree = null)
    {
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->buyerTradeParty->postalTradeAddress = $this->createAddress($postCode, $city, $countryCode, $lineOne, $lineTwo, $lineThree);
        return $this;
    }

    public function setSeller(string $id, string $idType, string $name, ?string $personName = null, ?string $departmentName = null, ?string $telephone = null, ?string $email = null)
    {
        try {
            $idType = InternationalCodeDesignator::from($idType);
        } catch (\ValueError $e) {
            throw new \Exception("$idType is an Invalide InternationalCodeDesignator");
        }
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->sellerTradeParty = $sellerTradeParty = new TradeParty();
        //$sellerTradeParty->globalID[] = Id::create($id, $idType->value);
        $sellerTradeParty->legalOrganization = LegalOrganization::create($id, $idType->value, $name);

        $sellerTradeParty->name =  $name;

        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->payeeTradeParty = $sellerTradeParty;

        return $this;
    }

    public function setSellerTaxRegistration(string $id, string $schemeID)
    {
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->sellerTradeParty->taxRegistrations[] = TaxRegistration::create($id, $schemeID);
    }

    public function setSellerAddress(string $lineOne, string $postCode, string $city, string $countryCode, ?string $lineTwo = null, ?string $lineThree = null)
    {
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->sellerTradeParty->postalTradeAddress = $this->createAddress($postCode, $city, $countryCode, $lineOne, $lineTwo, $lineThree);
        return $this;
    }



    public function addItem(string $name, float $price, float $taxRatePercent, float  $quantity = 1, string $unit = 'H87', ?string $globalID = null, string $globalIDCode = '0160')
    {
        try {
            $unit = UnitOfMeasurement::from($unit);
        } catch (\ValueError $e) {
            throw new \Exception("$unit is not a valide Unit of Unit Of Measurement");
        }

        $item = new SupplyChainTradeLineItem();
        $lineNumber = count($this->invoice->supplyChainTradeTransaction->lineItems) + 1;

        $item->associatedDocumentLineDocument = DocumentLineDocument::create((string) $lineNumber);

        $item->specifiedTradeProduct = new TradeProduct();
        $item->specifiedTradeProduct->name = $name;
        // if ($sellerAssignedID) {
        //     $item->specifiedTradeProduct->sellerAssignedID = $sellerAssignedID;
        // }
        if ($globalID) {
            $item->specifiedTradeProduct->globalID = Id::create($globalID, $globalIDCode);
        }

        $item->tradeAgreement = new LineTradeAgreement();

        $item->tradeAgreement->netPrice = TradePrice::create(number_format($price));
        //$item->tradeAgreement->grossPrice = TradePrice::create(number_format( $price) );

        $item->delivery = new LineTradeDelivery();
        $item->delivery->billedQuantity = Quantity::create(number_format($quantity), $unit->value);

        $item->specifiedLineTradeSettlement = new LineTradeSettlement();
        $item->specifiedLineTradeSettlement->tradeTax[] = $itemtax = new TradeTax();
        $itemtax->typeCode = TaxTypeCodeContent::VAT->value;
        $itemtax->categoryCode = VatCategory::STANDARD->value ;
        $itemtax->rateApplicablePercent =  number_format($taxRatePercent);


        $totalLineBasis = $price * $quantity;


        $item->specifiedLineTradeSettlement->monetarySummation = TradeSettlementLineMonetarySummation::create(number_format($totalLineBasis));

        $this->invoice->supplyChainTradeTransaction->lineItems[] = $item;

        // To be able to calc easily the invoice totals
        if (!isset($this->taxes[$itemtax->rateApplicablePercent])) {
            $this->taxes[$itemtax->rateApplicablePercent] = [];
        }
        $this->taxes[$itemtax->rateApplicablePercent][] = $totalLineBasis;

    }

    public function addNote(string $content, ?string $subjectCode = null, ?string $contentCode = null)
    {
        $this->invoice->exchangedDocument->notes[] = Note::create($content, $subjectCode, $contentCode);
    }

    public function validate(string $xml)
    {
        switch ($this->profile) {
            case self::BASIC: $against =  Validator::SCHEMA_BASIC;
                break;
            case self::XRECHNUNG: $against =  Validator::SCHEMA_EXTENDED;
                break;
            default: $against =  Validator::SCHEMA_MINIMUM;
        }
        return (new Validator())->validateAgainstXsd($xml, $against);
    }

    public function getPdf($pdf)
    {
        $factureX = new Facturx();
        return $factureX->generateFacturxFromFiles($pdf, $this->getXml());
    }

}
