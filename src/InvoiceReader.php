<?php

namespace DigitalInvoice;

/**
 * Main facade for reading e-invoices in any supported format.
 *
 * Mirrors the Invoice writing facade: auto-detects the format from the XML
 * root element and delegates to the appropriate XmlParser subclass.
 *
 * Supported formats:
 *   - FacturX / ZUGFeRD 2.1.1 / XRechnung  (Cross Industry Invoice)
 *   - ZUGFeRD 1.0                            (Cross Industry Document)
 *   - UBL 2.1 / Peppol / CIUS-*             (UBL Invoice)
 *
 * Usage:
 *   $data = InvoiceReader::fromXml($xmlString);
 *   $data = InvoiceReader::fromPdf($pdfPathOrContent);
 */
class InvoiceReader
{
    /**
     * Parse an e-invoice from an XML string.
     *
     * @throws \Exception if the format cannot be detected or parsing fails
     */
    public static function fromXml(string $xml): InvoiceData
    {
        $parser = self::detectParser($xml);
        return $parser->parse($xml);
    }

    /**
     * Parse an e-invoice from a PDF that embeds an XML attachment
     * (FacturX / ZUGFeRD).
     *
     * Accepts either a file path or the raw PDF binary content.
     *
     * @throws \Exception if no XML attachment is found or format is unsupported
     */
    public static function fromPdf(string $pdfPathOrContent): InvoiceData
    {
        $pdfWriter = new PdfWriter();
        $xml = $pdfWriter->getFacturxXmlFromPdf($pdfPathOrContent);
        return self::fromXml($xml);
    }

    /**
     * Detect the correct parser by inspecting the XML root element.
     *
     * Detection rules (order matters):
     *   localName == 'Invoice'               → UBL (Peppol, CIUS, Malaysia…)
     *   localName == 'CrossIndustryInvoice'  → FacturX / ZUGFeRD 2.1.1 / XRechnung
     *   localName == 'CrossIndustryDocument' → ZUGFeRD 1.0
     */
    private static function detectParser(string $xml): XmlParser
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml);
        libxml_use_internal_errors(false);

        if (!$loaded || !$doc->documentElement) {
            throw new \Exception('InvoiceReader: failed to parse XML document.');
        }

        $localName = $doc->documentElement->localName;

        return match ($localName) {
            'Invoice'               => new UblParser(),
            'CrossIndustryInvoice'  => new FacturXParser(),
            'CrossIndustryDocument' => new ZugferdParser(),
            default => throw new \Exception(
                "InvoiceReader: unknown invoice format (root element: <$localName>)."
            ),
        };
    }
}
