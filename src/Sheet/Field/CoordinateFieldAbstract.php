<?php

namespace Ipstack\Wizard\Sheet\Field;

/**
 * Class CoordinateFieldAbstract
 *
 * @property string $packFormatKey
 * @property double $min
 * @property double $max

 */
abstract class CoordinateFieldAbstract extends FieldAbstract
{
    /**
     * @var string
     */
    protected $packFormatKey = 'd';

    /**
     * @var null
     */
    protected $packFormatLength = null;

    /**
     * @var double
     */
    protected $min;

    /**
     * @var double
     */
    protected $max;

    /**
     * Get valid value.
     *
     * @param mixed $value
     * @return int|float|double
     */
    public function getValidValue($value)
    {
        if ($this->min !== null && $value < $this->min) $value = $this->min;
        if ($this->max !== null && $value > $this->max) $value = $this->max;
        $value = round((float)$value, 4, \PHP_ROUND_HALF_DOWN);
        return $value;
    }

    /**
     * Get format for pack() function.
     *
     * @return string
     */
    public function getPackFormat()
    {
        return $this->packFormatKey;
    }
}