<?php

namespace Ipstack\Wizard\Fields;

/**
 * Class LatitudeField
 *
 * @property double $min
 * @property double $max
 */
class LatitudeField extends CoordinateFieldAbstract
{
    /**
     * @var double
     */
    protected $min = -90.0;

    /**
     * @var double
     */
    protected $max = 90.0;
}
