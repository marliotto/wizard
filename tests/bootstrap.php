<?php

const IPSTACK_TEST_CSV_DIR = __DIR__.DIRECTORY_SEPARATOR.'csv';
const IPSTACK_TEST_TMP_DIR = __DIR__.DIRECTORY_SEPARATOR.'tmp';
$srcDir = realpath(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src').DIRECTORY_SEPARATOR;

require_once ($srcDir.'Sheet'.DIRECTORY_SEPARATOR.'Field'.DIRECTORY_SEPARATOR.'FieldAbstract.php');
require_once ($srcDir.'Sheet'.DIRECTORY_SEPARATOR.'Field'.DIRECTORY_SEPARATOR.'CoordinateFieldAbstract.php');
require_once ($srcDir.'Sheet'.DIRECTORY_SEPARATOR.'Field'.DIRECTORY_SEPARATOR.'NumericField.php');
require_once ($srcDir.'Sheet'.DIRECTORY_SEPARATOR.'Field'.DIRECTORY_SEPARATOR.'StringField.php');
require_once ($srcDir.'Sheet'.DIRECTORY_SEPARATOR.'Field'.DIRECTORY_SEPARATOR.'LatitudeField.php');
require_once ($srcDir.'Sheet'.DIRECTORY_SEPARATOR.'Field'.DIRECTORY_SEPARATOR.'LongitudeField.php');
require_once ($srcDir.'Sheet'.DIRECTORY_SEPARATOR.'SheetAbstract.php');
require_once ($srcDir.'Sheet'.DIRECTORY_SEPARATOR.'Register.php');
require_once ($srcDir.'Sheet'.DIRECTORY_SEPARATOR.'Network.php');
require_once ($srcDir.'Wizard.php');

/*
 * fix for using PHPUnit as composer package and PEAR extension
 */
$composerClassName = '\PHPUnit\Framework\TestCase';
$pearClassName = '\PHPUnit_Framework_TestCase';
if (!class_exists($composerClassName) && class_exists($pearClassName)) {
    class_alias($pearClassName, $composerClassName);
}