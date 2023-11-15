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
use Milo\Schematron;

// Factur-X conversion functions
require __DIR__ . '/Types.php';



class Invoice
{
    public const MINIMUM = 'urn:factur-x.eu:1p0:minimum';
    public const BASIC_WL = 'urn:factur-x.eu:1p0:basicwl';
    public const BASIC = 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic';
    public const EN16931 = 'urn:cen.eu:en16931:2017';
    public const EXTENDED = 'urn:cen.eu:en16931:2017#conformant#urn:zugferd.de:2p1:extended';
    public const XRECHNUNG = 'urn:cen.eu:en16931:2017#compliant#urn:xoev-de:kosit:standard:xrechnung_1.2';

    public const LEVEL_MINIMUM = 0;
    public const LEVEL_BASIC_WL = 1;
    public const LEVELS=[
        self::MINIMUM => self::LEVEL_MINIMUM ,
        self::BASIC_WL =>  self::BASIC_WL ,
        // will define thos later
        self::BASIC =>  self::BASIC_WL ,
        self::EN16931 =>  self::BASIC_WL ,
        self::EXTENDED =>  self::BASIC_WL ,
        self::XRECHNUNG =>  self::BASIC_WL
    ];




    protected $profile;
    protected CrossIndustryInvoice $invoice;
    protected $lines = [];
    protected $hasDelivery = false ;
    protected $totalBasis = null;
    protected $tax = null;

    public function __construct(
        string $invoiceId,
        \DateTime $issueDateTime,
        \DateTime $deliveryDate = null,
        string|CurrencyCode $currency = CurrencyCode::EURO,
        $profile = self::MINIMUM,
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
            $this->hasDelivery = false;
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery->chainEvent = new SupplyChainEvent();
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery->chainEvent->date =  self::convertDate($deliveryDate);
        }

        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement = new HeaderTradeSettlement();

        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->currency = $currency->value ;

    }

    public function getProfileLevel()
    {
        return self::LEVELS[ $this->profile ];
    }

    protected static function decimalFormat(float|int $number)
    {
        return number_format($number, 2, '.', '');
    }

    public function setPrice(float $totalBasis, float $tax=0)
    {
        $this->totalBasis = $totalBasis;
        $this->tax = $tax;
    }

    protected function calculateTotals()
    {

        if(count($this->lines)) {
            $totalBasis = 0;
            $tax = 0;
            foreach ($this->lines as $rate => $items) {
                $sum = array_sum($items);
                $tradeTax = new TradeTax();
                $tradeTax->typeCode = TaxTypeCodeContent::VAT->value;
                $tradeTax->categoryCode = VatCategory::STANDARD->value;
                $totalBasis += $sum;
                $tradeTax->basisAmount = Amount::create(self::decimalFormat($sum));
                $tradeTax->rateApplicablePercent = self::decimalFormat($rate) ;
                $tax += $calculated = $sum * $rate / 100;
                $tradeTax->calculatedAmount = Amount::create(self::decimalFormat($calculated));
                $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->tradeTaxes[] =$tradeTax;
            }
        } else {
            if (in_array($this->profile, [self::BASIC, self::EN16931, self::EXTENDED  ])) {
                throw new \Exception('You need to set invoice items using setItem');
            }
            if ($this->profile == self::MINIMUM || $this->profile ==  self::BASIC_WL) {
                if(!isset($this->totalBasis)) {
                    throw new \Exception('You should call setPrice to set taxBasisTotal and taxTotal');
                }
            }
            $totalBasis = $this->totalBasis ;
            $tax = $this->tax ;
        }

        $grand = $totalBasis + $tax  ;

        $summation = new TradeSettlementHeaderMonetarySummation();
        //$summation->chargeTotalAmount = Amount::create('0.00');
        //$summation->allowanceTotalAmount = Amount::create('0.00');
        $summation->taxBasisTotalAmount[] = Amount::create(self::decimalFormat($totalBasis));
        $summation->taxTotalAmount[] = Amount::create(self::decimalFormat($tax));
        $summation->grandTotalAmount[] = Amount::create(self::decimalFormat($grand));
        //$summation->totalPrepaidAmount = Amount::create('0.00');
        if ($this->profile != self::MINIMUM) {
            $summation->lineTotalAmount = Amount::create(self::decimalFormat($grand));
        }
        $summation->duePayableAmount = Amount::create(self::decimalFormat($grand));
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


    public function setBuyer(string $buyerReference, string $name, string $id=null)
    {
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->buyerReference = $buyerReference;
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->buyerTradeParty = $buyerTradeParty = new TradeParty();
        if ($this->getProfileLevel() > self::LEVEL_MINIMUM && $id) {
            $buyerTradeParty->id = Id::create($id);
        }
        $buyerTradeParty->name =  $name ;
        if ($this->hasDelivery) {
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery->shipToTradeParty = $buyerTradeParty;
        }
        return $this;
    }

    public function createAddress(string $postCode, string $city, string $countryCode, string $lineOne, ?string $lineTwo = null, ?string $lineThree = null)
    {
        $address = new TradeAddress();
        if ($this->getProfileLevel() > self::LEVEL_MINIMUM) {
            $address->postcode =  $postCode;
            $address->lineOne = $lineOne ;
            $address->city = $city;
            $address->lineTwo = $lineTwo ;
            $address->lineThree = $lineThree;
        }
        $address->countryCode = $countryCode;
        return $address;
    }


    public function setBuyerAddress(string $lineOne, string $postCode, string $city, string $countryCode, ?string $lineTwo = null, ?string $lineThree = null)
    {
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->buyerTradeParty->postalTradeAddress = $this->createAddress($postCode, $city, $countryCode, $lineOne, $lineTwo, $lineThree);
        return $this;
    }

    public function setSeller(string $id, string $idType, string $name, ?string $personName = null, ?string $departmentName = null, ?string $telephone = null, ?string $email = null, $tradingName = null)
    {
        try {
            $idType = InternationalCodeDesignator::from($idType);
        } catch (\ValueError $e) {
            throw new \Exception("$idType is an Invalide InternationalCodeDesignator");
        }
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->sellerTradeParty = $sellerTradeParty = new TradeParty();
        //$sellerTradeParty->globalID[] = Id::create($id, $idType->value);
        $sellerTradeParty->legalOrganization = LegalOrganization::create($id, $idType->value, $tradingName);

        $sellerTradeParty->name =  $name;

        // Pas de payeeTradeParty dans le minimum
        if ($this->profile != self::MINIMUM) {
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->payeeTradeParty = $sellerTradeParty;

        }
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

        $item->tradeAgreement->netPrice = TradePrice::create(self::decimalFormat($price));
        //$item->tradeAgreement->grossPrice = TradePrice::create(number_format( $price) );

        $item->delivery = new LineTradeDelivery();
        $item->delivery->billedQuantity = Quantity::create(number_format($quantity), $unit->value);

        $item->specifiedLineTradeSettlement = new LineTradeSettlement();
        $item->specifiedLineTradeSettlement->tradeTax[] = $itemtax = new TradeTax();
        $itemtax->typeCode = TaxTypeCodeContent::VAT->value;
        $itemtax->categoryCode = VatCategory::STANDARD->value ;
        $itemtax->rateApplicablePercent =  self::decimalFormat($taxRatePercent);


        $totalLineBasis = $price * $quantity;


        $item->specifiedLineTradeSettlement->monetarySummation = TradeSettlementLineMonetarySummation::create(self::decimalFormat($totalLineBasis));

        $this->invoice->supplyChainTradeTransaction->lineItems[] = $item;

        // To be able to calc easily the invoice totals
        if (!isset($this->lines[$itemtax->rateApplicablePercent])) {
            $this->lines[$itemtax->rateApplicablePercent] = [];
        }
        $this->lines[$itemtax->rateApplicablePercent][] = $totalLineBasis;

    }

    public function addNote(string $content, ?string $subjectCode = null, ?string $contentCode = null)
    {
        $this->invoice->exchangedDocument->notes[] = Note::create($content, $subjectCode, $contentCode);
    }

    public function validate(string $xml, $schematron = false)
    {
        switch ($this->profile) {
            case self::MINIMUM: $against =  Validator::SCHEMA_MINIMUM;
                break;
            case self::BASIC: $against =  Validator::SCHEMA_BASIC;
                break;
            case self::BASIC_WL: $against =  Validator::SCHEMA_BASIC_WL;
                break;
            case self::EN16931: $against =  Validator::SCHEMA_EN16931;
                break;
            case self::EXTENDED:
            case self::XRECHNUNG: $against =  Validator::SCHEMA_EXTENDED;
                break;
            default: $against =  Validator::SCHEMA_MINIMUM;
        }
        if ($schematron) {
            $against = str_replace([
                '.xsd',
                'FACTUR-X'
            ], [
                '.sch',
                'Schematron/FACTUR-X'
            ], $against);
        }

        if ($schematron) {
            // avoid deprecation milo/schematron is not fully php8.2 compatible, but gets the job done
            $schematron = @new Schematron();
            $schematron->load($against);
            $document = new \DOMDocument();
            $document->loadXml($xml);
            return @$schematron->validate($document, Schematron::RESULT_SIMPLE);
        }
        return (new Validator())->validateAgainstXsd($xml, $against);
    }


    public function getPdf($pdf)
    {
        $factureX = new Facturx();
        return $factureX->generateFacturxFromFiles($pdf, $this->getXml());
    }

}
