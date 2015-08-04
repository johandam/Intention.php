<?php
/**
 * Exception class, triggered when a query goes wrong
 */
class IntentionDatabaseException extends IntentionException{}
class IntentionQueryException extends IntentionDatabaseException{}

/**
 * Database class, wrapper for PHP PDO
 */
class IntentionDatabase
{
	protected $_pdo = null;

	public function __construct($options = null)
	{
		if ($options === null)
		{
			$options = Intention::getOption('db');
		}

		$this->connect($options);
	}

	/**
	 * Connects to the database, even though it's using PDO I'm only supporting mysql for now.
	 * @param  $host   The host to connect with
	 * @param  $user   The user to connect with
	 * @param  $pass   The password to connect with
	 * @param  $dbname The database name to connect with
	 * @return $this
	 */
	public function connect($options)
	{
		if (!isset($options['host'])){ throw new IntentionDatabaseException('Required option "host" is not set.'); }
		if (!isset($options['dbname'])){ throw new IntentionDatabaseException('Required option "dbname" is not set.'); }
		if (!isset($options['user'])){ throw new IntentionDatabaseException('Required option "user" is not set.'); }
		if (!isset($options['pass'])){ throw new IntentionDatabaseException('Required option "pass" is not set.'); }

		try
		{
			$this->_pdo = new PDO('mysql:host=' . $options['host'] . ';dbname=' . $options['dbname'], $options['user'], $options['pass']);

			// TODO: Probably put this in $options or something
			$this->_pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			$this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		catch(Exception $e)
		{
			throw new IntentionDatabaseException('Could not connect to database', 0, $e);
		}

		return $this;
	}

	/**
	 * In case you want to use PDO directly
	 * @return PDO Instance
	 */
	public function getPdo()
	{
		return $this->_pdo;
	}

	/**
	 * Facade function, fetches all results for the given sql query
	 * @param  String  $sql    The query to run
	 * @param  Array   $params Optional, the parameters that goes with the sql
	 * @return array   Array containing the results
	 */
	public function query($sql, $params = array())
	{
		$this->_sanityCheck();

		if (!is_array($params))
		{
			$params = array($params);
		}

		$stmt = $this->_pdo->prepare($sql);
		$stmt->execute($params);

		return $stmt;
	}

	public function exec($sql)
	{
		$this->_sanityCheck();

		return $this->_pdo->exec($sql);
	}

	public function prepare($sql, array $driver_options = array())
	{
		$this->_sanityCheck();

		return $this->_pdo->prepare($sql, $driver_options);
	}

	public function lastInsertId()
	{
		return $this->_pdo->lastInsertId();
	}

	/**
	 * Inserts a row into the given table
	 * @param  string $table  The table to insert the data in
	 * @param  array  $data   The data to enter
	 * @return int    $id     The primary key that resulted from the insert
	 */
	public function insert($table, $data = array())
	{
		$this->_sanityCheck();

		$sql = 'INSERT INTO `' . $table . '`(`';
		$sql .= implode('`,`', array_keys($data));
		$sql .= '`)VALUES(';
		foreach ($data as $key => $value)
		{
			$sql .= ':' . $key . ',';
		}
		$sql = substr($sql, 0, -1); // Get rid of trailing ,
		$sql .= ');';

		$stmt = $this->_pdo->prepare($sql);
		if (!$stmt->execute($data))
		{
			// TODO: With the error mode set in the constructor, this isn't necesary anymore
			$err = $stmt->errorInfo();
			throw new IntentionQueryException($err[2], $err[1]);
		}

		return $this->_pdo->lastInsertId();
	}

	/**
	 * Updates a row in the given table
	 * @param  string $table     The table to update the data in
	 * @param  array  $data      The data to update
	 * @param  mixed  $id        Column to check for, for example, the primary key
	 * @param  mixed  $id_value  Value to check on, goes with $id
	 * @return int    $id        The primary key that resulted from the insert
	 */
	public function update($table, $data = array(), $id, $id_value)
	{
		$this->_sanityCheck();

		$sql = 'UPDATE `' . $table . '` SET ';
		foreach ($data as $key => $value)
		{
			$sql .= $key . '=:' . $key . ',';
		}
		$sql = substr($sql, 0, -1); // Get rid of last comma
		$sql .= ' WHERE ' . $id . '= :' . $id . ' LIMIT 1';

		$stmt = $this->_pdo->prepare($sql);

		// Shortcut to get $id parameter in execute(), also prevents $id from being modified.
		$data[$id] = $id_value;
		if (!$stmt->execute($data))
		{
			$err = $stmt->errorInfo();
			throw new IntentionQueryException($err[2], $err[1]);
		}

		return $stmt->rowCount();
	}

	/**
	 * Fetches a row from the specified table, optionally filtered with specified $key and $value
	 * @param  string $table    The table to select from
	 * @param  array  $columns  Optional, contains the columns to retrieve, can be skipped
	 * @param  string $key      Optional, the key to filter on
	 * @param  string $value    Optional, the value to filter the key on
	 * @return array  $result   The row from the database
	 */
	public function fetch($table, $columns = array('*'), $key = 1, $value = 1)
	{
		$this->_sanityCheck();

		if (!is_array($columns))
		{
			$value = $key;
			$key = $columns;
			$columns = '*';
		}
		else
		{
			$columns = implode(',', $columns);
		}

		$sql = 'SELECT ' . $columns . ' FROM `' . $table . '` WHERE ' . $key . ' = :value';

		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindParam(':value', $value);
		if (!$stmt->execute())
		{
			$err = $stmt->errorInfo();
			throw new IntentionQueryException($err[2], $err[1]);
		}

		return $stmt->fetch();
	}

	/**
	 * Fetches all rows from the specified table, optionally filtered with specified $key and $value
	 * @param  string $table    The table to select from
	 * @param  array  $columns  Optional, contains the columns to retrieve, can be skipped
	 * @param  string $key      Optional, the key to filter on
	 * @param  string $value    Optional, the value to filter the key on
	 * @return array  $result   The rows from the database
	 */
	public function fetchAll($table, $columns = array('*'), $key = 1, $value = 1)
	{
		$this->_sanityCheck();

		if (!is_array($columns))
		{
			$value = $key;
			$key = $columns;
			$columns = '*';
		}
		else
		{
			$columns = implode(',', $columns);
		}

		$stmt = $this->_pdo->prepare('SELECT ' . $columns . ' FROM `' . $table . '` WHERE ' . $key . ' = :value');
		$stmt->bindParam(':value', $value);
		if (!$stmt->execute())
		{
			$err = $stmt->errorInfo();
			throw new IntentionQueryException($err[2], $err[1]);
		}

		return $stmt->fetchAll();
	}

	protected function _sanityCheck()
	{
		if ($this->_pdo === null)
		{
			throw new IntentionDatabaseException('No connection to database has been made yet');
		}
	}
}
