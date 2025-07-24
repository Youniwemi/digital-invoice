<?php
namespace DigitalInvoice\Presets;

use Einvoicing\Invoice;
use Einvoicing\Presets\AbstractPreset;


class Malaysia extends AbstractPreset {
    /**
     * @inheritdoc
     */
    public function getSpecification(): string {
        return "";
    }


    /**
     * @inheritdoc
     */
    public function getRules(): array {
        $res = [];
        return $res;
    }


    /**
     * @inheritdoc
     */
    public function setupInvoice(Invoice $invoice) {
        parent::setupInvoice($invoice);
        if ($invoice->getType()===Invoice::TYPE_COMMERCIAL_INVOICE){
            $invoice->setType(1);
        }
    }

    public function finalizeInvoice(Invoice $invoice) {
        if ($invoice->getType()===Invoice::TYPE_COMMERCIAL_INVOICE){
            $invoice->setType(1);
        }
    }

}
