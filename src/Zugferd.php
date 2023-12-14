<?php

namespace DigitalInvoice;


use Easybill\ZUGFeRD\Builder;
use Easybill\ZUGFeRD\Model\Address;
//use Easybill\ZUGFeRD\Model\AllowanceCharge;
use Easybill\ZUGFeRD\Model\Date;
use Easybill\ZUGFeRD\Model\Document;
use Easybill\ZUGFeRD\Model\Note;
use Easybill\ZUGFeRD\Model\Trade\Amount;
use Easybill\ZUGFeRD\Model\Trade\Agreement;
use Easybill\ZUGFeRD\Model\Trade\BillingPeriod;
use Easybill\ZUGFeRD\Model\Trade\CreditorFinancialAccount;
use Easybill\ZUGFeRD\Model\Trade\CreditorFinancialInstitution;
use Easybill\ZUGFeRD\Model\Trade\Delivery;
use Easybill\ZUGFeRD\Model\Trade\Item\LineDocument;
use Easybill\ZUGFeRD\Model\Trade\Item\LineItem;
use Easybill\ZUGFeRD\Model\Trade\Item\Price;
use Easybill\ZUGFeRD\Model\Trade\Item\Product;
use Easybill\ZUGFeRD\Model\Trade\Item\Quantity;
use Easybill\ZUGFeRD\Model\Trade\Item\SpecifiedTradeAgreement;
use Easybill\ZUGFeRD\Model\Trade\Item\SpecifiedTradeDelivery;
use Easybill\ZUGFeRD\Model\Trade\Item\SpecifiedTradeMonetarySummation;
use Easybill\ZUGFeRD\Model\Trade\Item\SpecifiedTradeSettlement;
use Easybill\ZUGFeRD\Model\Trade\MonetarySummation;
use Easybill\ZUGFeRD\Model\Trade\PaymentMeans;
use Easybill\ZUGFeRD\Model\Trade\PaymentTerms;
use Easybill\ZUGFeRD\Model\Trade\ReferencedDocument;
use Easybill\ZUGFeRD\Model\Trade\Settlement;
use Easybill\ZUGFeRD\Model\Trade\Tax\TaxRegistration;
use Easybill\ZUGFeRD\Model\Trade\Tax\TradeTax;
use Easybill\ZUGFeRD\Model\Trade\Trade;
use Easybill\ZUGFeRD\Model\Trade\TradeParty;
use Easybill\ZUGFeRD\Model\Schema;
use Easybill\ZUGFeRD\Model\Trade\TradeCountry;
use Easybill\ZUGFeRD\Model\Trade\SpecifiedLogisticsServiceCharge;
use Easybill\ZUGFeRD\Model\Trade\TradeContact;
use Easybill\ZUGFeRD\Model\Trade\UniversalCommunication;
use Easybill\ZUGFeRD\SchemaValidator;





class Zugferd extends XmlGenerator {
    public const ZUGFERD_CONFORT = Document::TYPE_COMFORT;
    public const ZUGFERD_BASIC =  Document::TYPE_BASIC;
    public const ZUGFERD_EXTENDED =  Document::TYPE_EXTENDED;

    public const LEVEL_MINIMUM = 0;

    public $invoice;
    public $header;
    public Trade $trade;
    public Agreement $agreement ;
    public Delivery $delivery ;
    public Settlement $settlement ;


    public function validate(string $xml , $schematron) {
        try {
            libxml_use_internal_errors(true);
            libxml_clear_errors();
            $isValid = SchemaValidator::isValid($xml);
            if ($isValid) {
                return null;
            }
            return implode("\n", array_column(libxml_get_errors(), 'message'));
        } finally {
            libxml_use_internal_errors(false);
            libxml_clear_errors();
        }
    }
    public function initDocument( $invoiceId , \DateTime $issueDateTime,  $invoiceType, ?\DateTime $deliveryDate=null  ){

        $this->invoice = new Document($this->profile);
        $this->header = $this->invoice->getHeader();
        
        $this->header->setId($invoiceId)
            ->setDate(new Date($issueDateTime, 102)) ;

        $this->trade = $this->invoice->getTrade();
        $this->agreement = $this->trade->getAgreement();
        
        if ($deliveryDate == null ) {
            $deliveryDate = clone $issueDateTime;
        } else {
            $this->hasDelivery = false;
        }
        $this->delivery = new Delivery($deliveryDate->format(self::DATE_102), 102);
        $this->settlement = new Settlement('', $this->currency->value);
        $this->trade
            ->setSettlement( $this->settlement)
            ->setDelivery($this->delivery );
       
       
    }
    protected function getAmount(float $amount) : Amount {
        return new Amount(self::decimalFormat($amount) , $this->currency->value );
    }
    protected function calculateTotals()
    {
        if(count($this->taxLines)) {
            $totalBasis = 0;
            $tax = 0;
            foreach ($this->taxLines as $rate => $items) {
                $sum = array_sum($items);
                $tradeTax = new TradeTax();
                $tradeTax->setCode(TaxTypeCodeContent::VAT->value);
                $tradeTax->setCategory(VatCategory::STANDARD->value);
                $totalBasis += $sum;
                $tradeTax->setBasisAmount( $this->getAmount($sum));
                $tradeTax->setPercent(self::decimalFormat($rate))  ;
                $tax += $calculated = $sum * $rate / 100;
                $tradeTax->setCalculatedAmount(  $this->getAmount($calculated)  );

                $this->settlement->addTradeTax($tradeTax);
            }
        } else {

            if(!isset($this->totalBasis)) {
                throw new \Exception('You should call setPrice to set taxBasisTotal and taxTotal');
            }

            $totalBasis = $this->totalBasis ;
            $tax = $this->tax ;
        }

        $grand = $totalBasis + $tax  ;

        $summation = new MonetarySummation($totalBasis, 0.00, 0.00, $totalBasis, $tax , $grand, $this->currency->value);
        $summation->setDuePayableAmount( $this->getAmount( $grand));

        $this->settlement->setMonetarySummation($summation);


    }

    public function setSeller(string $id, InternationalCodeDesignator $idType,
     string $name, 
      $tradingName = null)
    {
        $this->seller = new TradeParty(
            $name,
            new Address(), // to be filled later
            [ ], // Tax registration to be filled later  
        );
        $this->seller->setId($id);
        $this->seller->setGlobalId(new Schema($idType->value,$id));
        $this->agreement->setSeller($this->seller);
   
    }

    public function setSellerContact( ?string $personName = null,  ?string $telephone = null, ?string $email = null, ?string $departmentName = null)
    {
        $this->seller->definedTradeContact = new TradeContact(
            $personName,
            $departmentName,
            $telephone ? new UniversalCommunication( $telephone ) : null,
            null, // no fax
            $email ? new UniversalCommunication(null,  $email) : null
        );

    }

    public function setSellerAddress(string $lineOne, string $postCode, string $city, string $countryCode, ?string $lineTwo = null, ?string $lineThree = null)
    {
        $this->seller->setAddress( $this->createAddress( $postCode,  $city,  $countryCode,  $lineOne,  $lineTwo ,  $lineThree )  );
    }

    public function setSellerTaxRegistration(string $id, string $schemeID)   {
        // todo
        $this->seller->addTaxRegistration(new TaxRegistration($schemeID,$id) );
    }

    public function setBuyer(string $buyerReference, string $name, string $id = null){
        $this->buyer = new TradeParty(
            $name,
            new Address(), // to be filled later
        );

        if($id) {
            $this->buyer->setId($id);
        }
        $this->agreement
            ->setBuyer($this->buyer)
            ->setBuyerReference($buyerReference);

        if ($this->hasDelivery){
            $this->delivery->setShipToTradeParty($this->buyer);
        }
    }
    public function getXml(){
        $this->calculateTotals();
        $builder = Builder::create();
        return $builder->getXML($this->invoice);

    }
    public function createAddress(string $postCode, string $city, string $countryCode, string $lineOne, ?string $lineTwo = null, ?string $lineThree = null){
        $address = new Address(); // to be filled later
       
        $address->setPostcode($postCode) ;
        $address->setLineOne($lineOne) ;
        $address->setCity($city);
        if ($lineThree && $lineTwo){
            $lineTwo .= " ". $lineThree;
        }
        $address->setLineTwo( $lineTwo ) ;
        $address->setCountryCode( $countryCode);
        return $address;
    }
    public function setBuyerAddress(string $lineOne, string $postCode, string $city, string $countryCode, ?string $lineTwo = null, ?string $lineThree = null){
        $this->buyer->setAddress( $this->createAddress( $postCode,  $city,  $countryCode,  $lineOne,  $lineTwo ,  $lineThree )  );
    }
    public function addItem(string $name, float $price, float $taxRatePercent, float  $quantity , UnitOfMeasurement $unit , ?string $globalID = null, string $globalIDCode = null): float
    {
        $lineNumber = count($this->items) + 1;
        $tradeAgreement = new SpecifiedTradeAgreement();
        $grossPrice = new Price( $price, $this->currency->value, false);
        $grossPrice->setQuantity(new Quantity($unit->value, 1));
        $tradeAgreement->setGrossPrice($grossPrice);
        
        $grossNetPrice = new Price($price,$this->currency->value, false);
        $grossNetPrice->setQuantity(new Quantity($unit->value, 1));
        $tradeAgreement->setNetPrice($grossNetPrice);

        $lineItemSettlement = new SpecifiedTradeSettlement();
        
        $lineItemTradeTax = new TradeTax();
        $lineItemTradeTax->setCode(TaxTypeCodeContent::VAT->value);
        $lineItemTradeTax->setCategory( VatCategory::STANDARD->value);
        $lineItemTradeTax->setPercent($taxRatePercent);
        $totalPrice = $price * $quantity;
        
        $lineItemSettlement
            ->setTradeTax($lineItemTradeTax)
            ->setMonetarySummation(new SpecifiedTradeMonetarySummation($totalPrice));
        
        $product = new Product($globalID, $name);

        $lineItem = new LineItem();
        $lineItem
            ->setTradeAgreement($tradeAgreement)
            ->setDelivery(new SpecifiedTradeDelivery(new Quantity($unit->value, $quantity)))
            ->setSettlement($lineItemSettlement)
            ->setProduct($product)
            ->setLineDocument(new LineDocument($lineNumber));
        $this->trade->addLineItem($lineItem);

        return $totalPrice;
    }
    public function addNote(string $content, ?string $subjectCode = null, ?string $contentCode = null)
    {
        $this->header->addNote( new Note($content, $subjectCode));
    }

    public function addPaymentMean(PaymentMeansCode $typeCode , ?string $ibanId = null,?string $accountName = null, ?string $bicId = null){

            $this->settlement = new Settlement( '' , $this->currency); // should we send a payment reference?
            $mean = new PaymentMeans();
            $mean->setCode( $typeCode->value ) ;
           
            // $mean->information = 'get info from type code??';
            $mean->setPayeeAccount(new CreditorFinancialAccount($ibanId, $accountName, null)) ;
            if ($bicId){
                $mean->setPayeeInstitution( new CreditorFinancialInstitution($bicId, null,null)) ;
            }
            $this->settlement->setPaymentMeans( $mean);

    }
}
