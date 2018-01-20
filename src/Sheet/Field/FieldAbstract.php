<?php

namespace Ipstack\Wizard\Sheet\Field;

/**
 * Class FieldAbstract
 *
 * @property string $packFormatKey
 * @property string $sqliteType
 * @property int|null $packFormatLength
 */
abstract class FieldAbstract
{
    /*
     * @var string;
     */
    protected $sqliteType = 'REAL';

    /**
     * @var string
     */
    protected $packFormatKey = 'c';

    /**
     * @var int|null
     */
    protected $packFormatLength = null;

    /**
     * Get valid value.
     *
     * @param $value
     * @return mixed
     */
    public function getValidValue($value)
    {
        return $value;
    }

    /**
     * Get format for pack() function.
     *
     * @return string
     */
    public function getPackFormat()
    {
        return $this->packFormatKey.$this->packFormatLength;
    }

    /**
     * @param mixed $value
     */
    public function updatePackFormat($value)
    {
    }

    /*
     * @return string
     */
    public function getSqliteType()
    {
        return $this->sqliteType;
    }
}
