<?php

namespace DigitalInvoice;

use DateTime;
use Einvoicing\Delivery;
use Einvoicing\Exceptions\ValidationException;
use Einvoicing\Identifier;
use Einvoicing\Invoice;
use Einvoicing\InvoiceLine;
use Einvoicing\Party;
use Einvoicing\Payments\Payment;
use Einvoicing\Payments\Transfer;
use Einvoicing\Presets\{Peppol, Nlcius, CiusRo, CiusIt, CiusEsFace, CiusAtGov, CiusAtNat};
use Einvoicing\Writers\UblWriter;

class Ubl extends XmlGenerator {

    public const CIUS_AT_NAT = CiusAtNat::class;
    public const CIUS_AT_GOV = CiusAtGov::class;
    public const CIUS_ES_FACE = CiusEsFace::class;
    public const CIUS_IT = CiusIt::class;
    public const CIUS_RO = CiusRo::class;
    public const NLCIUS = Nlcius::class;
    public const PEPPOL = Peppol::class;

    /** @var Invoice */
    public $invoice;
    public Delivery $delivery;

    public function validate(string $xml, $schematron)
    {
        try {
            $this->invoice->validate();
        } catch(ValidationException $e) {
            return [$e->getBusinessRuleId() => $e->getMessage()];
        }
    }

    public function initDocument($invoiceId, DateTime $issueDateTime, $invoiceType, ?DateTime $deliveryDate = null)
    {
        $this->invoice = new Invoice($this->profile);
        $this->invoice->setNumber($invoiceId);
        $this->invoice->setIssueDate($issueDateTime);
        $this->invoice->setType($invoiceType->value);

        $payment = new Payment();
        $this->invoice->setPayment($payment);

        $this->delivery = new Delivery();
        if (isset($deliveryDate)) {
            $this->hasDelivery = true;
            $this->delivery->setDate($deliveryDate);
        }
        $this->invoice->setDelivery($this->delivery);
    }

    public function setSeller(string $id, InternationalCodeDesignator $idType, string $name, $tradingName = null)
    {
        $this->seller = new Party();
        $this->seller->setCompanyId(new Identifier($id, $idType->value));
        $this->seller->setName($name);
        $this->seller->setTradingName($tradingName);

        $this->invoice->setSeller($this->seller);
    }

    public function setSellerContact(?string $personName = null, ?string $telephone = null, ?string $email = null, ?string $departmentName = null)
    {
        $this->seller->setContactName($personName);
        $this->seller->setContactPhone($telephone);
        $this->seller->setContactEmail($email);
    }

    public function setSellerAddress(string $lineOne, string $postCode, string $city, string $countryCode, ?string $lineTwo = null, ?string $lineThree = null)
    {
        $this->seller->setAddress([$lineOne, $lineTwo, $lineThree]);
        $this->seller->setCity($city);
        $this->seller->setCountry($countryCode);
        $this->seller->setPostalCode($postCode);
    }

    public function setSellerTaxRegistration(string $id, string $schemeID)
    {
        $this->seller->setTaxRegistrationId(new Identifier($id));
    }

    public function setBuyer(string $buyerReference, string $name, ?string $id = null)
    {
        $this->buyer = new Party();
        $this->buyer->setName($name);
        $this->invoice->setBuyer($this->buyer);
        $this->invoice->setBuyerReference($buyerReference);
    }

    public function createAddress(string $postCode, string $city, string $countryCode, string $lineOne, ?string $lineTwo = null, ?string $lineThree = null)
    {
    }

    public function getXml()
    {
        $writer = new UblWriter();

        return $writer->export($this->invoice);
    }

    public function setBuyerAddress(string $lineOne, string $postCode, string $city, string $countryCode, ?string $lineTwo = null, ?string $lineThree = null)
    {
        $this->buyer->setAddress([$lineOne, $lineTwo, $lineThree]);
        $this->buyer->setCity($city);
        $this->buyer->setCountry($countryCode);
        $this->buyer->setPostalCode($postCode);
    }

    public function addItem(string $name, float $price, float $taxRatePercent, float $quantity, UnitOfMeasurement $unit, ?string $globalID = null, ?string $globalIDCode = null): float
    {
        $line = new InvoiceLine();
        $line->setId($globalID);
        $line->setUnit($unit->value);
        $line->setName($name);
        $line->setPrice($price);
        $line->setQuantity($quantity);
        $line->setVatRate(self::decimalFormat($taxRatePercent));

        $this->invoice->addLine($line);
        $this->items[] = $line;

        return $line->getNetAmountBeforeAllowancesCharges();
    }

    public function addNote(string $content, ?string $subjectCode = null, ?string $contentCode = null)
    {
        $this->invoice->addNote($content);
    }

    public function addPaymentMean(PaymentMeansCode $typeCode, ?string $ibanId = null, ?string $accountName = null, ?string $bicId = null)
    {
        $payment = $this->invoice->getPayment();
        $payment->setMeansCode($typeCode->value);

        $transfer = new Transfer();
        $transfer->setAccountId($ibanId);
        $transfer->setAccountName($accountName);

        $payment->addTransfer($transfer);
    }
}