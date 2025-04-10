<?php

namespace DigitalInvoice;

require_once __DIR__ . '/Types.php';

interface XmlGeneratorInterface
{
    public function validate(string $xml, $schematron);

    public function initDocument($invoiceId, \DateTime $issueDateTime, $invoiceType, ?\DateTime $deliveryDate = null);

    public function setTaxExemption(VatCategory $catCategory, ?string $exemptReason = null);

    public function setSeller(string $id, InternationalCodeDesignator $idType, string $name, $tradingName = null);

    public function setSellerContact(?string $personName = null, ?string $telephone = null, ?string $email = null, ?string $departmentName = null);

    public function setSellerAddress(string $lineOne, string $postCode, string $city, string $countryCode, ?string $lineTwo = null, ?string $lineThree = null);

    public function setSellerTaxRegistration(string $id, string $schemeID);

    public function setBuyer(string $buyerReference, string $name, string $id = null);

    public function createAddress(string $postCode, string $city, string $countryCode, string $lineOne, ?string $lineTwo = null, ?string $lineThree = null);

    public function getXml();

    public function setBuyerAddress(string $lineOne, string $postCode, string $city, string $countryCode, ?string $lineTwo = null, ?string $lineThree = null);

    public function addItem(string $name, float $price, float $taxRatePercent, float  $quantity, UnitOfMeasurement $unit, ?string $globalID = null, ?string $globalIDCode = null): float;

    public function addNote(string $content, ?string $subjectCode = null, ?string $contentCode = null);

    public function addPaymentMean(PaymentMeansCode $typeCode, ?string $ibanId = null, ?string $accountName = null, ?string $bicId = null);

    public function addEmbeddedAttachment(?string $id, ?string $scheme, ?string $filename, ?string $contents, ?string $mimeCode, ?string $description);

    public function setPaymentTerms(\DateTime $dueDate, ?string $description = null);

    public function addSellerIdentifier(InternationalCodeDesignator $idType, string $identifier);
}




abstract class XmlGenerator implements XmlGeneratorInterface
{
    protected $profile;
    protected float $totalBasis;
    protected float $tax;


    public $invoice;
    public $currency;
    public mixed $seller;
    public mixed $buyer;

    public const LEVELS = [];
    // Some common constants for
    public const DATE_102 = 'Ymd';
    public const DATE_610 = 'Ym';
    public const DATE_616 = 'YW';

    protected $hasDelivery;
    protected \Countable|array $taxLines = [];
    protected \Countable|array $items = [];

    protected $noTaxCategory = null;
    protected $noTaxReason =  null;


    public function __construct($profile, $currency)
    {
        $this->profile = $profile;
        $this->currency = $currency;
    }

    public function getProfileLevel()
    {
        return isset(static::LEVELS[ $this->profile ]) ? static::LEVELS[ $this->profile ] : 0 ;
    }

    public function setPrice(float $totalBasis, float $tax = 0)
    {
        $this->totalBasis = $totalBasis;
        $this->tax = $tax;
    }

    protected function calculateTaxRate(float $totalBasis, float $tax = 0)
    {
        if ($totalBasis==0) {
            return 0;
        }
        return ($tax/$totalBasis)*100;
    }

    public static function decimalFormat(float|int $number, int $decimals = 2)
    {
        return number_format($number, $decimals, '.', '');
    }

    public function addTaxLine(float|int|string $rate, $totalLineBasis)
    {
        if (! is_string($rate)) {
            // convert to string to not loose precision
            // for the tax rate, we use 4 digits
            $rate = self::decimalFormat($rate, 4);
        }
        if (! isset($this->taxLines[$rate])) {
            $this->taxLines[$rate] = [];
        }
        $this->taxLines[$rate][] = $totalLineBasis;
    }

    public function addEmbeddedAttachment(?string $id, ?string $scheme, ?string $filename, ?string $contents, ?string $mimeCode, ?string $description)
    {
        // not implemented
    }

    public function setTaxExemption(VatCategory $catCategory, ?string $noTaxReason = null)
    {
        $this->noTaxCategory = $catCategory;
        $this->noTaxReason = $noTaxReason;
    }
}
