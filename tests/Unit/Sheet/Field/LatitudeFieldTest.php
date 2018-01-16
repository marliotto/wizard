<?php

namespace Ipstack\Test\Unit\Wizard\Sheet\Field;

use PHPUnit\Framework\TestCase;
use Ipstack\Wizard\Sheet\Field\LatitudeField;

/**
 * @covers LatitudeField
 */
class LatitudeFieldTest extends TestCase
{

    /**
     * Big value
     */
    public function testBigValue()
    {
        $type = new LatitudeField();
        $latitude = $type->getValidValue(91.5);
        $this->assertSame(88.5, $latitude);
        unset($type);
    }

    /**
     * Very big value
     */
    public function testVeryBigValue()
    {
        $type = new LatitudeField();
        $latitude = $type->getValidValue(175.05);
        $this->assertSame(4.95, $latitude);
        unset($type);
    }

    /**
     * Small value
     */
    public function testSmallValue()
    {
        $type = new LatitudeField();
        $latitude = $type->getValidValue(-91.5);
        $this->assertSame(-88.5, $latitude);
        unset($type);
    }

    /**
     * Very small value
     */
    public function testVerySmallValue()
    {
        $type = new LatitudeField();
        $latitude = $type->getValidValue(-175.05);
        $this->assertSame(-4.95, $latitude);
        unset($type);
    }

    /**
     * Correct value
     */
    public function testCorrectValue()
    {
        $type = new LatitudeField();
        $latitude = $type->getValidValue(-75.05);
        $this->assertSame(-75.05, $latitude);
        unset($type);
    }
}