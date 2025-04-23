<?php

namespace DigitalInvoice\Tests;

use DigitalInvoice\Types;
use PHPUnit\Framework\TestCase;

class TypesTest extends TestCase
{
    public function testInternationnalCodes()
    {
        $codes = Types::getInternationalCodes();
        $this->assertEquals("France - Numéro SIRET (Système d'Identification du Répertoire des Établissements)",  $codes['0009']);
        $this->assertEquals("France - Numéro SIREN (Système d'Identification du Répertoire des Entreprises)",  $codes['0002']);
    }
}
