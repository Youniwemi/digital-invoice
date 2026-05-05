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
