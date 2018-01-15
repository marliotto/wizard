<?php

namespace Ipstack\Test\Unit\Wizard\Sheet\Field;

use PHPUnit\Framework\TestCase;
use Ipstack\Wizard\Sheet\Field\LongitudeField;

/**
 * @covers LongitudeField
 */
class LongitudeFieldTest extends TestCase
{

    /**
     * Big value
     */
    public function testBigValue()
    {
        $type = new LongitudeField();
        $latitude = $type->getValidValue(181.5);
        $this->assertSame(-178.5, $latitude);
        unset($type);
    }

    /**
     * Very big value
     */
    public function testVeryBigValue()
    {
        $type = new LongitudeField();
        $latitude = $type->getValidValue(675.05);
        $this->assertSame(-44.95, $latitude);
        unset($type);
    }

    /**
     * Small value
     */
    public function testSmallValue()
    {
        $type = new LongitudeField();
        $latitude = $type->getValidValue(-191.526);
        $this->assertSame(168.474, $latitude);
        unset($type);
    }

    /**
     * Very small value
     */
    public function testVerySmallValue()
    {
        $type = new LongitudeField();
        $latitude = $type->getValidValue(-720);
        $this->assertSame(0.0, $latitude);
        unset($type);
    }

    /**
     * Correct value
     */
    public function testCorrectValue()
    {
        $type = new LongitudeField();
        $latitude = $type->getValidValue(-75.05);
        $this->assertSame(-75.05, $latitude);
        unset($type);
    }
}