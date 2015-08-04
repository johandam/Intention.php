<?php
/**
 * TODO:
 *
 * Add better routing that also takes care of custom routing
 * Get rid of either the bootstrap or the config
 */

/**
 * Exception classes
 */

class IntentionException extends Exception{}

/**
 * Intention library class. This is where the magic happens.
 */
class Intention
{
	protected $_url = false;
	protected $_routing = array();

	static protected $_options = array();

	const DEFAULT_CONTROLLER = 'index';
	const DEFAULT_PAGE = 'index';

	const CLASS_NOT_FOUND = 'Failed to require class "%s"!';
	const ERROR_404 = '404 - page not found';
	const CLASS_EXTENSION = 'php';
	const VIEW_EXTENSION = 'phtml';

	/**
	 * Sets the autoloader, adds certain data to the configuration and maps the parameters if provided.
	 * @param array $params  Parameters for the page
	 * @param array $config  An array containing necesary configuration
	 */
	public function __construct($url = false)
	{
		spl_autoload_register(array($this, 'autoLoad'));

		$this->setParameters($url);
	}

	/**
	 * Starts the application, will call the requested controllers and sets up the view.
	 * @param  boolean | string $bootstrap 	If specified will load a bootstrap file
	 * @return string 			$content 	The content retrieved from the controller / view / whatever is called
	 */
	public function run($bootstrap = null)
	{
		if ($bootstrap !== null)
		{
			if (!is_file($bootstrap))
			{
				throw new IntentionException('Bootstrap specified but not found in "' . $boostrap . '"');
			}

			require_once($bootstrap);
		}

		$route = $this->getRoute();

		// Get the controller class
		$class = ucfirst($route['controller']) . 'Controller';
		$controller = new $class();

		if (method_exists($controller, 'init'))
		{
			call_user_func_array(array($controller, 'init'), $route['parameters']);
		}

		// Page functions are called based on the request method,
		// So visiting http://domain.com/product/category
		// would end in ProductController->categoryGet()
		$function = str_replace('-', '', $route['page']) . $route['method'];
		if (method_exists($controller, $function))
		{
			call_user_func_array(array($controller, $function), $route['parameters']);
		}
		else
		{
			// TODO: Better error message
			throw new Exception(static::ERROR_404);
		}

		$content = $controller->render($route['controller'] . DIRECTORY_SEPARATOR . $route['page'] . '.' . static::VIEW_EXTENSION);

		return $content;
	}

	/**
	 * The autoloader
	 * @param  string $class The class to be loaded
	 */
	public function autoLoad($class)
	{
		$file = $class . '.' . static::CLASS_EXTENSION;
		if (stream_resolve_include_path($file))
		{
			require_once($file);
		}
		elseif (stream_resolve_include_path($class . DIRECTORY_SEPARATOR . $file))
		{
			require_once($class . DIRECTORY_SEPARATOR . $file);
		}
		else
		{
			throw new IntentionException(sprintf(static::CLASS_NOT_FOUND, $class));
		}
	}

	public function getRoute()
	{
		// Default routing based on url
		$controller = $this->getOption('controller');
		$page = $this->getOption('page');
		$params = $this->getOption('arguments');
		$method = $this->getRequestMethod();

		// If custom routing has been set, it will overwrite the default specified above
		foreach ($this->_routing as $regex => $r)
		{
			if (preg_match($regex, $this->_url))
			{
				$controller = $r['controller'];
				$page = $r['page'];
				$params = (isset($r['params']) ? $r['params'] : $params);
				break;
			}
		}

		return array(
			'controller' => $controller,
			'page' => $page,
			'parameters' => $params,
			'method' => $method
		);
	}

	/**
	 * Adds a route to Intention
	 * @param array $routing contains the routing to be used,
	 *                       must contain an array with the regex as key
	 *                       where the array has keys for controller and page
	 * @return $this
	 */
	public function addRouting($routing = array())
	{
		foreach ($routing as $key => $r)
		{
			$this->_routing[$key] = $r;
		}

		return $this;
	}

	public function getRequestMethod()
	{
		return $_SERVER['REQUEST_METHOD'];
	}

	/**
	 * Sets up the parameters (usualy from the url) in a logical manner
	 * @param  array  $params Array containing the parameters
	 * @return $this
	 */
	protected function setParameters($url = '')
	{
		$this->_url = $url;
		$params = explode('/', $url);

		$p['controller'] = empty($params[0]) ? static::DEFAULT_CONTROLLER : $params[0];
		$p['page'] = empty($params[1]) ? static::DEFAULT_PAGE : $params[1];
		$p['arguments'] = array();

		if (!empty($params[2]))
		{
			for($i = 2; $i < count($params); $i ++)
			{
				$p['arguments'][] = $params[$i];
			}
		}

		self::$_options = array_merge(self::$_options, $p);

		return $this;
	}

	public function setOption($key, $value = null)
	{
		if (is_array($key))
		{
			foreach ($key as $k => $v)
			{
				$this->setOption($k, $v);
			}
		}

		else
		{
			self::$_options[$key] = $value;
		}
	}

	static public function getOption($key = null, $default = null)
	{
		// return everything if no key is specified
		if ($key === null)
		{
			return self::$_options;
		}

		// return an array of options if the key is actualy an array of keys
		elseif (is_array($key))
		{
			$options = array();
			foreach ($key as $id)
			{
				if (isset(self::$_options[$id]))
				{
					$options[$id] = self::$_options[$id];
				}
				else
				{
					$options[$id] = $default;
				}
			}

			return $options;
		}

		// return the boring option
		elseif (is_string($key))
		{
			if (isset(self::$_options[$key]))
			{
				return self::$_options[$key];
			}

			return $default;
		}
	}
}