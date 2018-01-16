# 1. Установка

## 1.1. Системные требования
* PHP 5.3 или выше;
* PHP расширение PDO с поддержкой SQLite.

## 1.2. Установка
1. Добавьте пакет в зависимости проекта:
    ```text
    composer require ipstack/wizard:~2.0
    ```
2. Подключите автозагрузчик
    ```php
    <?php
    require_once('vendor/autoload.php');
    ```
# 2. Формат базы данных (версия 2)

|Размер блока, байт|Описание|
|---|---|
|3|Контрольное слово. Всегда равно ISD|
|2|Размер заголовка|
|1|Версия формата Ipstack|
|1|Количество справочников ($RGC)|
|2|Размер строки с форматом распаковки метаданных регистров ($RGF)|
|2|Размер упакованной строки метаданных регистров ($RGD)|
|1|Количество связейй ($RLC)|
|1|Размер формата распакковки описания связей ($RLF)|
|2|Размер упакованной строки описания связей ($LRD)|
|$RLF|Формат распаковки описания связей|
|$RGF|Формат распаковки метаданныйй справочников|
|$RLC*$LRD|Описание связей|
|$RGC*$RGD|Метаданные справочников|
|$RGD|Метаданные диапазонов|
|1024|Индекс|
|?|База интервалов|
|?|База справочника 1|
|?|База справочника 2|
|...|...|
|?|База справочника $RGC|
|4|Метка времени актуальности базы данных|
|128|Автор базы данных|
|?|Лицензия базы данных|


# 3. Использование

1. Подготовьте файлы описания интервалов т справочников для бинарной базы данных. Эти файлы должны быть схожи с таблицами реляционнойй базы данных.
    
    Пример:
        
    Файл справочника `/path/to/cvs/register.csv`.
    
    ```text
    Неиспользуемая строка. Например, копирайт. Вторая строка содержит шапку таблицы и тоже не используется.
    id,interval_name
    1,"Interval 1"
    2,"Interval 2"
    3,"Interval 3"
    4,"Interval 4"
    5,"Unused interval"
    ```
    
    Файл интервалов `/path/to/cvs/networks.csv`.
    
    ```text
    first_ip,last_ip,register_id
    "0.0.0.0","63.255.255.255",1
    "64.0.0.0","127.255.255.255",2
    "128.0.0.0","191.255.255.255",3
    "192.0.0.0","255.255.255.255",4
    ```
    
1. Инициализация мастера.
    
    ```php
    $tmpDir = 'path/to/dir/for/temporary/files';
    $wizard = new \Ipstack\Wizard\Wizard($tmpDir);
    ```
    
1. Установите актуальное время БД.
    
    ```php
    /**
     * $time - метка времени в формате unixstamp.
     */
    $wizard->setTime(1507638600); // 2017/10/10 15:30:00
    ```

1. Укажите информацию об авторе.
    
    ```php
    /**
     * $author - может быть не длинне 64 символов.
     */
    $author = 'Имя Автора';
    $wizard->setAuthor($author);
    ```

1. Укажите информацию о лицензии базы данных.
    
    ```php
    /**
     * $license - может быть название публичной лицензии, ссылка на лицензию или непосредственно текст лицензии. Длина параметра не лимитирована.
     */
    $license = 'MIT';
    $wizard->setLicense($license);
    ```

1. Опишите справочники.
    
    ```php
    $intervals = (new Register('/path/to/cvs/register.csv'))
        ->setCsv('UTF-8')
        ->setFirstRow(2)
        ->setId(1)
        ->addField('name', 2, new StringField())
    ;
    ```

1. Опишите интервалы.

    ```php
    $network = (new Network('/path/to/cvs/networks.csv', Network::IP_TYPE_ADDRESS, 1, 2))
        ->setCsv('UTF-8')
        ->setFirstRow(3)
    ;
    ```

1. Добавьте справочники и интервалы к базе данных.
    
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

1. Запустите компиляцию базы данных.
    
    ```php
    $wizard->compile($dbFile);
    ```
    
    Дождитесь выполнения и используйте!

# 4. Связи

Если вам нужно указать связи справочников, используйте метод addRelation() объекта Wizard.

Например:

`/path/to/countries.csv`

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
$countries = (new Register('/path/to/countries.csv'))
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
