<?php
namespace DigitalInvoice\Presets;

use Einvoicing\Invoice;
use Einvoicing\Presets\AbstractPreset;
use DigitalInvoice\EnumToArray;
use DigitalInvoice\Label;

/*
 * Malaysian Tax Type Codes (MyInvois API)
 */
enum MalaysianTaxType: string
{
    use EnumToArray;

    #[Label("Sales Tax")]
    case SALES_TAX = "01";
    
    #[Label("Service Tax")]
    case SERVICE_TAX = "02";
    
    #[Label("Tourism Tax")]
    case TOURISM_TAX = "03";
    
    #[Label("High-Value Goods Tax")]
    case HIGH_VALUE_GOODS_TAX = "04";
    
    #[Label("Sales Tax on Low Value Goods")]
    case LOW_VALUE_GOODS_TAX = "05";
    
    #[Label("Not Applicable")]
    case NOT_APPLICABLE = "06";
    
    #[Label("Tax Exemption")]
    case TAX_EXEMPTION = "E";
}

/*
 * Malaysian Tax Scheme Constants (MyInvois API)
 */
enum MalaysianTaxScheme: string
{
    case SCHEME_ID = "OTH";
    case SCHEME_LIST_ID = "UN/ECE 5153";
    case SCHEME_AGENCY_ID = "6";
}


class Malaysia extends AbstractPreset {
    /**
     * @inheritdoc
     */
    public function getSpecification(): string {
        return '';
    }


    /**
     * @inheritdoc
     */
    public function getRules(): array {
        $res = [];

        // Supplier mandatory fields
        $res['BR-MY-001'] = static function(Invoice $inv) {
            $supplier = $inv->getSeller();
            if (!$supplier || !$supplier->getName()) {
                return "Supplier Name is mandatory";
            }
        };

        $res['BR-MY-002'] = static function(Invoice $inv) {
            $supplier = $inv->getSeller();
            if (!$supplier) return "Supplier TIN is mandatory";
            
            $identifiers = $supplier->getIdentifiers();
            $hasTIN = false;
            foreach ($identifiers as $id) {
                if ($id->getScheme() === 'TIN') {
                    $hasTIN = true;
                    if (strlen($id->getValue()) > 14) {
                        return "Supplier TIN must not exceed 14 characters";
                    }
                    break;
                }
            }
            if (!$hasTIN) {
                return "Supplier TIN is mandatory";
            }
        };

        $res['BR-MY-003'] = static function(Invoice $inv) {
            $supplier = $inv->getSeller();
            if (!$supplier) return "Supplier Registration Number is mandatory";
            
            // Check both identifiers and company ID for valid registration numbers
            $identifiers = $supplier->getIdentifiers();
            $hasValidId = false;
            $validSchemes = ['BRN', 'NRIC', 'PASSPORT', 'ARMY'];
            
            // Check in identifiers
            foreach ($identifiers as $id) {
                if (in_array($id->getScheme(), $validSchemes)) {
                    $hasValidId = true;
                    break;
                }
            }
            
            // Also check company ID which might contain BRN
            if (!$hasValidId && $supplier->getCompanyId()) {
                $companyId = $supplier->getCompanyId();
                if ($companyId && in_array($companyId->getScheme(), $validSchemes)) {
                    $hasValidId = true;
                }
            }
            
            if (!$hasValidId) {
                return "Supplier Registration Number (BRN/NRIC/PASSPORT/ARMY) is mandatory";
            }
        };

        $res['BR-MY-004'] = static function(Invoice $inv) {
            $supplier = $inv->getSeller();
            if (!$supplier) {
                return "Supplier Address is mandatory";
            }
            $address = $supplier->getAddress();
            if (!$address || empty($address[0])) {
                return "Supplier Street Name is mandatory";
            }
            if (!$supplier->getCity()) {
                return "Supplier City Name is mandatory";
            }
            if (!$supplier->getPostalCode()) {
                return "Supplier Postal Zone is mandatory";
            }
            if (!$supplier->getCountry()) {
                return "Supplier Country Code is mandatory";
            }
            // State code is mandatory for Malaysian addresses
            if (!$supplier->getSubdivision()) {
                return "Supplier State Code is mandatory";
            }
        };

        $res['BR-MY-005'] = static function(Invoice $inv) {
            $supplier = $inv->getSeller();
            if (!$supplier || !$supplier->getContactPhone()) {
                return "Supplier Contact Number is mandatory";
            }
        };

        $res['BR-MY-005a'] = static function(Invoice $inv) {
            $supplier = $inv->getSeller();
            if (!$supplier) return null; // TODO: MSIC Code validation - disabled for now
            
            // MSIC code should be in supplier's additional fields or identifiers
            // This would need to be stored as custom data in the supplier object
            // For now, we'll check if it exists in any identifier with scheme 'MSIC'
            $identifiers = $supplier->getIdentifiers();
            $hasMSIC = false;
            foreach ($identifiers as $id) {
                if ($id->getScheme() === 'MSIC') {
                    $value = $id->getValue();
                    if (!preg_match('/^\d{5}$/', $value)) {
                        return "MSIC Code must be exactly 5 digits";
                    }
                    $hasMSIC = true;
                    break;
                }
            }
            // Temporarily disabled - would need proper implementation
            // if (!$hasMSIC) {
            //     return "Supplier MSIC Code is mandatory";
            // }
            return null;
        };

        $res['BR-MY-005b'] = static function(Invoice $inv) {
            $supplier = $inv->getSeller();
            if (!$supplier) return "Supplier Business Activity Description is mandatory";
            
            // Business activity description would need to be stored as custom data
            // For now, we'll assume it should be in supplier description or additional data
            // This validation would need enhancement in the actual implementation
            // We'll implement a basic check for now
            return null; // Placeholder - would need proper field mapping
        };

        $res['BR-MY-005c'] = static function(Invoice $inv) {
            $supplier = $inv->getSeller();
            if (!$supplier) return null;
            
            // SST Registration is conditional - check if supplier has SST identifier
            $identifiers = $supplier->getIdentifiers();
            foreach ($identifiers as $id) {
                if ($id->getScheme() === 'SST') {
                    $value = $id->getValue();
                    if (strlen($value) > 35) {
                        return "SST Registration Number must not exceed 35 characters";
                    }
                }
            }
            return null; // SST is conditional, not always mandatory
        };

        // Buyer mandatory fields
        $res['BR-MY-006'] = static function(Invoice $inv) {
            $buyer = $inv->getBuyer();
            if (!$buyer || !$buyer->getName()) {
                return "Buyer Name is mandatory";
            }
        };

        $res['BR-MY-007'] = static function(Invoice $inv) {
            $buyer = $inv->getBuyer();
            if (!$buyer) return "Buyer TIN is mandatory";
            
            // Check company ID for TIN (buyer TIN is stored as company ID in UBL)
            $companyId = $buyer->getCompanyId();
            if ($companyId && $companyId->getScheme() === 'TIN') {
                if (strlen($companyId->getValue()) > 14) {
                    return "Buyer TIN must not exceed 14 characters";
                }
                return null; // TIN found and valid
            }
            
            // Also check identifiers as fallback
            $identifiers = $buyer->getIdentifiers();
            $hasTIN = false;
            foreach ($identifiers as $id) {
                if ($id->getScheme() === 'TIN') {
                    $hasTIN = true;
                    if (strlen($id->getValue()) > 14) {
                        return "Buyer TIN must not exceed 14 characters";
                    }
                    break;
                }
            }
            if (!$hasTIN) {
                return "Buyer TIN is mandatory";
            }
        };

        $res['BR-MY-008'] = static function(Invoice $inv) {
            $buyer = $inv->getBuyer();
            if (!$buyer) {
                return "Buyer Address is mandatory";
            }
            $address = $buyer->getAddress();
            if (!$address || empty($address[0])) {
                return "Buyer Street Name is mandatory";
            }
            if (!$buyer->getCity()) {
                return "Buyer City Name is mandatory";
            }
            if (!$buyer->getPostalCode()) {
                return "Buyer Postal Zone is mandatory";
            }
            if (!$buyer->getCountry()) {
                return "Buyer Country Code is mandatory";
            }
            // State code is mandatory for Malaysian addresses
            if (!$buyer->getSubdivision()) {
                return "Buyer State Code is mandatory";
            }
        };

        $res['BR-MY-009'] = static function(Invoice $inv) {
            $buyer = $inv->getBuyer();
            if (!$buyer || !$buyer->getContactPhone()) {
                return "Buyer Contact Number is mandatory";
            }
        };

        $res['BR-MY-009a'] = static function(Invoice $inv) {
            $buyer = $inv->getBuyer();
            if (!$buyer) return null;
            
            // SST Registration is conditional for buyer - check if buyer has SST identifier
            $identifiers = $buyer->getIdentifiers();
            foreach ($identifiers as $id) {
                if ($id->getScheme() === 'SST') {
                    $value = $id->getValue();
                    if (strlen($value) > 35) {
                        return "Buyer SST Registration Number must not exceed 35 characters";
                    }
                }
            }
            return null; // SST is conditional, not always mandatory
        };

        // Invoice core details
        $res['BR-MY-010'] = static function(Invoice $inv) {
            // InvoiceTypeCode listVersionID check - this would need to be checked at XML level
            // For now, we assume it's handled by the XML generation
            return null;
        };

        $res['BR-MY-010a'] = static function(Invoice $inv) {
            // e-Invoice Version is mandatory - would typically be "1.0" for MyInvois
            // This would need to be stored as metadata or custom field
            // For now, we assume it's handled at XML generation level
            return null; // Version validation would need proper field mapping
        };

        $res['BR-MY-011'] = static function(Invoice $inv) {
            $type = $inv->getType();
            if ($type === null) {
                return "Invoice Type Code is mandatory";
            }
            
            // Valid Malaysian invoice type codes
            $validMalaysianTypes = ['01', '02', '03', '04', '11', '12', '13', '14'];
            
            // Allow standard UBL types that will be converted
            $validUblTypes = [
                Invoice::TYPE_COMMERCIAL_INVOICE,
                Invoice::TYPE_TAX_INVOICE,
                Invoice::TYPE_CREDIT_NOTE,
                Invoice::TYPE_CREDIT_NOTE_RELATED_TO_GOODS_OR_SERVICES,
                Invoice::TYPE_CREDIT_NOTE_RELATED_TO_FINANCIAL_ADJUSTMENTS,
                Invoice::TYPE_DEBIT_NOTE,
                Invoice::TYPE_DEBIT_NOTE_RELATED_TO_GOODS_OR_SERVICES,
                Invoice::TYPE_DEBIT_NOTE_RELATED_TO_FINANCIAL_ADJUSTMENTS
            ];
            
            if (!in_array($type, $validMalaysianTypes) && !in_array($type, $validUblTypes)) {
                return "Invoice Type Code must be a valid Malaysian type (01-04, 11-14) or standard UBL type";
            }
        };

        $res['BR-MY-012'] = static function(Invoice $inv) {
            $number = $inv->getNumber();
            if (!$number) {
                return "Invoice Number is mandatory";
            }
            if (strlen($number) > 50) {
                return "Invoice Number must not exceed 50 characters";
            }
        };

        $res['BR-MY-013'] = static function(Invoice $inv) {
            if (!$inv->getIssueDate()) {
                return "Invoice Date is mandatory";
            }
        };

        $res['BR-MY-013a'] = static function(Invoice $inv) {
            // Invoice time is mandatory in MyInvois - would need to be stored separately
            // This would require checking if time component exists in the date field
            // For now, we assume time is part of the issue date/time
            // TODO: Implement proper invoice time validation
            // $issueDate = $inv->getIssueDate();
            // if ($issueDate && $issueDate->format('H:i:s') === '00:00:00') {
            //     // If time is exactly midnight, it might indicate missing time component
            //     return "Invoice Time is mandatory (separate from date)";
            // }
            return null; // Time validation temporarily disabled
        };

        $res['BR-MY-014'] = static function(Invoice $inv) {
            if (!$inv->getCurrency()) {
                return "Invoice Currency Code is mandatory";
            }
        };

        // Tax totals
        $res['BR-MY-015'] = static function(Invoice $inv) {
            $totals = $inv->getTotals();
            // Tax total is reflected in vatAmount property
            if ($totals->vatAmount === null) {
                return "Tax Total is mandatory";
            }
        };

        // Legal monetary totals
        $res['BR-MY-017'] = static function(Invoice $inv) {
            $totals = $inv->getTotals();
            if ($totals->netAmount === null) {
                return "Line Extension Amount is mandatory";
            }
        };

        $res['BR-MY-018'] = static function(Invoice $inv) {
            $totals = $inv->getTotals();
            if ($totals->taxExclusiveAmount === null) {
                return "Tax Exclusive Amount is mandatory";
            }
        };

        $res['BR-MY-019'] = static function(Invoice $inv) {
            $totals = $inv->getTotals();
            if ($totals->taxInclusiveAmount === null) {
                return "Tax Inclusive Amount is mandatory";
            }
        };

        $res['BR-MY-020'] = static function(Invoice $inv) {
            $totals = $inv->getTotals();
            if ($totals->payableAmount === null) {
                return "Payable Amount is mandatory";
            }
        };

        // Invoice line items
        $res['BR-MY-021'] = static function(Invoice $inv) {
            if (count($inv->getLines()) === 0) {
                return "At least one Invoice Line is mandatory";
            }
        };

        $res['BR-MY-022'] = static function(Invoice $inv) {
            foreach ($inv->getLines() as $index => $line) {
                if (!$line->getId()) {
                    return "Invoice Line ID is mandatory for line " . ($index + 1);
                }
            }
        };

        $res['BR-MY-023'] = static function(Invoice $inv) {
            foreach ($inv->getLines() as $index => $line) {
                if ($line->getQuantity() === null) {
                    return "Invoice Line Quantity is mandatory for line " . ($index + 1);
                }
            }
        };

        $res['BR-MY-024'] = static function(Invoice $inv) {
            foreach ($inv->getLines() as $index => $line) {
                if ($line->getNetAmount() === null) {
                    return "Invoice Line Amount is mandatory for line " . ($index + 1);
                }
            }
        };

        $res['BR-MY-025'] = static function(Invoice $inv) {
            foreach ($inv->getLines() as $index => $line) {
                if (!$line->getName()) {
                    return "Item Name is mandatory for line " . ($index + 1);
                }
                if (!$line->getDescription()) {
                    return "Item Description is mandatory for line " . ($index + 1);
                }
            }
        };

        $res['BR-MY-026'] = static function(Invoice $inv) {
            foreach ($inv->getLines() as $index => $line) {
                if ($line->getPrice() === null) {
                    return "Item Price is mandatory for line " . ($index + 1);
                }
            }
        };

        $res['BR-MY-027'] = static function(Invoice $inv) {
            foreach ($inv->getLines() as $index => $line) {
                // Each line must have a VAT category
                if (!$line->getVatCategory()) {
                    return "Item Tax Category is mandatory for line " . ($index + 1);
                }
            }
        };

        // Format validations
        $res['BR-MY-028'] = static function(Invoice $inv) {
            // Country codes should be exactly "MYS" for Malaysia (3 character ISO code)
            $supplier = $inv->getSeller();
            if ($supplier && $supplier->getCountry()) {
                $countryCode = $supplier->getCountry();
                if ($countryCode !== 'MYS') {
                    return "Supplier country code must be 'MYS'";
                }
                if (strlen($countryCode) !== 3) {
                    return "Supplier country code must be exactly 3 characters";
                }
            }
            
            $buyer = $inv->getBuyer();
            if ($buyer && $buyer->getCountry()) {
                $countryCode = $buyer->getCountry();
                if ($countryCode !== 'MYS') {
                    return "Buyer country code must be 'MYS'";
                }
                if (strlen($countryCode) !== 3) {
                    return "Buyer country code must be exactly 3 characters";
                }
            }
        };

        $res['BR-MY-029'] = static function(Invoice $inv) {
            // Email validation
            $emailPattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
            
            $supplier = $inv->getSeller();
            if ($supplier && $supplier->getContactEmail()) {
                if (!preg_match($emailPattern, $supplier->getContactEmail())) {
                    return "Invalid supplier email format";
                }
            }
            
            $buyer = $inv->getBuyer();
            if ($buyer && $buyer->getContactEmail()) {
                if (!preg_match($emailPattern, $buyer->getContactEmail())) {
                    return "Invalid buyer email format";
                }
            }
        };

        $res['BR-MY-030'] = static function(Invoice $inv) {
            // Phone number validation (Malaysian format: optional +, digits only, max 20 chars)
            $supplier = $inv->getSeller();
            if ($supplier && $supplier->getContactPhone()) {
                $phone = $supplier->getContactPhone();
                if (strlen($phone) > 20) {
                    return "Supplier contact number must not exceed 20 characters";
                }
                // Malaysian format: optional +, followed by digits only (no hyphens, spaces, or special chars)
                if (!preg_match('/^\+?\d{7,19}$/', $phone)) {
                    return "Supplier contact number must contain only digits and optional + symbol (no hyphens, spaces, or special characters)";
                }
            }
            
            $buyer = $inv->getBuyer();
            if ($buyer && $buyer->getContactPhone()) {
                $phone = $buyer->getContactPhone();
                if (strlen($phone) > 20) {
                    return "Buyer contact number must not exceed 20 characters";
                }
                // Malaysian format: optional +, followed by digits only (no hyphens, spaces, or special chars)
                if (!preg_match('/^\+?\d{7,19}$/', $phone)) {
                    return "Buyer contact number must contain only digits and optional + symbol (no hyphens, spaces, or special characters)";
                }
            }
        };

        return $res;
    }


    /**
     * @inheritdoc
     */
    public function setupInvoice(Invoice $invoice) {
        parent::setupInvoice($invoice);
        $this->convertInvoiceType($invoice);
    }

    public function finalizeInvoice(Invoice $invoice) {
        $this->convertInvoiceType($invoice);
    }

    private function convertInvoiceType(Invoice $invoice) {
        $type = $invoice->getType();
        
        // Map standard UBL types to Malaysian codes
        switch ($type) {
            case Invoice::TYPE_COMMERCIAL_INVOICE:
            case Invoice::TYPE_TAX_INVOICE:
                $invoice->setType('01'); // Invoice
                break;
            case Invoice::TYPE_CREDIT_NOTE:
            case Invoice::TYPE_CREDIT_NOTE_RELATED_TO_GOODS_OR_SERVICES:
            case Invoice::TYPE_CREDIT_NOTE_RELATED_TO_FINANCIAL_ADJUSTMENTS:
                $invoice->setType('02'); // Credit Note
                break;
            case Invoice::TYPE_DEBIT_NOTE:
            case Invoice::TYPE_DEBIT_NOTE_RELATED_TO_GOODS_OR_SERVICES:
            case Invoice::TYPE_DEBIT_NOTE_RELATED_TO_FINANCIAL_ADJUSTMENTS:
                $invoice->setType('03'); // Debit Note
                break;
            // Add other mappings as needed
            default:
                // If it's already a Malaysian code (01-04, 11-14), leave it as is
                if (in_array($type, ['01', '02', '03', '04', '11', '12', '13', '14'])) {
                    return;
                }
                // Otherwise, default to Invoice
                $invoice->setType('01');
                break;
        }
    }

    public function finalizeXml(\UXML\UXML $xml, \Einvoicing\Invoice $invoice, \Einvoicing\Writers\AbstractWriter $writer): void {
        $dom = $xml->element()->ownerDocument;
        
        // Add IssueTime after IssueDate if not exists
        if (!$xml->get('//cbc:IssueTime')) {
            // Use the actual time from the issue date
            $timeValue = $invoice->getIssueDate()->format('H:i:s') . 'Z';
            $issueDateElement = $xml->get('//cbc:IssueDate');
            $issueDateNode = $issueDateElement->element();
            $issueTimeNode = $dom->createElement('cbc:IssueTime', $timeValue);
            $issueDateNode->parentNode->insertBefore($issueTimeNode, $issueDateNode->nextSibling);
        }
        
        // Add listVersionID="1.0" attribute to InvoiceTypeCode
        $invoiceTypeCodeElement = $xml->get('//cbc:InvoiceTypeCode');
        if ($invoiceTypeCodeElement) {
            $invoiceTypeCodeNode = $invoiceTypeCodeElement->element();
            $invoiceTypeCodeNode->setAttribute('listVersionID', '1.0');
        }
        
        // Fix address format and reorder elements for UBL compliance
        $postalAddresses = $xml->getAll('//cac:PostalAddress');
        foreach ($postalAddresses as $postalAddress) {
            $parentNode = $postalAddress->element();
            
            // Convert StreetName to AddressLine and insert after PostalZone/CountrySubentityCode
            $streetNameElement = $postalAddress->get('.//cbc:StreetName');
            if ($streetNameElement) {
                $streetNode = $streetNameElement->element();
                $addressValue = $streetNode->textContent;
                
                // Find where to insert AddressLine elements (after CountrySubentityCode, before Country)
                $countryElement = $postalAddress->get('.//cac:Country');
                $insertBefore = $countryElement ? $countryElement->element() : null;
                
                // Split address by comma and create multiple AddressLine elements  
                $addressParts = array_map('trim', explode(',', $addressValue));
                
                foreach ($addressParts as $part) {
                    if (!empty($part)) {
                        $addressLineElement = $dom->createElement('cac:AddressLine');
                        $lineElement = $dom->createElement('cbc:Line', $part);
                        $addressLineElement->appendChild($lineElement);
                        
                        if ($insertBefore) {
                            $parentNode->insertBefore($addressLineElement, $insertBefore);
                        } else {
                            $parentNode->appendChild($addressLineElement);
                        }
                    }
                }
                
                // Remove the original StreetName element
                $parentNode->removeChild($streetNode);
            }
            
            // Fix CountrySubentity to CountrySubentityCode
            $countrySubElement = $postalAddress->get('.//cbc:CountrySubentity');
            if ($countrySubElement) {
                $subNode = $countrySubElement->element();
                $stateCode = $subNode->textContent;
                $countrySubCodeElement = $dom->createElement('cbc:CountrySubentityCode', $stateCode);
                $subNode->parentNode->replaceChild($countrySubCodeElement, $subNode);
            }
            
            // No need to reorder since we're inserting AddressLine in the correct position
        }
        
        // Add missing ItemPriceExtension to invoice lines (Malaysian requirement)
        $invoiceLines = $xml->getAll('//cac:InvoiceLine');
        foreach ($invoiceLines as $lineElement) {
            $itemPriceExtensionElement = $lineElement->get('.//cac:ItemPriceExtension');
            
            // Add ItemPriceExtension if missing (mandatory for Malaysia)
            if (!$itemPriceExtensionElement) {
                $priceElement = $lineElement->get('.//cac:Price');
                if ($priceElement) {
                    $priceAmountElement = $priceElement->get('.//cbc:PriceAmount');
                    if ($priceAmountElement) {
                        $priceAmount = $priceAmountElement->element()->textContent;
                        $currency = $priceAmountElement->element()->getAttribute('currencyID') ?: 'MYR';
                        
                        // Create ItemPriceExtension element
                        $itemPriceExtension = $dom->createElement('cac:ItemPriceExtension');
                        $amountElement = $dom->createElement('cbc:Amount', $priceAmount);
                        $amountElement->setAttribute('currencyID', $currency);
                        $itemPriceExtension->appendChild($amountElement);
                        
                        // Insert after Price element
                        $priceNode = $priceElement->element();
                        $priceNode->parentNode->insertBefore($itemPriceExtension, $priceNode->nextSibling);
                    }
                }
            }
            
            // Add line-level TaxTotal for Malaysian format (required instead of ClassifiedTaxCategory)
            $lineTaxTotal = $lineElement->get('.//cac:TaxTotal');
            if (!$lineTaxTotal) {
                $itemElement = $lineElement->get('.//cac:Item');
                $lineExtensionAmount = $lineElement->get('.//cbc:LineExtensionAmount');
                
                if ($itemElement && $lineExtensionAmount) {
                    $classifiedTaxCategory = $itemElement->get('.//cac:ClassifiedTaxCategory');
                    
                    if ($classifiedTaxCategory) {
                        $taxId = $classifiedTaxCategory->get('.//cbc:ID');
                        $taxPercent = $classifiedTaxCategory->get('.//cbc:Percent');
                        
                        if ($taxId && $taxPercent) {
                            $taxIdValue = $taxId->element()->textContent;
                            $taxPercentValue = floatval($taxPercent->element()->textContent);
                            $lineAmountValue = floatval($lineExtensionAmount->element()->textContent);
                            $currency = $lineExtensionAmount->element()->getAttribute('currencyID') ?: 'MYR';
                            
                            // Calculate tax amount
                            $taxAmount = ($lineAmountValue * $taxPercentValue) / 100;
                            
                            // Create line-level TaxTotal structure
                            $taxTotal = $dom->createElement('cac:TaxTotal');
                            $taxAmountElement = $dom->createElement('cbc:TaxAmount', number_format($taxAmount, 2, '.', ''));
                            $taxAmountElement->setAttribute('currencyID', $currency);
                            $taxTotal->appendChild($taxAmountElement);
                            
                            // Create TaxSubtotal
                            $taxSubtotal = $dom->createElement('cac:TaxSubtotal');
                            $taxableAmountElement = $dom->createElement('cbc:TaxableAmount', number_format($lineAmountValue, 2, '.', ''));
                            $taxableAmountElement->setAttribute('currencyID', $currency);
                            $taxSubtotal->appendChild($taxableAmountElement);
                            
                            $subTaxAmountElement = $dom->createElement('cbc:TaxAmount', number_format($taxAmount, 2, '.', ''));
                            $subTaxAmountElement->setAttribute('currencyID', $currency);
                            $taxSubtotal->appendChild($subTaxAmountElement);
                            
                            // Create TaxCategory with Malaysian tax ID
                            $taxCategory = $dom->createElement('cac:TaxCategory');
                            $malaysianTaxId = $this->mapToMalaysianTaxType($taxIdValue);
                            $categoryIdElement = $dom->createElement('cbc:ID', $malaysianTaxId);
                            $taxCategory->appendChild($categoryIdElement);
                            
                            $percentElement = $dom->createElement('cbc:Percent', $taxPercentValue);
                            $taxCategory->appendChild($percentElement);
                            
                            // Create TaxScheme using Malaysian enum values
                            $taxScheme = $dom->createElement('cac:TaxScheme');
                            $schemeIdElement = $dom->createElement('cbc:ID', MalaysianTaxScheme::SCHEME_ID->value);
                            $schemeIdElement->setAttribute('schemeID', MalaysianTaxScheme::SCHEME_LIST_ID->value);
                            $schemeIdElement->setAttribute('schemeAgencyID', MalaysianTaxScheme::SCHEME_AGENCY_ID->value);
                            $taxScheme->appendChild($schemeIdElement);
                            $taxCategory->appendChild($taxScheme);
                            
                            $taxSubtotal->appendChild($taxCategory);
                            $taxTotal->appendChild($taxSubtotal);
                            
                            // Insert TaxTotal after LineExtensionAmount, before Item
                            $lineExtensionNode = $lineExtensionAmount->element();
                            $lineExtensionNode->parentNode->insertBefore($taxTotal, $itemElement->element());
                        }
                    }
                }
            }
            
            // Keep ClassifiedTaxCategory but also fix its tax scheme
            $itemElement = $lineElement->get('.//cac:Item');
            if ($itemElement) {
                $classifiedTaxCategories = $itemElement->getAll('.//cac:ClassifiedTaxCategory');
                foreach ($classifiedTaxCategories as $taxCategory) {
                    $taxSchemeElement = $taxCategory->get('.//cac:TaxScheme');
                    if ($taxSchemeElement) {
                        $schemeIdElement = $taxSchemeElement->get('.//cbc:ID');
                        if ($schemeIdElement) {
                            $schemeIdElement->element()->textContent = MalaysianTaxScheme::SCHEME_ID->value;
                            $schemeIdElement->element()->setAttribute('schemeID', MalaysianTaxScheme::SCHEME_LIST_ID->value);
                            $schemeIdElement->element()->setAttribute('schemeAgencyID', MalaysianTaxScheme::SCHEME_AGENCY_ID->value);
                        }
                    }
                }
            }
        }
    }

    /**
     * Map UBL tax categories (UNCL5305) to Malaysian tax type codes
     */
    private function mapToMalaysianTaxType(string $ublTaxCategory): string
    {
        $mapping = [
            'S' => MalaysianTaxType::SALES_TAX->value,           // Standard rate -> Sales Tax (01)
            'Z' => MalaysianTaxType::NOT_APPLICABLE->value,     // Zero rated goods -> Not Applicable (06)
            'E' => MalaysianTaxType::TAX_EXEMPTION->value,      // Exempt from Tax -> Tax Exemption (E)
            'O' => MalaysianTaxType::NOT_APPLICABLE->value,     // Services outside scope of tax -> Not Applicable (06)
            'G' => MalaysianTaxType::NOT_APPLICABLE->value,     // Free export item, VAT not charged -> Not Applicable (06)
            'AE' => MalaysianTaxType::SALES_TAX->value,         // VAT Reverse Charge -> Sales Tax (01)
            'K' => MalaysianTaxType::TAX_EXEMPTION->value,      // VAT exempt for EEA -> Tax Exemption (E)
            'L' => MalaysianTaxType::SALES_TAX->value,          // Canary Islands general indirect tax -> Sales Tax (01)
            'M' => MalaysianTaxType::SALES_TAX->value,          // Tax for production, services and importation -> Sales Tax (01)
            'B' => MalaysianTaxType::SALES_TAX->value,          // Transferred (VAT), In Italy -> Sales Tax (01)
        ];
        
        return $mapping[$ublTaxCategory] ?? MalaysianTaxType::SALES_TAX->value;
    }

    /**
     * Load codes from JSON file (same format as EnumToArray::valueArray)
     * @param string $filename JSON filename in Presets directory
     * @return array Array in format [code => description]
     */
    private static function loadCodesFromJson(string $filename): array
    {
        $jsonFile = __DIR__ . '/' . $filename;
        if (file_exists($jsonFile)) {
            $jsonData = json_decode(file_get_contents($jsonFile), true);
            $codes = [];
            foreach ($jsonData as $item) {
                $codes[$item['Code']] = $item['Description'];
            }
            return $codes;
        }
        return [];
    }

    /**
     * Get MSIC codes array (same format as EnumToArray::valueArray)
     * @return array Array in format [code => description]
     */
    public static function getMsicCodes(): array
    {
        static $codes = null;
        if ($codes === null) {
            $codes = self::loadCodesFromJson('Malaysia_MSIC.json');
        }
        return $codes;
    }

    /**
     * Get Item Classification codes array (same format as EnumToArray::valueArray)
     * @return array Array in format [code => description]
     */
    public static function getItemClassificationCodes(): array
    {
        static $codes = null;
        if ($codes === null) {
            $codes = self::loadCodesFromJson('Malaysia_Classification_Codes.json');
        }
        return $codes;
    }

}