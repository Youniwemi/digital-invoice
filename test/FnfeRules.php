<?php

namespace DigitalInvoice\Tests;

/**
 * Runs the FNFE validation artefacts (https://github.com/fnfempe/France_RFE) with Saxon-HE.
 * The rules are XSLT 2.0: `make fnfe` downloads them and Saxon into test/fnfe.
 */
trait FnfeRules
{
    protected static function fnfeAvailable(): bool
    {
        return is_file(__DIR__.'/fnfe/saxon-he.jar');
    }

    /**
     * Asserts the xml passes the given FNFE stylesheets (e.g. FACTUR-X_EN16931, BR-FR-CII), warnings are ignored
     */
    protected function assertFnfeRules(string $xml, array $stylesheets): void
    {
        if (!self::fnfeAvailable()) {
            return;
        }
        foreach ($stylesheets as $stylesheet) {
            $errors = $this->fnfeErrors($xml, $stylesheet);
            $this->assertEmpty($errors, "$stylesheet\n".implode("\n", $errors)."\n".$xml);
        }
    }

    /**
     * Returns the failed asserts, except warnings, as "ID: message"
     */
    private function fnfeErrors(string $xml, string $stylesheet): array
    {
        $file = tempnam(sys_get_temp_dir(), 'fnfe');
        file_put_contents($file, $xml);
        exec(sprintf(
            'java -jar %s -s:%s -xsl:%s 2>&1',
            escapeshellarg(__DIR__.'/fnfe/saxon-he.jar'),
            escapeshellarg($file),
            escapeshellarg(__DIR__."/fnfe/$stylesheet.xslt")
        ), $output, $code);
        unlink($file);
        $svrl = implode("\n", $output);
        $this->assertSame(0, $code, $svrl);

        $doc = new \DOMDocument();
        $doc->loadXML($svrl);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('svrl', 'http://purl.oclc.org/dsdl/svrl');
        $errors = [];
        foreach ($xpath->query('//svrl:failed-assert[not(@flag="warning") and not(@flag="information")]') as $assert) {
            $errors[] = $assert->getAttribute('id').': '.trim(preg_replace('/\s+/', ' ', $assert->textContent));
        }
        return $errors;
    }
}
