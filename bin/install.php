<?php
/**
 *  воспроизводим установку с комадной строки
 **/

define('DIAFAN_MYINSTALL', 1);
define('DIAFAN', 1);
define('ABSOLUTE_PATH', dirname(dirname(__FILE__)) . '/vendor/diafan/cms/');
chdir(ABSOLUTE_PATH);

define('IS_HTTPS', false);
define('REVATIVE_PATH', '');
define('IS_ADMIN', 1);
define('IS_INSTALL', true);
define('INSTALL_DEMO', false);
define('BASE_PATH', "http://localhost:8811/");

@date_default_timezone_set('Europe/Moscow');

include_once ABSOLUTE_PATH . '/includes/custom.php';
include_once(dirname(dirname(__FILE__)) . '/public/includes/developer.php');
include_once(ABSOLUTE_PATH . 'includes/diafan.php');
include_once(ABSOLUTE_PATH . 'includes/file.php');

Custom::init();
Dev::init();
Custom::inc('includes/core.php');

$_SESSION["install_admin_name"] = 'test';
$_SESSION["install_admin_pass"] = 'test';
$_SESSION["install_admin_mail"] = 'test@test.loc';
$_SESSION["install_admin_fio"] = 'Test Testovich';

$dbUrlDefault = dirname(dirname(dirname(__FILE__))) . '/diafan_db_url.php';
if (file_exists($dbUrlDefault)) {
    // создайте файл с этой строкой в папке где у вас проекты
    // $db_url = 'mysqli://username:password@localhost/subdname';
    include_once($dbUrlDefault);
} else {
    $db_url = 'mysqli://root@localhost/diafan';
}


if (!file_exists(ABSOLUTE_PATH . '/install.php')) {
    die('allready installed');
}


try {
    Custom::inc('includes/init.php');

    // ---------------------- начало --------------------
    $project = array_pop(explode(DIRECTORY_SEPARATOR, dirname(dirname(__FILE__)))); // папка проекта


// init

    set_time_limit(0);

// step 4
    $new_values = array(
        'TIT1' => $project,
        'DB_URL' => $db_url,
        'DB_PREFIX' => $project . '_',
        'DB_CHARSET' => 'utf8',
        'LANGUAGE_BASE' => 'ru',
        'VERSION_CMS' => '6.0',
        'MOD_DEVELOPER' => true,
        'MOD_DEVELOPER_CACHE' => false,
        'MOD_DEVELOPER_PROFILING' => false,
        'ROUTE_END' => '/',
        'ROUTE_AUTO_MODULE' => true,
        'USERADMIN' => true,
        'MOBILE_VERSION' => false,
        'ADMIN_FOLDER' => 'admin',
        'USERFILES' => 'userfiles',
        'EMAIL_CONFIG' => $_SESSION["install_admin_mail"],
    );
    include_once(ABSOLUTE_PATH . 'includes/config.php');
    Config::save($new_values, array(0 => array('id' => 1)), INSTALL_DEMO);

    include ABSOLUTE_PATH . 'config.php';

    $diafan = new Init();

    include_once ABSOLUTE_PATH . 'plugins/encoding.php';
    include_once(ABSOLUTE_PATH . "includes/install.php");
    include_once(ABSOLUTE_PATH . "includes/model.php");

// 5
    $install_modules = array();
    if ($dir = opendir(ABSOLUTE_PATH . 'modules')) {
        while (($module = readdir($dir)) !== false) {
            if ($module != '.' && $module != '..') {
                if (file_exists(ABSOLUTE_PATH . 'modules/' . $module . '/' . $module . '.install.php')) {
                    include_once(ABSOLUTE_PATH . 'modules/' . $module . '/' . $module . '.install.php');
                    $name = Ucfirst($module) . '_install';
                    $class_t = new $name($diafan);

                    if (!$class_t->is_core) {
                        $sort = 99;
                        if (!empty($class->admin[0]["sort"])) {
                            $sort = $class->admin[0]["sort"];
                        }
                        while (isset($install_modules[$sort])) {
                            $sort++;
                        }
                        $install_modules[$sort] = $module;
                    }
                }
            }
        }
        closedir($dir);
    }
    ksort($install_modules);
//  6

    if (!is_dir(ABSOLUTE_PATH . 'userfiles/demo')) {
        if (!file_exists(ABSOLUTE_PATH . 'userfiles/demo.zip')) {
            File::copy_file('http://www.diafan.ru/demo.zip', 'userfiles/demo.zip');
        }
        if (!class_exists('ZipArchive')) {
            $this->error('На сервере не установлено расширение для распоковки ZIP-архивов. Распакуйте содержимое архива userfiles/demo.zip в папку userfiles/demo.');
        }
        $zip = new ZipArchive;
        if ($zip->open(ABSOLUTE_PATH . 'userfiles/demo.zip') === true) {
            $zip->extractTo(ABSOLUTE_PATH . 'userfiles/demo');
            $zip->close();
        }
    }

    // ставим только русский
    define('_LANG', 1);
    $langs = array(1);


    // ядро

    $core_tables = array('service', 'admin', 'config', 'images', 'attachments', 'menu', 'site');
    $modules = $core_tables;

    $dir = Custom::read_dir('modules');
    foreach ($dir as $module) {
        if (file_exists(ABSOLUTE_PATH . 'modules/' . $module . '/' . $module . '.install.php')) {
            include_once(ABSOLUTE_PATH . 'modules/' . $module . '/' . $module . '.install.php');
            $name = Ucfirst($module) . '_install';
            $class[$module] = new $name($diafan);
            $class[$module]->langs = $langs;
            $class[$module]->module = $module;
            $class[$module]->install_modules = $install_modules;
            if (!in_array($module, $core_tables)) {
                $modules[] = $module;
            }
        }
    }
    
    foreach ($modules as $module) {
        $class[$module]->tables();
    }
    foreach ($modules as $module) {
        $class[$module]->start(true);
    }
    foreach ($modules as $module) {
        $class[$module]->action_post();
    }
    $diafan->_cache->delete("", array());

    // переименовываем инстал
    rename(ABSOLUTE_PATH . 'install.php', ABSOLUTE_PATH . 'copy.install.php');

    // ---------------------- конец --------------------

} catch (Exception $e) {
    echo PHP_EOL . PHP_EOL . 'ERROR' . PHP_EOL . PHP_EOL;
    if (DIRECTORY_SEPARATOR === '/') {
        echo $e->getMessage() . PHP_EOL;
    } else {
        // консоль винды кодировка доса
        echo mb_convert_encoding($e->getMessage(), 'cp-866', 'UTF-8') . PHP_EOL;
    }
    echo $e->getFile();
    echo ':';
    echo $e->getLine() . PHP_EOL . PHP_EOL;
}
