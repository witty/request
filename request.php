<?php

/**
 * heavily borrowed yii's CHttpRequest
 *
 * @author lzyy http://blog.leezhong.com
 * @version 0.1.0
 */
class Request extends Witty_Base
{
	protected $_request_uri;
	protected $_pathinfo;
	protected $_script_file;
	protected $_script_url;
	protected $_host_info;
	protected $_url;
	protected $_base_url;
	protected $_cookies;

	public function __construct()
	{
		if (get_magic_quotes_gpc())
		{
			if (isset($_GET))
				$_GET = $this->strip_slashes($_GET);
			if (isset($_POST))
				$_POST = $this->strip_slashes($_POST);
			if (isset($_REQUEST))
				$_REQUEST = $this->strip_slashes($_REQUEST);
			if (isset($_COOKIE))
				$_COOKIE = $this->strip_slashes($_COOKIE);
		}
	}

	public function get_param($name,$default = NULL)
	{
		return isset($_GET[$name]) ? $_GET[$name] : (isset($_POST[$name]) ? $_POST[$name] : $default);
	}

	public function get_base_url($absolute=false)
	{
		if($this->_base_url===null)
			$this->_base_url=rtrim(dirname($this->get_script_url()),'\\/');
		return $absolute ? $this->get_host_info().$this->_base_url : $this->_base_url;
	}

	public function get_host_info()
	{
		$protocol = 'http';
		if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on')
			$protocol = 'https';
		return $protocol.'://'.$_SERVER['HTTP_HOST'];
	}
	
	public function get_url()
	{
		if ($this->_url !== NULL)
			return $this->_url;
		else
		{
			if (isset($_SERVER['REQUEST_URI']))
				$this->_url = $_SERVER['REQUEST_URI'];
			else
			{
				$this->_url = $this->get_script_url();
				if (($pathinfo = $this->get_pathinfo())!=='')
					$this->_url .= '/'.$pathinfo;
				if (($query_string = $this->get_query_string())!=='')
					$this->_url .= '?'.$query_string;
			}
			return $this->_url;
		}
	}

	public function get_query_string()
	{
		return isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
	}
	
	public function get_pathinfo()
	{
		if ($this->_pathinfo === NULL)
		{
			$request_uri = urldecode($this->get_request_uri());
			$script_url = $this->get_script_url();
			$base_url = $this->get_base_url();
			if (strpos($request_uri,$script_url)===0)
				$pathinfo = substr($request_uri,strlen($script_url));
			elseif ($base_url ==='' || strpos($request_uri,$base_url) === 0)
				$pathinfo = substr($request_uri,strlen($base_url));
			elseif (strpos($_SERVER['PHP_SELF'],$script_url)===0)
				$pathinfo = substr($_SERVER['PHP_SELF'],strlen($script_url));
			else
				throw new Request_Exception('无法检测到PATH INFO');

			if (($pos = strpos($pathinfo,'?')) !== FALSE)
				$pathinfo = substr($pathinfo, 0, $pos);

			$this->_pathinfo = trim($pathinfo,'/');
		}
		return $this->_pathinfo;
	}

	public function get_request_uri()
	{
		if ($this->_request_uri === NULL)
		{
			if (isset($_SERVER['HTTP_X_REWRITE_URL'])) // IIS
				$this->_request_uri = $_SERVER['HTTP_X_REWRITE_URL'];
			elseif (isset($_SERVER['REQUEST_URI']))
			{
				$this->_request_uri = $_SERVER['REQUEST_URI'];
				if (isset($_SERVER['HTTP_HOST']))
				{
					if (strpos($this->_request_uri,$_SERVER['HTTP_HOST'])!==FALSE)
						$this->_request_uri = preg_replace('/^\w+:\/\/[^\/]+/','',$this->_request_uri);
				}
				else
					$this->_request_uri = preg_replace('/^(http|https):\/\/[^\/]+/i','',$this->_request_uri);
			}
			else if (isset($_SERVER['ORIG_PATH_INFO']))  // IIS 5.0 CGI
			{
				$this->_request_uri = $_SERVER['ORIG_PATH_INFO'];
				if (!empty($_SERVER['QUERY_STRING']))
					$this->_request_uri .= '?'.$_SERVER['QUERY_STRING'];
			}
			else
				throw new Request_Exception('无法检测到REQUEST URI');
		}

		return $this->_request_uri;
	}

	public function get_script_url()
	{
		if ($this->_script_url === NULL)
		{
			$script_name = basename($_SERVER['SCRIPT_FILENAME']);
			if (basename($_SERVER['SCRIPT_NAME']) === $script_name)
				$this->_script_url = $_SERVER['SCRIPT_NAME'];
			elseif (basename($_SERVER['PHP_SELF']) === $script_name)
				$this->_script_url = $_SERVER['PHP_SELF'];
			elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $script_name)
				$this->_script_url = $_SERVER['ORIG_SCRIPT_NAME'];
			elseif (($pos = strpos($_SERVER['PHP_SELF'],'/'.$script_name)) !== FALSE)
				$this->_script_url = substr($_SERVER['SCRIPT_NAME'], 0, $pos).'/'.$script_name;
			elseif (isset($_SERVER['DOCUMENT_ROOT']) && strpos($_SERVER['SCRIPT_FILENAME'], $_SERVER['DOCUMENT_ROOT']) === 0)
				$this->_script_url = str_replace('\\','/',str_replace($_SERVER['DOCUMENT_ROOT'],'',$_SERVER['SCRIPT_FILENAME']));
			else
				throw new Request_Exception('无法检测到SCRIPT URL');

		}
		return $this->_script_url;
	}

	public function strip_slashes(&$data)
	{
		return is_array($data) ? array_map(array($this,'strip_slashes'), $data) : stripslashes($data);
	}

	public static function is_ajax()
	{
		return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH']==='XMLHttpRequest';
	}

	public static function get_referer()
	{
		return $_SERVER['HTTP_REFERER'];
	}

	public static function get_user_agent()
	{
		$_SERVER['HTTP_USER_AGENT'];
	}

	public static function get_ip()
	{
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
		{
			return $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		elseif (isset($_SERVER['HTTP_CLIENT_IP']))
		{
			return $_SERVER['HTTP_CLIENT_IP'];
		}
		elseif (isset($_SERVER['REMOTE_ADDR']))
		{
			return $_SERVER['REMOTE_ADDR'];
		}
	}

	public static function redirect($url, $status=302)
	{
		if(strpos($url,'/')===0)
			$url=$this->get_host_info().$url;
		header('Location: '.$url, true, $status);
		exit;
	}
	
}
