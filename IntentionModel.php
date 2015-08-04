<?php
/**
 * Model class
 *
 * TODO: Split it up!
 */

class IntentionModelException extends IntentionException {}

class IntentionModel
{
	protected $_db = null;
	protected $_table = null;
	protected $_relation = null;
	protected $_data = array();

	const KEY_NOT_FOUND = 'Table could not be loaded because key (%s) / value (%s) pair could not be found';

	public function __construct($key = null, $value = null)
	{
		$this->_db = $this->_getDB();

		if (is_array($key) && $value === null)
		{
			$this->_data = $key;
		}

		elseif ($value !== null && $this->_table !== null)
		{
			$this->load($key, $value);

			if (is_array($this->_relation))
			{
				foreach($this->_relation as $relation)
				{
					call_user_func_array(array($this, 'loadRelation'), $relation);
				}
			}
		}

		if (method_exists($this, 'init'))
		{
			$this->init();
		}
	}

	public function load($key = 1, $value = 1)
	{
		$this->_sanityCheck();

		$this->_data = $this->query('SELECT * FROM ' . $this->_table . ' WHERE ' . $key . ' = ?', $value)->fetch(PDO::FETCH_ASSOC);
		if (count($this->_data) === 0)
		{
			throw new IntentionModelException(sprintf(static::KEY_NOT_FOUND, $key, $value));
		}
	}

	public function loadRelation($name, $model, $key, $value)
	{
		$relation = new $model($key, $this->$value);

		$this->_data[$name] = $relation;
	}

	protected function _getDB($options = null)
	{
		return new IntentionDatabase($options);
	}

	//----- ORM-like helper methods -----//

	public function __get($key)
	{
		if (isset($this->_data[$key]))
		{
			return $this->_data[$key];
		}

		return null;
	}

	//----- Database Facade methods meant to make it easier to do simple tasks -----//

	public function query($sql, $params = array())
	{
		return $this->_db->query($sql, $params);
	}

	public function exec($sql)
	{
		return $this->_db->exec($sql);
	}

	public function prepare($sql, array $driver_options = array())
	{
		return $this->_db->prepare($sql, $driver_options);
	}

	public function insert($data = array())
	{
		$this->_sanityCheck();

		return $this->_db->insert($this->_table, $data);
	}

	public function update($data = array(), $key, $value)
	{
		$this->_sanityCheck();

		return $this->_db->update($this->_table, $data, $key, $value);
	}

	public function fetch($columns = array('*'), $key = 1, $value = 1)
	{
		$this->_sanityCheck();

		return $this->_db->fetch($this->_table, $columns, $key, $value);
	}

	public function fetchAll($columns = array('*'), $key = 1, $value = 1)
	{
		$this->_sanityCheck();

		return $this->_db->fetchAll($this->_table, $columns, $key, $value);
	}

	//----- To prevent sloppy mistakes -----//

	protected function _sanityCheck()
	{
		if ($this->_db === null)
		{
			throw new IntentionModelException('Table-like method was requested, but no database was set');
		}

		elseif ($this->_table === null)
		{
			throw new IntentionModelException('Table-like method was requested, but no table was set');
		}
	}
}