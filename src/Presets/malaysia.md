# Malaysian e-Invoice Complete Field Specifications

## Invoice Fields Table

| ELEMENT | Field TYPE | DESCRIPTION | VALUE EXAMPLE | UBL Schema Mapping | Mandatory | Number of Chars | Cardinality |
|---------|------------|-------------|---------------|-------------------|-----------|-----------------|-------------|
| e-Invoice Version | xsd:normalizedString | Current e-Invoice version | "1.0" | cbc:CustomizationID | Yes | - | 1..1 |
| e-Invoice Type Code | xsd:normalizedString | Document type (invoice, credit note, etc.) | "01" | cbc:InvoiceTypeCode | Yes | 2 | 1..1 |
| e-Invoice Code/Number | xsd:normalizedString | Supplier's internal tracking number | "INV12345" | cbc:ID | Yes | - | 1..1 |
| e-Invoice Date | xsd:date | Issuance date (UTC) | "2017-11-26" | cbc:IssueDate | Yes | - | 1..1 |
| e-Invoice Time | xsd:time | Issuance time | "15:30:00Z" | cbc:IssueTime | Yes | - | 1..1 |
| Invoice Currency Code | xsd:normalizedString | Currency used | "MYR" | cbc:DocumentCurrencyCode | Yes | 3 | 1..1 |
| Total Excluding Tax | xsd:decimal | Amount before taxes | 1436.50 | cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount | Yes | - | 1..1 |
| Total Including Tax | xsd:decimal | Amount including taxes | 1436.50 | cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount | Yes | - | 1..1 |
| Total Payable Amount | xsd:decimal | Final amount to pay | 1436.50 | cac:LegalMonetaryTotal/cbc:PayableAmount | Yes | - | 1..1 |
| Total Tax Amount | xsd:decimal | Total tax charged | 87.63 | cac:TaxTotal/cbc:TaxAmount | Yes | - | 1..1 |
| Supplier Name | xsd:string | Supplier name | "AMS Setia Jaya Sdn. Bhd." | cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName | Yes | - | 1..1 |
| Supplier TIN | xsd:normalizedString | Tax Identification Number | "IG21136626090" | cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID | Yes | - | 1..1 |
| Supplier Registration Number | xsd:normalizedString | Registration/Identification Number | "202001234567" | cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID | Yes | - | 1..1 |
| Supplier SST Registration Number | xsd:normalizedString | SST Registration Number | - | - | No | - | 0..1 |
| Supplier Address | - | Address details | - | cac:AccountingSupplierParty/cac:Party/cac:PostalAddress | Yes | - | 1..1 |
| Supplier Contact | - | Contact information | - | cac:AccountingSupplierParty/cac:Party/cac:Contact | Yes | - | 1..1 |
| Buyer Name | xsd:string | Buyer name | "Hebat Group" | cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName | Yes | - | 1..1 |
| Buyer TIN | xsd:normalizedString | Tax Identification Number | "C2584563200" | cac:AccountingCustomerParty/cac:Party/cac:PartyIdentification/cbc:ID | Yes | - | 1..1 |
| Buyer Registration Number | xsd:normalizedString | Registration/Identification Number | "201901234567" | cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID | Yes | - | 1..1 |
| Buyer Address | - | Address details | - | cac:AccountingCustomerParty/cac:Party/cac:PostalAddress | Yes | - | 1..1 |
| Buyer Contact | - | Contact information | - | cac:AccountingCustomerParty/cac:Party/cac:Contact | Yes | - | 1..1 |
| Line Item Classification Code | xsd:normalizedString | Product/service category | "001" | cac:InvoiceLine/cac:Item/cac:CommodityClassification/cbc:ItemClassificationCode | Yes | - | 1..1 |
| Line Item Description | xsd:string | Item details | "Laptop Peripherals" | cac:InvoiceLine/cac:Item/cbc:Description | Yes | - | 1..1 |
| Line Item Unit Price | xsd:decimal | Price per unit | 1436.50 | cac:InvoiceLine/cac:Price/cbc:PriceAmount | Yes | - | 1..1 |
| Line Item Quantity | xsd:decimal | Number of units | 1 | cac:InvoiceLine/cbc:InvoicedQuantity | Yes | - | 1..1 |
| Line Item Line Extension Amount | xsd:decimal | Total for line item | 1436.50 | cac:InvoiceLine/cbc:LineExtensionAmount | Yes | - | 1..1 |
| AddressLine | xsd:string | Address line | "Lot 66" | cac:PostalAddress/cac:AddressLine/cbc:Line | Yes | - | 1..n |
| CityName | xsd:string | City | "Kuala Lumpur" | cac:PostalAddress/cbc:CityName | Yes | - | 1..1 |
| PostalZone | xsd:string | Postal code | "50480" | cac:PostalAddress/cbc:PostalZone | Yes | - | 1..1 |
| CountrySubentityCode | xsd:string | State code | "14" | cac:PostalAddress/cbc:CountrySubentityCode | Yes | - | 1..1 |
| Country IdentificationCode | xsd:string | Country code | "MYS" | cac:PostalAddress/cac:Country/cbc:IdentificationCode | Yes | 3 | 1..1 |
| Contact Name | xsd:string | Contact person name | "Ahmad Hassan" | cac:Contact/cbc:Name | Yes | - | 1..1 |
| Contact Telephone | xsd:string | Phone number (digits and + only) | "+60123456789" | cac:Contact/cbc:Telephone | Yes | - | 1..1 |
| Contact Email | xsd:string | Email address | "general.ams@supplier.com" | cac:Contact/cbc:ElectronicMail | Yes | - | 1..1 |
| InvoiceTypeCode listVersionID | xsd:string | List version attribute | "1.0" | cbc:InvoiceTypeCode/@listVersionID | Yes | - | 1..1 |
| Tax Category Code | xsd:string | Tax category | "S" | cac:TaxCategory/cbc:ID | Yes | - | 1..1 |
| Tax Percentage | xsd:decimal | Tax rate | 0 | cac:TaxCategory/cbc:Percent | Yes | - | 1..1 |
| Tax Scheme ID | xsd:string | Tax scheme | "VAT" | cac:TaxScheme/cbc:ID | Yes | - | 1..1 |
| Payment Means Code | xsd:string | Payment method code | "58" | cac:PaymentMeans/cbc:PaymentMeansCode | Yes | - | 1..1 |
| Payment Terms Note | xsd:string | Payment terms description | "Payment method is cash" | cac:PaymentTerms/cbc:Note | No | - | 0..1 |
| Due Date | xsd:date | Payment due date | "2025-09-05" | cbc:DueDate | No | - | 0..1 |
| Note | xsd:string | Invoice notes | "Malaysian e-invoice test" | cbc:Note | No | - | 0..n |
| BuyerReference | xsd:string | Buyer reference | "201901234567" | cbc:BuyerReference | Yes | - | 1..1 |