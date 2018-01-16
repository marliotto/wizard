<?php

namespace Ipstack\Wizard\Sheet\Field;

/**
 * Class CoordinateFieldAbstract
 *
 * @property string $packFormatKey
 * @property null $packFormatLength
 * @property double $limit

 */
abstract class CoordinateFieldAbstract extends FieldAbstract
{
    /**
     * @var string
     */
    protected $packFormatKey = 'f';

    /**
     * @var null
     */
    protected $packFormatLength = null;

    /**
     * @var double
     */
    protected $limit;

    /**
     * Get valid value.
     *
     * @param mixed $value
     * @return double
     */
    public function getValidValue($value)
    {
        $value = (double)$value;
        $value = intval($value * 10000);
        if ($this->limit !== null) {
            $limit = $this->limit*10000;
            if ($value < -$limit) {
                $value += $limit;
                $value = ($value % ($limit*2));
                $value += $limit;
            }
            if ($value > $limit) {
                $value -= $limit;
                $value = ($value % ($limit*2));
                $value -= $limit;
            }
        }
        $value /= 10000;
        return $value;
    }
}