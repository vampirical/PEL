<?php

namespace PEL\Db;

/**
 * PEL Db Connection
 *
 * @package PEL
 */


/**
 * PEL Db Connection
 *
 * @package PEL
 */
class Connection extends \PDO
{
	/**
	 * Transaction depth
	 *
	 * @var int
	 */
	protected $transactionDepth = 0;

	/**
	 * Quote character to use for identifiers
	 *
	 * @var string|null
	 */
	protected $identifierQuote;

	/**
	 * Name of the current database
	 *
	 * @var string|null
	 */
	protected $dbName;

	/**
	 * Cache
	 *
	 * @var Cache|null
	 */
	protected $cache;

	/**
	 * Get Data Source Name
	 *
	 * @param string $driverName
	 * @param array $parameters
	 *
	 * @return string
	 */
	public static function constructDsn($driverName, $parameters = array())
	{
		switch ($driverName) {
			case 'mysql':
				$dsnString = $driverName .':';

				$validParameters = array('host' => 'host', 'dbname' => 'dbname', 'user' => 'user', 'password' => 'password', 'port' => 'port');
				$dsnParameters = array();
				foreach ($validParameters as $validParameter => $dsnParameterKey) {
					if (!empty($parameters[$validParameter])) {
						$dsnParameters[$dsnParameterKey] = $parameters[$validParameter];
					}
				}

				foreach ($dsnParameters as $dsnParameterKey => $dsnParameterValue) {
					$dsnString .= $dsnParameterKey .'='. $dsnParameterValue .';';
				}

				return $dsnString;
				break;

			case 'pgsql':
				$dsnString = $driverName .':';

				$validParameters = array('host' => 'host', 'dbname' => 'dbname', 'user' => 'user', 'password' => 'password', 'port' => 'port');
				$dsnParameters = array();
				foreach ($validParameters as $validParameter => $dsnParameterKey) {
					if (!empty($parameters[$validParameter])) {
						$dsnParameters[$dsnParameterKey] = $parameters[$validParameter];
					}
				}

				foreach ($dsnParameters as $dsnParameterKey => $dsnParameterValue) {
					$dsnString .= $dsnParameterKey .'='. $dsnParameterValue .' ';
				}

				return $dsnString;
				break;

			default:
				throw new Exception('Unsupported database driver, unable to construct DSN.');
				break;
		}
	}

	/**
	 * Create an instance based on config
	 *
	 * @param SimpleXMLElement $config
	 *
	 * @return __CLASS__
	 */
	public static function createFromConfig(\SimpleXMLElement $config)
	{
		$className = __CLASS__;
		$dsnString = self::constructDsn($config['type'], $config);
		$instance = new $className($dsnString, $config['user'], $config['password']);

		return $instance;
	}

	/**
	 * Connection contructor
	 *
	 * @param string $dsn
	 * @param string $username
	 * @param string $password
	 * @param array $options
	 */
	public function __construct($dsn, $username = null, $password = null, $options = array())
	{
		try {
			parent::__construct($dsn, $username, $password, $options);
		} catch(Exception $e) {
			throw new Exception('Unable to construct '. __CLASS__ .': '. $e->getMessage());
		}

		$this->detectIdentifierQuote();
	}

	/**
	 * Begin transaction
	 *
	 * Increases the transaction depth by 1.
	 * Ensures a transaction has begun.
	 * Does not allow for true nested transactions.
	 *
	 * @return boolean
	 */
	public function beginTransaction()
	{
		$beginResult = true;

		if ($this->transactionDepth === 0) {
			$beginResult = parent::beginTransaction();
		}
		if ($beginResult) {
			++$this->transactionDepth;
		}

		return $beginResult;
	}

	/**
	 * Commit
	 *
	 * Reduces the transaction depth by 1.
	 * This may or may not result in a database side commit.
	 *
	 * @return boolean
	 */
	public function commit()
	{
		$commitResult = true;

		if ($this->transactionDepth === 0) {
			return false;
		} else if ($this->transactionDepth === 1) {
			$commitResult = parent::commit();
		}
		--$this->transactionDepth;

		return $commitResult;
	}

	/**
	 * Commit full
	 *
	 * Brings the transaction depth to 0.
	 * Results in a database side commit in all cases.
	 *
	 * @return boolean
	 */
	public function commitFull()
	{
		$commitResult = true;

		if ($this->transactionDepth === 0) {
			return false;
		} else if ($this->transactionDepth > 0) {
			$commitResult = parent::commit();
		}
		$this->transactionDepth = 0;

		return $commitResult;
	}

	/**
	 * Rollback
	 *
	 * @return boolean
	 */
	public function rollback()
	{
		$this->transactionDepth = 0;
		return parent::rollback();
	}

	/**
	 * Checks whether there is currently an active transaction on the Connection
	 *
	 * @return boolean
	 */
	public function inTransaction()
	{
		return ($this->transactionDepth > 0);
	}

	/**
	 * Set the identifier quote character based on database driver
	 *
	 * @return void
	 */
	protected function detectIdentifierQuote()
	{
		switch ($this->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
			case 'mysql':
				$this->identifierQuote = '`'; // With our without ANSI_QUOTES enabled MySQL always allows the backtick (`) as an identifier quote character
				break;

			case 'pgsql':
			default:
				$this->identifierQuote = '"'; // Standard SQL identifier quote character as specified in the RFC
			break;
		}
	}

	/**
	 * Quote SQL identifier, such as table or field name
	 * Does not perform any escaping
	 *
	 * @param string $identifier
	 *
	 * @return string
	 */
	public function quoteIdentifier($identifier)
	{
		return $this->identifierQuote . $identifier . $this->identifierQuote;
	}

	/**
	 * Last error message
	 *
	 * @return string|null
	 */
	public function error()
	{
		$errorInfo = $this->errorInfo();
		return (isset($errorInfo[2])) ? $errorInfo[2] : null;
	}

	/**
	 * Get the current database's name
	 *
	 * @return string|null
	 */
	public function getDbName()
	{
		if (!$this->dbName) {
			$driver = $this->getAttribute(\PDO::ATTR_DRIVER_NAME);
			switch ($driver) {
				case 'pgsql':
					$query = 'SELECT CURRENT_DATABASE()';
					break;
				case 'mysql':
					$query = 'SELECT DATABASE()';
					break;
				default:
					throw new Exception('Unable to determine database name, unsupported driver ('. $driver .').');
					break;
			}

			$this->dbName = $this->query($query)->fetchColumn();
		}

		return $this->dbName;
	}

	/**
	 * Set cache
	 *
	 * @param	Cache $cache
	 *
	 * @return void
	 */
	public function setCache($cache)
	{
		$this->cache = $cache;
	}

	/**
	 * Get cache
	 *
	 * @return Cache|null
	 */
	public function getCache()
	{
		return $this->cache;
	}
}

?>
