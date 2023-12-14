<?php

namespace DigitalInvoice;


use Atgp\FacturX\Facturx ;



// We need to overide, Atgp is not full compatible with zugferd1.0 so we by pass
class PdfWriter extends Facturx {
    const FACTURX_PROFIL_TO_XMP = [
        self::PROFIL_FACTURX_MINIMUM => 'MINIMUM',
        self::PROFIL_FACTURX_BASICWL => 'BASIC WL',
        self::PROFIL_FACTURX_BASIC => 'BASIC',
        self::PROFIL_FACTURX_EN16931 => 'EN 16931',
        self::PROFIL_FACTURX_EXTENDED => 'EXTENDED',
        self::PROFIL_ZUGFERD => 'ZUGFERD'
    ];
    protected $pdfMetaData;
    public function setPdfMetaData($invoiceInformations){
        
        $dateString = date('Y-m-d', strtotime($invoiceInformations['date']));
        $title = sprintf('%s : %s %s', $invoiceInformations['seller'], $invoiceInformations['docTypeName'], $invoiceInformations['invoiceId']);
        $subject = sprintf('Factur-X %s %s dated %s issued by %s', $invoiceInformations['docTypeName'], $invoiceInformations['invoiceId'], $dateString, $invoiceInformations['seller']);
        $this->pdfMetaData = [
            'author' => $invoiceInformations['seller'],
            'keywords' => sprintf('%s, Factur-X',$invoiceInformations['docTypeName']),
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
        if (!$facturxXml instanceof \DOMDocument) {
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
        if (!array_key_exists(strtolower($profil), static::FACTURX_PROFIL_TO_XSD)) {
            $profil = $doc_id_exploded[count($doc_id_exploded) - 2];
        }
        if (!array_key_exists(strtolower($profil), static::FACTURX_PROFIL_TO_XSD)) {
            throw new \Exception('Invalid Factur-X URN : '.$doc_id. $profil);
        }

        return $profil;
    }

}