<?php

namespace Ipstack\Wizard;

use Ipstack\Wizard\Sheet\Field\LatitudeField;
use Ipstack\Wizard\Sheet\Field\LongitudeField;
use Ipstack\Wizard\Sheet\Field\NumericField;
use Ipstack\Wizard\Sheet\Field\StringField;
use Ipstack\Wizard\Sheet\Network;
use Ipstack\Wizard\Sheet\Register;
use Ipstack\Wizard\Sheet\Field\FieldAbstract;
use PDO;
use PDOStatement;
use PDOException;

/**
 * Class Wizard
 *
 * @const int FORMAT_VERSION
 * @property string $tmpDir
 * @property string $author
 * @property string $license
 * @property int    $time
 * @property array  $networks
 * @property array  $registers
 * @property array  $relations
 * @property PDO    $pdo
 * @property PDOStatement $insertIps
 * @property PDOStatement $insertRegister
 * @property PDOStatement[][] $prepare
 * @property string $prefix
 * @property array $meta
 * @property FieldAbstract[][] $fields
 */
class Wizard
{
    /**
     * @const int
     */
    const FORMAT_VERSION = 2;

    /**
     * @var string
     */
    protected $tmpDir;

    /**
     * @var string
     */
    protected $author;

    /**
     * @var string
     */
    protected $license;

    /**
     * @var integer
     */
    protected $time;

    /**
     * @var array
     */
    protected $networks = array();

    /**
     * @var array
     */
    protected $registers = array();

    /**
     * @var array
     */
    protected $relations = array();

    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * @var PDOStatement
     */
    protected $insertIps;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var PDOStatement[]
     */
    protected $prepare;

    /**
     * @var array $meta
     */
    protected $meta = array(
        'index' => array(),
        'registers' => array(),
        'relations' => array(
            'pack' => '',
            'unpack' => '',
            'len' => 3,
            'items' => 0,
        ),
        'networks' => array(
            'pack' => '',
            'unpack' => '',
            'len' => 4,
            'items' => 0,
            'fields' => array(),
        ),
    );

    /**
     * @var array
     */
    protected $fields = array();

    /**
     * Wizard constructor.
     *
     * @param string $tmpDir
     * @throws \InvalidArgumentException
     */
    public function __construct($tmpDir)
    {
        if (!is_string($tmpDir)) {
            throw new \InvalidArgumentException('incorrect tmpDir');
        }
        if (!is_dir($tmpDir)) {
            throw new \InvalidArgumentException('tmpDir is not a directory');
        }
        if (!is_writable($tmpDir)) {
            throw new \InvalidArgumentException('tmpDir is not a writable');
        }
        $this->tmpDir = $tmpDir;
        $this->prefix = $this->tmpDir.DIRECTORY_SEPARATOR.'iptool.wizard.'.uniqid();
    }

    /**
     * Set author.
     *
     * @param string $author
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setAuthor($author)
    {
        if (!is_string($author)) {
            throw new \InvalidArgumentException('incorrect author');
        }
        if (mb_strlen($author) > 64) $author = mb_substr($author,0,64);
        $this->author = $author;
        return $this;
    }

    /**
     * Set license.
     *
     * @param string $license
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setLicense($license)
    {
        if (!is_string($license)) {
            throw new \InvalidArgumentException('incorrect license');
        }
        $this->license = $license;
        return $this;
    }

    /**
     * Set time.
     *
     * @param integer $time
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setTime($time)
    {
        if (!is_int($time) || $time < 0) {
            throw new \InvalidArgumentException('incorrect time');
        }
        $this->time = $time;
        return $this;
    }

    /**
     * Add network.
     *
     * @param Network $network
     * @param array $map
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addNetwork($network, $map)
    {
        if (!($network instanceof Network)) {
            throw new \InvalidArgumentException('incorrect network');
        }
        $this->networks[] = array(
            'network' => $network,
            'map' => $map,
        );
        return $this;
    }

    /**
     * Add register.
     *
     * @param string $name
     * @param Register $register
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addRegister($name, $register)
    {
        if (!Register::checkName($name)) {
            throw new \InvalidArgumentException('incorrect name');
        }
        if (!($register instanceof Register)) {
            throw new \InvalidArgumentException('incorrect register');
        }
        $fields = $register->getFields();
        if (empty($fields)) {
            throw new \InvalidArgumentException('fields of register can not be empty');
        }
        foreach ($fields as $field=>$type) {
            $this->fields[$name][$field] = $type['type'];
        }
        $this->registers[$name] = $register;
        return $this;
    }

    /**
     * Remove register.
     *
     * @param string $name
     * @return $this
     */
    public function removeRegister($name)
    {
        if (isset($this->registers[$name])) {
            unset($this->registers[$name]);
        }
        return $this;
    }

    /**
     * Add relation
     *
     * @param string $parent
     * @param string $field
     * @param string $child
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addRelation($parent, $field, $child)
    {
        if (!isset($this->registers[$parent])) {
            throw new \InvalidArgumentException('parent register not exists');
        }
        if (!isset($this->registers[$child])) {
            throw new \InvalidArgumentException('child register not exists');
        }
        if (!is_string($field)) {
            throw new \InvalidArgumentException('incorrect field');
        }
        $this->relations[$parent][$field] = $child;
        return $this;
    }

    /**
     * Get relations.
     *
     * @return array
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * Remove relation.
     *
     * @param string $parent
     * @param string $column
     * @return $this
     */
    public function removeRelation($parent, $column)
    {
        if (isset($this->relations[$parent][$column])) {
            unset($this->registers[$parent][$column]);
        }
        return $this;
    }

    /**
     * Compile database.
     *
     * @param string $filename
     * @throws \PDOException
     * @throws \ErrorException
     */
    public function compile($filename)
    {
        if (!is_string($filename)) {
            throw new \InvalidArgumentException('incorrect filename');
        }
        if (file_exists($filename) && !is_writable($filename)) {
            throw new \InvalidArgumentException('file not writable');
        }
        if (!file_exists($filename) && !is_writable(dirname($filename))) {
            throw new \InvalidArgumentException('directory not writable');
        }
        if (empty($this->time)) $this->time = time();

        $this->checkRelations();

        $tmpDb = $this->prefix.'.db.sqlite';
        try {
            $this->pdo = new PDO('sqlite:' . $tmpDb);
            $this->pdo->exec('PRAGMA foreign_keys = 1;PRAGMA integrity_check = 1;PRAGMA encoding = \'UTF-8\';');
        } catch (PDOException $e) {
            throw  $e;
        }
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->sortRegisters();

        $this->createTmpDb();

        foreach ($this->registers as $table=>$register) {
            $this->addRegisterInTmpDb($register, $table);
        }

        foreach ($this->networks as $network) {
            $this->addNetworkInTmpDb($network);
        }

        $this->cleanTmpDb();

        $this->updatePackFormat();

        foreach ($this->registers as $table=>$register) {
            $this->compileRegister($table);
        }

        $this->compileNetwork();

        $this->compileHeader();

        $this->makeFile($filename);

        $this->pdo = null;

        //unlink($tmpDb);

    }

    /**
     * Create tmp sqlite database.
     */
    protected function createTmpDb()
    {
        $sql = '
            CREATE TABLE `_ips` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `ip` INTEGER,
                `action` TEXT,
                `parameter` TEXT,
                `value` TEXT,
                `offset` TEXT
            );
            CREATE INDEX `ip` ON `_ips` (`ip`);
            CREATE INDEX `parameter` ON `_ips` (`parameter`);
            CREATE INDEX `value` ON `_ips` (`value`);
        ';
        $this->pdo->exec($sql);
        $sql = 'INSERT INTO `_ips` (`ip`,`action`,`parameter`,`value`) VALUES (:ip,:action,:parameter,:value);';
        $this->insertIps = $this->pdo->prepare($sql);
        $this->insertIps->execute(array(
            'ip' => 0,
            'action' => 'add',
            'parameter' => NULL,
            'value' => NULL,
        ));
        foreach ($this->registers as $table=>$register) {
            $columns = $register->getFields();
            $fields = array('`_pk` TEXT', '`_offset` INTEGER');
            $index = array();
            foreach ($columns as $field=>$data) {
                $fields[] = '`' . $field . '` '.$data['type']->getSqliteType();
                $fields[] = '`_len_' . $field . '` INTEGER';
                $index[] = '`_len_' . $field . '`';
            }
            $constraints = array(
                'CONSTRAINT `_pk` PRIMARY KEY (`_pk`) ON CONFLICT IGNORE',
            );
            if (!empty($this->relations[$table])) {
                foreach ($this->relations[$table] as $field => $child) {
                    $line = 'CONSTRAINT `'.$table.'_'.$field.'_'.$child.'` FOREIGN KEY (`' . $field . '`)';
                    $line .= ' REFERENCES `' . $child . '` (`_pk`)';
                    $line .= ' ON DELETE CASCADE ON UPDATE CASCADE';
                    $constraints[] = $line;
                }
            }
            $sql = 'CREATE TABLE `' . $table . '` (' . implode(', ', $fields) . ', '.implode(', ', $constraints).');'.PHP_EOL;
            foreach ($index as $i) {
                $sql .= 'CREATE INDEX '.$i.' ON `'.$table.'` ('.$i.');'.PHP_EOL;
            }
            $this->pdo->exec($sql);
        }
    }

    /**
     * Create temporary network.
     *
     * @param array $data
     * @throws \ErrorException
     */
    protected function addNetworkInTmpDb($data)
    {
        /**
         * @var Network $network
         */
        $network = $data['network'];
        $source = $network->getCsv();

        $firstRow = $network->getFirstRow()-1;
        $csv = fopen($source['file'], 'r');
        for($ignore=0; $ignore < $firstRow; $ignore++) {
            $row = fgetcsv($csv, 4096, $source['delimiter'], $source['enclosure'], $source['escape']);
            unset($row);
        }
        $this->pdo->beginTransaction();
        $transactionIterator = 0;
        $insert = $this->pdo->prepare('INSERT INTO `_ips` (`ip`,`action`,`parameter`,`value`) VALUES (:ip,:action,:parameter,:value);');
        while ($row = fgetcsv($csv, 4096, $source['delimiter'], $source['enclosure'], $source['escape'])) {
            $firstIpColumn = $network->getFistIpColumn()-1;
            $lastIpColumn = $network->getLastIpColumn()-1;
            if (!isset($row[$firstIpColumn])) {
                $this->pdo->rollBack();
                throw new \ErrorException('have not column with first ip address');
            }
            if (!isset($row[$lastIpColumn])) {
                $this->pdo->rollBack();
                throw new \ErrorException('have not column with last ip address');
            }
            $firstIp = $network->getLongIp($row[$firstIpColumn], false);
            $lastIp = $network->getLongIp($row[$lastIpColumn], true);
            foreach ($data['map'] as $column=>$register) {
                $column--;
                $value = isset($row[$column]) ? $row[$column] : null;
                $insert->execute(array(
                    'ip' => $firstIp,
                    'action' => 'add',
                    'parameter' => $register,
                    'value' => $value,
                ));
                $insert->execute(array(
                    'ip' => $lastIp + 1,
                    'action' => 'remove',
                    'parameter' => $register,
                    'value' => $value,
                ));
                $transactionIterator += 2;
                if ($transactionIterator > 100000) {
                    $transactionIterator = 0;
                    $this->pdo->commit();
                    $this->pdo->beginTransaction();
                }
                if (isset($row[$column])) {
                    $this->meta['networks']['fields'][$register] = null;
                }
            }
        }
        $this->pdo->commit();
    }

    /**
     * Create temporary register.
     *
     * @param Register $register
     * @param string $table
     */
    protected function addRegisterInTmpDb($register, $table)
    {
        $source = $register->getCsv();

        $columns = $register->getFields();
        $fields = array('`_pk`');
        $params = array(':_pk');
        foreach ($columns as $field=>$data) {
            $fields[] = '`' . $field . '`';
            $fields[] = '`_len_' . $field . '`';
            $params[] = ':' . $field;
            $params[] = ':_len_' . $field;
        }
        $sql = 'INSERT INTO `'.$table.'` (' . implode(',', $fields) . ') VALUES (' . implode(',', $params) . ');';
        $insertStatement = $this->pdo->prepare($sql);

        $firstRow = $register->getFirstRow()-1;
        $csv = fopen($source['file'], 'r');
        for($ignore=0; $ignore < $firstRow; $ignore++) {
            $row = fgetcsv($csv, 4096, $source['delimiter'], $source['enclosure'], $source['escape']);
            unset($row);
        }
        $rowIterator = 0;
        $idColumn = $register->getId()-1;
        $this->pdo->beginTransaction();
        $transactionIterator = 0;
        while ($row = fgetcsv($csv, 4096, $source['delimiter'], $source['enclosure'], $source['escape'])) {
            $rowIterator++;
            $rowId = $rowIterator;
            if ($idColumn >= 0 && isset($row[$idColumn])) {
                $rowId = $row[$idColumn];
            }
            $values = array(
                '_pk' => $rowId,
            );
            foreach ($columns as $field=>$data) {
                $column = $data['column']-1;
                $value = isset($row[$column])?$row[$column]:null;
                $value = $this->fields[$table][$field]->getValidValue($value);
                $values[$field] = $value;
                $values['_len_'.$field] = strlen($value);
            };

            $insertStatement->execute($values);
            $transactionIterator++;
            if ($transactionIterator > 100000) {
                $this->pdo->commit();
                $this->pdo->beginTransaction();
                $transactionIterator = 0;
            }
        }
        $this->pdo->commit();
    }

    /**
     * Compile register from temporary db
     *
     * @param $register
     */
    protected function compileRegister($register)
    {
        $file = fopen($this->prefix.'.reg.'.$register.'.dat', 'w');
        $pack = $this->meta['registers'][$register]['pack'];
        $empty =  $this->meta['registers'][$register]['fields'];
        $bin = self::packArray($pack, $empty);
        fwrite($file,$bin);
        $offset = 0;
        $select = array(
            '*' => '`'.$register.'`.*',
        );
        $join = array();
        if (isset($this->relations[$register])) {
            foreach ($this->relations[$register] as $fk=>$fTable) {
                $as = $fTable.'_'.$fk;
                $join[$as] = ' INNER JOIN `'.$fTable.'` `'.$as.'` ON (`'.$register.'`.`'.$fk.'` = `'.$as.'`.`_pk`)';
                $select[$as] = '`'.$as.'`.`_offset` AS `'.$fk.'`';
            }
        }
        $sql = 'SELECT '.implode(', ', $select).' FROM `'.$register.'`'.implode(',', $join).';';
        $data = $this->pdo->query($sql);
        $this->pdo->beginTransaction();
        $transactionIterator = 0;
        while($row = $data->fetch()) {
            $rowId = $row['_pk'];
            unset($row['_pk']);
            unset($row['_offset']);
            foreach ($this->fields[$register] as $field=>$type) {
                unset($row['_len_'.$field]);
            }
            $check = 0;
            foreach ($row as $cell=>$cellValue) {
                if (!empty($cellValue)) $check = 1;
            }
            $bin = self::packArray($pack, $row);
            if ($check) {
                $offset ++;
                fwrite($file,$bin);
            }

            $this->pdo->exec('UPDATE `'.$register.'` SET `_offset` =\''.($check?$offset:0).'\' WHERE `_pk` = \''.$rowId.'\';');
            $this->pdo->exec('UPDATE `_ips` SET `offset` =\''.($check?$offset:0).'\' WHERE `parameter` = \''.$register.'\' AND `value`=\''.$rowId.'\';');

            $transactionIterator += 2;
            if ($transactionIterator > 100000) {
                $this->pdo->commit();
                $this->pdo->beginTransaction();
                $transactionIterator = 0;
            }
        }
        $this->meta['registers'][$register]['items'] = $offset;
        $this->pdo->commit();
        fclose($file);
    }

    /**
     * Compile network from temporary db
     */
    protected function compileNetwork()
    {
        $ip = -1;
        $this->meta['networks']['pack'] = '';
        $fields = $this->meta['networks']['fields'];
        $values = array();
        foreach ($fields as $register=>$null) {
            $values[$register] = array();
            $type = new NumericField(0);
            $type->updatePackFormat($this->meta['registers'][$register]['items']);
            $pack = $type->getPackFormat();
            $this->meta['networks']['pack'] .= $pack;
            $this->meta['networks']['unpack'] .= $pack.$register.'/';
        }
        $pack = $this->meta['networks']['pack'];
        $this->meta['networks']['unpack'] = mb_substr($this->meta['networks']['unpack'],0,-1);
        $binaryPrevData = self::packArray($pack, $fields);
        $this->meta['networks']['len'] += strlen($binaryPrevData);
        $offset = 0;
        $this->meta['index'][0] = 0;
        $file = fopen($this->prefix.'.networks.dat','w');
        $ipInfo = $this->pdo->query('SELECT * FROM `_ips` ORDER BY `ip` ASC, `action` DESC, `id` ASC;');
        while ($row = $ipInfo->fetch()) {
            if ($row['ip'] !== $ip) {
                foreach ($values as $param=>$v) {
                    if (!empty($param)) $fields[$param] = array_pop($v);
                }
                $binaryData = self::packArray($pack, $fields);
                if ($binaryData !== $binaryPrevData || empty($ip)) {
                    fwrite($file, pack('N', $ip) . $binaryData);
                    $octet = (int)long2ip($ip);
                    if (!isset($this->meta['index'][$octet])) $this->meta['index'][$octet] = $offset;
                    $offset++;
                    $binaryPrevData = $binaryData;
                }
                $ip = $row['ip'];
            }
            if ($row['action'] == 'remove') {
                $key = array_search($row['offset'],$values[$row['parameter']]);
                if ($key !== false) {
                    unset($values[$row['parameter']][$key]);
                }
            } else {
                $values[$row['parameter']][] = $row['offset'];
            }
        }
        if ($ip < ip2long('255.255.255.255')) {
            foreach ($values as $param => $v) {
                if (!empty($param)) $fields[$param] = array_pop($v);
            }
            $binaryData = self::packArray($pack, $fields);
            if ($binaryData !== $binaryPrevData) {
                $octet = (int)long2ip($ip);
                if (!isset($this->meta['index'][$octet])) $this->meta['index'][$octet] = $offset;
                $offset++;
                fwrite($file, pack('N', $ip) . $binaryData);
            }
        }
        $this->meta['networks']['items'] = $offset;
        for($i=1;$i<=255;$i++) {
            if (!isset($this->meta['index'][$i])) $this->meta['index'][$i] = $this->meta['index'][$i-1];
        }
        ksort($this->meta['index']);
        fclose($file);
        unset($ip);
    }

    /**
     * Check for recursive relations.
     *
     * @throws \ErrorException
     */
    protected function checkRelations()
    {
        foreach ($this->relations as $parent=>$relation) {
            foreach ($relation as $field=>$child) {
                if (isset($this->relations[$child]) && in_array($parent, $this->relations[$child])) {
                    throw new \ErrorException('relations can not be recursive');
                }
            }
        }
        $parentType = new StringField();
        $fieldType = new StringField();
        $childType = new StringField();
        foreach ($this->relations as $parent => $networkRelation) {
            foreach ($networkRelation as $field => $child) {
                $parentType->updatePackFormat($parent);
                $childType->updatePackFormat($child);
                $fieldType->updatePackFormat($field);

                $this->meta['relations']['items'] ++;
                $this->meta['relations']['data'][] = array(
                    $parent,
                    $field,
                    $child
                );
            }
        }
        $this->meta['relations']['pack'] = $parentType->getPackFormat()
            .$fieldType->getPackFormat()
            .$childType->getPackFormat();
        $this->meta['relations']['unpack'] = $parentType->getPackFormat().'p/'
            .$fieldType->getPackFormat().'f/'
            .$childType->getPackFormat().'c';
        $this->meta['relations']['len'] = strlen(pack($this->meta['relations']['pack'], null, null, null));
    }

    /**
     * Compile header.
     */
    protected function compileHeader()
    {
        /*
         * Ipstack format version.
         */
        $header = pack('C', self::FORMAT_VERSION);

        /*
         * Registers count.
         */
        $header .= pack('C', count($this->meta['registers']));

        $rnmType = new StringField();
        $rnmType->updatePackFormat('n');
        $pckType = new StringField();;
        $pckType->updatePackFormat($this->meta['networks']['unpack']);
        $lenType = new NumericField();
        $lenType->updatePackFormat($this->meta['networks']['len']);
        $itmType = new NumericField();
        $itmType->updatePackFormat($this->meta['networks']['items']);
        foreach ($this->meta['registers'] as $registerName => $register) {
            $rnmType->updatePackFormat($registerName);
            $pckType->updatePackFormat($register['unpack']);
            $lenType->updatePackFormat($register['len']);
            $itmType->updatePackFormat($register['items']);
        }
        $len = $lenType->getPackFormat();
        $itm = $itmType->getPackFormat();
        $pck = $pckType->getPackFormat();
        $rnm = $rnmType->getPackFormat();

        $pack = $rnm.$pck.$len.$itm;
        $unpack =$rnm.'name/'.$pck.'pack/'.$len.'len/'.$itm.'items';

        /*
         * Size of registers definition unpack format.
         */
        $header .= pack('S',strlen($unpack));

        /*
         * Size of registers definition row.
         */
        $header .= pack('S',strlen(pack($pack,'','',0,0)));

        /*
         * Relations count.
         */
        $header .= pack('C', $this->meta['relations']['items']);

        /*
         * Size of relations definition unpack format.
         */
        $lenRelationsFormat = strlen($this->meta['relations']['unpack']);
        $header .= pack('C', $lenRelationsFormat);

        /*
         * Size of relation definition row.
         */
        $header .= pack('S', $this->meta['relations']['len']);

        /*
         * Relation unpack format (parent, column, child).
         */
        $header .= $this->meta['relations']['unpack'];

        /*
         * Registers metadata unpack format.
         */
        $header .= pack('A*',$unpack);

        /*
         * Relations.
         */
        if (!empty($this->meta['relations']['data'])) {
            foreach ($this->meta['relations']['data'] as $relation) {
                $header .= self::packArray(
                    $this->meta['relations']['pack'],
                    $relation
                );
            }
        }

        /**
         * Registers metadata.
         */
        foreach ($this->meta['registers'] as $registerName => $register) {
            $header .= pack(
                $pack,
                $registerName,
                $register['unpack'],
                $register['len'],
                $register['items']
            );
        }

        /*
         * Networks metadata.
         */
        $header .= pack(
            $pack,
            'n',
            $this->meta['networks']['unpack'],
            $this->meta['networks']['len'],
            $this->meta['networks']['items']
        );

        /*
         * Index of first octets.
         */
        $header .= $this->packArray('I*',$this->meta['index']);

        /*
         * Control word and header size.
         */
        $headerLength = strlen($header);
        $header = 'ISD'.pack('S', $headerLength).$header;

        $file = fopen($this->prefix.'.header', 'w');
        fwrite($file, $header);
        fclose($file);
    }

    /**
     * Make file.
     *
     * @param string $fileName
     */
    protected function makeFile($fileName)
    {
        /*
         * Create binary database.
         */
        $tmp = $this->prefix.'.database.dat';
        $database = fopen($tmp,'w');

        /*
         * Write header to database.
         */
        $file = $this->prefix.'.header';
        $stream = fopen($file, 'rb');
        stream_copy_to_stream($stream, $database);
        fclose($stream);
        if (is_writable($file)) unlink($file);

        /*
         * Write networks to database.
         */
        $file = $this->prefix.'.networks.dat';
        $stream = fopen($file, 'rb');
        stream_copy_to_stream($stream, $database);
        fclose($stream);
        if (is_writable($file)) unlink($file);

        foreach ($this->meta['registers'] as $register=>$data) {
            $file = $this->prefix.'.reg.'.$register.'.dat';
            $stream = fopen($file, 'rb');
            stream_copy_to_stream($stream, $database);
            fclose($stream);
            if (is_writable($file)) unlink($file);
        }

        $time = empty($this->time)?time():$this->time;
        fwrite($database,pack('I1A128',$time,$this->author));
        fwrite($database,pack('A*',$this->license));
        fclose($database);

        rename($tmp, $fileName);
    }

    /**
     * Sort registers.
     *
     */
    protected function sortRegisters()
    {
        $sortedRegisters = array();
        $deletedRegisters = $registers = array_keys($this->registers);
        foreach ($this->networks as $network) {
            foreach ($network['map'] as $register) {
                $nd = array_search($register, $deletedRegisters);
                if (false !== $nd) {
                    unset($deletedRegisters[$nd]);
                }
            }
        }
        foreach ($registers as $register) {
            $nd = array_search($register, $deletedRegisters);
            if (false !== $nd) {
                unset($deletedRegisters[$nd]);
            }
            if (!empty($this->relations[$register])) {
                foreach ($this->relations[$register] as $child) {
                    $nd = array_search($child, $deletedRegisters);
                    if (false !== $nd) {
                        unset($deletedRegisters[$nd]);
                    }
                    $nc = array_search($child, $sortedRegisters);
                    if (false === $nc) {
                        array_unshift($sortedRegisters, $child);
                        unset($registers[$child]);
                        $nc = 0;
                    }
                    $np = array_search($register, $sortedRegisters);
                    if (false === $np) {
                        array_splice($sortedRegisters, $nc+1, 0, $register);
                        unset($registers[$register]);
                    } elseif ( $np < $nc ) {
                        array_splice($sortedRegisters, $np+1, 1);
                        array_splice($sortedRegisters, $nc+1, 0, $register);
                    }
                }
            }
        }
        foreach ($sortedRegisters as $register) {
            if (in_array($register, $deletedRegisters)) {
                unset($this->registers[$register]);
            } else {
                $data = $this->registers[$register];
                unset($this->registers[$register]);
                $this->registers[$register] = $data;
            }
        }
    }

    protected function cleanTmpDb()
    {
        $registers = array_reverse(array_keys($this->registers));
        foreach ($registers as $parent) {
            if (!empty($this->relations[$parent])) {
                foreach ($this->relations[$parent] as $field => $child) {
                    $innerSql = 'SELECT `' . $field . '` FROM `' . $parent . '` GROUP BY `' . $field . '`';
                    $sql = 'DELETE FROM `' . $child . '` WHERE `_pk` NOT IN (' . $innerSql . ');';
                    $this->pdo->exec($sql);
                }
            }
        }
        foreach ($this->networks as $network) {
            foreach ($network['map'] as $register) {
                $innerSql = 'SELECT `_pk` FROM `'.$register.'` GROUP BY `_pk`';
                $sql = 'DELETE FROM `_ips` WHERE  WHERE `parameter` = "'.$register.'" AND `value` NOT IN ('.$innerSql.');';
                $this->pdo->exec($sql);
            }
        }
    }

    protected function updatePackFormat() {
        foreach ($this->fields as $table=>$fields) {
            $format = array();
            $empty = array();
            foreach ($fields as $field=>$type) {
                $row = array(
                    'max' => null,
                );
                if (isset($this->relations[$table][$field])) {
                    $this->fields[$table][$field] = new NumericField(0);
                    $sql = 'SELECT COUNT(*) AS `max` FROM `' . $this->relations[$table][$field] . '`;';
                    $res = $this->pdo->query($sql);
                    $row = $res->fetch();
                } else {
                    switch (get_class($this->fields[$table][$field])) {
                        case LatitudeField::class:
                            break;
                        case LongitudeField::class:
                            break;
                        case StringField::class:
                            $sql = 'SELECT MAX(`_len_' . $field . '`) AS `max` FROM `'.$table.'`;';
                            $res = $this->pdo->query($sql);
                            $r = $res->fetch();
                            $row['max'] = pack('A'.$r['max'], '1');
                            break;
                        default:
                            $sql = 'SELECT MAX(`' . $field . '`) AS `max` FROM `' . $table . '`;';
                            $res = $this->pdo->query($sql);
                            $row = $res->fetch();
                            break;
                    }
                }
                $this->fields[$table][$field]->updatePackFormat($row['max']);
                $fieldPackFormat = $this->fields[$table][$field]->getPackFormat();
                $format['pack'][] = $fieldPackFormat;
                $format['unpack'][] = $fieldPackFormat.$field;
                $empty[$field] = null;
            }
            $pack = implode('', $format['pack']);
            $bin = self::packArray($pack,$empty);
            $this->meta['registers'][$table]['pack'] = $pack;
            $this->meta['registers'][$table]['unpack'] = implode('/',$format['unpack']);
            $this->meta['registers'][$table]['len'] = strlen($bin);
            $this->meta['registers'][$table]['items'] = 0;
            $this->meta['registers'][$table]['fields'] = $empty;
        }
    }

    /**
     * Function-helper for pack arrays
     *
     * @param string $format
     * @param array $array
     * @return string
     */
    public static function packArray($format,$array)
    {
        $packParams = array_values($array);
        array_unshift($packParams,$format);
        return call_user_func_array('pack',$packParams);
    }
}
