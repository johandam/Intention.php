<?php
// Exception class
class IntentionViewException extends IntentionException {}

/**
 * View class
 */
class IntentionView
{
	protected $_localProperties = array();
	protected $_properties = array();

	const SCRIPT_NOT_FOUND = 'Script (%s) not found';

	/**
	 * Renders the given script with optional properties and returns the content.
	 *
	 * @method render
	 *
	 * @throws {IntentionViewException} If there is no valid file
	 *
	 * @param {string} $script The script to render
	 * @param {array} $properties Local properties that will only be available to the script being rendered
	 *
	 * @return {string} The content from the script.
	 */
	public function render($script, array $properties = array())
	{
		if (!is_file($script))
		{
			$script = VIEW_PATH . $script;
		}

		if (!is_file($script))
		{
			// Well, this is akward...
			throw new IntentionViewException(sprintf(static::SCRIPT_NOT_FOUND, $script));
		}

		ob_start();

		$this->_localProperties = $properties;
		require $script;
		$this->_localProperties = array(); // Don't forget to clean up after yourself!

		$content = ob_get_clean();

		return $content;
	}

	/**
	 * Sets a property to be used by the rendered files.
	 * Th be magic method __set refers this method
	 *
	 * @method set
	 *
	 * @param [String] $key The propety name to set
	 * @param [Mixed] $value The value to set it to
	 */
	public function set($key, $value = null)
	{
		if (is_array($key))
		{
			foreach ($key as $k => $v)
			{
				$this->set($k, $v);
			}
		}
		else
		{
			$this->_properties[$key] = $value;
		}

		return $this;
	}

	/**
	 * Gets the specified property from the locally assigned properties,
	 * or if that's not present, the global property list.
	 * If neither are defined, the value of $default is returned
	 *
	 * The magic method __get refers this method
	 *
	 * @method get
	 *
	 * @param [String] $key The name of the property to return
	 * @param [Mixed] $default Optional, what to return if the value can not be found, defaults to null
	 *
	 * @return [type]
	 */
	public function get($key, $default = null)
	{
		// _localProperties are variables that will be removed after the script has executed.
		if (isset($this->_localProperties[$key]))
		{
			return $this->_localProperties[$key];
		}

		// _properties are global variables usualy set by Controller classes
		elseif (isset($this->_properties[$key]))
		{
			return $this->_properties[$key];
		}

		return $default;
	}

	public function __get($key)
	{
		return $this->get($key);
	}

	public function __set($key, $value)
	{
		return $this->set($key, $value);
	}

	/**
	 * Easy method to create links.
	 * @param  string $controller The controller to link to
	 * @param  string $action     The page to link to
	 * @return string             The resulting link
	 */
	public function link($controller, $page = null)
	{
		$page = ($page === null ? $page : DIRECTORY_SEPARATOR . $page);
		if (func_num_args() > 2)
		{
			$args = implode(DIRECTORY_SEPARATOR, array_splice(func_get_args(), 2));
			$page .= DIRECTORY_SEPARATOR . $args;
		}

		return $this->_baseurl . $controller . $page . DIRECTORY_SEPARATOR;
	}
}