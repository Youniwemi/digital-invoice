<?php

namespace DigitalInvoice;

/**
 * Renders an InvoiceData object to HTML using a PHP template.
 *
 * Variables injected into the template scope:
 *   $invoice       InvoiceData
 *   $cur           currency code string
 *   $labels        array<string,string>  — translated UI labels
 *   $esc           fn(?string): string   — HTML-safe output
 *   $fmt           fn(?float, string): string — formatted amount
 *   $date          fn(?\DateTime): string — formatted date or '—'
 *   $schemeLabel   fn(string): string    — human label for an ISO 6523 / tax scheme ID
 *
 * Usage:
 *   $html = (new InvoiceRenderer())->render($data);
 *   $html = (new InvoiceRenderer(lang: 'fr'))->render($data);
 *   $html = (new InvoiceRenderer('/my/template.php', '/my/styles.css', 'de'))->render($data);
 */
class InvoiceRenderer
{
    /** Profile URN/class fragments → human-readable format badge */
    private const FORMAT_LABELS = [
        'MINIMUM'           => 'FacturX Minimum',
        'BASIC_WL'          => 'FacturX Basic WL',
        'BASIC'             => 'FacturX Basic',
        'EN16931'           => 'FacturX EN 16931',
        'EXTENDED'          => 'FacturX Extended',
        'XRECHNUNG'         => 'XRechnung',
        'comfort'           => 'ZUGFeRD 1.0 Comfort',
        'basic'             => 'ZUGFeRD 1.0 Basic',
        'extended'          => 'ZUGFeRD 1.0 Extended',
        'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0' => 'Peppol BIS 3.0',
        'urn:cen.eu:en16931:2017#compliant#urn:fdc:nen.nl:nlcius:v1.0' => 'NLCIUS',
        'urn:cen.eu:en16931:2017#compliant#urn:fdc:cius-ro.eu:en16931:2017' => 'CIUS-RO',
        'urn:cen.eu:en16931:2017#compliant#urn:fdc:www.agid.gov.it:2018:peppol:billing:3.0' => 'CIUS-IT',
    ];

    /** ISO 6523 and tax-scheme codes → display label */
    private const SCHEME_LABELS = [
        '0002' => 'SIRET',
        '0009' => 'SIRET',
        '0183' => 'SIREN',
        '0060' => 'DUNS',
        '0088' => 'EAN',
        '0190' => 'LEI',
        '0192' => 'Org. nr.',    // NO
        '0196' => 'KEID',        // DK
        '0208' => 'KBO/BCE',     // BE
        '0210' => 'NIF',         // ES
        '0211' => 'NIF-IVA',     // ES
        'VA'   => 'TVA / VAT',
        'FC'   => 'Tax number',
        'GS1'  => 'GLN',
    ];

    private string $templatePath;
    private string $cssPath;
    private string $lang;

    public function __construct(
        ?string $templatePath = null,
        ?string $cssPath = null,
        string $lang = 'en'
    ) {
        $this->templatePath = $templatePath ?? __DIR__ . '/templates/invoice.html.php';
        $this->cssPath      = $cssPath      ?? __DIR__ . '/templates/invoice.css';
        $this->lang         = $lang;
    }

    /**
     * Render the invoice to a self-contained HTML fragment (styles inlined).
     *
     * @throws \InvalidArgumentException if the template file does not exist
     */
    public function render(InvoiceData $data): string
    {
        if (!file_exists($this->templatePath)) {
            throw new \InvalidArgumentException(
                'InvoiceRenderer: template file not found.'
            );
        }

        $invoice = $data;
        $cur     = $data->currency;
        $labels  = $this->loadLabels();

        $esc = static fn (?string $s): string =>
            htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $fmt = static fn (?float $v, string $currency = ''): string =>
            $v === null ? '—' : number_format($v, 2, '.', ' ')
                . ($currency ? ' ' . htmlspecialchars($currency, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '');

        $date = static fn (?\DateTime $d): string =>
            $d ? $d->format('Y-m-d') : '—';

        $schemeMap   = self::SCHEME_LABELS;
        $schemeLabel = static fn (string $scheme): string => $schemeMap[$scheme] ?? $scheme;

        $formatMap   = self::FORMAT_LABELS;
        $formatLabel = static function (string $profile) use ($formatMap): string {
            if (isset($formatMap[$profile])) {
                return $formatMap[$profile];
            }
            // Match by URN fragment or class basename
            foreach ($formatMap as $key => $label) {
                if (str_contains($profile, $key)) {
                    return $label;
                }
            }
            return '';
        };

        $styles = $this->getStyles();

        ob_start();
        if ($styles !== '') {
            echo '<style>', $styles, '</style>';
        }
        include $this->templatePath;
        return (string) ob_get_clean();
    }

    /**
     * Return the raw CSS content for the invoice styles.
     * Returns an empty string when the CSS file does not exist.
     */
    public function getStyles(): string
    {
        if (!file_exists($this->cssPath)) {
            return '';
        }
        return (string) file_get_contents($this->cssPath);
    }

    private function loadLabels(): array
    {
        $i18nDir = __DIR__ . '/i18n/';
        $path    = $i18nDir . $this->lang . '.php';

        if (!file_exists($path)) {
            $path = $i18nDir . 'en.php';
        }

        return (array) require $path;
    }
}
