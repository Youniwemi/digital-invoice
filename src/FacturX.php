<?php

namespace DigitalInvoice;

// Factur-X Xml Stuff
use Easybill\ZUGFeRD2\Builder;
use Easybill\ZUGFeRD2\Model\Amount;
use Easybill\ZUGFeRD2\Model\BinaryObject;
use Easybill\ZUGFeRD2\Model\CreditorFinancialAccount;
use Easybill\ZUGFeRD2\Model\CreditorFinancialInstitution;
use Easybill\ZUGFeRD2\Model\CrossIndustryInvoice;
use Easybill\ZUGFeRD2\Model\DateTime;
use Easybill\ZUGFeRD2\Model\DocumentContextParameter;
use Easybill\ZUGFeRD2\Model\DocumentLineDocument;
use Easybill\ZUGFeRD2\Model\ExchangedDocument;
use Easybill\ZUGFeRD2\Model\ExchangedDocumentContext;
use Easybill\ZUGFeRD2\Model\FormattedDateTime;
use Easybill\ZUGFeRD2\Model\HeaderTradeAgreement;
use Easybill\ZUGFeRD2\Model\HeaderTradeDelivery;
use Easybill\ZUGFeRD2\Model\HeaderTradeSettlement;
use Easybill\ZUGFeRD2\Model\Id;
use Easybill\ZUGFeRD2\Model\Indicator;
use Easybill\ZUGFeRD2\Model\LegalOrganization;
use Easybill\ZUGFeRD2\Model\LineTradeAgreement;
use Easybill\ZUGFeRD2\Model\LineTradeDelivery;
use Easybill\ZUGFeRD2\Model\LineTradeSettlement;
use Easybill\ZUGFeRD2\Model\Note;
use Easybill\ZUGFeRD2\Model\Quantity;
use Easybill\ZUGFeRD2\Model\ReferencedDocument;
use Easybill\ZUGFeRD2\Model\SupplyChainEvent;
use Easybill\ZUGFeRD2\Model\SupplyChainTradeLineItem;
use Easybill\ZUGFeRD2\Model\SupplyChainTradeTransaction;
use Easybill\ZUGFeRD2\Model\TaxRegistration;
use Easybill\ZUGFeRD2\Model\TradeAllowanceCharge;
use Easybill\ZUGFeRD2\Model\TradeAddress;
use Easybill\ZUGFeRD2\Model\TradeContact;
use Easybill\ZUGFeRD2\Model\TradeParty;
use Easybill\ZUGFeRD2\Model\TradePaymentTerms;
use Easybill\ZUGFeRD2\Model\TradePrice;
use Easybill\ZUGFeRD2\Model\TradeProduct;
use Easybill\ZUGFeRD2\Model\TradeSettlementHeaderMonetarySummation;
use Easybill\ZUGFeRD2\Model\TradeSettlementLineMonetarySummation;
use Easybill\ZUGFeRD2\Model\TradeSettlementPaymentMeans;
use Easybill\ZUGFeRD2\Model\TradeTax;
use Easybill\ZUGFeRD2\Model\UniversalCommunication;
use Easybill\ZUGFeRD2\Validator;
use Milo\Schematron;

class FacturX extends XmlGenerator
{
    public const MINIMUM = 'urn:factur-x.eu:1p0:minimum';
    public const BASIC_WL = 'urn:factur-x.eu:1p0:basicwl';
    public const BASIC = 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic';
    public const EN16931 = 'urn:cen.eu:en16931:2017';
    public const EXTENDED = 'urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended';
    // Former EXTENDED value, rejected by the current Factur-X schematron, still accepted as input
    public const EXTENDED_LEGACY = 'urn:cen.eu:en16931:2017#conformant#urn:zugferd.de:2p1:extended';
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

    // Document level allowances (BG-20), [rate, amount, reason, reasonCode]
    protected array $allowances = [];

    //protected $noTaxCategory = VatCategory::FREE_EXPORT_ITEM_TAX_NOT_CHARGED;
    //protected $noTaxCategory = VatCategory::EXEMPT_FROM_TAX;

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
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery->actualDeliverySupplyChainEvent = new SupplyChainEvent();
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery->actualDeliverySupplyChainEvent->occurrenceDateTime = self::convertDate($deliveryDate);
        }

        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement = new HeaderTradeSettlement();

        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->invoiceCurrencyCode = $this->currency->value ;

        return $this->invoice;
    }

    public function setPaymentTerms(\DateTime $dueDate, ?string $description = null)
    {
        if ($this->getProfileLevel() >= self::LEVEL_BASIC_WL) {
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->specifiedTradePaymentTerms[] = $paymentTerms = new TradePaymentTerms();
            $paymentTerms->dueDateDateTime = self::convertDate($dueDate);
            if ($this->getProfileLevel() > self::LEVEL_BASIC) {
                $paymentTerms->description = $description;
            }
        }
    }

    public function setSeller(string $id, InternationalCodeDesignator $idType, string $name, $tradingName = null)
    {
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->sellerTradeParty = $this->seller = new TradeParty();
        
        $this->seller->specifiedLegalOrganization = new LegalOrganization();
        $this->seller->specifiedLegalOrganization->id = Id::create($id, $idType->value);
        $this->seller->specifiedLegalOrganization->tradingBusinessName = $tradingName;

        $this->seller->name = $name;
    }

    public function addSellerIdentifier(InternationalCodeDesignator $idType, string $identifier)
    {
        $this->seller->globalID[] = Id::create($identifier, $idType->value);
    }

    public function setPayee()
    {
        // Pas de payeeTradeParty dans le minimum
        if ($this->getProfileLevel() > self::LEVEL_MINIMUM) {
            // The Payee name (BT-59) shall be provided in the Invoice, if the Payee (BG-10) is different from the Seller (BG-4).

            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->payeeTradeParty = $this->seller;
        }
    }

    protected function createContact(?string $personName = null, ?string $telephone = null, ?string $email = null, ?string $departmentName = null): TradeContact
    {
        $contact = new TradeContact();
        $contact->personName = $personName;
        
        if ($telephone) {
            $contact->telephoneUniversalCommunication = new UniversalCommunication();
            $contact->telephoneUniversalCommunication->completeNumber = $telephone;
        }
        
        if ($email) {
            $contact->emailURIUniversalCommunication = new UniversalCommunication();
            $contact->emailURIUniversalCommunication->uriid = Id::create($email);
        }
        
        if ($departmentName) {
            $contact->departmentName = $departmentName;
        }
        
        return $contact;
    }

    public function setSellerContact(?string $personName = null, ?string $telephone = null, ?string $email = null, ?string $departmentName = null)
    {
        if ($this->getProfileLevel() >= self::LEVEL_EN16931) {
            $this->seller->definedTradeContact = [$this->createContact($personName, $telephone, $email, $departmentName)];
        }
    }

    public function setBuyerContact(?string $personName = null, ?string $telephone = null, ?string $email = null, ?string $departmentName = null)
    {
        if ($this->getProfileLevel() >= self::LEVEL_EN16931) {
            $buyer = $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->buyerTradeParty;
            if ($buyer) {
                $buyer->definedTradeContact = [$this->createContact($personName, $telephone, $email, $departmentName)];
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
                $mean->payeePartyCreditorFinancialAccount->accountName = $accountName;
            }
            if ($bicId) {
                $mean->payeeSpecifiedCreditorFinancialInstitution = new CreditorFinancialInstitution();
                $mean->payeeSpecifiedCreditorFinancialInstitution->bicId = Id::create($bicId);
            }
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->specifiedTradeSettlementPaymentMeans[] = $mean;
        }
    }

    public function setSellerAddress(string $lineOne, string $postCode, string $city, string $countryCode, ?string $lineTwo = null, ?string $lineThree = null, ?string $stateCode = null)
    {
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->sellerTradeParty->postalTradeAddress = $this->createAddress($postCode, $city, $countryCode, $lineOne, $lineTwo, $lineThree);

        return $this;
    }

    public function setBuyerIdentifier( string $identifier, ?InternationalCodeDesignator $idType=null, IdentificationType $type = IdentificationType::OTHER )
    {
        if ($this->getProfileLevel() > self::LEVEL_MINIMUM ) {
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->buyerTradeParty->id = [Id::create($identifier)];
        }

        return $this;
    }
    public function setBuyerAddress(string $lineOne, string $postCode, string $city, string $countryCode, ?string $lineTwo = null, ?string $lineThree = null, ?string $stateCode = null)
    {
        $address = $this->createAddress($postCode, $city, $countryCode, $lineOne, $lineTwo, $lineThree);
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->buyerTradeParty->postalTradeAddress = $address;
        if ($this->hasDelivery && $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery->shipToTradeParty) {
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery->shipToTradeParty->postalTradeAddress = $address;
        }

        return $this;
    }

    public function setBuyerOrderReference(string $reference)
    {
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->buyerOrderReferencedDocument = ReferencedDocument::create($reference);
    }

    public function setBillingMode(string $mode)
    {
        $this->invoice->exchangedDocumentContext->businessProcessSpecifiedDocumentContextParameter = new DocumentContextParameter();
        $this->invoice->exchangedDocumentContext->businessProcessSpecifiedDocumentContextParameter->id = $mode;
    }

    public function addPrecedingInvoiceReference(string $invoiceId, ?\DateTime $issueDate = null)
    {
        if ($this->getProfileLevel() >= self::LEVEL_BASIC_WL) {
            $reference = ReferencedDocument::create($invoiceId);
            if ($issueDate) {
                $reference->formattedIssueDateTime = FormattedDateTime::create(102, $issueDate->format(self::DATE_102));
            }
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->invoiceReferencedDocument[] = $reference;
        }
    }

    public function setSellerElectronicAddress(string $id, string $scheme)
    {
        $this->setElectronicAddress($this->seller, $id, $scheme);
    }

    public function setBuyerElectronicAddress(string $id, string $scheme)
    {
        $this->setElectronicAddress($this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->buyerTradeParty, $id, $scheme);
    }

    protected function setElectronicAddress(TradeParty $party, string $id, string $scheme)
    {
        if ($this->getProfileLevel() > self::LEVEL_MINIMUM) {
            $party->uriUniversalCommunication = new UniversalCommunication();
            $party->uriUniversalCommunication->uriid = Id::create($id, $scheme);
        }
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
            $buyerTradeParty->id = [Id::create($id)];
        }
        $buyerTradeParty->name = $name ;
        if ($this->hasDelivery) {
            $shipTo = new TradeParty();
            $shipTo->name = $name;
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery->shipToTradeParty = $shipTo;
        }

        return $this;
    }

    public function createAddress(string $postCode, string $city, string $countryCode, string $lineOne, ?string $lineTwo = null, ?string $lineThree = null)
    {
        $address = new TradeAddress();
        if ($this->getProfileLevel() > self::LEVEL_MINIMUM) {
            $address->postcodeCode = $postCode;
            $address->lineOne = $lineOne ;
            $address->cityName = $city;
            $address->lineTwo = $lineTwo ;
            $address->lineThree = $lineThree;
        }
        $address->countryID = $countryCode;

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
                $rate = $this->calculateTaxRate($this->totalBasis, $this->tax);
                $this->addTaxLine($rate, $this->totalBasis);
            }
        }

        // Allowances are deducted from the VAT basis of their rate
        $allowancesByRate = [];
        $allowanceTotal = 0;
        foreach ($this->allowances as [$rate, $amount]) {
            $allowancesByRate[$rate] = ($allowancesByRate[$rate] ?? 0) + $amount;
            $allowanceTotal += $amount;
            if (! isset($this->taxLines[$rate])) {
                $this->taxLines[$rate] = [];
            }
        }

        if (count($this->taxLines)) {
            $totalBasis = 0;
            $tax = 0;
            // We recalculate, so we reset.
            $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->tradeTaxes = [];
            foreach ($this->taxLines as $rate => $items) {
                $sum = array_sum($items) - ($allowancesByRate[$rate] ?? 0);
                // turn back rate to float
                $rate = (float) $rate;
                $totalBasis += $sum;
                $tax += $calculated = $sum * $rate / 100;
                // and skip tax 0
            
                $tradeTax = new TradeTax();
                $tradeTax->typeCode = TaxTypeCodeContent::VAT->value;
                if ($rate==0) {
                    if ($this->noTaxCategory) {
                        $tradeTax->categoryCode = $this->noTaxCategory->value;
                        $tradeTax->exemptionReason = $this->noTaxReason;
                    }
                } else {
                    $tradeTax->categoryCode = VatCategory::STANDARD->value;
                }
                
                $tradeTax->basisAmount = Amount::create(self::decimalFormat($sum));
                $tradeTax->rateApplicablePercent = self::decimalFormat($rate) ;
                $tradeTax->calculatedAmount = Amount::create(self::decimalFormat($calculated));
                $tradeTax->dueDateTypeCode = $this->vatDueDateTypeCode?->value;
                if ($this->getProfileLevel() >= self::LEVEL_BASIC_WL) {
                    $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->tradeTaxes[] = $tradeTax;
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
        $summation->taxBasisTotalAmount[] = Amount::create(self::decimalFormat($totalBasis));
        $summation->taxTotalAmount[] = Amount::create(self::decimalFormat($tax), $this->currency->value);
        $summation->grandTotalAmount[] = Amount::create(self::decimalFormat($grand));
        //$summation->totalPrepaidAmount = Amount::create('0.00');
        if ($this->getProfileLevel() > self::LEVEL_MINIMUM) {
            // [BR-CO-13]-Invoice total amount without VAT (BT-109) = Σ Invoice line net amount (BT-131) - Sum of allowances on document level (BT-107) + Sum of charges on document level (BT-108).
            $summation->lineTotalAmount = Amount::create(self::decimalFormat($totalBasis + $allowanceTotal));
            if ($allowanceTotal) {
                $summation->allowanceTotalAmount = Amount::create(self::decimalFormat($allowanceTotal));
            }
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
        [$against, $name] = match ($this->profile) {
            self::BASIC => [Validator::SCHEMA_BASIC, 'BASIC'],
            self::BASIC_WL => [Validator::SCHEMA_BASIC_WL, 'BASIC-WL'],
            self::EN16931 => [Validator::SCHEMA_EN16931, 'EN16931'],
            self::EXTENDED, self::XRECHNUNG => [Validator::SCHEMA_EXTENDED, 'EXTENDED'],
            default => [Validator::SCHEMA_MINIMUM, 'MINIMUM'],
        };

        if ($schematron) {
            // easybill/zugferd-php no longer ships the schematrons, they are bundled in src/Schematron
            // avoid deprecation milo/schematron is not fully php8.2 compatible, but gets the job done
            $schematron = @new Schematron();
            $schematron->load(__DIR__."/Schematron/FACTUR-X_$name.sch");
            $document = new \DOMDocument();
            $document->loadXml($xml);

            return @$schematron->validate($document, Schematron::RESULT_COMPLEX);
        }

        return (new Validator())->validateAgainstXsd($xml, $against);
    }

    public function addItem(string $name, float $price, float $taxRatePercent, float  $quantity, UnitOfMeasurement $unit, ?string $globalID = null, string $globalIDCode = null, ?string $description = null): array
    {

        $item = new SupplyChainTradeLineItem();
        $lineNumber = count($this->items) + 1;

        $item->associatedDocumentLineDocument = DocumentLineDocument::create((string) $lineNumber);

        $item->specifiedTradeProduct = new TradeProduct();
        $item->specifiedTradeProduct->name = $name;
        if ($description !== null) {
            $item->specifiedTradeProduct->description = $description;
        }
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
        if ($taxRatePercent==0) {
            if ($this->noTaxCategory) {
                $itemtax->categoryCode = $this->noTaxCategory->value ;
                $itemtax->rateApplicablePercent = self::decimalFormat($taxRatePercent);
            }
        } else {
            $itemtax->categoryCode = VatCategory::STANDARD->value ;
            $itemtax->rateApplicablePercent = self::decimalFormat($taxRatePercent);
        }
        
        


        $totalLineBasis = $price * $quantity;


        $item->specifiedLineTradeSettlement->monetarySummation = TradeSettlementLineMonetarySummation::create(self::decimalFormat($totalLineBasis));

        $this->items[] = $item;
        if ($this->getProfileLevel() >= self::LEVEL_BASIC) {
            $this->invoice->supplyChainTradeTransaction->lineItems[] = $item;
        }

        return [$item, $totalLineBasis];
    }

    public function addAllowance(float $amount, float $taxRatePercent, ?string $reason = null, ?string $reasonCode = null)
    {
        if ($this->getProfileLevel() < self::LEVEL_BASIC_WL) {
            throw new \Exception('Allowances are not supported for the MINIMUM profile');
        }
        $rate = self::decimalFormat($taxRatePercent, 4);
        $this->allowances[] = [$rate, $amount, $reason, $reasonCode];

        $tradeTax = new TradeTax();
        $tradeTax->typeCode = TaxTypeCodeContent::VAT->value;
        if ($taxRatePercent == 0) {
            if ($this->noTaxCategory) {
                $tradeTax->categoryCode = $this->noTaxCategory->value;
                // [BR-O-14] No rate for the "Not subject to VAT" category
                if ($this->noTaxCategory !== VatCategory::SERVICE_OUTSIDE_SCOPE_OF_TAX) {
                    $tradeTax->rateApplicablePercent = self::decimalFormat($taxRatePercent);
                }
            }
        } else {
            $tradeTax->categoryCode = VatCategory::STANDARD->value;
            $tradeTax->rateApplicablePercent = self::decimalFormat($taxRatePercent);
        }

        $indicator = new Indicator();
        $indicator->indicator = false;
        $allowance = TradeAllowanceCharge::create(Amount::create(self::decimalFormat($amount)), $indicator, null, null, $reason, [$tradeTax]);
        $allowance->reasonCode = $reasonCode;
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->specifiedTradeAllowanceCharge[] = $allowance;
    }

    public function addNote(string $content, ?string $subjectCode = null, ?string $contentCode = null)
    {
        if ($this->getProfileLevel() > self::LEVEL_MINIMUM) {
            $this->invoice->exchangedDocument->notes[] = Note::create($content, $subjectCode, $contentCode);
        }
    }

    public function addEmbeddedAttachment(?string $id, ?string $scheme, ?string $filename, ?string $contents, ?string $mimeCode, ?string $description)
    {
        // BG-24 supporting documents are only allowed from EN16931
        if ($this->getProfileLevel() < self::LEVEL_EN16931) {
            throw new \Exception('Attachments are only supported from the EN16931 profile');
        }
        // BT-122 is mandatory, BT-125-1 and BT-125-2 are mandatory with an attached document
        if ($id === null || ($contents !== null && ($filename === null || $mimeCode === null))) {
            throw new \Exception('An attachment needs an id, and a filename and a mime code when it has contents');
        }
        $attachment = ReferencedDocument::create($id);
        // 916: related document
        $attachment->typeCode = '916';
        $attachment->name = $description;
        if ($contents !== null) {
            $binary = new BinaryObject();
            $binary->filename = $filename;
            $binary->mimeCode = $mimeCode;
            $binary->value = base64_encode($contents);
            $attachment->attachmentBinaryObject = $binary;
        }
        $this->invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement->additionalReferencedDocuments[] = $attachment;
    }
}
