<?php
/**
 * Router script for PHP built-in server
 */
 $_SERVER = array_merge($_SERVER, $_ENV);


 // так как движок активно использует getenv()
 foreach($_SERVER as $key=>$value){
     putenv("$key=$value");
 }



function existsFile($file){
   // статика
    $mimeTypes = [
        'css' => 'text/css',
        'js'  => 'application/javascript',
        'gif' => 'image/gif',
        'jpg' => 'image/jpg',
        'png' => 'image/png',
        'ttf' => 'font/ttf',
        'woff' => 'application/font-woff'
    ];
    if (is_file($file)) {
            $path = pathinfo($file);
            $path['extension'] = strtolower($path['extension']);
            if(isset($mimeTypes[$path['extension']])) {
                header("Content-Type: {$mimeTypes[$path['extension']]}");
            }
            readfile($file);
            exit;
    }

}


// Workaround https://bugs.php.net/64566
if (ini_get('auto_prepend_file') && !in_array(realpath(ini_get('auto_prepend_file')), get_included_files(), true)) {
    require ini_get('auto_prepend_file');
}


$file = $_SERVER['DOCUMENT_ROOT'] . $_SERVER['SCRIPT_NAME'];
// echo $file, PHP_EOL; die();

// rewrite
if (strlen($_SERVER["REQUEST_URI"])>1) {
    $uri = $_SERVER["REQUEST_URI"];
    $qpos= strpos($uri,'?');
    if($qpos>0){
          $uri = substr($uri,0,$qpos);
    }
    $uri = substr($uri,1);
 
    if(preg_match("#^(js|css|adm|modules|cache|img|userfiles)/(.*)$#", $uri, $a)){
        existsFile($file);

        $file = $_SERVER['DOCUMENT_ROOT'].'/../src'.$_SERVER['SCRIPT_NAME'];
        existsFile($file);

        $file = $_SERVER['DOCUMENT_ROOT'].'/../vendor/diafan/cms'.$_SERVER['SCRIPT_NAME'];
        existsFile($file);
    }



    if (preg_match("|^shop/1c/(.*)$|", $uri, $a)) {
        die('ne ralizovano');
        $_GET['rewrite'] = $a[1];
    }elseif (preg_match("|^_profiler/|", $uri, $a)) {
        include(dirname(dirname(__FILE__)) . '/_profiler/index.php');
        die();
    }elseif (preg_match("|^(.*)sitemap\.xml$|", $uri, $a)) {
        $_GET['rewrite'] = 'sitemap.xml';
    }elseif (preg_match("|^(&*)(.*)/$|", $uri, $a)) {
        $_GET['rewrite'] = $a[2];
    }elseif (preg_match("|^(&*)(.*)$|", $uri, $a)) {
        $_GET['rewrite'] = $a[2];
    }
}

if (is_file($file)) {
    return false;
}

// 404 ЕСЛИ НЕТ РЕВРАЙТА
if(empty($_GET['rewrite'])){
    header('HTTP/1.0 404 Not Found');
    header('Content-Type: text/html; charset=utf-8');
    die('404: '.$file);
}


$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . '/index.php';
// echo $_SERVER['SCRIPT_FILENAME'], PHP_EOL;

chdir($_SERVER['DOCUMENT_ROOT']);
require $_SERVER['SCRIPT_FILENAME'];
