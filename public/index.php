<?php
// 3 правки в исходном файле от CMS
/**
 * @package    DIAFAN.CMS
 * Bootstrap
 *
 * ВНИМАНИЕ, не надо править этот файл, закачивайте HTML-шаблон в /themes/site.php!
 *
 * @author     diafan.ru
 * @version    6.0
 * @license    http://www.diafan.ru/license.html
 * @copyright  Copyright (c) 2003-2016 OOO «Диафан» (http://www.diafan.ru/)
 */

define('DIAFAN', 1);

// [1] правим пути
define('ANTSON_PATH', dirname(__FILE__).'/');
define('ABSOLUTE_PATH', realpath(dirname(__FILE__).'/../vendor/diafan/cms/').'/');

if (empty($_GET["rewrite"]))
{
    $_GET["rewrite"] = '';
}

define('IS_HTTPS', (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' || ! empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || isset($_SERVER['HTTP_X_HTTPS']) && $_SERVER['HTTP_X_HTTPS'] == '1'));

include ABSOLUTE_PATH . 'config.php';

if (! defined('TIMEZONE') || !TIMEZONE || @!date_default_timezone_set(TIMEZONE))
{
    @date_default_timezone_set('Europe/Moscow');
}

// [2] Свой кастом
include_once ANTSON_PATH.'includes/custom.php';
Custom::init();

// [3] Свой деволоп
include_once ANTSON_PATH.'includes/developer.php';
Dev::init();

try
{
    Custom::inc('includes/core.php');

    if (preg_match('/^'.ADMIN_FOLDER.'(\/|$)/', $_GET["rewrite"]))
    {
// [4] пришлось добавить до полного пути
        include_once(ABSOLUTE_PATH.Custom::path('adm/index.php'));
    }

    define('IS_ADMIN', 0);

    Custom::inc('includes/init.php');

    $diafan = new Init();

    if (file_exists(ABSOLUTE_PATH.'install.php'))
    {
        include ABSOLUTE_PATH.'install.php';
    }
    elseif($_GET["rewrite"] == 'installation')
    {
        header('Location: http://'.getenv("HTTP_HOST").str_replace('installation/', '', getenv("REQUEST_URI")), true, 301);
        exit;
    }

    define('BASE_PATH', "http".(IS_HTTPS ? "s" : '')."://".getenv("HTTP_HOST")."/".(REVATIVE_PATH ? REVATIVE_PATH.'/' : ''));

    $diafan->start();
}
catch (Exception $e)
{
    Dev::exception($e);
}

exit;
