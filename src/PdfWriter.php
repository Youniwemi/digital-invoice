<?php

namespace DigitalInvoice;

use Atgp\FacturX\Facturx ;
use Atgp\FacturX\Fpdi\FdpiFacturx;

// We need to overide, Atgp is not full compatible with zugferd1.0 so we by pass
// We also need to overide generateFacturxFromFiles to use setSourceFileWithParserParams instead of setSourceFile (and pass params)
class PdfWriter extends Facturx
{
    public const FACTURX_PROFIL_TO_XMP = [
        self::PROFIL_FACTURX_MINIMUM => 'MINIMUM',
        self::PROFIL_FACTURX_BASICWL => 'BASIC WL',
        self::PROFIL_FACTURX_BASIC => 'BASIC',
        self::PROFIL_FACTURX_EN16931 => 'EN 16931',
        self::PROFIL_FACTURX_EXTENDED => 'EXTENDED',
        self::PROFIL_ZUGFERD => 'ZUGFERD',
    ];
    protected $pdfMetaData;

    public function setPdfMetaData($invoiceInformations)
    {

        $dateString = date('Y-m-d', strtotime($invoiceInformations['date']));
        $title = sprintf('%s : %s %s', $invoiceInformations['seller'], $invoiceInformations['docTypeName'], $invoiceInformations['invoiceId']);
        $subject = sprintf('Factur-X %s %s dated %s issued by %s', $invoiceInformations['docTypeName'], $invoiceInformations['invoiceId'], $dateString, $invoiceInformations['seller']);
        $this->pdfMetaData = [
            'author' => $invoiceInformations['seller'],
            'keywords' => sprintf('%s, Factur-X', $invoiceInformations['docTypeName']),
            'title' => $title,
            'subject' => $subject,
            'createdDate' => $invoiceInformations['date'],
            'modifiedDate' => date('Y-m-d\TH:i:s').'+00:00',
        ];
    }

    protected function preparePdfMetadata(\DOMDocument $facturxXml)
    {
        return $this->pdfMetaData;
    }

    /**
     * Get Factur-X profil.
     *
     * @param \DOMDocument $facturxXml
     *
     * @throws \Exception
     *
     * @return string
     */
    public function getFacturxProfil(\DOMDocument $facturxXml)
    {
        if (! $facturxXml instanceof \DOMDocument) {
            throw new \Exception('$facturxXml must be a DOMDocument object');
        }
        $xpath = new \DOMXpath($facturxXml);
        $elements = $xpath->query('//rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID');
        if (0 == $elements->length) {
            // maybe zugferd
            $elements = $xpath->query('//rsm:SpecifiedExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID');

            if (0 == $elements->length) {
                throw new \Exception('This XML is not a Factur-X XML because it misses the XML
                tag ExchangedDocumentContext/GuidelineSpecifiedDocumentContextParameter/ram:ID  and SpecifiedExchangedDocumentContext/GuidelineSpecifiedDocumentContextParameter/ram:ID .');
            } else {
                return 'zugferd';
            }
        }
        $doc_id = $elements->item(0)->nodeValue;
        $doc_id_exploded = explode(':', $doc_id);
        $profil = end($doc_id_exploded);
        if (! array_key_exists(strtolower($profil), static::FACTURX_PROFIL_TO_XSD)) {
            $profil = $doc_id_exploded[count($doc_id_exploded) - 2];
        }
        if (! array_key_exists(strtolower($profil), static::FACTURX_PROFIL_TO_XSD)) {
            throw new \Exception('Invalid Factur-X URN : '.$doc_id. $profil);
        }

        return $profil;
    }

    /**
     * Generate Factur-X PDF from PDF invoice and Factur-X XML.
     * Overriden form original function to allow passing Fpdi Parser Params
     *
     *
     * @param string      $pdfInvoice            File name or content of the PDF invoice
     * @param string      $facturxXml            File name or content of the XML invoice
     * @param string|null $facturxProfil         One of \Atgp\FacturX\Facturx::PROFIL_* (null for auto-detection)
     * @param bool        $checkXsd              check Factur-X XML against official XSD
     * @param string      $outputFilePath        Output file path for PDF Factur-X, if empty, file string will be returned
     * @param bool        $addFacturxLogo        Add Factur-X logo on PDF first page according to Factur-X profil
     * @param mixed       $additionalAttachments
     * @param string      $relationship          the embarkation relationship, must be Data|Source|Alternative
     * @param string      $fpdiParserParams          Fpdi Parser Params (can be used to provide options such as authentication info)
     *
     * @throws \Exception
     *
     * @return string
     */
    public function generateFacturxFromFiles(
        $pdfInvoice,
        $facturxXml,
        $facturxProfil = null,
        $checkXsd = true,
        $outputFilePath = '',
        $additionalAttachments = [],
        $addFacturxLogo = false,
        $relationship = 'Data',
        $fpdiParserParams = []
    ) {
        $pdfInvoiceRef = null;
        if (@is_file($pdfInvoice)) {
            $pdfInvoiceRef = $pdfInvoice;
        } elseif (is_string($pdfInvoice)) {
            $pdfInvoiceRef = \setasign\Fpdi\PdfParser\StreamReader::createByString($pdfInvoice);
        }
        if (@is_file($facturxXml)) {
            $xmlString = file_get_contents($facturxXml);
            $facturxXmlRef = $facturxXml;
        } elseif (is_string($facturxXml)) {
            if ('<?xml' != substr($facturxXml, 0, 5)) { // Add XML tags
                $facturxXml = "<?xml version='1.0' encoding='".static::FACTURX_ENCODING."' ?>\n".$facturxXml;
            }
            $xmlString = $facturxXml;
            $facturxXmlRef = \setasign\Fpdi\PdfParser\StreamReader::createByString($facturxXml);
        } else {
            throw new \Exception('$facturxXml argument must be a string or a file');
        }
        $docFacturx = new \DOMDocument();
        $docFacturx->loadXML($xmlString);

        if (null === $facturxProfil) {
            $facturxProfil = $this->getFacturxProfil($docFacturx);
        }

        if (! array_key_exists($facturxProfil, static::FACTURX_PROFIL_TO_XSD)) {
            throw new \Exception("Wrong profil '$facturxProfil' for Factur-X invoice.");
        }

        // changed from original : profil is private, so we skip updating it here (it is set by checkFacturxXsd )
        // $this->profil = $facturxProfil;
        // So we force the xsd check to ensure $this->profil is set correctly (it will be used in updatePdfMetadata to set pdf metadata)
        $this->checkFacturxXsd($facturxXml, $facturxProfil);

        $pdfWriter = new FdpiFacturx();
        $pageCount = $pdfWriter->setSourceFileWithParserParams($pdfInvoiceRef, $fpdiParserParams);
        for ($i = 1; $i <= $pageCount; ++$i) {
            $tplIdx = $pdfWriter->importPage($i, '/MediaBox');
            $pdfWriter->AddPage();
            $pdfWriter->useTemplate($tplIdx, 0, 0, null, null, true);
            if (true == $addFacturxLogo && 1 == $i) { // add Factur-X logo on first page only
                $pdfWriter->Image(__DIR__.'/../img/'.static::FACTURX_LOGO[$facturxProfil], 197, 2.5, 7);
            }
        }
        if (! in_array($relationship, ['Data', 'Source', 'Alternative'])) {
            throw new \Exception('$relationship argument must be one of the values "Data", "Source", "Alternative".');
        }
        $pdfWriter->Attach($facturxXmlRef, static::FACTURX_FILENAME, 'Factur-X Invoice', $relationship, 'text#2Fxml');
        foreach ($additionalAttachments as $attachment) {
            if (@is_file($attachment['path'])) {
                $attachment_file_ref = $attachment['path'];
            } elseif (is_string($attachment['path'])) {
                $attachment_file_ref = sys_get_temp_dir().'/'.$attachment['name'];
                file_put_contents($attachment_file_ref, $attachment['path']); // creating tmp file to solve mime_content_type errors
            } else {
                throw new \Exception('$attachment_file argument must be a string or a file');
            }
            $pdfWriter->Attach($attachment_file_ref, $attachment['name'], $attachment['desc']);
        }
        $pdfWriter->OpenAttachmentPane();
        $pdfWriter->SetPDFVersion('1.7', true); // version 1.7 according to PDF/A-3 ISO 32000-1
        $pdfWriter = $this->updatePdfMetadata($pdfWriter, $docFacturx);
        $facturxGeneratedFileName = 'invoice-facturx-'.date('Ymdhis').'.pdf';
        if (! empty($outputFilePath)) {
            return $this->generateFacturxFile($pdfWriter, $outputFilePath, $facturxGeneratedFileName);
        }

        return $this->generateFacturxString($pdfWriter, $facturxGeneratedFileName);
    }
}
