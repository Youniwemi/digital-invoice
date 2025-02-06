<?php

namespace DigitalInvoice\Tests;

use DigitalInvoice\Types;
use PHPUnit\Framework\TestCase;

class TypesTest extends TestCase
{
    public function testInternationnalCodes()
    {
        $codes = Types::getInternationalCodes();
        $this->assertEquals('SIRET CODE',  $codes['0009']);
    }
}
