<?php

namespace DigitalInvoice;

/**
 * Main facade for reading e-invoices in any supported format.
 *
 * Single entry point: read() auto-detects XML vs PDF, then delegates to the
 * appropriate parser based on the XML root element.
 *
 * Supported formats:
 *   - FacturX / ZUGFeRD 2.1.1 / XRechnung  → CiiParser   (CrossIndustryInvoice)
 *   - ZUGFeRD 1.0                            → ZugferdParser (CrossIndustryDocument)
 *   - UBL 2.1 / Peppol / CIUS-*             → UblParser   (Invoice)
 *
 * Usage:
 *   $data = InvoiceReader::read($xmlString);
 *   $data = InvoiceReader::read($pdfContent);   // FacturX / ZUGFeRD PDF
 *   $data = InvoiceReader::fromXml($xmlString); // explicit XML
 */
class InvoiceReader
{
    /**
     * Unified entry point: accepts XML or PDF content (or file path for PDF).
     * PDF input is routed to CiiParser since only CII-based formats (FacturX,
     * ZUGFeRD) embed XML in PDFs.
     */
    public static function read(string $input): InvoiceData
    {
        if (self::looksLikeXml($input)) {
            return self::fromXml($input);
        }

        // Reject anything that is not PDF binary content.
        // The Facturx library calls @is_file() on its input, so passing a
        // filesystem path would silently read an arbitrary local file.
        if (!str_starts_with($input, '%PDF-')) {
            throw new \InvalidArgumentException(
                'InvoiceReader: input is neither valid XML nor a PDF file.'
            );
        }

        return (new CiiParser())->parsePdf($input);
    }

    /**
     * Parse an e-invoice from an XML string.
     *
     * @throws \Exception if the format cannot be detected or parsing fails
     */
    public static function fromXml(string $xml): InvoiceData
    {
        return self::detectParser($xml)->parse($xml);
    }

    /**
     * Detect the correct parser by inspecting the XML root element.
     *
     * Detection rules:
     *   localName == 'Invoice'               → UBL (Peppol, CIUS, Malaysia…)
     *   localName == 'CrossIndustryInvoice'  → CII (FacturX / ZUGFeRD 2.1.1 / XRechnung)
     *   localName == 'CrossIndustryDocument' → ZUGFeRD 1.0
     */
    private static function detectParser(string $xml): XmlParser
    {
        // Block DOCTYPE declarations to prevent billion-laughs entity expansion.
        if (stripos($xml, '<!doctype') !== false) {
            throw new \Exception('InvoiceReader: DOCTYPE declarations are not allowed.');
        }

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml, LIBXML_NONET);
        libxml_use_internal_errors(false);

        if (!$loaded || !$doc->documentElement) {
            throw new \Exception('InvoiceReader: failed to parse XML document.');
        }

        $localName = $doc->documentElement->localName;

        return match ($localName) {
            'Invoice'               => new UblParser(),
            'CrossIndustryInvoice'  => new CiiParser(),
            'CrossIndustryDocument' => new ZugferdParser(),
            default => throw new \Exception(
                "InvoiceReader: unknown invoice format (root element: <$localName>)."
            ),
        };
    }

    /**
     * A string is considered XML if, after stripping leading whitespace,
     * it starts with '<'. PDF binaries never start with '<'.
     */
    private static function looksLikeXml(string $input): bool
    {
        return str_starts_with(ltrim($input), '<');
    }
}
