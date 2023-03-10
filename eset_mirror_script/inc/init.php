<?php

chdir(__DIR__ . "/..");

$DIRECTORIES = [
    'v3' => [
        'file' => 'eset_upd/update.ver',
        'dll' => false,
        'name' => 'ESET NOD32 Ver. 3-4, 6-8'
    ],
    'v5' => [
        'file' => 'eset_upd/v5/update.ver',
        'dll' => false,
        'name' => 'ESET NOD32 Ver. 5'
    ],
    'ep6' => [
        'file' => 'eset_upd/ep6/update.ver',
        'dll' => false,
        'name' => 'ESET NOD32 Endpoint Ver. 6'
    ],
    'ep7' => [
        'file' => 'eset_upd/ep7/update.ver',
        'dll' => 'eset_upd/ep7/dll/update.ver',
        'name' => 'ESET NOD32 Endpoint Ver. 7'
    ],
    'ep8' => [
        'file' => 'eset_upd/ep8/update.ver',
        'dll' => 'eset_upd/ep8/dll/update.ver',
        'name' => 'ESET NOD32 Endpoint Ver. 8'
    ],
    'ep9' => [
        'file' => 'eset_upd/ep9/update.ver',
        'dll' => 'eset_upd/ep9/dll/update.ver',
        'name' => 'ESET NOD32 Endpoint Ver. 9'
    ],
    'ep10' => [
        'file' => 'eset_upd/ep10/update.ver',
        'dll' => 'eset_upd/ep10/dll/update.ver',
        'name' => 'ESET NOD32 Endpoint Ver. 10'
    ],
    'v9' => [
        'file' => 'eset_upd/v9/update.ver',
        'dll' => false,
        'name' => 'ESET NOD32 Ver. 9'
    ],
    'v10' => [
        'file' => 'eset_upd/v10/update.ver',
        'dll' => 'eset_upd/v10/dll/update.ver',
        'name' => 'ESET NOD32 Ver. 10-11'
    ],
    'v12' => [
        'file' => 'eset_upd/v12/update.ver',
        'dll' => 'eset_upd/v12/dll/update.ver',
        'name' => 'ESET NOD32 Ver. 12'
    ],
    'v13' => [
        'file' => 'eset_upd/v13/update.ver',
        'dll' => 'eset_upd/v13/dll/update.ver',
        'name' => 'ESET NOD32 Ver. 13'
    ],
    'v14' => [
        'file' => 'eset_upd/v14/update.ver',
        'dll' => 'eset_upd/v14/dll/update.ver',
        'name' => 'ESET NOD32 Ver. 14'
    ],
    'v15' => [
        'file' => 'eset_upd/v15/update.ver',
        'dll' => 'eset_upd/v15/dll/update.ver',
        'name' => 'ESET NOD32 Ver. 15'
    ]
];

$VERSION = '20210130 [Freedom for All by Kingston]';

@define('DS', DIRECTORY_SEPARATOR);
@define('SELF', dirname(__DIR__) . DS);
@define('INC', SELF . "inc" . DS);
@define('CLASSES', INC . "classes" . DS);
@define('PATTERN', SELF . "patterns" . DS);
@define('CONF_FILE', SELF . "nod32ms.conf");
@define('LANGPACKS_DIR', SELF . 'langpacks' . DS);
@define('DEBUG_DIR', SELF . 'debug' . DS);
@define('TMP_PATH', SELF . 'tmp' . DS);
@define('KEY_FILE_VALID', 'nod_keys.valid');
@define('KEY_FILE_INVALID', 'nod_keys.invalid');
@define('LOG_FILE', 'nod32ms.log');
@define('SUCCESSFUL_TIMESTAMP', 'nod_lastupdate');
@define('LINKTEST', 'nod_linktest');
@define('DATABASES_SIZE', 'nod_databases_size');

$autoload = function ($class) {
    @include_once CLASSES . "$class.class.php";
};
spl_autoload_register($autoload);
