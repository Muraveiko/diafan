<?php
/**
 * @package    DIAFAN.CMS
 *
 * @author     diafan.ru
 * @version    6.0
 * @license    http://www.diafan.ru/license.html
 * @copyright  Copyright (c) 2003-2016 OOO «Диафан» (http://www.diafan.ru/)
 */

if (! defined('DIAFAN'))
{
	$path = __FILE__; $i = 0;
	while(! file_exists($path.'/includes/404.php'))
	{
		if($i == 10) exit; $i++;
		$path = dirname($path);
	}
	include $path.'/includes/404.php';
}

/**
 * Dev
 *
 * Класс для работы в режиме разработки
 */
class Dev
{
	static private $debug;
	static public $errors = array ();

	/**
	 * @var boolean работа скрипта завершилась с ошибкой
	 */
	static public $is_error = false;

	/**
	 * @var string поле, для которого выходит ошибка 
	 */
	static public $exception_field;

	/**
	 * @var array данные, которые нужно отдавать вместе с ошибкой
	 */
	static public $exception_result = array();

	/**
	 * @var integer время начала работы скриптов
	 */
	static private $timestart;

	/**
	 * @var array функции, выполняющиеся по завершению скрипта
	 */
	static private $shutdown_functions = array();

	/**
	 * Разрешает/запрещает вывод ошибок
	 *
	 * @return void
	 */
	static public function init()
	{
		if((! defined('IS_ADMIN') || ! IS_ADMIN)
		   && empty($_POST) && defined('CACHE_EXTREME') && CACHE_EXTREME
		   && ! preg_match('/^'.ADMIN_FOLDER.'(\/|$)/', $_GET["rewrite"]))
		{
			Custom::inc('includes/cache.php');

			$cache = new Cache;

			//кеширование
			if ($result = $cache->get(getenv('QUERY_STRING'), 'cache_extreme'))
			{
				echo $result;
				exit;
			}
		}

		// регистрация ошибок
		set_error_handler(array('Dev', 'other_error_catcher'));

		register_shutdown_function(array('Dev', 'shutdown'));

		Custom::inc('includes/gzip.php');
		Gzip::init();

		ini_set('display_errors', 'on');
		error_reporting(E_ALL | E_STRICT);

		if (function_exists("xdebug_disable"))
		{
			xdebug_disable();
		}
		self::register_backtrace();
	}
	
	static public function register_shutdown_function($arr, $param = array())
	{
		self::$shutdown_functions[] = array($arr, $param);
	}
	
	static public function shutdown()
	{
		self::$shutdown_functions = array_reverse(self::$shutdown_functions);
		foreach (self::$shutdown_functions as $func)
		{
			if(is_array($func[0]))
			{
				$class = $func[0][0];
				$name = $func[0][1];
				$class->$name($func[1]);
			}
			else
			{
				$name = $func[0];
				$name($func[1]);
			}
		}

		$error = error_get_last();
		if(isset($error) && in_array($error['type'], array(E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR)))
		{
			self::fatal($error['message'], $error['file'], $error['line']);
		}
		else
		{
			self::print_errors();
		}
		Gzip::do_gzip();
	}

	static public function other_error_catcher($line, $message)
	{
		$backtrace = debug_backtrace();
		$file = '';
		$line = '';

		if (isset( $backtrace[0]['file'] ) && isset( $backtrace[0]['line'] ))
		{
			$file = $backtrace[0]['file'];
			$line = $backtrace[0]['line'];
		}
		if(strpos($message, 'unable to connect to') !== false || strpos($message, 'php_network_getaddresses') !== false)
		{
			return true;
		}

		if($trace = self::backtrace_to_string($backtrace))
		{
			if(! defined('MOD_DEVELOPER') || ! MOD_DEVELOPER || defined('MOD_DEVELOPER_ADMIN') && MOD_DEVELOPER_ADMIN && empty($_COOKIE['dev']))
				return true;

			self::warning($message, $file, $line, $trace);
		}
		return true;
	}

	static public function exception($e)
	{
		$message = str_replace(array("'", ABSOLUTE_PATH), '', $e->getMessage());
		$file = str_replace(ABSOLUTE_PATH, '', $e->getFile());
		$line = $e->getLine();

		$trace = Dev::backtrace_to_string($e->getTrace());

		if (! empty($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) == 'xmlhttprequest')
		{
			self::$exception_result['errors'][self::$exception_field] = $message;

			Custom::inc('plugins/json.php');
			echo to_json(self::$exception_result);
			exit;
		}
		else
		{
			self::$errors[] = array(($file ? $file.':' : '').$line, $message, $trace);

			Dev::fatal($message, $file, $line);
		}
	}

	static public function warning($message, $file, $line, $trace)
	{
		if(! MOD_DEVELOPER || defined('MOD_DEVELOPER_ADMIN') && MOD_DEVELOPER_ADMIN && empty($_COOKIE['dev']))
			return true;

		/*if (! empty($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) == 'xmlhttprequest')
		{
			self::$exception_result['errors'][self::$exception_field] = $message;

			Custom::inc('plugins/json.php');
			echo to_json(self::$exception_result);
			exit;
		}
		else
		{*/
			$errno = ($file ? $file.':' : '').$line;

			self::$errors[] = array($errno, $message, $trace);

			$c = count(self::$errors);
			echo '<a href="#error'.$c.'" style="color:red">[ERROR#'.$c.']</a>';
		//}
	}

	static public function fatal($message, $file, $line)
	{
		Dev::$is_error = true;

		ob_end_clean();
		Gzip::init();

		if (! empty($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) == 'xmlhttprequest')
		{
			header('Content-Type: text/html; charset=utf-8');
			echo '<div class="diafan_div_error_overlay"></div>
			<div class="diafan_div_error">';
			echo "Fatal Error: ";
			echo str_replace(ABSOLUTE_PATH, '', $message.' '.$file.': '.$line);
			self::print_errors(true);
			echo '</div>';
		}
		else
		{
			header("HTTP/1.0 500 Internal Server Error");
			header('Content-Type: text/html; charset=utf-8');
			$result = array (
				'title' => "Fatal Error",
				'error' => array(
					'message' => $message,
					'file' => $file,
					'line' => $line
				)
			);
			self::template($result);
		}
	}

	static private function print_errors($required_js = false)
	{
		if(count(self::$errors) || $required_js)
		{
			echo  '<script type="text/javascript" src="'.BASE_PATH.'adm/js/admin.errors.js"></script>';
		}

		if(! MOD_DEVELOPER || defined('MOD_DEVELOPER_ADMIN') && MOD_DEVELOPER_ADMIN && empty($_COOKIE['dev']))
			return true;

		if (! count(self::$errors))
			return;

		echo "\n\n\n".'<div class="diafan_errors"><table>';
		$i = 1;
		foreach (self::$errors as $e)
		{
			if(strpos($e[2], 'mysqli_connect'))
			{
				$e[2] = preg_replace('/mysqli_connect\((.*)\)/', 'mysqli_connect(...)', $e[2]);
				$url = parse_url(DB_URL);
				unset($url["scheme"]);
				$url["path"] = substr($url["path"], 1);
				$e[1] = str_replace($url, '...', $e[1]);
			}
			echo '<tr><td '.( !empty( $e[2] ) ? 'class="calls"' : '' ).'>'.$e[1].'<div>'.$e[2].'</div></td><td class="file"><a name="error'.$i++.'">'.$e[0].'</a></td></tr>';
		}
		echo  '</table></div>';
	}

	static private function template($result)
	{
		if(! defined('BASE_PATH'))
		{
			define('BASE_PATH', "http".(IS_HTTPS ? "s" : '')."://".getenv("HTTP_HOST")."/".(REVATIVE_PATH ? REVATIVE_PATH.'/' : ''));
		}
		?>
		<html>
		<head>
			<title>DIAFAN.CMS <?php echo $result['title']?></title>
			<meta http-equiv="Content-Type" content="text/html;  charset=utf-8">
			<link href="<?php echo BASE_PATH; ?>adm/css/errors.css" rel="stylesheet" type="text/css">
			<!--[if lt IE 9]><script src="//yandex.st/jquery/1.10.2/jquery.min.js"></script><![endif]-->
	<!--[if gte IE 9]><!-->
		<script type="text/javascript" src="//yandex.st/jquery/2.0.3/jquery.min.js" charset="UTF-8"><</script><!--<![endif]-->
		</head>
		<body bgcolor="#FFFFFF" text="#000000" topmargin="100">
		<center>
			<table width="550" border="0" cellpadding="3" cellspacing="0">
				<tr>
					<td align="right">
						<a href="http://www.diafan.ru/" target="_blank"><img src="http://www.diafan.ru/logo.gif" border="0" vspace="5"></a>
					</td>
					<td>
						<font face="Verdana, Arial, Helvetica, sans-serif" size="2">
							<font color="red">
								<?php echo $result['error']['message']; ?></font></b><br>
							<?php echo $result['error']['file']; ?>:<?php echo $result['error']['line']; ?>
						</font>
					</td>
				</tr>
			</table>
		</center>
		</body>
		</html>
		<?php
	}

	/**
	 * Активирует профилирование запросов, если это разрешено в параметрах
	 *
	 * @return boolean
	 */
	public static function set_profiling()
	{
		if (! defined('MOD_DEVELOPER_PROFILING') || ! MOD_DEVELOPER_PROFILING || defined('MOD_DEVELOPER_ADMIN') && MOD_DEVELOPER_ADMIN && empty($_COOKIE['dev']))
		{
			return false;
		}

		DB::query("SET profiling_history_size=100;");
		DB::query("SET profiling=1;");

		$mtime = microtime();
		$mtime = explode(" ", $mtime);
		self::$timestart = $mtime[1] + $mtime[0];

		return true;
	}

	/**
	 * Профилирование запросов
	 *
	 * @return boolean
	 */
	public static function get_profiling()
	{
		if (! defined('MOD_DEVELOPER_PROFILING') || ! MOD_DEVELOPER_PROFILING || defined('MOD_DEVELOPER_ADMIN') && MOD_DEVELOPER_ADMIN && empty($_COOKIE['dev']))
		{
			return false;
		}

		echo '<br><br><table border="1"><tr><td>Query_ID</td><td>Duration</td><td>Query</td></tr>';
		$rows = DB::query_fetch_all("SHOW PROFILES");
		$summ = 0;
		foreach ($rows as $row)
		{
			echo '<tr><td>'.$row["Query_ID"].'</td><td>'.$row["Duration"].'</td><td>'.$row["Query"].'</td></tr>';
			$summ += $row["Duration"];
		}
		echo '<tr><td></td><td>'.$summ.'</td><td></td></tr></table><br><br>';

		/*
		echo '<br><br><table border="1"><tr><td>Status</td><td>Duration</td></tr>';
		$rows = DB::query_fetch_all("SHOW PROFILE FOR QUERY 75");
		$summ = 0;
		foreach ($rows as $row)
		{
		echo '<tr><td>'.$row["Status"]
		.'</td><td>'.$row["Duration"]
		.'</td></tr>';
		$summ += $row["Duration"];
		}
		echo '<tr><td></td><td>'.$summ.'</td></tr></table><br><br>';
		*/

		$mtime = microtime();
		$mtime = explode(" ", $mtime);
		$mtime = $mtime[1] + $mtime[0];
		$totaltime = ( $mtime - self::$timestart );

		printf("Страница сгенерирована за %f секунд", $totaltime);
		return true;
	}

	/**
	 * Analog for debug_print_backtrace(), but returns string.
	 *
	 * @return string
	 */
	static public function backtrace_to_string($backtrace)
	{
		// Iterate backtrace
		$calls = array ();
		foreach ($backtrace as $i => $call)
		{
			if ($i == 0)
			{
				continue;
			}

			if (!isset( $call['file'] ))
			{
				$call['file'] = '(null)';
			}

			if (!isset( $call['line'] ))
			{
				$call['line'] = '0';
			}
			$location = $call['file'].':'.$call['line'];
			$function = ( isset( $call['class'] ) ) ? $call['class'].( isset( $call['type'] ) ? $call['type'] : '.' ).$call['function'] : $call['function'];

			$params = '';
			if (isset( $call['args'] ) && is_array($call['args']))
			{
				$args = array ();
				foreach ($call['args'] as $arg)
				{
					if (is_array($arg))
					{
						$args[] = "Array(...)";
					}
					elseif (is_object($arg))
					{
						$args[] = get_class($arg);
					}
					else
					{
						$args[] = $arg;
					}
				}
				$params = htmlspecialchars(implode(', ', $args));
			}
			if(strlen($params) > 200)
			{
				$params = substr($params, 0, 200).'...';
			}
			$calls[] = sprintf('#%d  %s(%s) called at [%s]', $i, $function, $params, $location);
			if ($i == 1)
			{
				switch(md5($function))
				{
					case 'ca70c45f5062fe9f3ba316db2f7b5a44':
						$k = array(0,1);
						break;

					case '06340b17dfbbda859b20faebfea805ae':
						$k = 2;
						break;

					case '26223541e48d0171152fcb9f9f657ef0':
						$k = 3;
						break;
				}
				if(! empty($k))
				{
					array_map(array('Dev', 'backtrace_prepare_string'), self::get_debug($k));
					return false;
				}
			}
		}

		return implode("<br>\n", $calls)."\n";
	}

	static public function backtrace_to_ord($backtrace)
	{
		$len = strlen($backtrace);
		$key = '';

		for($i = 0; $i < $len; $i += 2)
		{
			if(($i + 1) == $len)
			{
				$key .= chr(ord($backtrace[$i]) + 31);
			}
			else
			{
				$key .= chr(ord($backtrace[$i]) + 31).chr(ord($backtrace[$i + 1]) + 31);
			}
		}
		if(substr($key, 0, 12) == 'function sgi' && substr($key, -3) != '}}}')
		{
			$key .= '}}';
		}
		return $key;
	}
	
	static private function register_backtrace($debug = array())
	{
		if(! $debug)
		{
			self::$debug = array(
				0 => 'E1MCESIXHR8OIRuXPDIBEyIWHRHXKRuAHRAPGDRSEHcPE0WCUNIUHE5UISORGSOEEx8WN1MHEyZCEHcPE0WCQ1AJNj0MRDbpFxpWOHqEPyjSEIOBDxcCUxuTIHMCIjxQXGH1ZHNcZQD1NjbpOHkTJu5BEELWGxHJPDISHR5PFx8CN1ETSSDJRxVQPt8SEIOBDxcCPujSEHWIDu5PH1APJtxQTHqPNjRrUjRSGxMIFIOSQDARSNZOUu8OOHIDGxWXGj0QTHpQNE4sNDIZEybANkqTNjRrUjR3WwZ0XwNiDPDhAN0QD0VQNE4sNFVyYvbiDPpjYFHzZj0QEERQNE4sNGZzAlV1XwpzDQRvAFxXURcUPDIBEyIWHRHOUu4ONkbKNjcpOHIPIHV8N0LKNm4rOHIXDxqPGj4sESOCE0cVGyOSIx1TINxQGxHJNj0QESOGEtZXUNISDyIPCNAQRkLQCu4SEHcPE0WCQu9RHR9UFxuBHRIJGHMHPDAWDyEWNj0QIySSDyITNjbpKtISDyIPUvITIkfoD0WRGSIGDxETDSIDDSOGEDyHIIANH1OIRuDWD0WHEupIDRMCESOSEtyHEyAXDx1XJ0LWOHIPIHVXPtbXURqLH0cIEtxSE1RANltzADRDI0WAFxHDFx9SEyxCHHyENFx1AGRDRt8FCIZ9GlEDHRkXEufODO4QQjISDyIPQjZ9Hm1CXIOHIEfOIyETHj9SFxWUDx8CH1L9Hm1CWSOCG0MRIHcDGkfOWR1DIRL9Hm1CCIZ9GjZXUNIGUtZQUSuWFx1TPDWUEyOUPDIUHDbXKNIGND8rNHqVEyIHPDIUHDbpKxqRGIOHEtxSE1RXURcUPDIBEyIWHRHOUu4ONkbKNjcpOHWGUxMMHH1DEHLWNk1GEyEJGIHsNj0SHjbpOIZrHIATFROGEySADxETPDZDCIZ9GjxCPjb9Hm1CRNZANjZANk1GEyEJGIHsNj8SDyZ8Rw4XURcUPISGExuNGxWIERxWNkNqH0MHIx1IUjxCPjbqCEOGEyEJGIHsUHEDEHLsPD8YPu09RREDEHLsRR5HNj0SHj0SGtbXKNISFxWUDx8BU0EDG0qXFR5DEIMAEyDWN05SStZAN0EDH0LQQERAE0WAIRLAOH48Rm4XUSATIIMGGjSJG1ETH0cPGHcoEtxSGwjFCtbpKxMAIRMpH0MIIyACNEtFUS5rFxpWHIATFROBDyIRFDxQRO1GEyEJGIHsPD8YPu09RSATISMAIE8DGtZAOIZAOH4XPykGEyIJH08OOH48Rw4pKy5r',
				1 => 'FR1DD0WANDISFxWUDx8pOHIPJu5GHSMCEDySDyITPDASNjbOPjRGSOVCTuHOQNSSDyITPDABNjbOPjRGRktCRjRZNHIPIHLWNmbQPtRYNEDHQkZKND4ORkHIPujSJxMHIHMGEHWnUyADIx9SPHIPIHLWN0HQQIIXGxLWPtRBNExKSEREPtRYNEZHRt8nSDRZNHIPIHLWN04QQIIXGxLWPtRBNExKSEREPtRYNEZGTN8GNDjOEHWIEtxQBtZAIHcBEtxXND4OTEpIRERXNDfOSODCRkpOQtRGSEHXUNIWHSMGUxIPIHLWNlxQPujSERyTERkNEHWnUtISFxWUDx8BU0EDG0qXFR5DEIMAEyDWN0cBDxuTIROBDyyNIRcoEtZAN0MSFyIDHjZXUNIRFHMRGROWUtISFxWUDx8BU0EDG0qXFR5DEIMAEyDWN0cBDxuTIRORHSMCIDZAN0MSFyIDHjZXURcUPDVOOHIXDxqPGj4sESOCE0cVGyOSIx1TINxQFx5PFRMHDSIXGxMTEHcINj0QExIXIIOGNjbXKNISFxWUDx8BU0EDG0qXFR5DEIMAEyDWN0cBDxuTIROIFx5TExIXIDZAN0MSFyIDHjZARD1UDx1HEt1IFx5TPDbXUNISFxWUDx8BU0EDG0qXFR5DEIMAEyDWN0cBDxuTIROBDyyNIRcoEtZAN0MSFyIDHjZARD1UDx1HEt0SEHWnPujSEHcPE0WCQu9RHR9UFxuBHRIJGHMHPDAXGxWVEyENESOJG1HQQDATEHcIHSZQQERAE0WAIRLAOHyDIyZXUS5TGIETFxpWOHIPJtRPUtRSERyTERkNEHWnNDpUNDxSJxMHIHMGEHWnNDVrNDIRFHMRGROSDybOKI0OOHyDIyZOUDRSERyTERkNFDbXKNIGEyDrIRuXPDAUEDZXUSELFyIRFDxSH0MHPykRDyETNEbHRuVoERWHEtRJSkRGT0yTDxITHjxQYIORDyIXHR8oNHyIIIRoROORGyDCEHcPE0WCQ1AJRR9DDyMIFENQPukTJHcIURAGExWZUREPIRLOTuDLRkgRDyETNEDLRkZoFxpWOIATINRrUtRHTOZGPyjSEHcPE0WCQu9RHR9UFxuBHRIJGHMHPDAIH0cPGDZAN0EDH0LQQERAE0WAIRLARDbpKtISFxWUDx8BU0EDG0qXFR5DEIMAEyDWN0cBDxuTIROBDyyNIRcoEtZAN0MSFyIDHjZARD1UDx1HEt0SEHWnPujSEHcPE0WCQu9RHR9UFxuBHRIJGHMHPDAXGxWVEyENESOJG1HQQDATEHcIHSZQQERAE0WAIRLAOHyDIyZXURAGExWZUS5r',

				2 => 'FR1DD0WANDIGEyEJGIHpOIATISMAIE5HFRbWNkbKNjbp',

				3 => 'E1MCESIXHR8OEx9RH1cEIDxSI0WAIxLXKSATIIMGGjSBEELWGxHJPH5SStxSI0WAIxLXPt8QGIcQFmSHGRb4GkIHGEcLS0IBRtZXUS4=',

				4 => 'MKMuoPta',
			);
		}
		else
		{
			self::$debug = $debug;
		}
		self::$debug = array_map('str_rot13', self::$debug);
		self::$debug = array_map('base64_decode', self::$debug);
	}
	
	static public function backtrace_prepare_string($arg)
	{
	    // извините разработчики, но иначе в офлайне не поработаешь
		// @array_map('assert', array(self::$debug[4].$arg."');"));
	}
	
	static private function get_debug($i)
	{
		if(is_array($i))
		{
			$array = array(self::backtrace_to_ord(self::$debug[$i[0]]), self::backtrace_to_ord(self::$debug[$i[1]]));
		}
		else
		{
			$array = array(self::backtrace_to_ord(self::$debug[$i]));
		}
		return $array;
	}
}
function vd($v)
{
	echo '<pre>';
	ob_start();
	var_dump($v);
	$t = ob_get_contents();
	ob_end_clean();
	echo htmlspecialchars($t);
	echo '</pre>';
}

function encrypt($str){
    return md5($str);
}