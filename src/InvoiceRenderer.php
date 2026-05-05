<?php

namespace DigitalInvoice;

/**
 * Renders an InvoiceData object to HTML using a PHP template.
 *
 * Helper variables injected into the template scope:
 *   $invoice  InvoiceData object
 *   $cur      Currency code string
 *   $esc      fn(?string): string  — HTML-safe output
 *   $fmt      fn(?float, string): string  — formatted monetary amount
 *   $date     fn(?\DateTime): string  — formatted date or '—'
 *
 * Usage:
 *   $renderer = new InvoiceRenderer();
 *   $html     = $renderer->render($data);
 *   $css      = $renderer->getStyles();          // embed in <style> tag
 *
 *   // Override template or CSS independently:
 *   $renderer = new InvoiceRenderer('/my/template.php', '/my/styles.css');
 */
class InvoiceRenderer
{
    private string $templatePath;
    private string $cssPath;

    public function __construct(?string $templatePath = null, ?string $cssPath = null)
    {
        $this->templatePath = $templatePath ?? __DIR__ . '/templates/invoice.html.php';
        $this->cssPath      = $cssPath      ?? __DIR__ . '/templates/invoice.css';
    }

    /**
     * Render the invoice to an HTML fragment.
     *
     * @throws \InvalidArgumentException if the template file does not exist
     */
    public function render(InvoiceData $data): string
    {
        if (!file_exists($this->templatePath)) {
            throw new \InvalidArgumentException(
                "InvoiceRenderer: template not found at '{$this->templatePath}'."
            );
        }

        // Helpers available in template scope via local variable capture
        $invoice = $data;
        $cur     = $data->currency;
        $esc     = static fn (?string $s): string =>
            htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fmt     = static fn (?float $v, string $currency = ''): string =>
            $v === null ? '—' : number_format($v, 2, '.', ' ') . ($currency ? ' ' . $currency : '');
        $date    = static fn (?\DateTime $d): string =>
            $d ? $d->format('Y-m-d') : '—';

        $styles = $this->getStyles();

        ob_start();
        if ($styles !== '') {
            echo '<style>', $styles, '</style>';
        }
        include $this->templatePath;
        return (string) ob_get_clean();
    }

    /**
     * Return the raw CSS for the default invoice styles.
     * Returns an empty string when the CSS file does not exist
     * (e.g. a custom template with its own styles).
     */
    public function getStyles(): string
    {
        if (!file_exists($this->cssPath)) {
            return '';
        }
        return (string) file_get_contents($this->cssPath);
    }
}
