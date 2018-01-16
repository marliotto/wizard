<?php

namespace Ipstack\Wizard\Sheet\Field;

/**
 * Class LatitudeField
 *
 * @property double $limit
 */
class LatitudeField extends CoordinateFieldAbstract
{
    /**
     * @var double
     */
    protected $limit = 90.0;

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
                $value = -$limit - ($value % $limit);
            }
            if ($value > $limit) {
                $value = $limit - ($value % $limit);
            }
        }
        $value /= 10000;
        return $value;
    }
}
