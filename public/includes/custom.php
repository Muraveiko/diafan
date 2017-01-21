<?php
/**
 * Подключение пользовательских разработок
 *
 * @package    DIAFAN.CMS
 * @author     diafan.ru
 * @version    6.0
 * @license    http://www.diafan.ru/license.html
 * @copyright  Copyright (c) 2003-2016 OOO «Диафан» (http://www.diafan.ru/)
 */

if (!defined('DIAFAN')) {
    $path = __FILE__;
    $i = 0;
    while (!file_exists($path . '/includes/404.php')) {
        if ($i == 10) exit;
        $i++;
        $path = dirname($path);
    }
    include $path . '/includes/404.php';
}

class Custom
{
    /**
     * Обнуляет внутрений кэш класса
     *
     * @param string $name
     * @return void
     */
    public static function init($name = '')
    {
        $GLOBALS["custom"] = array(
            // название примененных темы
            "names" => array(),
            // внутренний кэш
            "cache" => array(),
            // для старых версий: получение название темы из бд недоступно
            "no_db" => TRUE,
        );
        if ($name) {
            $GLOBALS["custom"]["names"] = array($name);
        }
        if (!$name && (!defined('IS_DEMO') || !IS_DEMO)) {
            if (defined('CUSTOM')) {
                $GLOBALS["custom"]["names"] = explode(',', CUSTOM);
            } else {
                $GLOBALS["custom"]["no_db"] = true;
            }
        }
    }

    private static function check()
    {
        if ($GLOBALS["custom"]["no_db"] && class_exists('DB') && class_exists('Diafan') && class_exists('File')) {
            if (defined('IS_INSTALL') && IS_INSTALL) {
                $GLOBALS["custom"]["names"] = array();
            } else {
                $GLOBALS["custom"]["no_db"] = false;
                $GLOBALS["custom"]["names"] = array(DB::query_result("SELECT name FROM {custom} WHERE current='1'"));
            }
        }
    }

    /**
     * Возвращаяет название примененной темы
     *
     * @return string
     */
    public static function name($name = '')
    {
        if ($name) {
            $GLOBALS["custom"]["names"][0] = $name;
            return;
        }
        if ($GLOBALS["custom"]["names"]) {
            return $GLOBALS["custom"]["names"][0];
        } else {
            return '';
        }
    }

    /**
     * Возвращаяет названия примененных тем
     *
     * @return array
     */
    public static function names()
    {
        return $GLOBALS["custom"]["names"];
    }

    /**
     * Подключает PHP-файл
     *
     * @param string $path путь до файла относительно корня сайта
     * @return void
     */
    public static function inc($path)
    {
        $path_to_file = self::path($path);
        if (!$path_to_file) {
            throw new Exception('Невозможно подключить файл ' . $path . '.');
        }

        include_once(ABSOLUTE_PATH . $path_to_file);
    }

    /**
     * Проверяет существует ли файл
     *
     * @param string $path_to_file путь до файла относительно корня сайта
     * @return boolean
     */
    public static function exists($path_to_file)
    {
        if (self::path($path_to_file)) {
            return true;
        }
        return false;
    }

    /**
     * Возвращает путь до файла
     *
     * @param string $path_to_file путь до файла относительно корня сайта
     * @return string
     */
    public static function path($path_to_file)
    {
        self::check();
        if (!isset($GLOBALS["custom"]["cache"]["path"][$path_to_file])) {
            $GLOBALS["custom"]["cache"]["path"][$path_to_file] = '';

            $path_to_file_my = '../../../src/' . $path_to_file;
            if (file_exists(ABSOLUTE_PATH . $path_to_file_my)) {

                if('.php'==substr($path_to_file_my,-4))
                {
                    $GLOBALS["custom"]["cache"]["path"][$path_to_file] = $path_to_file_my;
                } else {
                    $GLOBALS["custom"]["cache"]["path"][$path_to_file] = $path_to_file;
                }
            } else {


                foreach ($GLOBALS["custom"]["names"] as $name) {
                    if (file_exists(ABSOLUTE_PATH . 'custom/' . $name . '/' . $path_to_file)) {
                        $GLOBALS["custom"]["cache"]["path"][$path_to_file] = 'custom/' . $name . '/' . $path_to_file;
                    }
                }

            }
            if (empty($GLOBALS["custom"]["cache"]["path"][$path_to_file]) && file_exists(ABSOLUTE_PATH . $path_to_file)) {
                $GLOBALS["custom"]["cache"]["path"][$path_to_file] = $path_to_file;
            }


        }
        if (preg_match('/\.php$/', $path_to_file)) {
            $path_to_custom_file = preg_replace('/\.php$/', '.custom.php', $path_to_file);
            $files_custom = array();
            foreach ($GLOBALS["custom"]["names"] as $name) {
                if (file_exists(ABSOLUTE_PATH . 'custom/' . $name . '/' . $path_to_custom_file)) {
                    $files_custom[] = 'custom/' . $name . '/' . $path_to_custom_file;
                }
            }
            if ($files_custom) {
                $cache_name = md5($path_to_file) . ".php";
                if (MOD_DEVELOPER || !file_exists(ABSOLUTE_PATH . "cache/50a04ed5229b48c39039e72dbedb84fc/" . $cache_name)) {
                    if (!extension_loaded('tokenizer')) {
                        $prefix = (PHP_SHLIB_SUFFIX === 'dll') ? 'php_' : '';
                        if (!function_exists('dl') || false === dl($prefix . 'tokenizer.' . PHP_SHLIB_SUFFIX)) {
                            throw new Exception('Отключено PHP-расширение tokenizer. Обратитесь в техническую поддержку хостинга.');
                        }
                    }
                    if (!is_dir(ABSOLUTE_PATH . 'cache/50a04ed5229b48c39039e72dbedb84fc')) {
                        mkdir(ABSOLUTE_PATH . 'cache/50a04ed5229b48c39039e72dbedb84fc');
                    }
                    $c = self::parse($path_to_file);
                    foreach ($files_custom as $file) {
                        $c_custom = self::parse($file);
                        $c = self::replace($c, $c_custom);
                    }

                    $code = self::create($c);
                    if ($fp = fopen(ABSOLUTE_PATH . "cache/50a04ed5229b48c39039e72dbedb84fc/" . $cache_name, "wb")) {
                        fwrite($fp, $code);
                        fclose($fp);
                    }
                }
                return "cache/50a04ed5229b48c39039e72dbedb84fc/" . $cache_name;
            }
        }
        return $GLOBALS["custom"]["cache"]["path"][$path_to_file];
    }

    /**
     * Читает директорию
     *
     * @param string $path путь до директории относительно корня сайта
     * @return array
     */
    public static function read_dir($path)
    {
        self::check();
        $rows = array();
        if (is_dir(ABSOLUTE_PATH . $path) && $dir = opendir(ABSOLUTE_PATH . $path)) {
            while (($file = readdir($dir)) !== false) {
                if ($file != '.' && $file != '..') {
                    $rows[$path . '/' . $file] = $file;
                    $GLOBALS["custom"]["cache"]["path"][$path . '/' . $file] = $path . '/' . $file;
                }
            }
            closedir($dir);
        }
        if (self::names()) {
            foreach (self::names() AS $name) {
                if (is_dir(ABSOLUTE_PATH . 'custom/' . $name . '/' . $path) && $dir = opendir(ABSOLUTE_PATH . 'custom/' . $name . '/' . $path)) {
                    while (($file = readdir($dir)) !== false) {
                        if ($file != '.' && $file != '..') {
                            $rows[$path . '/' . $file] = $file;
                            $GLOBALS["custom"]["cache"]["path"][$path . '/' . $file] = 'custom/' . $name . '/' . $path . '/' . $file;
                        }
                    }
                    closedir($dir);
                }
            }
        }

        return $rows;
    }

    /**
     * Парсит PHP-файл
     *
     * @param string $path_to_file путь до файла
     * @return array массив классов и фунций, описанных в файле
     */
    private static function parse($path_to_file)
    {
        $result = array();
        $class = '';
        $var = '';
        $func = '';
        $const = '';
        $prefix = array();
        $text = '';
        $level = 0;
        $action = '';

        $source = file_get_contents(ABSOLUTE_PATH . $path_to_file);
        $arr = token_get_all($source);

        $type = 'text';

        //text: type, value
        //var: type, name, prefix, action, value
        //const: type, name, prefix, action, value
        //func: type, name, prefix, post, action, value
        //class: type, name, prefix, post, action, var, const, func

        $open_tag = false;

        foreach ($arr as $i => $s) {
            if (!empty($s[1]) || $s[0] == T_LNUMBER) {
                $k = $s[0];
                $v = $s[1];
            } else {
                $k = 'T_' . $s[0];
                $v = $s[0];
            }
            //echo '<br><b>'.($s[1] ? token_name($s[0]) : $s[0]).' <pre>'.htmlspecialchars($s[1])."</pre>\n</b>";

            switch ($k) {
                case T_STATIC:
                case T_PRIVATE:
                case T_PUBLIC:
                case T_PROTECTED:
                case T_VAR:
                case T_ABSTRACT:
                    if (!$func) {
                        $prefix[] = $v;
                    } else {
                        $text .= $v;
                    }
                    break;

                case T_DOC_COMMENT:
                    break;

                case T_INTERFACE:
                    $prefix[] = 'interface';
                case T_CLASS:
                    if ($text) {
                        $result[] = array('type' => 'text', 'value' => $text);
                        $text = '';
                    }
                    $class = array(
                        'type' => 'class',
                        'name' => '',
                        'prefix' => $prefix,
                        'action' => $action,
                        'post' => '',
                        'open' => false,
                        'const' => array(),
                        'var' => array(),
                        'func' => array(),
                    );
                    $action = '';
                    $prefix = array();
                    break;

                case T_FUNCTION:
                    $func = array(
                        'type' => 'func',
                        'name' => '',
                        'prefix' => $prefix,
                        'action' => $action,
                        'post' => '',
                        'open' => false,
                        'value' => '',
                    );
                    $prefix = array();
                    $action = '';
                    if ($text) {
                        if (!$class) {
                            $result[] = array('type' => 'text', 'value' => $text);
                        }
                        $text = '';
                    }
                    break;

                case T_VARIABLE:
                    if ($class && !$func) {
                        $var = array(
                            'type' => 'var',
                            'name' => $v,
                            'prefix' => $prefix,
                            'action' => $action,
                            'value' => '',
                        );
                        $text = '';
                        $prefix = array();
                        $action = '';
                    } else {
                        $text .= $v;
                    }
                    break;

                case T_CONST:
                    if ($class && !$func) {
                        $const = array(
                            'type' => 'const',
                            'name' => '',
                            'prefix' => $prefix,
                            'action' => $action,
                            'value' => '',
                        );
                        $prefix = array();
                        $action = '';
                    } else {
                        $text .= $v;
                    }
                    break;

                case T_STRING:
                    if ($class && empty($class["name"])) {
                        $class["name"] = $v;
                    } elseif ($const && empty($const["name"])) {
                        $const["name"] = $v;
                    } elseif ($func && empty($func["name"])) {
                        $func["name"] = $v;
                    } elseif ($const && empty($const["name"])) {
                        $const["name"] = $v;
                    } elseif ($v == 'replace') {
                        if ($arr[$i + 1][0] == T_WHITESPACE && in_array($arr[$i + 2][0], array(T_CONST, T_VAR, T_FUNCTION, T_STATIC, T_PRIVATE, T_PUBLIC, T_PROTECTED, T_ABSTRACT))) {
                            $action = $v;
                        } else {
                            $text .= $v;
                        }
                    } elseif (in_array($v, array('after', 'before'))) {
                        if ($arr[$i + 1][0] == T_WHITESPACE && in_array($arr[$i + 2][0], array(T_FUNCTION, T_STATIC, T_PRIVATE, T_PUBLIC, T_PROTECTED, T_ABSTRACT))) {
                            $action = $v;
                        } else {
                            $text .= $v;
                        }
                    } else {
                        $text .= $v;
                    }
                    break;

                case T_NEW:
                    if ($arr[$i + 1][0] == T_WHITESPACE && in_array($arr[$i + 2][0], array(T_CONST, T_VAR, T_FUNCTION, T_STATIC, T_PRIVATE, T_PUBLIC, T_PROTECTED, T_ABSTRACT))) {
                        $action = $v;
                    } else {
                        $text .= $v;
                    }
                    break;


                case 'T_;':
                    if ($var) {
                        $var["value"] = trim($text);
                        $text = '';
                        $class["var"][] = $var;
                        $var = '';
                    } elseif ($const) {
                        $const["value"] = $text;
                        $text = '';
                        $class["const"][] = $const;
                        $const = '';
                    } elseif ($func && $func["open"] == false) {
                        $func["open"] = true;
                        $func["post"] = trim($text);
                        $text = '';
                        if ($class) {
                            $class["func"][] = $func;
                        } else {
                            $result[] = $func;
                        }
                        $func = '';
                    } else {
                        $text .= $v;
                    }
                    break;

                case 'T_{':
                    if ($class && $class["open"] == false) {
                        $class["post"] = trim($text);
                        $class["open"] = true;
                        $text = '';
                    } elseif ($func && $func["open"] == false) {
                        $func["post"] = trim($text);
                        $func["open"] = true;
                        $text = '';
                    } else {
                        $text .= $v;
                        $level++;
                    }
                    break;

                case 'T_}':
                    if ($level) {
                        $level--;
                        $text .= $v;
                    } else {
                        if ($func && $func["open"] == true) {
                            $func["value"] = $text;
                            if ($class) {
                                $class["func"][] = $func;
                            } else {
                                $result[] = $func;
                            }
                            $func = '';
                            $text = '';
                        } elseif ($class) {
                            $result[] = $class;
                            $class = '';
                        }
                    }
                    break;

                default:
                    $text .= $v;
                    break;
            }
            //echo '<br><b>'.($s[1] ? token_name($s[0]) : $s[0]).' <pre>'.htmlspecialchars($s[1])."</pre>\n</b>";
        }
        if (trim($text)) {
            $result[] = array('type' => 'text', 'value' => $text);
        }
        //vd($result);exit;
        return $result;
    }

    /**
     * Заменяет функции, переменные, константы на кастомные значения
     *
     * @param array $c исходный значения
     * @param array $c_custom кастомные значения
     * @return array
     */
    static private function replace($c, $c_custom)
    {
        $c_new = array();

        foreach ($c as $r) {
            switch ($r["type"]) {
                case 'text':
                    $c_new[] = $r;
                    break;

                case 'class':
                    $class_custom = '';
                    foreach ($c_custom as $r_custom) {
                        if ($r_custom["type"] == 'class' && $r["name"] == $r_custom["name"]) {
                            $class_custom = $r_custom;
                            break;
                        }
                    }
                    if ($class_custom) {
                        $r["post"] = $class_custom["post"];
                        $r["prefix"] = $class_custom["prefix"];

                        // заменяет переменные в классе
                        foreach ($r["var"] as $i => $v) {
                            foreach ($class_custom["var"] as $v_custom) {
                                if ($v_custom["name"] == $v["name"]) {
                                    $r["var"][$i] = $v_custom;
                                }
                            }
                        }
                        // добавляет переменные в класс
                        foreach ($class_custom["var"] as $v_custom) {
                            if ($v_custom["action"] == 'new') {
                                $r["var"][] = $v_custom;
                            }
                        }

                        // заменяет констранты в классе
                        foreach ($r["const"] as $i => $v) {
                            foreach ($class_custom["const"] as $v_custom) {
                                if ($v_custom["name"] == $v["name"]) {
                                    $r["const"][$i] = $v_custom;
                                }
                            }
                        }

                        //добавляет константы в класс
                        foreach ($class_custom["const"] as $v_custom) {
                            if ($v_custom["action"] == 'new') {
                                $r["const"][] = $v_custom;
                            }
                        }

                        // заменяет|дорабатывает функции в классе
                        foreach ($r["func"] as $i => $v) {
                            foreach ($class_custom["func"] as $v_custom) {
                                if ($v_custom["name"] == $v["name"]) {
                                    $v["prefix"] = $v_custom["prefix"];
                                    $v["post"] = $v_custom["post"];
                                    switch ($v_custom["action"]) {
                                        case 'replace':
                                            $v["value"] = $v_custom["value"];
                                            break;

                                        case 'after':
                                            $count = 0;
                                            $new_value = preg_replace('/(.+)(return [^\{\}]+)$/s', '$1' . $v_custom["value"] . '$2', $v["value"], -1, $count);
                                            if ($count) {
                                                $v["value"] = $new_value;
                                            } else {
                                                $v["value"] .= "\n" . $v_custom["value"];
                                            }
                                            break;

                                        case 'before':
                                            $v["value"] = $v_custom["value"] . "\n" . $v["value"];
                                            break;
                                    }
                                    $r["func"][$i] = $v;
                                }
                            }
                        }

                        // добавляет новые функции в класс
                        foreach ($class_custom["func"] as $v_custom) {
                            if ($v_custom["action"] == 'new') {
                                $r["func"][] = $v_custom;
                            }
                        }
                    }
                    $c_new[] = $r;
                    break;

                case 'func':
                    // заменяет|дорабатывает функции
                    foreach ($c_custom as $v_custom) {
                        if ($v_custom["type"] == 'func' && $v_custom["name"] == $r["name"]) {
                            $r["prefix"] = $v_custom["prefix"];
                            $r["post"] = $v_custom["post"];
                            switch ($v_custom["action"]) {
                                case 'replace':
                                    $r["value"] = $v_custom["value"];
                                    break;

                                case 'after':
                                    $r["value"] .= "\n" . $v_custom["value"];
                                    break;

                                case 'before':
                                    $r["value"] = $v_custom["value"] . "\n" . $r["value"];
                                    break;
                            }
                        }
                    }
                    $c_new[] = $r;
                    break;
            }

            // добавляет новые функции
            foreach ($c_custom as $v_custom) {
                if (!empty($v_custom["action"]) && $v_custom["action"] == 'new') {
                    $c_new[] = $v_custom;
                }
            }
        }
        return $c_new;
    }

    /**
     * Формирует код PHP-файла
     *
     * @param array $c сведенные значения
     * @return string
     */
    static private function create($c)
    {
        $code = '';
        foreach ($c as $r) {
            switch ($r["type"]) {
                case 'text':
                    $code .= $r["value"];
                    break;

                case 'class':
                    $code .= implode(' ', $r["prefix"]) . (!in_array('interface', $r["prefix"]) ? ' class' : '') . ' ' . $r["name"] . ' ' . $r["post"] . "{\n";
                    foreach ($r["const"] as $v) {
                        $code .= implode(' ', $v["prefix"]) . ' const ' . $v["name"] . ($v["value"] ? ' ' . $v["value"] : '') . ";\n";
                    }
                    foreach ($r["var"] as $v) {
                        $code .= implode(' ', $v["prefix"]) . ' ' . $v["name"] . ($v["value"] ? ' ' . $v["value"] : '') . ";\n";
                    }
                    foreach ($r["func"] as $v) {
                        $code .= implode(' ', $v["prefix"]) . ' function ' . $v["name"] . ' ' . $v["post"];
                        if (in_array('interface', $r["prefix"])) {
                            $code .= ';';
                        } else {
                            $code .= "\n{" . $v["value"] . '}';
                        }
                        $code .= "\n";
                    }
                    $code .= '}';
                    break;

                case 'func':
                    $code .= implode(' ', $r["prefix"]) . ' function ' . $r["name"] . ' ' . $r["post"];
                    if ($r["value"]) {
                        $code .= "\n{" . $r["value"] . '}';
                    }
                    $code .= "\n";
                    break;
            }
        }
        return $code;
    }
}