# Digital Invoice

Digital Invoice offers an effortless and unified interface for generating E-Invoices across various formats. It serves as a simplified wrapper around key libraries such as `easybill/zugferd-php`, `josemmo/einvoicing`, and `atgp/factur-x`. Our objective is to streamline the process of E-Invoice generation by bringing together the capabilities of these established libraries into one easy-to-use package.

We extend our sincere appreciation to the developers of these libraries for their foundational contributions to the field.

## Supported Formats

Digital Invoice supports a range of formats, catering to diverse invoicing needs:

- **Factur-X PDF Invoice**:
  - Profiles: `MINIMUM`, `BASIC_WL`, `BASIC`, `EN16931`, `EXTENDED`

- **ZUGFeRD PDF Invoice**:
  - Profiles: `CONFORT`, `BASIC`, `EXTENDED`

- **XRechnung XML Invoice**:
  - A widely-used German XML format

- **UBL XML Invoices**:
    - Profiles: `Peppol`, `Nlcius`, `CiusRo`, `CiusIt`, `CiusEsFace`, `CiusAtNat`, `CiusAtGov`

## Getting Started

To integrate Digital Invoice into your PHP project:

1. Add Digital Invoice to your project. For installation details, refer to the accompanying documentation.
2. Explore our test cases for examples and usage guidelines.

Digital Invoice enables you to generate E-Invoices in the following formats with ease:

- **Factur-X PDF Invoice**:
  - Profiles: `MINIMUM`, `BASIC_WL`, `BASIC`, `EN16931`, `EXTENDED`

- **ZUGFeRD PDF Invoice**:
  - Profiles: `CONFORT`, `BASIC`, `EXTENDED`

- **XRechnung XML Invoice**:
  - A standard German XML format

- **UBL XML Invoices**:
    - Profiles: `Peppol`, `Nlcius`, `CiusRo`, `CiusIt`, `CiusEsFace`, `CiusAtNat`, `CiusAtGov`


## Development Status and Collaboration

**Active Development**: Please note that Digital Invoice is currently under active development. As such, the API and functionalities may undergo changes. We encourage users to use this package with caution and to stay updated with the latest versions for the most stable experience.

**Collaboration and Support**: We welcome collaboration and are eager to support specific use cases that enhance the utility of Digital Invoice. If you have unique invoicing needs or suggestions for improvements, we would be delighted to hear from you and work together to expand the capabilities of this package. Your contributions and feedback are invaluable in shaping a more versatile and robust tool.


## Credits and Acknowledgements

We are grateful to the following projects and teams for their contributions:

- [ZUGFeRD PHP SDK (Factur-X, XRechnung)](https://github.com/easybill/zugferd-php) - For enabling the conversion of PHP objects to XML and vice versa.
- [PHP Factur-X Library](https://github.com/atgp/factur-x) - Providing foundational tools for Factur-X invoicing.
- [Tiime Software Team](https://github.com/Tiime-Software/EN-16931) - For their work on structured data types in E-Invoicing.
