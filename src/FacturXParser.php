<?php

namespace DigitalInvoice;

/**
 * Reads FacturX / XRechnung XML.
 *
 * Extends CiiParser which handles the full Cross Industry Invoice format.
 * Kept as a named subclass so callers can type-hint on the specific standard.
 */
class FacturXParser extends CiiParser
{
}
