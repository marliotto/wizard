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
}
