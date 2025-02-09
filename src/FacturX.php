<?php

namespace DigitalInvoice;

// Factur-X Xml Stuff
use Easybill\ZUGFeRD211\Builder;
use Easybill\ZUGFeRD211\Model\Amount;
use Easybill\ZUGFeRD211\Model\BinaryObject;
use Easybill\ZUGFeRD211\Model\CreditorFinancialAccount;
use Easybill\ZUGFeRD211\Model\CreditorFinancialInstitution;
use Easybill\ZUGFeRD211\Model\CrossIndustryInvoice;
use Easybill\ZUGFeRD211\Model\DateTime;
use Easybill\ZUGFeRD211\Model\DocumentContextParameter;
use Easybill\ZUGFeRD211\Model\DocumentLineDocument;
use Easybill\ZUGFeRD211\Model\ExchangedDocument;
use Easybill\ZUGFeRD211\Model\ExchangedDocumentContext;
use Easybill\ZUGFeRD211\Model\HeaderTradeAgreement;
use Easybill\ZUGFeRD211\Model\HeaderTradeDelivery;
use Easybill\ZUGFeRD211\Model\HeaderTradeSettlement;
use Easybill\ZUGFeRD211\Model\Id;
use Easybill\ZUGFeRD211\Model\LegalOrganization;
use Easybill\ZUGFeRD211\Model\LineTradeAgreement;
use Easybill\ZUGFeRD211\Model\LineTradeDelivery;
use Easybill\ZUGFeRD211\Model\LineTradeSettlement;
use Easybill\ZUGFeRD211\Model\Note;
use Easybill\ZUGFeRD211\Model\Quantity;
use Easybill\ZUGFeRD211\Model\ReferencedDocument;
use Easybill\ZUGFeRD211\Model\SupplyChainEvent;
use Easybill\ZUGFeRD211\Model\SupplyChainTradeLineItem;
use Easybill\ZUGFeRD211\Model\SupplyChainTradeTransaction;
use Easybill\ZUGFeRD211\Model\TaxRegistration;
use Easybill\ZUGFeRD211\Model\TradeAddress;
use Easybill\ZUGFeRD211\Model\TradeContact;
use Easybill\ZUGFeRD211\Model\TradeParty;
use Easybill\ZUGFeRD211\Model\TradePaymentTerms;
use Easybill\ZUGFeRD211\Model\TradePrice;
use Easybill\ZUGFeRD211\Model\TradeProduct;
use Easybill\ZUGFeRD211\Model\TradeSettlementHeaderMonetarySummation;
use Easybill\ZUGFeRD211\Model\TradeSettlementLineMonetarySummation;
use Easybill\ZUGFeRD211\Model\TradeSettlementPaymentMeans;
use Easybill\ZUGFeRD211\Model\TradeTax;
use Easybill\ZUGFeRD211\Model\UniversalCommunication;
use Easybill\ZUGFeRD211\Validator;
use Milo\Schematron;

class FacturX extends XmlGenerator
{
    public const MINIMUM = 'urn:factur-x.eu:1p0:minimum';
    public const BASIC_WL = 'urn:factur-x.eu:1p0:basicwl';
    public const BASIC = 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic';
    public const EN16931 = 'urn:cen.eu:en16931:2017';
    public const EXTENDED = 'urn:cen.eu:en16931:2017#conformant#urn:zugferd.de:2p1:extended';
    public const XRECHNUNG = 'urn:cen.eu:en16931:2017#compliant#urn:xoev-de:kosit:standard:xrechnung_1.2';

    public const LEVEL_MINIMUM = 0;
    public const LEVEL_BASIC_WL = 1;
    public const LEVEL_BASIC = 2;
    public const LEVEL_EN16931 = 3;

    public const LEVELS = [
        FacturX::MINIMUM => self::LEVEL_MINIMUM ,
        FacturX::BASIC_WL => self::LEVEL_BASIC_WL ,
        // will define thos later
        FacturX::BASIC => self::LEVEL_BASIC ,
        FacturX::EN16931 => self::LEVEL_EN16931 ,
        FacturX::EXTENDED => self::LEVEL_EN16931 ,
        FacturX::XRECHNUNG => self::LEVEL_EN16931,
    ];

    protected static function convertDate(\DateTime $date)
    {
        return DateTime::create(102, $date->format('Ymd'));
    }

    public function initDocument($invoiceId, \DateTime $issueDateTime, $invoiceType, ?\DateTime $deliveryDate = null)
    {
        $this->invoice = new CrossIndustryInvoice();

        $this->invoice->exchangedDocumentContext = new ExchangedDocumentContext();
        $this->invoice->exchangedDocumentContext->documentContextParameter = new DocumentContextParameter();
        $this->invoice->exchangedDocumentContext->documentContextParameter->id = $this->profile;

        $this->invoice->exchangedDocument = new ExchangedDocument();
        $this->invoice->exchangedDocument->id = $invoiceId;
        $this->invoice->exchangedDocument->issueDateTime = self::convertDate($issueDateTime);
        $this->invoice->exchangedDocument->typeCode = $invoiceType->value ;

        $this->invoice->supplyChainTradeTransaction = new SupplyChainTradeTransaction();

        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement = new HeaderTradeAgreement();


        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery = new HeaderTradeDelivery();

        if ($deliveryDate) {
            $this->hasDelivery = true;
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery->chainEvent = new SupplyChainEvent();
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery->chainEvent->date = self::convertDate($deliveryDate);
        }

        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement = new HeaderTradeSettlement();

        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->currency = $this->currency->value ;

        return $this->invoice;
    }

    public function setPaymentTerms(\DateTime $dueDate, ?string $description = null)
    {
        if ($this->getProfileLevel() >= self::LEVEL_BASIC_WL) {
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->specifiedTradePaymentTerms[] = $paymentTerms = new TradePaymentTerms();
            $paymentTerms->dueDate = self::convertDate($dueDate);
            if ($this->getProfileLevel() > self::LEVEL_BASIC) {
                $paymentTerms->description = $description;
            }
        }
    }

    public function setSeller(string $id, InternationalCodeDesignator $idType, string $name, $tradingName = null)
    {
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->sellerTradeParty = $this->seller = new TradeParty();
        //$sellerTradeParty->globalID[] = Id::create($id, $idType->value);
        $this->seller->legalOrganization = LegalOrganization::create($id, $idType->value, $tradingName);

        $this->seller->name = $name;
    }

    public function setPayee()
    {
        // Pas de payeeTradeParty dans le minimum
        if ($this->getProfileLevel() > self::LEVEL_MINIMUM) {
            // The Payee name (BT-59) shall be provided in the Invoice, if the Payee (BG-10) is different from the Seller (BG-4).

            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->payeeTradeParty = $this->seller;
        }
    }

    public function setSellerContact(?string $personName = null, ?string $departmentName = null, ?string $telephone = null, ?string $email = null)
    {
        if ($this->getProfileLevel() >= self::LEVEL_EN16931) {
            $this->seller->definedTradeContact = new TradeContact();
            $this->seller->definedTradeContact->personName = $personName;
            if ($telephone) {
                $this->seller->definedTradeContact->telephoneUniversalCommunication = new UniversalCommunication();
                $this->seller->definedTradeContact->telephoneUniversalCommunication->completeNumber = $telephone;
            }
            if ($email) {
                $this->seller->definedTradeContact->emailURIUniversalCommunication = new UniversalCommunication();
                $this->seller->definedTradeContact->emailURIUniversalCommunication->uriid = Id::create($email);
            }
            if ($departmentName) {
                $this->seller->definedTradeContact->departmentName = $departmentName;
            }
        }
    }

    public function addPaymentMean(PaymentMeansCode $typeCode, ?string $ibanId = null, ?string $accountName = null, ?string $bicId = null)
    {
        if ($this->getProfileLevel() >= self::LEVEL_BASIC_WL) {
            $mean = new TradeSettlementPaymentMeans();
            $mean->typeCode = $typeCode->value ;

            // $mean->information = 'get info from type code??';
            $mean->payeePartyCreditorFinancialAccount = new CreditorFinancialAccount();
            $mean->payeePartyCreditorFinancialAccount->ibanId = Id::create($ibanId);
            if ($this->getProfileLevel() > self::LEVEL_BASIC) {
                $mean->payeePartyCreditorFinancialAccount->AccountName = $accountName;
            }
            if ($bicId) {
                $mean->payeeSpecifiedCreditorFinancialInstitution = new CreditorFinancialInstitution();
                $mean->payeeSpecifiedCreditorFinancialInstitution->bicId = Id::create($bicId);
            }
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->specifiedTradeSettlementPaymentMeans[] = $mean;
        }
    }

    public function setSellerAddress(string $lineOne, string $postCode, string $city, string $countryCode, ?string $lineTwo = null, ?string $lineThree = null)
    {
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->sellerTradeParty->postalTradeAddress = $this->createAddress($postCode, $city, $countryCode, $lineOne, $lineTwo, $lineThree);

        return $this;
    }

    public function setBuyerAddress(string $lineOne, string $postCode, string $city, string $countryCode, ?string $lineTwo = null, ?string $lineThree = null)
    {
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->buyerTradeParty->postalTradeAddress = $this->createAddress($postCode, $city, $countryCode, $lineOne, $lineTwo, $lineThree);

        return $this;
    }

    public function setSellerTaxRegistration(string $id, string $schemeID)
    {
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->sellerTradeParty->taxRegistrations[] = TaxRegistration::create($id, $schemeID);
    }

    public function setBuyer(string $buyerReference, string $name, string $id = null)
    {

        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->buyerReference = $buyerReference;
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->buyerTradeParty = $buyerTradeParty = new TradeParty();
        if ($this->getProfileLevel() > self::LEVEL_MINIMUM && $id) {
            $buyerTradeParty->id = Id::create($id);
        }
        $buyerTradeParty->name = $name ;
        if ($this->hasDelivery) {
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery->shipToTradeParty = $buyerTradeParty;
        }

        return $this;
    }

    public function createAddress(string $postCode, string $city, string $countryCode, string $lineOne, ?string $lineTwo = null, ?string $lineThree = null)
    {
        $address = new TradeAddress();
        if ($this->getProfileLevel() > self::LEVEL_MINIMUM) {
            $address->postcode = $postCode;
            $address->lineOne = $lineOne ;
            $address->city = $city;
            $address->lineTwo = $lineTwo ;
            $address->lineThree = $lineThree;
        }
        $address->countryCode = $countryCode;

        return $address;
    }

    protected function calculateTotals()
    {
        if ($this->profile == self::BASIC_WL) {
            // calculate Tax Lines if not provided
            if (count($this->taxLines) == 0) {
                if (! isset($this->totalBasis)) {
                    throw new \Exception('You should call setPrice to set taxBasisTotal and taxTotal');
                }
                $this->addTaxLine($this->tax, $this->totalBasis);
            }
        }

        if (count($this->taxLines)) {
            $totalBasis = 0;
            $tax = 0;
            // We recalculate, so we reset.
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->tradeTaxes = [];
            foreach ($this->taxLines as $rate => $items) {
                // turn back rate to float
                $rate = (float) $rate;
                $sum = array_sum($items);
                $totalBasis += $sum;
                $tax += $calculated = $sum * $rate / 100;
                // and skip tax 0
                if ($rate > 0) {
                    $tradeTax = new TradeTax();
                    $tradeTax->typeCode = TaxTypeCodeContent::VAT->value;
                    $tradeTax->categoryCode = VatCategory::STANDARD->value;
                    $tradeTax->basisAmount = Amount::create(self::decimalFormat($sum));
                    $tradeTax->rateApplicablePercent = self::decimalFormat($rate) ;
                    $tradeTax->calculatedAmount = Amount::create(self::decimalFormat($calculated));
                    if ($this->getProfileLevel() >= self::LEVEL_BASIC_WL) {
                        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->tradeTaxes[] = $tradeTax;
                    }
                }
            }
        } else {
            if (in_array($this->profile, [self::BASIC, self::EN16931, self::EXTENDED  ])) {
                throw new \Exception('You need to set invoice items using addItem');
            }
            if ($this->profile == self::MINIMUM || $this->profile == self::BASIC_WL) {
                if (! isset($this->totalBasis)) {
                    throw new \Exception('You should call tax to set totalBasis and taxTotal');
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
        $summation->taxTotalAmount[] = Amount::create(self::decimalFormat($tax), $this->currency->value);
        $summation->grandTotalAmount[] = Amount::create(self::decimalFormat($grand));
        //$summation->totalPrepaidAmount = Amount::create('0.00');
        if ($this->getProfileLevel() > self::LEVEL_MINIMUM) {
            $summation->lineTotalAmount = Amount::create(self::decimalFormat($totalBasis));
            // [BR-CO-13]-Invoice total amount without VAT (BT-109) = Σ Invoice line net amount (BT-131) - Sum of allowances on document level (BT-107) + Sum of charges on document level (BT-108).
            //$summation->chargeTotalAmount = Amount::create('0.00');
            //$summation->allowanceTotalAmount = Amount::create('0.00');
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

    public function validate(string $xml, $schematron)
    {
        switch ($this->profile) {
            case self::MINIMUM:
                $against = Validator::SCHEMA_MINIMUM;

                break;
            case self::BASIC:
                $against = Validator::SCHEMA_BASIC;

                break;
            case self::BASIC_WL:
                $against = Validator::SCHEMA_BASIC_WL;

                break;
            case self::EN16931:
                $against = Validator::SCHEMA_EN16931;

                break;
            case self::EXTENDED:
            case self::XRECHNUNG:
                $against = Validator::SCHEMA_EXTENDED;

                break;
            default:
                $against = Validator::SCHEMA_MINIMUM;
        }
        if ($schematron) {
            $against = str_replace([
                '.xsd',
                'FACTUR-X',
            ], [
                '.sch',
                'Schematron/FACTUR-X',
            ], $against);
        }

        if ($schematron) {
            // avoid deprecation milo/schematron is not fully php8.2 compatible, but gets the job done
            $schematron = @new Schematron();
            $schematron->load($against);
            $document = new \DOMDocument();
            $document->loadXml($xml);

            return @$schematron->validate($document, Schematron::RESULT_COMPLEX);
        }

        return (new Validator())->validateAgainstXsd($xml, $against);
    }

    public function addItem(string $name, float $price, float $taxRatePercent, float  $quantity, UnitOfMeasurement $unit, ?string $globalID = null, string $globalIDCode = null): float
    {

        $item = new SupplyChainTradeLineItem();
        $lineNumber = count($this->items) + 1;

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

        if ($this->getProfileLevel() >= self::LEVEL_EN16931) {
            $item->tradeAgreement->grossPrice = TradePrice::create(self::decimalFormat($price));
        }
        $item->tradeAgreement->netPrice = TradePrice::create(self::decimalFormat($price));

        $item->delivery = new LineTradeDelivery();
        $item->delivery->billedQuantity = Quantity::create(self::decimalFormat($quantity), $unit->value);

        $item->specifiedLineTradeSettlement = new LineTradeSettlement();
        $item->specifiedLineTradeSettlement->tradeTax[] = $itemtax = new TradeTax();
        $itemtax->typeCode = TaxTypeCodeContent::VAT->value;
        $itemtax->categoryCode = VatCategory::STANDARD->value ;
        $itemtax->rateApplicablePercent = self::decimalFormat($taxRatePercent);


        $totalLineBasis = $price * $quantity;


        $item->specifiedLineTradeSettlement->monetarySummation = TradeSettlementLineMonetarySummation::create(self::decimalFormat($totalLineBasis));

        $this->items[] = $item;
        if ($this->getProfileLevel() >= self::LEVEL_BASIC) {
            $this->invoice->supplyChainTradeTransaction->lineItems[] = $item;
        }

        return $totalLineBasis;
    }

    public function addNote(string $content, ?string $subjectCode = null, ?string $contentCode = null)
    {
        $this->invoice->exchangedDocument->notes[] = Note::create($content, $subjectCode, $contentCode);
    }

    public function addEmbeddedAttachment(?string $id, ?string $scheme, ?string $filename, ?string $contents, ?string $mimeCode, ?string $description)
    {
        // The attachement is correctly added but schematron fails, need to
        // $attachment = ReferencedDocument::create($id);
        // $attachment->name = $description;
        // $binary = new BinaryObject();
        // $binary->filename=$filename;
        // $binary->mimeCode=$mimeCode;
        // $binary->value=base64_encode($contents);
        // $attachment->attachmentBinaryObject = $binary;

        // switch($scheme) {
        //     // TODO, depending on the type of documents it could be attached
        //     default : $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->invoiceReferencedDocument = $attachment;
        // }
    }
}
