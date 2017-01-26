<?php
define('DIAFAN', 1);
define('ABSOLUTE_PATH', dirname(dirname(__FILE__)) . '/vendor/diafan/cms/');
define("VERSION", "6.0");

include_once(ABSOLUTE_PATH . "config.php");

if (!defined("DB_PREFIX")) {
    die('no DB_PREFIX');
}
$prefix = DB_PREFIX;

if ($argc > 1) {
    $prefix = $argv[1] . '_';
}

include_once ABSOLUTE_PATH . 'includes/custom.php';
Custom::init();
include_once(ABSOLUTE_PATH . 'includes/developer.php');
include_once(ABSOLUTE_PATH . 'includes/diafan.php');
include_once(ABSOLUTE_PATH . 'includes/file.php');

File::create_dir('return', true);

include_once(ABSOLUTE_PATH . 'includes/core.php');
include_once ABSOLUTE_PATH . 'includes/init.php';
$diafan = new Init();

$result = DB::query("SHOW TABLES LIKE '" . $prefix . "%'");
while ($row = DB::fetch_row($result)) {
    echo $row[0] . PHP_EOL;
    DB::query("DROP TABLE " . $row[0]);
}

// переименовываем инстал обратно
rename(ABSOLUTE_PATH . 'copy.install.php', ABSOLUTE_PATH . 'install.php');
