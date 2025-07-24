<?php

namespace DigitalInvoice;

use Easybill\ZUGFeRD211\Model\DateTime;

class Invoice
{
    public const FACTURX_MINIMUM = FacturX::MINIMUM;
    public const FACTURX_BASIC_WL = FacturX::BASIC_WL;
    public const FACTURX_BASIC = FacturX::BASIC;
    public const FACTURX_EN16931 = FacturX::EN16931;
    public const FACTURX_EXTENDED = FacturX::EXTENDED;
    public const FACTURX_XRECHNUNG = FacturX::XRECHNUNG;
    public const ZUGFERD_BASIC = Zugferd::ZUGFERD_BASIC;
    public const ZUGFERD_CONFORT = Zugferd::ZUGFERD_CONFORT;
    public const ZUGFERD_EXTENDED = Zugferd::ZUGFERD_EXTENDED;
    public const UBL_PEPOOL = Ubl::PEPPOL;
    public const UBL_NLCIUS = Ubl::NLCIUS;
    public const UBL_CIUS_RO = Ubl::CIUS_RO;
    public const UBL_CIUS_IT = Ubl::CIUS_IT;
    public const UBL_CIUS_ES_FACE = Ubl::CIUS_ES_FACE;
    public const UBL_CIUS_AT_GOV = Ubl::CIUS_AT_GOV;
    public const UBL_CIUS_AT_NAT = Ubl::CIUS_AT_NAT;
    public const UBL_MALAYSIA = Ubl::MALAYSIA;


    public const EXEMPT_FROM_TAX = VatCategory::EXEMPT_FROM_TAX;
    public const SERVICE_OUTSIDE_SCOPE_OF_TAX = VatCategory::SERVICE_OUTSIDE_SCOPE_OF_TAX;
    public const FREE_EXPORT_ITEM_TAX_NOT_CHARGED = VatCategory::FREE_EXPORT_ITEM_TAX_NOT_CHARGED;
    public const STANDARD_TAX = VatCategory::STANDARD;




    protected $profile;
    protected $lines = [];
    protected $hasDelivery = false ;
    protected $totalBasis = null;
    protected $tax = null;

    public XmlGenerator $xmlGenerator;


    protected $invoiceInformations = [];

    protected $noTaxCategory = VatCategory::SERVICE_OUTSIDE_SCOPE_OF_TAX;
    protected $noTaxReason =  null;

    public function __construct(
        string $invoiceId,
        \DateTime $issueDate,
        \DateTime $deliveryDate = null,
        string|CurrencyCode $currency = CurrencyCode::EURO,
        $profile = self::FACTURX_MINIMUM,
        string|InvoiceTypeCode $invoiceType = InvoiceTypeCode::COMMERCIAL_INVOICE
    ) {
        if (is_string($currency)) {
            try {
                $currency = CurrencyCode::from($currency);
            } catch (\ValueError $e) {
                throw new \Exception("$currency is not a valid Currency");
            }
        }
        if (is_string($invoiceType)) {
            try {
                $invoiceType = InvoiceTypeCode::from($invoiceType);
            } catch (\ValueError $e) {
                throw new \Exception("$invoiceType is not a valid Invoice Type");
            }
        }
        $this->profile = $profile;
        if ( $this->isUbl()) {
            $this->xmlGenerator = new Ubl($profile, $currency);
            $this->profile = (new $profile())->getSpecification();
        } elseif (in_array($profile, [FacturX::MINIMUM ,  FacturX::BASIC_WL,  FacturX::BASIC, FacturX::EN16931,  FacturX::EXTENDED, FacturX::XRECHNUNG])) {
            $this->xmlGenerator = new FacturX($profile, $currency);
        } else {
            $this->xmlGenerator = new Zugferd($profile, $currency);
        }
        $this->invoiceInformations['profile'] = $profile;
        $this->invoiceInformations['invoiceId'] = $invoiceId;
        $this->invoiceInformations['date'] = $issueDate->format('Y-m-d');
        $this->invoiceInformations['docTypeName'] = $invoiceType->value;
        $this->xmlGenerator->initDocument($invoiceId, $issueDate, $invoiceType, $deliveryDate);
    }

    public function setTaxExemption(VatCategory $vatCategory, ?string $reason = null)
    {
        $this->xmlGenerator->setTaxExemption($vatCategory, $reason);
    }

    public function getProfileLevel()
    {
        return $this->xmlGenerator->getProfileLevel();
    }

    public function setPrice(float $totalBasis, float $tax = 0)
    {
        $this->xmlGenerator->setPrice($totalBasis, $tax);
    }

    public function getXml()
    {
        return $this->xmlGenerator->getXml();
    }

    protected static function convertDate(\DateTime $date)
    {
        return DateTime::create(102, $date->format('Ymd'));
    }

    public function setBuyer(string $buyerReference, string $name, string $id = null)
    {
        $this->xmlGenerator->setBuyer($buyerReference, $name, $id);
    }

    public function setBuyerIdentifier(string $id, ?string $idDesignator, string $type = 'Legal')
    {
        try {
            $idType = InternationalCodeDesignator::from($idDesignator);
        } catch (\ValueError $e) {
            throw new \Exception("$idDesignator is an Invalide InternationalCodeDesignator");
        }

        try {
            $type = IdentificationType::from($type);
        } catch (\ValueError $e) {
            throw new \Exception("$type is an Invalide IdentificationType");
        }
        $this->xmlGenerator->setBuyerIdentifier($id, $idType, $type);
    }

    public function createAddress(string $postCode, string $city, string $countryCode, string $lineOne, ?string $lineTwo = null, ?string $lineThree = null)
    {

        return $this->xmlGenerator->createAddress($postCode, $city, $countryCode, $lineOne, $lineTwo, $lineThree);
    }

    public function setBuyerAddress(string $lineOne, string $postCode, string $city, string $countryCode, ?string $lineTwo = null, ?string $lineThree = null)
    {
        $this->xmlGenerator->setBuyerAddress($lineOne, $postCode, $city, $countryCode, $lineTwo, $lineThree);
    }

    public function setSeller(string $id, string $idType, string $name, $tradingName = null)
    {
        try {
            $idType = InternationalCodeDesignator::from($idType);
        } catch (\ValueError $e) {
            throw new \Exception("$idType is an Invalide InternationalCodeDesignator");
        }
        $this->invoiceInformations['seller'] = $name;
        $this->xmlGenerator->setSeller($id, $idType, $name, $tradingName);
    }

    public function addSellerIdentifier(string $identifier, string $idType)
    {
        try {
            $idType = InternationalCodeDesignator::from($idType);
        } catch (\ValueError $e) {
            throw new \Exception("$idType is an Invalide InternationalCodeDesignator");
        }
        $this->xmlGenerator->addSellerIdentifier($idType, $identifier);
    }



    public function setSellerContact(?string $personName = null, ?string $telephone = null, ?string $email = null, ?string $departmentName = null)
    {
        $this->xmlGenerator->setSellerContact($personName, $telephone, $email, $departmentName);
    }

    public function setSellerTaxRegistration(string $id, string $schemeID)
    {
        $this->xmlGenerator->setSellerTaxRegistration($id, $schemeID);
    }

    public function setSellerAddress(string $lineOne, string $postCode, string $city, string $countryCode, ?string $lineTwo = null, ?string $lineThree = null)
    {
        $this->xmlGenerator->setSellerAddress($lineOne, $postCode, $city, $countryCode, $lineTwo, $lineThree);

        return $this;
    }

    public function addItem(string $name, float $price, float $taxRatePercent, float  $quantity = 1, string $unit = 'H87', ?string $globalID = null, string $globalIDCode = '0160')
    {
        try {
            $unit = UnitOfMeasurement::from($unit);
        } catch (\ValueError $e) {
            throw new \Exception("$unit is not a valide Unit of Unit Of Measurement");
        }

        $totalLineBasis = $this->xmlGenerator->addItem($name, $price, $taxRatePercent, $quantity, $unit, $globalID, $globalIDCode);
        // To be able to calc easily the invoice totals
        $this->xmlGenerator->addTaxLine($taxRatePercent, $totalLineBasis);
    }

    public function addPaymentMean(string $typeCode, ?string $ibanId = null, ?string $accountName = null, ?string $bicId = null)
    {
        try {
            $typeCode = PaymentMeansCode::from($typeCode);
        } catch (\ValueError $e) {
            throw new \Exception("$typeCode is not a valid PaymentMeans Code");
        }

        $this->xmlGenerator->addPaymentMean($typeCode, $ibanId, $accountName, $bicId);
    }

    public function setPaymentTerms(\DateTime $dueDate, ?string $description = null)
    {
        $this->xmlGenerator->setPaymentTerms($dueDate, $description);
    }

    public function addNote(string $content, ?string $subjectCode = null, ?string $contentCode = null)
    {
        $this->xmlGenerator->addNote($content, $subjectCode, $contentCode);
    }

    /**
     * Validates the invoice, returns an array of validation errors
     *
     * @param string $xml
     * @param bool $schematron
     * @return mixed
     */
    public function validate(string $xml, bool $schematron = false): mixed
    {
        return $this->xmlGenerator->validate($xml, $schematron);
    }

    /**
     * Generates a pdf string to be saved
     *
     * @param string $pdf
     * @param bool $addFacturxLogo
     * @param array $fpdiParams
     * @return string
     */
    public function getPdf($pdf, $addFacturxLogo = false, $fpdiParams = [])
    {
        if (in_array($this->profile, [static::ZUGFERD_BASIC, static::ZUGFERD_CONFORT, static::ZUGFERD_EXTENDED])) {
            // Ensure false, there is no logo for those profiles
            $addFacturxLogo = false;
        }
        $factureX = new PdfWriter();
        $factureX->setPdfMetaData($this->invoiceInformations);

        return $factureX->generateFacturxFromFiles(
            $pdf,
            $this->getXml(),
            null,
            true,
            '',
            [],
            $addFacturxLogo,
            'Data',
            $fpdiParams
        );
    }

    public function addEmbeddedAttachment(?string $id, ?string $scheme, ?string $filename, ?string $contents, ?string $mimeCode, ?string $description)
    {
        $this->xmlGenerator->addEmbeddedAttachment($id, $scheme, $filename, $contents, $mimeCode, $description);
    }

    public function isUbl()
    {
        return in_array($this->profile, [self::UBL_NLCIUS, self::UBL_PEPOOL, self::UBL_CIUS_IT, self::UBL_CIUS_RO, self::UBL_CIUS_AT_GOV, self::UBL_CIUS_AT_NAT, self::UBL_CIUS_ES_FACE, self::UBL_MALAYSIA]);
    }
}
