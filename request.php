<?php

/**
 * combine yii's CHttpRequest and Kohana's Request
 *
 * @author lzyy http://blog.leezhong.com
 * @dependency route
 * @homepage https://github.com/witty/request
 * @version 0.1.3
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
	protected $_route;
	protected $_uri;
	protected $_directory;
	protected $_controller;
	protected $_action;
	protected $_params;

	public static $initial;
	public static $client_ip;

	/**
	 * @since 0.1.1
	 */
	protected function _after_construct($uri)
	{
		if (Witty::$is_cli)
		{
			// Get the command line options
			$options = CLI::options('uri', 'get', 'post');

			if (isset($options['uri']))
			{
				// Use the specified URI
				$uri = $options['uri'];
			}

			if (isset($options['get']))
			{
				// Overload the global GET data
				parse_str($options['get'], $_GET);
			}

			if (isset($options['post']))
			{
				// Overload the global POST data
				parse_str($options['post'], $_POST);
			}
		}

		if ($uri === NULL)
		{
			$uri = $this->url;
		}

		$base_url = $this->get_base_url();

		if (strpos($uri, $base_url) === 0)
		{
			$uri = substr($uri, strlen($base_url));
		}

		$uri = trim(preg_replace('#/[a-z]+.php/#', '', $uri), '/');

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

		$params = Request::process_uri($uri);
		if ($params)
		{
			// Store the URI
			$this->_uri = $params['uri'];

			// Store the matching route
			$this->_route = $params['route'];

			if (isset($params['directory']))
			{
				// Controllers are in a sub-directory
				$this->_directory = $params['directory'];
			}

			// Store the controller
			$this->_controller = $params['controller'];

			if (isset($params['action']))
			{
				// Store the action
				$this->_action = $params['action'];
			}
			else
			{
				// Use the default action
				$this->_action = Route::$default_action;
			}

			// These are accessible as public vars and can be overloaded
			unset($params['controller'], $params['action'], $params['directory']);

			// Params cannot be changed once matched
			$this->_params = $params;

			// Put into $_GET
			$_GET += $this->_params;
			// add in 0.1.2
			unset($_GET['route']);
			unset($_GET['uri']);
		}
		else
		{
			throw new Request_Exception('Unable to find a route to match the URI: {uri}',
				array('{uri}' => $uri), 404);
		}
	}

	public function get_param($name,$default = NULL)
	{
		return isset($_GET[$name]) ? $_GET[$name] : (isset($_POST[$name]) ? $_POST[$name] : $default);
	}

	public function get_base_url($absolute=false)
	{
		if($this->_base_url===null)
		{
			//$this->_base_url=rtrim(dirname($this->get_script_url()),'\\/');
			$this->_base_url=rtrim($this->get_script_url(),'\\/');
		}
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

	public function get_controller()
	{
		return $this->_controller;
	}

	public function get_action()
	{
		return $this->_action;
	}

	public function get_directory()
	{
		return $this->_directory;
	}

	public function param($param = NULL)
	{
		if (is_null($param))
			return $this->_params;
		return $this->_params[$param];
	}

	/**
	 * Processes the request, executing the controller action that handles this
	 * request, determined by the [Route].
	 *
	 * 1. Before the controller action is called, the [Controller::before] method
	 * will be called.
	 * 2. Next the controller action will be called.
	 * 3. After the controller action is called, the [Controller::after] method
	 * will be called.
	 *
	 * By default, the output from the controller is captured and returned, and
	 * no headers are sent.
	 *
	 *     $request->execute();
	 *
	 * @param   Request $request
	 * @return  Response
	 * @throws  Kohana_Exception
	 * @uses    [Kohana::$profiling]
	 * @uses    [Profiler]
	 * @deprecated passing $params to controller methods deprecated since version 3.1
	 *             will be removed in 3.2
	 */
	public function execute()
	{
		// Create the class prefix
		$prefix = 'controller_';

		// Directory
		$directory = $this->_directory;

		// Controller
		$controller = $this->_controller;

		if ($directory)
		{
			// Add the directory name to the class prefix
			$prefix .= str_replace(array('\\', '/'), '_', trim($directory, '/')).'_';
		}

		if (Profiler::$enabled)
		{
			// Set the benchmark name
			$benchmark = '"'.$this->url.'"';

			// Start benchmarking
			$benchmark = Profiler::start('Requests', $benchmark);
		}

		try
		{
			if (!class_exists($prefix.$controller))
			{
				throw new Request_Exception('The requested URL {uri} was not found on this server.',
													array('{uri}' => $this->param('uri')), 404);
			}

			// Load the controller using reflection
			$class = new ReflectionClass($prefix.$controller);

			if ($class->isAbstract())
			{
				throw new Request_Exception('Cannot create instances of abstract {controller}',
					array('{controller}' => $prefix.$controller), 500);
			}

			// Create a new instance of the controller
			$controller = $class->newInstance();

			if ($class->hasMethod('before'))
				$class->getMethod('before')->invoke($controller);

			// Determine the action to use
			$action = $this->_action;

			$params = $this->param();

			// If the action doesn't exist, it's a 404
			if (!$class->hasMethod('action_'.$action))
			{
				throw new Request_Exception('The requested URL {uri} was not found on this server.',
													array('{uri}' => $request->_params['uri']), 404);
			}

			$method = $class->getMethod('action_'.$action);

			/**
			 * Execute the main action with the parameters
			 *
			 * @deprecated $params passing is deprecated since version 3.1
			 *             will be removed in 3.2.
			 */
			$method->invoke($controller);

			// Execute the "after action" method
			if ($class->hasMethod('before'))
				$class->getMethod('after')->invoke($controller);

		}
		catch (Exception $e)
		{
			if (isset($benchmark))
			{
				// Delete the benchmark, it is invalid
				Profiler::delete($benchmark);
			}

			// Re-throw the exception
			throw $e;
		}

		if (isset($benchmark))
		{
			// Stop the benchmark
			Profiler::stop($benchmark);
		}

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

	public static function process_uri($uri)
	{
		$routes = Route::all();
		$params = NULL;

		foreach ($routes as $name => $route)
		{
			// We found something suitable
			if ($params = $route->matches($uri))
			{
				if ( ! isset($params['uri']))
				{
					$params['uri'] = $uri;
				}

				if ( ! isset($params['route']))
				{
					$params['route'] = $route;
				}

				break;
			}
		}

		return $params;
	}

	
}
