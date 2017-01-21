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


$file = $_SERVER['DOCUMENT_ROOT'] . $_SERVER['SCRIPT_NAME'];
// echo $file, PHP_EOL; die();


// rewrite
    $uri = $_SERVER["REQUEST_URI"];
    $qpos= strpos($uri,'?');
    if($qpos>0){
          $uri = substr($uri,0,$qpos);
    }
    $uri = substr($uri,1);

if ($uri>'') {

    if(preg_match("#^(js|css|adm|modules|cache|custom|img|includes|langs|plugins|return|themes|tmp|userfiles)/(.*)$#", $uri, $a)){

        existsFile($file);

        $file = $_SERVER['DOCUMENT_ROOT'].'/../src'.$_SERVER['SCRIPT_NAME'];
        existsFile($file);

        $file = $_SERVER['DOCUMENT_ROOT'].'/../vendor/diafan/cms'.$_SERVER['SCRIPT_NAME'];
        existsFile($file);


	header('HTTP/1.0 404 Not Found');
        header('Content-Type: text/html; charset=utf-8');
        die('404: '.$_SERVER["REQUEST_URI"]);

    }



    if (preg_match("|^shop/1c/(.*)$|", $uri, $a)) {
        die('ne ralizovano');
    }elseif (preg_match("|^(.*)sitemap\.xml$|", $uri, $a)) {
        $_GET['rewrite'] = 'sitemap.xml';
    }elseif (preg_match("|^(&*)(.*)/$|", $uri, $a)) {
        $_GET['rewrite'] = $a[2];
    }elseif (preg_match("|^(&*)(.*)$|", $uri, $a)) {
        $_GET['rewrite'] = $a[2];
    }
}




$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . '/index.php';
// echo $_SERVER['SCRIPT_FILENAME'], PHP_EOL;

chdir($_SERVER['DOCUMENT_ROOT']);
require $_SERVER['SCRIPT_FILENAME'];
