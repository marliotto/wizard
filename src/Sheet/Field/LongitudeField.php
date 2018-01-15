<?php

namespace Ipstack\Wizard\Sheet\Field;

/**
 * Class LongitudeField
 *
 * @property double $limit
 */
class LongitudeField extends CoordinateFieldAbstract
{
    /**
     * @var double
     */
    protected $limit = 180.0;
}
