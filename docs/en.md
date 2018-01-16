# 1. Install

## 1.1. Requirements
* PHP 5.3 or later
* PHP Extension PDO with SQLite support

## 1.2. Install
1. Add package to requires:
    ```text
    composer require ipstack/wizard:~2.0
    ```
2. Include autoload file
    ```php
    <?php
    require_once('vendor/autoload.php');
    ```
# 2. Database format (version 2)

|Size|Caption|
|---|---|
|3|A control word that confirms the authenticity of the file. It is always equal to ISD|
|2|Header size|
|1|Ipstack format version|
|1|Registers count ($RGC)|
|2|Size of registers definition unpack format ($RGF)|
|2|Size of registers definition row ($RGD)|
|1|Relations count ($RLC)|
|1|Size of relations definition unpack format ($RLF)|
|2|Size of relation definition row ($LRD)|
|$RLF|Relation unpack format|
|$RGF|Registers metadata unpack format|
|$RLC*$LRD|Relations|
|$RGC*$RGD|Registers metadata|
|$RGD|Networks metadata|
|1024|Index of first octets|
|?|Database of intervals|
|?|Database of Register 1|
|?|Database of Register 2|
|...|...|
|?|Database of Register $RGC|
|4|Unixtime of database creation time|
|128|Author|
|?|Database license|


# 3. Using

1. Prepare files describing the intervals and registers from which you are going to compile a binary database. These files should look like a relational database table.
    
    For example
    
    Intervals file `/path/to/cvs/networks.csv`
    
    ```text
    first_ip,last_ip,register_id
    "0.0.0.0","63.255.255.255",1
    "64.0.0.0","127.255.255.255",2
    "128.0.0.0","191.255.255.255",3
    "192.0.0.0","255.255.255.255",4
    ```
    
    Register file `/path/to/cvs/register.csv`.
    
    ```text
    Unused row. e.g. copiryght. Second row is a header, also unused.
    id,interval_name
    1,"Interval 1"
    2,"Interval 2"
    3,"Interval 3"
    4,"Interval 4"
    5,"Unused interval"
    ```
    
1. Initialization wizard
    
    ```php
    $tmpDir = 'path/to/dir/for/temporary/files';
    $wizard = new \Ipstack\Wizard\Wizard($tmpDir);
    ```
    
1. Set creation time.
    
    ```php
    /**
     * $time - time in unixstamp format.
     */
    $wizard->setTime(1507638600); // 2017/10/10 15:30:00
    ```

1. Set author information
    
    ```php
    /**
     * $author can be a string no longer than 64 characters.
     */
    $author = 'Name Surname';
    $wizard->setAuthor($author);
    ```

1. Set license of database
    
    ```php
    /**
     * $license may be the name of a public license or the text of a license. The length is unlimited.
     */
    $license = 'MIT';
    $wizard->setLicense($license);
    ```

1. Add registers
    
    ```php
    $intervals = (new Register('/path/to/cvs/register.csv'))
        ->setCsv('UTF-8')
        ->setFirstRow(2)
        ->setId(1)
        ->addField('name', 2, new StringField())
    ;
    ```

1. Add networks

    ```php
    $network = (new Network('/path/to/cvs/networks.csv', Network::IP_TYPE_ADDRESS, 1, 2))
        ->setCsv('UTF-8')
        ->setFirstRow(3)
    ;
    ```

1. Add networks and registers to database
    
    ```php
    $wizard
        ->addRegister('interval', $intervals)
        ->addNetwork(
            $network,
            array(
                3 => 'interval',
            )
        );
    ```

1. Run compile database
    
    ```php
    $wizard->compile($dbFile);
    ```
    
    Wait and use!

# 4. Relations

If you need relations, use method addRelation() of Wizard object.

For example

`/path/to/coutries.csv`

```text
id,country_code,country_name
1,Au,Australia
2,Cn,China
3,De,Germany
4,Kz,Kazakhstan
```

`/path/to/cities.csv`

```text
id,name,country,latitude,longitude
1,Sidney,1,-33.8699,151.2082
2,Astana,4,51.1271,71.4884
3,Berlin,3,52.5106,13.4383
4,Almaty,4,43.2427,76.9548
5,Ust-Kamenogorsk,4,49.9075,82.6213
```

`/path/to/networks.csv`

```text
first_ip,last_ip,city
"1.0.0.0","1.255.255.255",1
"2.0.0.0","2.255.255.255",2
"3.0.0.0","3.255.255.255",3
"4.0.0.0","4.255.255.255",4
"5.0.0.0","5.255.255.255",5
```

`script.php`

```php
<?php
require_once('vendor/autoload.php');
$countries = (new Register('/path/to/coutries.csv'))
    ->setCsv('UTF-8')
    ->setFirstRow(2)
    ->setId(1)
    ->addField('code', 2, new StringField(StringField::TRANSFORM_LOWER, 2))
    ->addField('name', 3, new StringField())
;
$cities = (new Register('/path/to/cities.csv'))
    ->setCsv('UTF-8')
    ->setFirstRow(2)
    ->setId(1)
    ->addField('name', 2, new StringField(0))
    ->addField('countryId', 3, new NumericField(0))
    ->addField('latitude', 4, new LatitudeField())
    ->addField('longitude', 5, new LongitudeField())
;
$network = (new Network('/path/to/networks.csv', Network::IP_TYPE_ADDRESS, 1, 2))
    ->setCsv('UTF-8')
    ->setFirstRow(2)
;

$wizard = (new Wizard('/path/to/tmp'))
    ->setAuthor('Ipstack wizard')
    ->setTime(time())
    ->setLicense('WTFPL')
    ->addRegister('city', $cities)
    ->addRegister('country', $countries)
    ->addRelation('city', 'countryId', 'country')
    ->addNetwork(
        $network,
        array(
            3 => 'city',
        )
    )
;
$wizard->compile('ipstack.dat');
``` 
