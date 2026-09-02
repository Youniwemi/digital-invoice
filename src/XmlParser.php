<?php

namespace DigitalInvoice;

/**
 * Common interface for all format-specific invoice parsers.
 */
interface XmlParserInterface
{
    public function parse(string $xml): InvoiceData;
}

/**
 * Base class with shared helpers for XML invoice parsers.
 */
abstract class XmlParser implements XmlParserInterface
{
    /**
     * DOM document of the invoice XML, parsed once and reused
     * (e.g. passed in by InvoiceReader which already parsed it for detection).
     */
    protected ?\DOMDocument $docElement = null;

    /** XML string the cached document was loaded from (null when injected). */
    private ?string $docXml = null;

    public function __construct(?\DOMDocument $docElement = null)
    {
        $this->docElement = $docElement;
    }

    /**
     * Block DOCTYPE declarations to prevent billion-laughs entity expansion.
     */
    public static function assertNoDoctype(string $xml): void
    {
        if (stripos($xml, '<!doctype') !== false) {
            throw new \Exception('InvoiceReader: DOCTYPE declarations are not allowed.');
        }
    }

    /**
     * Return the cached DOM document, loading it from $xml if no document
     * was cached yet or the cached one came from a different XML string.
     * Null when the XML is invalid.
     */
    protected function getDoc(string $xml): ?\DOMDocument
    {
        if ($this->docElement !== null && ($this->docXml === null || $this->docXml === $xml)) {
            // Injected document (docXml null) is trusted to match the first
            // XML parsed; remember it so a later different XML reloads.
            $this->docXml = $xml;
            return $this->docElement;
        }
        self::assertNoDoctype($xml);
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml, LIBXML_NONET);
        libxml_use_internal_errors(false);
        if (!$loaded) {
            return null;
        }
        $this->docElement = $doc;
        $this->docXml = $xml;
        return $doc;
    }

    /**
     * Parse a date string into a DateTime using the given format.
     * Returns null when the string is empty or unparseable.
     */
    protected function parseDate(?string $value, string $format = 'Ymd'): ?\DateTime
    {
        if ($value === null || $value === '') {
            return null;
        }
        $dt = \DateTime::createFromFormat($format, $value);
        return $dt !== false ? $dt : null;
    }

    /**
     * Build an AddressData object from individual fields,
     * returning null when every field is empty.
     */
    protected function buildAddress(
        ?string $lineOne,
        ?string $postCode,
        ?string $city,
        ?string $countryCode,
        ?string $lineTwo = null,
        ?string $lineThree = null,
        ?string $stateCode = null
    ): ?AddressData {
        if (!$lineOne && !$postCode && !$city && !$countryCode) {
            return null;
        }
        $address = new AddressData();
        $address->lineOne = $lineOne ?: null;
        $address->lineTwo = $lineTwo ?: null;
        $address->lineThree = $lineThree ?: null;
        $address->postCode = $postCode ?: null;
        $address->city = $city ?: null;
        $address->countryCode = $countryCode ?: null;
        $address->stateCode = $stateCode ?: null;
        return $address;
    }

    /**
     * Build a ContactData object, returning null when all fields are empty.
     */
    protected function buildContact(
        ?string $name,
        ?string $phone,
        ?string $email,
        ?string $department = null
    ): ?ContactData {
        if (!$name && !$phone && !$email && !$department) {
            return null;
        }
        $contact = new ContactData();
        $contact->name = $name ?: null;
        $contact->phone = $phone ?: null;
        $contact->email = $email ?: null;
        $contact->department = $department ?: null;
        return $contact;
    }
}
