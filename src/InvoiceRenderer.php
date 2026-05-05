<?php

namespace DigitalInvoice;

/**
 * Renders an InvoiceData object to HTML using a PHP template.
 *
 * Usage:
 *   $html = (new InvoiceRenderer())->render($data);
 *   $html = (new InvoiceRenderer('/path/to/custom.html.php'))->render($data);
 */
class InvoiceRenderer
{
    private string $templatePath;

    public function __construct(?string $templatePath = null)
    {
        $this->templatePath = $templatePath ?? __DIR__ . '/templates/invoice.html.php';
    }

    /**
     * Render the invoice data to an HTML string.
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

        ob_start();
        $invoice = $data;
        include $this->templatePath;
        return (string) ob_get_clean();
    }
}
