<?php

namespace PEL\Db;

use PEL\Db;

/**
 * PEL Db Record
 *
 * @package PEL
 */


/**
 * PEL Db Record
 *
 * @package PEL
 */
class Record implements \Iterator, \ArrayAccess, \JsonSerializable
{
	/**
	 * Database Connection
	 *
	 * @var Connection
	 */
	protected $db;

	/**
	 * Name of Connection to use if an explicit Connection is not provided
	 *
	 * @var string|null
	 */
	protected static $connectionName = null;

	/**
	 * Name of the SQL table
	 *
	 * @var string
	 */
	protected static $table = '';

	/**
	 * Record Fields
	 *
	 * Flat array of field names or objectVariableName => fieldDefinition mapping.
	 * Field definition must define:
	 *   type
	 * Can optionally define:
	 *   dbField
	 *   null
	 *   default
	 *   auto
	 *
	 * When using a flat field specification some functionality is disabled,
	 * such as value processing (@see processValues).
	 *
	 * @var array
	 */
	protected static $fields = array();

	/**
	 * Primary key fields
	 *
	 * Primary key fields. If there is an auto_increment/serial/sequence field it must be the first element.
	 * You must populate this array with the full list of fields used as the primary key for the database table.
	 *
	 * @var array
	 */
	protected static $primaryKeyFields = array();

	/**
	 * Private fields
	 *
	 * @var array
	 */
	protected static $privateFields = array();

	/**
	 * References
	 *
	 * refName => array(localField, ClassName, referenceField)
	 * Local and reference fields can either be strings or arrays (for multi-column references)
	 *
	 * @var array
	 */
	protected static $references = array();

	/**
	 * Constraints
	 *
	 * @var array
	 */
	protected static $constraints = array();

	/**
	 * Field names
	 * Auto populated from static::$fields
	 *
	 * @var array
	 */
	protected $fieldNames = array();

	/**
	 * Database field names
	 * Auto populated from static::$fields
	 *
	 * @var array objectFieldName => dbFieldName
	 */
	protected $fieldDbNames = array();

	/**
	 * Field types
	 * Auto populated from static::$fields
	 *
	 * @var array objectFieldName => fieldType
	 */
	protected $fieldTypes = array();

	/**
	 * Field values
	 *
	 * @var array
	 */
	protected $fieldValues = array();

	/**
	 * Whether fields are set. Keys are field names. Values are truthy.
	 *
	 * @var array
	 */
	protected $fieldSetValues = array();

	/**
	 * Internal primary key field values for use when changes occur to primary key fields
	 *
	 * @var array
	 */
	protected $primaryKeyFieldInternalValues = array();

	/**
	 * Determines if and how field values are processed
	 *
	 * Used for ensuring that values come back from the db in the expected format.
	 * Values can either be process locally or on the database side.
	 *
	 * @var string|null Db::PROCESS_VALUES_LOCAL|Db::PROCESS_VALUES_DB|null
	 */
	public $processValues = Db::PROCESS_VALUES_LOCAL;

	/**
	 * Whether or not database information is loaded
	 *
	 * Whether or not database information is loaded. This is not whether the information in the object is synced with the database but rather only whether the data in the object originated in from a record in the database.
	 *
	 * @var boolean
	 */
	protected $loaded = false;

	/**
	 * Erro log
	 *
	 * @var array
	 */
	public $errorLog = array();

	/**
	 * Whether or not queries are logged
	 *
	 * @var boolean
	 */
	public $queryLogging = false;

	/**
	 * Query log
	 *
	 * @var array
	 */
	public $queryLog = array();

	/**
	 * Log query execution times
	 *
	 * @var boolean
	 */
	public $queryTimeLogging = false;

	/**
	 * Total query time
	 *
	 * @var int
	 */
	public $queryTime = 0;

	/**
	 * Cache
	 *
	 * @var Cache
	 */
	protected $cache;

	/**
	 * Whether object can be cached
	 *
	 * @var boolean
	 */
	protected $cacheable = false;

	/**
	 * Cache expiration in seconds. 0 is never. Defaults to 1 hour.
	 *
	 * @var integer
	 */
	protected $cacheExpiration = 3600;

	/**
	 * Not null field value used by getNotNullValue()
	 *
	 * @var Record\Value
	 */
	protected $notNullValue;

	/**
	 * Position tracking for \Iterator implementation
	 *
	 * @var int
	 */
	protected $iterPosition = 0;

	/**
	 * Create a new Record
	 *
	 * @param Connection $dbConnection If an explicit Connection is not provided one will be requested from Db, using $connectionName if set.
	 *
	 * @throws Exception
	 */
	public function __construct(Connection $dbConnection = null)
	{
		if (!$dbConnection) {
			$dbConnection = Db::getConnection(static::$connectionName);
			if (!$dbConnection) {
				throw new Exception('Unable to find database connection.');
			}
		}
		$this->db = $dbConnection;

		// Populate local instance field maps from static::$fields
		// TODO Cache these by class in Db::$recordTypes (or something) and avoid recalculating them each construct.
		$flatFields = isset(static::$fields[0]);
		if ($flatFields) {
			$this->fieldNames = array_values(static::$fields);
			$this->fieldDbNames = array_combine(static::$fields, static::$fields);
		} else {
			$this->fieldNames = array_keys(static::$fields);
			foreach (static::$fields as $field => $data) {
				$this->fieldDbNames[$field] = (isset($data['dbField'])) ? $data['dbField'] : $field;
				$this->fieldTypes[$field] = static::fieldType($field);
			}
		}

		if (count(static::$primaryKeyFields) < 1) {
			throw new Exception(get_class($this) .': No primary key fields defined on object. Go back to Relational Set Theory 101.');
		}

		foreach (static::$primaryKeyFields as $primaryKeyField) {
			if (!isset($this->fieldDbNames[$primaryKeyField])) {
				throw new Exception(get_class($this) .': primary key field "'. $primaryKeyField .'" is not defined as a field.');
			}
		}

		$this->cache = $this->db->getCache();
	}

	/**
	 * Log error
	 *
	 * Log to $this->errorLog and syslog
	 *
	 * @param string $error
	 * @param array  $data
	 *
	 * @return void
	 */
	protected function logError($error, $data = array())
	{
		$this->logSilentError($error, $data);
		syslog(LOG_ERR, get_class($this) .' error: '. $error);
	}

	/**
	 * Log error silently
	 *
	 * Log to $this->errorLog
	 *
	 * @param string $error
	 * @param array  $data
	 *
	 * @return void
	 */
	protected function logSilentError($error, $data = array())
	{
		$this->errorLog[] = array('error' => $error, 'data' => $data);
		Db::logSilentError($error, $data);
	}

	/**
	 * Log query
	 *
	 * Log to $this->queryLog and syslog
	 *
	 * @param string $query
	 * @param mixed  $result
	 * @return void
	 */
	protected function logQuery($query, $result = null)
	{
		$this->queryLog[] = array('query' => $query, 'result' => $result);
		syslog(LOG_INFO, get_called_class(). ' query: '. $query);
	}

	/**
	 * Get table
	 *
	 * @return array
	 */
	public static function getTable()
	{
		return static::$table;
	}

	/**
	 * Get field definitions
	 *
	 * @return array
	 */
	public static function getFieldDefinitions()
	{
		return static::$fields;
	}

	/**
	 * Get reference definitions
	 *
	 * @return array
	 */
	public static function getReferenceDefinitions()
	{
		return static::$references;
	}

	/**
	 * Get list of field names
	 *
	 * @return array
	 */
	public function getFieldNames()
	{
		return $this->fieldNames;
	}

	/**
	 * Get list of private field names
	 *
	 * @return array
	 */
	public function getPrivateFieldNames()
	{
		return $this->privateFields;
	}

	/**
	 * Get list of database field names
	 *
	 * @return array
	 */
	public function getFieldDbNames()
	{
		return $this->fieldDbNames;
	}

	/**
	 * Check whether a field exists
	 *
	 * @param string $field
	 *
	 * @return boolean
	 */
	public function fieldExists($field)
	{
		return isset($this->fieldDbNames[$field]);
	}

	/**
	 * Get list of primary key field names
	 *
	 * @return array
	 */
	public static function getPrimaryKeyFieldNames()
	{
		return static::$primaryKeyFields;
	}

	/**
	 * Get list of required fields
	 *
	 * @return array
	 */
	public function getRequiredFields()
	{
		$requiredFields = array();
		foreach ($this->fieldNames as $fieldName) {
			if (!$this->fieldNullable($fieldName) && !$this->fieldAuto($fieldName) && $this->fieldDefault($fieldName) === null) {
				$requiredFields[] = $fieldName;
			}
		}
		return $requiredFields;
	}

	/**
	 * Get list of non-primary key field names
	 *
	 * @return array
	 */
	public function getNonPrimaryKeyFieldNames()
	{
		return array_diff($this->getFieldNames(), $this->getPrimaryKeyFieldNames());
	}

	/**
	 * Get list of non-private field names
	 *
	 * @return array
	 */
	public function getPublicFieldNames()
	{
		return array_diff($this->getFieldNames(), $this->getPrivateFieldNames());
	}

	/**
	 * Field type
	 *
	 * @param string $field
	 *
	 * @return mixed|null
	 */
	protected static function fieldType($field)
	{
		return (isset(static::$fields[$field]['type'])) ? strtolower(static::$fields[$field]['type']) : null;
	}

	/**
	 * Whether field is nullable
	 *
	 * @param string $field
	 *
	 * @return boolean
	 */
	public static function fieldNullable($field)
	{
		return (isset(static::$fields[$field]['null'])) ? (boolean) static::$fields[$field]['null'] : true;
	}

	/**
	 * Whether field is uniquely constrained
	 *
	 * @param string $field
	 *
	 * @return boolean
	 */
	public static function fieldUnique($field)
	{
		return (isset(static::$fields[$field]['unique'])) ? (boolean) static::$fields[$field]['unique'] : false;
	}

	/**
	 * Whether field is auto populate-able (serial, autoinc, timestamp with CURRENT_TIMESTAMP, etc.)
	 *
	 * @param string $field
	 *
	 * @return boolean
	 */
	public static function fieldAuto($field)
	{
		return (isset(static::$fields[$field]['auto'])) ? (boolean) static::$fields[$field]['auto'] : false;
	}

	/**
	 * Field default value
	 *
	 * @param string $field
	 *
	 * @return mixed|null
	 */
	public static function fieldDefault($field)
	{
		return (isset(static::$fields[$field]['default'])) ? static::$fields[$field]['default'] : null;
	}

	/**
	 * Get a field value
	 *
	 * @param string $field
	 *
	 * @return mixed
	 */
	protected function get($field)
	{
		return $this->fieldValues[$field];
	}

	/**
	 * Set a field value
	 *
	 * @param string $field
	 * @param mixed  $value
	 *
	 * @return boolean
	 */
	protected function set($field, $value)
	{
		$this->fieldValues[$field] = $value;
		$this->fieldSetValues[$field] = true;

		return true;
	}

	/**
	 * Get loaded state
	 *
	 * @return boolean
	 */
	public function getLoaded()
	{
		return $this->loaded;
	}

	/**
	 * Alias for getLoaded()
	 *
	 * @return boolean
	 */
	public function isLoaded()
	{
		return $this->getLoaded();
	}

	/**
	 * Set whether or not database information is loaded
	 *
	 * @param bool $loaded
	 * @param bool $skipPrimaryKeyCheck
	 *
	 * @throws Exception
	 */
	public function setLoaded($loaded, $skipPrimaryKeyCheck = false)
	{
		if ($loaded) {
			if (!$skipPrimaryKeyCheck && !$this->isPrimaryKeySet()) {
				throw new Exception('Unable to set '. __CLASS__ .' as loaded, one or more primary key fields are not set.');
			}

			// Set the internal primary key values at this point since they are used when the object is in a loaded state
			foreach (static::$primaryKeyFields as $primaryKeyObjectField) {
				$this->primaryKeyFieldInternalValues[$primaryKeyObjectField] = $this->fieldValues[$primaryKeyObjectField];
			}

			$this->loaded = true;
		} else {
			$this->loaded = false;
		}
	}

	/**
	 * Magic get
	 *
	 * If the requested property is defined as a field than get() otherwise throw an exception
	 *
	 * @param string $property
	 *
	 * @throws Exception
	 * @return mixed
	 */
	public function __get($property)
	{
		if (isset($this->fieldDbNames[$property])) {
			return (isset($this->fieldValues[$property])) ? $this->fieldValues[$property] : null;
		} else {
			throw new Exception('Attempt to get value for invalid field ('. $property .').');
		}
	}

	/**
	 * Magic set
	 *
	 * If the requested property is defined as a field than set() otherwise throw an exception
	 *
	 * @param string $property
	 * @param mixed  $value
	 *
	 * @throws Exception
	 * @return boolean
	 */
	public function __set($property, $value)
	{
		if (isset($this->fieldDbNames[$property])) {
			return $this->set($property, $value);
		} else {
			throw new Exception('Attempt to set value for invalid field ('. $property .').');
		}
	}

	/**
	 * Magic isset
	 *
	 * @param string $property
	 *
	 * @return boolean
	 */
	public function __isset($property)
	{
		if (isset($this->fieldDbNames[$property])) {
			return isset($this->fieldValues[$property]);
		}
		return false;
	}

	/**
	 * Magic unset
	 *
	 * @param string $property
	 *
	 * @throws Exception
	 */
	public function __unset($property)
	{
		if (isset($this->fieldDbNames[$property])) {
			unset($this->fieldValues[$property]);
			unset($this->fieldSetValues[$property]);
		} else {
			throw new Exception('Attempt to unset invalid field ('. $property .').');
		}
	}

	/**
	 * Magic call
	 *
	 * @param string $method
	 * @param array $arguments
	 *
	 * @throws Exception
	 * @return Record
	 */
	public function __call($method, $arguments)
	{
		if (isset(static::$references[$method])) {
			return $this->getReferenced($method);
		} else {
			throw new Exception('Attempt to call invalid method ('. $method .').');
		}
	}

	/**
	 * Check if a field has a set value
	 *
	 * @param string $field
	 *
	 * @return boolean
	 */
	public function hasFieldBeenSet($field)
	{
		return isset($this->fieldSetValues[$field]);
	}

	/**
	 * Get whether object is cacheable
	 *
	 * @return boolean
	 */
	public function getCacheable()
	{
		return $this->cacheable;
	}

	/**
	 * Alias for getCacheable()
	 *
	 * @return boolean
	 */
	public function isCacheable()
	{
		return $this->getCacheable();
	}

	/**
	 * Get cache expiration
	 *
	 * @return integer;
	 */
	public function getCacheExpiration()
	{
		return $this->cacheExpiration;
	}

	/**
	 * Create a unique key to identify database data in cache
	 *
	 * @param array $data Defaults to primary key fields and their values
	 *
	 * @return string
	 */
	protected function getCacheKey($data = null)
	{
		if (!$data) {
			$data = array();
			foreach (static::$primaryKeyFields as $primaryKeyFieldName) {
				$data[$primaryKeyFieldName] = $this->fieldValues[$primaryKeyFieldName];
			}
		}
		$dataString = '';
		foreach ($data as $key => $value) {
			$dataString .= $key . (string) $value;
		}

		return $this->db->getDbName() .'_'. static::$table .'_'. md5($dataString);
	}

	/**
	 * Get SQL comparison and values for fields
	 *
	 * @param array $fields
	 *
	 * @throws Exception
	 * @return array sqlString => string, values => array
	 */
	protected function getWherePack($fields)
	{
		$whereString = '';
		$values = array();
		foreach ((array) $fields as $key => $value) {
			if ($value === true) {
				$sqlComparison = '= TRUE';
			} else if ($value === false) {
				$sqlComparison = '= FALSE';
			} else if ($value === null) {
				$sqlComparison = 'IS NULL';
			} else if (is_string($value) && strtoupper($value) === Db::VALUE_NOT_NULL) {
				$sqlComparison = 'IS NOT NULL';
			} else if (is_array($value)) {
				$sqlComparison = 'IN ('. implode(', ', array_fill(0, count($value), '?')) .')';
				$values = array_merge($values, array_values($value));
			} else if (is_object($value) && is_callable(array($value, 'getSqlComparison'))) {
				$comparison = $value->getSqlComparison();
				if ($comparison == Db::COMPARISON_IS_NULL || $comparison == Db::COMPARISON_IS_NOT_NULL) {
					$sqlComparison = $comparison;
				} else if ($comparison == Db::COMPARISON_IN || $comparison == Db::COMPARISON_NOT_IN) {
					if (is_array($value)) {
						$sqlComparison = $comparison .' ('. implode(', ', array_fill(0, count($value), '?')) .')';
						$values = array_merge($value, array_values($value));
					} else {
						$sqlComparison = ($comparison == Db::COMPARISON_NOT_IN) ? Db::COMPARISON_NOT_EQUAL : Db::COMPARISON_EQUAL;
						$values[] = $value;
					}
				} else {
					$sqlComparison = $comparison .' ?';
					$values[] = $value;
				}
			} else {
				$sqlComparison = '= ?';
				$values[] = $value;
			}

			if ($whereString != '') {
				$whereString .= ' AND ';
			}
			if (!isset($this->fieldDbNames[$key])) throw new Exception('Field ('. $key .') not defined.');
			$whereString .= $this->db->quoteIdentifier($this->fieldDbNames[$key]) .' '. $sqlComparison;
		}
		return array('sqlString' => $whereString, 'values' => $values);
	}

	/**
	 * Creates a Record\Value instance
	 *
	 * @param ...
	 *
	 * @return Record\Value
	 */
	public function newValue()
	{
		$arguments = func_get_args();
		// array_unshift($arguments, $this->db);

		$reflectionObject = new \ReflectionClass('\PEL\Db\Record\Value');
		return $reflectionObject->newInstanceArgs($arguments);
	}

	/**
	 * Creates a Record\Value suitable not null comparison
	 *
	 * @return Record\Value
	 */
	public function getNotNullValue()
	{
		if (!$this->notNullValue) {
			$this->notNullValue = new Record\Value($this->db, null, false, Db::COMPARISON_IS_NOT_NULL);
		}

		return $this->notNullValue;
	}

	/**
	 * Check if primary key fields are set
	 *
	 * @return boolean
	 */
	public function isPrimaryKeySet()
	{
		$set = false;

		foreach (static::$primaryKeyFields as $primaryKeyField) {
			if (isset($this->fieldSetValues[$primaryKeyField])) {
				$set = true;
			}
		}

		return $set;
	}

	/**
	 * Get primary key field values
	 *
	 * @param bool $useInternalValues Return the internal field values.
	 *
	 * @return array
	 */
	protected function getPrimaryKeyValues($useInternalValues = false)
	{
		$values = array();

		$primaryKeyFields = $this->getPrimaryKeyFieldNames();
		foreach ($primaryKeyFields as $primaryKeyField) {
			if ($useInternalValues) {
				$values[$primaryKeyField] = $this->primaryKeyFieldInternalValues[$primaryKeyField];
			} else {
				$values[$primaryKeyField] = (isset($this->fieldValues[$primaryKeyField])) ? $this->fieldValues[$primaryKeyField] : null;
			}
		}

		return $values;
	}

	/**
	 * Get primary key where pack
	 *
	 * @param	boolean	$useInternalValues
	 *
	 * @return array
	 */
	protected function getPrimaryKeyWherePack($useInternalValues = false)
	{
		return $this->getWherePack($this->getPrimaryKeyValues($useInternalValues));
	}

	/**
	 * Process a field value from the database
	 *
	 * @param string $field
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	public function processFieldFromDb($field, $value)
	{
		if ($this->processValues == Db::PROCESS_VALUES_LOCAL && isset($this->fieldTypes[$field])) {
			$fieldType = $this->fieldTypes[$field];
			switch ($fieldType) {
				case 'timestamp':
					if ($value) $value = strtotime($value);
					break;
			}
		}

		return $value;
	}

	/**
	 * Process a field value for the databse
	 *
	 * @param string $field
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	public function processFieldForDb($field, $value)
	{
		if ($this->processValues == Db::PROCESS_VALUES_LOCAL && isset($this->fieldTypes[$field])) {
			$fieldType = $this->fieldTypes[$field];
			switch ($fieldType) {
				case 'timestamp':
					if ($value !== null && $value !== '') {
						$isNumeric = is_numeric($value);
						$dbDriverName = $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME);
						switch ($dbDriverName) {
							case 'mysql':
								$format = 'Y-m-d H:i:s';
								if ($isNumeric) {
									$value = gmdate($format, $value);
								} else {
									$ts = strtotime($value);
									if ($ts) $value = gmdate($format, $ts);
								}
								break;
							case 'pgsql':
								$format = 'r';
								if ($isNumeric) {
									$value = gmdate($format, $value);
								} else {
									$ts = strtotime($value);
									if ($ts) $value = gmdate($format, $ts);
								}
								break;
							case 'sqlite':
								if (!$isNumeric) {
									$ts = strotime($value);
									if ($ts) $value = $ts;
								}
								break;
						}
					}
					break;
			}
		}

		return $value;
	}

	/**
	 * Load cache result
	 *
	 * Cache results should be keyed with internal field names
	 *
	 * @param array $result
	 */
	protected function loadCacheResult(array $result)
	{
		foreach ($this->fieldNames as $objectFieldName) {
			$this->set($objectFieldName, $result[$objectFieldName]);
		}

		$this->setLoaded(true, true);
	}

	/**
	 * Load database result
	 *
	 * Database results should be keyed with database, rather than internal, field names
	 *
	 * @param array $result
	 */
	protected function loadDatabaseResult($result)
	{
		foreach ($this->fieldNames as $objectFieldName) {
			$dbFieldName = $this->fieldDbNames[$objectFieldName];
			$value = $this->processFieldFromDb($objectFieldName, $result[$dbFieldName]);
			$this->set($objectFieldName, $value);
		}

		$this->setLoaded(true, true);
	}

	/**
	 * Prepare a sql statement
	 *
	 * @param string $sql
	 *
	 * @throws Exception
	 * @return PDOStatement|false
	 */
	protected function prepare($sql)
	{
		$statement = $this->db->prepare($sql);
		if (!$statement) {
			throw new Exception('Unable to create statement for SQL ('. $sql .'): '. $this->db->error());
		}
		return $statement;
	}

	/**
	 * Bind parameter
	 *
	 * @param PDOStatement $statement
	 * @param int          $index
	 * @param mixed        $value
	 *
	 * @return void
	 */
	protected function bindParam($statement, $index, $value)
	{
		if (is_bool($value)) {
			$statement->bindParam($index, $value, \PDO::PARAM_BOOL);
		} else {
			$statement->bindParam($index, $value);
		}
	}

	/**
	 * Load from database
	 *
	 * @param array|null $fields
	 *
	 * @return boolean
	 */
	public function load($fields = null)
	{
		if ($this->preLoad() === false) {
			return false;
		}

		if (!$fields) {
			foreach ($this->fieldNames as $key) {
				if (isset($this->fieldSetValues[$key])) {
					$fields[$key] = $this->fieldValues[$key];
				}
			}
		}

		$fieldCount = count($fields);
		if ($fieldCount > 0) {
			if ($this->cache && $this->cacheable) {
				$cacheKey = $this->getCacheKey($fields);
				$cachedValues = $this->cache->get($cacheKey);
				if ($cachedValues) {
					$this->loadCacheResult($cachedValues);

					if ($this->postLoad() !== false) {
						return true;
					} else {
						return false;
					}
				}
			}

			$wherePack = $this->getWherePack($fields);

			if ($this->processValues == Db::PROCESS_VALUES_DB) {
				$dbDriverName = $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME);

				$sqlSelectFields = array();
				foreach ($this->fieldDbNames as $selectObjectField => $selectDbField) {
					$fieldType = (isset($this->fieldTypes[$selectObjectField])) ? $this->fieldTypes[$selectObjectField] : null;
					$quotedDbField = $this->db->quoteIdentifier($selectDbField);
					switch ($fieldType) {
						case 'timestamp':
							switch ($dbDriverName) {
								default:
								case 'mysql':
									$sqlSelectField = 'UNIX_TIMESTAMP('. $quotedDbField .') AS '. $selectDbField;
									break;

								case 'sqlite':
									$sqlSelectField = 'strftime(\'%s\', '. $quotedDbField .')';
									break;

								case 'pgsql':
									$sqlSelectField = 'date_part(\'epoch\', '. $quotedDbField .')::int AS '. $selectDbField;
									break;
							}
							break;

						default:
							$sqlSelectField = $quotedDbField;
							break;
					}
					$sqlSelectFields[] = $sqlSelectField;
				}
				$selectFieldsSql = join(', ', $sqlSelectFields);
			} else {
				$selectFieldsSql = join(', ', array_map(array($this->db, 'quoteIdentifier'), $this->fieldDbNames));
			}

			$loadQuery = 'SELECT '. $selectFieldsSql .
			             ' FROM '. $this->db->quoteIdentifier(static::$table) .
			             ' WHERE '. $wherePack['sqlString'] .' LIMIT 2';
			$statement = $this->prepare($loadQuery);
			for ($i = 0, $l = count($wherePack['values']); $i < $l; $i++) {
				$this->bindParam($statement, $i + 1, $wherePack['values'][$i]);
			}

			if ($this->queryTimeLogging) {
				$beforeQuery = microtime(true);
			}
			$result = $statement->execute();
			if ($this->queryTimeLogging) {
				$this->queryTime += microtime(true) - $beforeQuery;
			}
			if ($this->queryLogging) {
				$this->logQuery($loadQuery, $result);
			}
			if ($result === false) {
				$this->logError($this->db->error());
			}

			$results = $statement->fetchAll(\PDO::FETCH_ASSOC);
			$resultsCount = count($results);
			switch ($resultsCount) {
				default:
				case 0:
					// Nothing found
					break;

				case 2:
					$this->logError('Multiple results matched load() attempt.');
					break;

				case 1:
					$this->loadDatabaseResult($results[0]);

					if ($this->cache && $this->cacheable) {
						$this->cache->set($this->getCacheKey(), $this->fieldValues, $this->cacheExpiration);
					}

					if ($this->postLoad() !== false) {
						return true;
					} else {
						return false;
					}
					break;
			}
		}

		return false;
	}

	/**
	 * Save to database
	 *
	 * Save to database. Will only update based on primary key fields so a load must be run first, in order to retrieve the primary key fields, for other update cases.
	 *
	 * @param boolean $skipReload Skip the load() which would normally occur after a successful save. If a save is executed with this set to true further use of the instance is not recommended as the state will then be inconsistent. Should only be set to true if the save will be the last action executed using the instance and the performance matters.
	 *
	 * @throws Exception
	 * @return void
	 */
	public function save($skipReload = false)
	{
		if ($this->preSave() === false) {
			return;
		}

		if ($this->loaded) {
			if ($this->preUpdate() === false) {
				return;
			}

			$primaryKeyWherePack = $this->getPrimaryKeyWherePack(true);

			$setString = '';
			$setValues = array();
			foreach ($this->fieldNames as $key) {
				if ($setString != '') {
					$setString .= ', ';
				}
				if (isset(static::$primaryKeyFields[$key])) {
					$setValue = $this->primaryKeyFieldInternalValues[$key];
				} else {
					$setValue = (isset($this->fieldValues[$key])) ? $this->fieldValues[$key] : null;
				}
				$setString .= $this->db->quoteIdentifier($this->fieldDbNames[$key]) .' = ?';
				$setValues[] = $this->processFieldForDb($key, $setValue);
			}

			$updateQuery = 'UPDATE '. $this->db->quoteIdentifier(static::$table) .
			               ' SET '. $setString .
			               ' WHERE '. $primaryKeyWherePack['sqlString'];
			$statement = $this->prepare($updateQuery);
			$bindIndex = 1;
			for ($i = 0, $l = count($setValues); $i < $l; $i++) {
				$this->bindParam($statement, $bindIndex, $setValues[$i]);
				$bindIndex++;
			}
			for ($i = 0, $l = count($primaryKeyWherePack['values']); $i < $l; $i++) {
				$this->bindParam($statement, $bindIndex, $primaryKeyWherePack['values'][$i]);
				$bindIndex++;
			}

			$result = $statement->execute();
			if ($this->queryLogging) {
				$this->logQuery($updateQuery, ($result) ? $statement->rowCount() : null);
			}
			if ($result !== false) {
				if ($this->cache && $this->cacheable) {
					$internalCacheKey = $this->getCacheKey($this->getPrimaryKeyValues(true));
					$cacheKey = $this->getCacheKey();

					$this->cache->delete($internalCacheKey);
					if ($cacheKey != $internalCacheKey) {
						$this->cache->delete($cacheKey);
					}
				}

				if (!$skipReload) {
					$reloadFields = $this->getPrimaryKeyValues();
					if (!$this->load($reloadFields)) {
						throw new Exception('Reload after save failed: '. var_export($reloadFields, true));
					}
				}

				$this->postUpdate();
				$this->postSave();
			} else {
				throw new Exception($this->db->error());
			}
		} else {
			if ($this->preInsert() === false) {
				return;
			}

			$insertFields = array();
			$insertValues = array();
			$fieldValueKeys = array_flip(array_keys($this->fieldValues));
			foreach ($this->fieldNames as $key) {
				if (isset($fieldValueKeys[$key])) {
					$insertFields[$key] = $this->fieldDbNames[$key];
					$insertValues[] = $this->processFieldForDb($key, $this->fieldValues[$key]);
				}
			}

			$valuesString = 'DEFAULT VALUES';
			if ($insertValues) {
				$valuesString = 'VALUES ('. join(',', array_fill(0, count($insertValues), '?')) .')';
			}

			$primaryKeyReturnedInStatement = false;
			$primaryKeyVariablesSet = true;
			foreach (static::$primaryKeyFields as $primaryKeyVariable) {
				$fieldValue = (isset($this->fieldValues[$primaryKeyVariable])) ? $this->fieldValues[$primaryKeyVariable] : null;
				if (!isset($fieldValue)) {
					$primaryKeyVariablesSet = false;
				}
			}
			$primaryKeyFieldName = static::$primaryKeyFields[count(static::$primaryKeyFields) - 1];
			switch ($this->db->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
				default:
				case 'mysql': // Both mysql and sqlite provide a valid lastInsertId()
				case 'sqlite':
					$returningString = '';
					break;

				case 'pgsql':
					$primaryKeyReturnedInStatement = true;
					$returningString = ' RETURNING '. $this->db->quoteIdentifier($primaryKeyFieldName);
					break;
			}

			$insertQuery = 'INSERT INTO '. $this->db->quoteIdentifier(static::$table) .
			               ' ('. join(', ', array_map(array($this->db, 'quoteIdentifier'), $insertFields)) .')'.
										 ' '. $valuesString . $returningString;
			$statement = $this->prepare($insertQuery);
			for ($i = 0, $l = count($insertValues); $i < $l; $i++) {
				$this->bindParam($statement, $i + 1, $insertValues[$i]);
			}

			if ($this->queryTimeLogging) {
				$beforeQuery = microtime(true);
			}
			$result = $statement->execute();
			if ($this->queryTimeLogging) {
				$this->queryTime += microtime(true) - $beforeQuery;
			}
			if ($this->queryLogging) {
				$this->logQuery($insertQuery, ($result) ? $statement->rowCount() : null);
			}
			if ($result !== false) {
				if (count(static::$primaryKeyFields) > 0) {
					if (!$primaryKeyVariablesSet) { // Successfully inserted without any primary key fields, attempt to load insertId in to the primary key field reserved for auto_increment or sequence
						$lastInsertId = ($primaryKeyReturnedInStatement) ? $statement->fetch(\PDO::FETCH_COLUMN) : $this->db->lastInsertId();
						$statement->closeCursor();
						if ($lastInsertId) {
							$this->set($primaryKeyFieldName, $lastInsertId);
						}
					}
				}

				if (!$skipReload) {
					$this->load($this->getPrimaryKeyValues());
				}

				$this->postInsert();
				$this->postSave();
			} else {
				// @todo TODO Consider attempting load() and then if successful save() in this case
				throw new Exception($this->db->error());
			}
		}
	}

	/**
	 * Ensure record exists
	 *
	 * If the record is not loaded or able to be loaded, save
	 *
	 * @throws Exception
	 * @return void
	 */
	public function ensure() {
		if (!($this->isLoaded() || $this->load())) {
			$this->save();
		}
	}

	/**
	 * Delete from database
	 *
	 * @throws Exception
	 * @return void
	 */
	public function delete()
	{
		$primaryKeyWherePack = $this->getPrimaryKeyWherePack();
		$countQuery = 'SELECT COUNT(*) FROM '. $this->db->quoteIdentifier(static::$table) .' WHERE '. $primaryKeyWherePack['sqlString'];
		$countStatement = $this->prepare($countQuery);
		for ($i = 0, $l = count($primaryKeyWherePack['values']); $i < $l; $i++) {
			$this->bindParam($countStatement, $i + 1, $primaryKeyWherePack['values'][$i]);
		}

		if ($this->queryTimeLogging) {
			$beforeQuery = microtime(true);
		}
		$countResult = $countStatement->execute();
		if ($this->queryTimeLogging) {
			$this->queryTime += microtime(true) - $beforeQuery;
		}
		if ($countResult === false) {
			throw new Exception('Error during delete: '. $this->db->error());
		}

		$count = $countStatement->fetchColumn();
		$countStatement->closeCursor();

		if ($this->queryLogging) {
			$this->logQuery($countQuery, $count);
		}

		if ($count == 1) {
			$deleteQuery = 'DELETE FROM '. $this->db->quoteIdentifier(static::$table) .' WHERE '. $primaryKeyWherePack['sqlString'];
			$deleteStatement = $this->prepare($deleteQuery);
			for ($i = 0, $l = count($primaryKeyWherePack['values']); $i < $l; $i++) {
				$this->bindParam($deleteStatement, $i + 1, $primaryKeyWherePack['values'][$i]);
			}
			if ($this->queryTimeLogging) {
				$beforeQuery = microtime(true);
			}
			$deleteResult = $deleteStatement->execute();
			if ($this->queryTimeLogging) {
				$this->queryTime += microtime(true) - $beforeQuery;
			}
			if ($this->queryLogging) {
				$this->logQuery($deleteQuery, ($deleteResult) ? $deleteStatement->rowCount() : null);
			}
			if ($deleteResult !== false) {
				if ($this->cache && $this->cacheable) {
					$this->cache->delete($this->getCacheKey());
				}
			} else {
				throw new Exception('Deletion failed: '. $this->db->error());
			}
		} elseif ($count > 1) {
			throw new Exception('There are multiple matches for the current key values, cowardly refusing to delete.');
		} else {
			throw new Exception('No match for deletion.');
		}
	}

	/**
	 * Restore state
	 *
	 * @param	Traversable  $fieldValues
	 * @param	boolean      $processFields
	 *
	 * @return boolean
	 */
	public function restore($fieldValues, $processFields = false)
	{
		if ($processFields) {
			foreach ($fieldValues as $key => $value) {
				$fieldValues[$key] = $this->processFieldFromDb($key, $value);
			}
		}

		$this->fieldValues = $fieldValues;
		$this->fieldSetValues = array_fill_keys(array_keys($fieldValues), true);
		$this->setLoaded(true, true);
		if ($this->postLoad() !== false) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Set properties from data type that can be iterated over
	 *
	 * @param stdClass|array $iterable
	 *
	 * @return void
	 */
	public function fromIterable($iterable)
	{
		foreach ($iterable as $key => $value) {
			$this->__set($key, $value);
		}
	}

	/**
	 * Set properties from object
	 *
	 * @param stdClass $object
	 *
	 * @return void
	 */
	public function fromObject(stdClass $object)
	{
		$this->fromIterable($object);
	}

	/**
	 * Set properties from array
	 *
	 * @param array $array
	 *
	 * @return void
	 */
	public function fromArray(array $array, $ignoreInvalid = false)
	{
		if ($ignoreInvalid) {
			$cleanArray = array();
			foreach ($this->fieldNames as $fieldName) {
				if (isset($array[$fieldName])) {
					$cleanArray[$fieldName] = $array[$fieldName];
				}
			}
			$this->fromIterable($cleanArray);
		} else {
			$this->fromIterable($array);
		}
	}

	/**
	 * Create an object representation of the field data in the object
	 *
	 * @param array $fields
	 *
	 * @return stdClass
	 */
	public function getObject($fields = array())
	{
		if (empty($fields)) {
			$fields = array_keys($this->fieldValues);
		}

		$object = new \stdClass();

		foreach ($fields as $field) {
			if (isset($this->fieldSetValues[$field])) {
				$object->$field = $this->fieldValues[$field];
			}
		}

		return $object;
	}

	/**
	 * Create an array representation of the field data in the object
	 *
	 * @param array $fields
	 * @param bool  $includePrivate
	 *
	 * @return array
	 */
	public function getArray($fields = array(), $includePrivate = false)
	{
		if (empty($fields)) {
			$fields = array_keys($this->fieldValues);
		}
		if (!$includePrivate) {
			$privateFields = array_flip(static::$privateFields);
			foreach ($fields as $key => $value) {
				if (isset($privateFields[$value])) {
					unset($fields[$key]);
				}
			}
		}

		$array = array();

		foreach ($fields as $field) {
			if (isset($this->fieldSetValues[$field])) {
				$array[$field] = $this->fieldValues[$field];
			}
		}

		return $array;
	}

	/**
	 * Create an object representation of the data in the object without primary key fields
	 *
	 * @return stdClass
	 */
	public function getObjectWithoutPrimaryKeys()
	{
		return $this->getObject($this->getNonPrimaryKeyFieldNames());
	}

	/**
	 * Create an object representation of the data in the object without primary key fields
	 *
	 * @param bool $includePrivate
	 *
	 * @return stdClass
	 */
	public function getArrayWithoutPrimaryKeys($includePrivate = false)
	{
		return $this->getArray($this->getNonPrimaryKeyFieldNames(), $includePrivate);
	}

	/**
	 * Create a duplicate of the object with field data for all fields
	 *
	 * @return Record of current Record type
	 */
	public function getDuplicate()
	{
		$className = get_class($this);
		$copy = new $className($this->db);
		$copy->fromArray($this->getArray(null, true));

		return $copy;
	}

	/**
	 * Create a duplicate of the object with field data for non-primary key fields
	 *
	 * @return Record of current Record type
	 */
	public function getDuplicateWithoutPrimaryKeys()
	{
		$className = get_class($this);
		$copy = new $className($this->db);
		$copy->fromArray($this->getArrayWithoutPrimaryKeys(true));

		return $copy;
	}

	/**
	 * Select field values from table
	 *
	 * @param array $fields     Fields to select, fields defined in static::$fields will be substituted
	 * @param array $where      If non-empty will be used to select values, otherwise normal data loading logic will be used to select values
	 * @param mixed $resultType One of the PDO::FETCH_ constants
	 *
	 * @throws Exception
	 * @return array Array of arrays
	 */
	public function select($fields = array(), $where = array(), $resultType = null)
	{
		$selectFields = array();
		foreach ($fields as $field) {
			if (is_scalar($field) && isset($this->fieldDbNames[$field])) {
				$selectFields[] = $this->db->quoteIdentifier($this->fieldDbNames[$field]);
			} else {
				$selectFields[] = $this->db->quoteIdentifier((string) $field); // TODO Re-examine after quick hack
			}
		}

		$results = array();

		$wherePack = $this->getWherePack($where);

		$selectQuery = 'SELECT ' . join(', ', $selectFields) . ' FROM ' . $this->db->quoteIdentifier(static::$table) .' WHERE '. $wherePack['sqlString'];
		$statement = $this->prepare($selectQuery);
		for ($i = 0, $l = count($wherePack['values']); $i < $l; $i++) {
			$this->bindParam($statement, $i + 1, $wherePack['values'][$i]);
		}
		if ($this->queryTimeLogging) {
			$beforeQuery = microtime(true);
		}
		$result = $statement->execute();
		if ($this->queryTimeLogging) {
			$this->queryTime += microtime(true) - $beforeQuery;
		}
		if ($result !== false) {
			$results = $statement->fetchAll(($resultType) ? $resultType : \PDO::FETCH_ASSOC);
		} else {
			throw new Exception($this->db->error());
		}

		return $results;
	}

	/**
	 * Load from query
	 *
	 * @todo Unfinished
	 * @unfinished
	 *
	 * @param string $query First result from query will be loaded
	 *
	 * @throws Exception
	 * @return boolean
	 */
	public function loadFromQuery($query)
	{
		if ($this->queryTimeLogging) {
			$beforeQuery = microtime(true);
		}
		$statement = $this->db->query($query);
		if ($this->queryTimeLogging) {
			$this->queryTime += microtime(true) - $beforeQuery;
		}
		if ($this->queryLogging) {
			$this->logQuery($query);
		}
		if ($statement) {
			$result = $statement->fetch(\PDO::FETCH_ASSOC);
			if ($result) {
				$this->loadDatabaseResult($result);
				$result->closeCursor();

				return true;
			}
		}

		return false;
	}

	/**
	 * Get a Record that is referenced by this Record
	 *
	 * @uses $this->references
	 *
	 * @param string $name
	 *
	 * @throws Exception
	 * @return Record
	 */
	public function getReferenced($name)
	{
		if (!isset(static::$references[$name])) {
			throw new Exception('Reference "'. $name .'" is not defined.');
		}

		$refData = static::$references[$name];
		$localRefFields = (array) $refData[0];
		$refClass = $refData[1];
		$remoteRefFields = (array) $refData[2];

		if (count($localRefFields) !== count($remoteRefFields)) {
			throw new Exception('Reference "'. $name .'" number of reference fields do not match.');
		}

		$ref = new $refClass($this->db);
		foreach ($remoteRefFields as $index => $remoteRefField) {
			$localRefField = $localRefFields[$index];
			$ref->$remoteRefField = $this->$localRefField;
		}
		$ref->load();
		return $ref;
	}

	/**
	 * Get a Set of Records that is referenced by this Record
	 *
	 * @uses $this->references
	 *
	 * @param string     $name
	 * @param array|null $where
	 * @param array|null $sort
	 * @param mixed      $type
	 *
	 * @throws Exception
	 * @return Set
	 */
	public function getReferencedSet($name, $where = null, $sort = null, $type = null)
	{
		if ($where === null) {
			$where = array();
		}
		if ($sort === null) {
			$sort = array();
		}

		if (!isset(static::$references[$name])) {
			throw new Exception('Reference "'. $name .'" is not defined.');
		}

		$refData = static::$references[$name];
		$localRefFields = (array) $refData[0];
		$refClass = $refData[1];
		$remoteRefFields = (array) $refData[2];

		if (count($localRefFields) !== count($remoteRefFields)) {
			throw new Exception('Reference "'. $name .'" number of reference fields do not match.');
		}

		$refWheres = array();
		foreach ($remoteRefFields as $index => $remoteRefField) {
			$localRefField = $localRefFields[$index];
			$refWheres[$remoteRefField] = $this->$localRefField;
		}

		$refIter = new Iterator($refClass, $type, $this->db);
		$refIter->where(array_merge($refWheres, $where));
		if ($sort) {
			$refIter->sort($sort);
		}
		return $refIter->set();
	}

	/**
	 * Get a Set of this Record that has a specific referenced value
	 *
	 * @uses $this->references
	 *
	 * @param string     $name
	 * @param string     $value
	 * @param array|null $where
	 * @param array|null $sort
	 * @param mixed      $type
	 *
	 * @throws Exception
	 * @return Set
	 */
	public static function getSetByReference($name, $value, $where = null, $sort = null, $type = null)
	{
		if (!isset(static::$references[$name])) {
			throw new Exception('Reference "'. $name .'" is not defined.');
		}

		$refData = static::$references[$name];
		$localRefFields = (array) $refData[0];
		$refClass = $refData[1];
		$remoteRefFields = (array) $refData[2];

		if (count($localRefFields) !== count($remoteRefFields)) {
			throw new Exception('Reference "'. $name .'" number of reference fields do not match.');
		}

		$valueArray = (array) $value;

		$refWheres = array();
		foreach ($localRefFields as $index => $localRefField) {
			if (!isset($valueArray[$index])) {
				throw new Exception('Missing value for field "'. $localRefField .'".');
			}
			$refWheres[$localRefField] = $valueArray[$index];
		}

		$iter = static::iter($type);
		$iter->where($refWheres);
		if ($where) $iter->where($where);
		if ($sort) $iter->sort($sort);
		$set = $iter->set();
		return $set;
	}

	/**
	 * Create a Iterator for this type of Record
	 *
	 * @param mixed           $type
	 * @param Connection|null $db
	 *
	 * @return Set
	 */
	public static function iter($type = null, Connection $db = null)
	{
		return new Iterator(get_called_class(), $type, $db);
	}

	// Hooks

	// Load Hooks
	protected function preLoad() {}
	protected function postLoad() {}

	// Save Hooks
	protected function preSave() {}
	protected function preInsert() {}
	protected function preUpdate() {}
	protected function postSave() {}
	protected function postInsert() {}
	protected function postUpdate() {}

	// \Iterator Implementation

	public function rewind() {
		$this->iterPosition = 0;
	}

	public function current() {
		$fieldName = $this->fieldNames[$this->iterPosition];
		return (isset($this->fieldValues[$fieldName])) ? $this->fieldValues[$fieldName] : null;
	}

	public function key() {
		return $this->fieldNames[$this->iterPosition];
	}

	public function next() {
		++$this->iterPosition;
	}

	public function valid() {
		return isset($this->fieldNames[$this->iterPosition]);
	}

	// \ArrayAccess Implementation

	public function offsetExists($offset) {
		return $this->__isset($offset);
	}

	public function offsetGet($offset) {
		return $this->__get($offset);
	}

	public function offsetSet($offset, $value) {
		$this->__set($offset, $value);
	}

	public function offsetUnset($offset) {
		$this->__unset($offset);
	}

	// \JsonSerializable Implementation

	/**
	 * Specify data which should be serialized to JSON
	 *
	 * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
	 *
	 * @return mixed Data which can be serialized by json_encode(), which is a value of any type other than a resource.
	 */
	public function jsonSerialize() {
		return $this->getArray();
	}

	// Utilities

	public function getCreateTableSql($databaseName = null)
	{
		if (!$databaseName) {
			$databaseName = strtolower($this->db->getAttribute(\PDO::ATTR_DRIVER_NAME));
		}

		$primaryKeyFields = array_flip(static::$primaryKeyFields);
		$primaryKeyFieldCount = count($primaryKeyFields);

		$fields = array();
		$fieldNum = 1;
		foreach ($this->fieldDbNames as $objectFieldName => $dbFieldName) {
			$fieldType = Db::mapType(static::fieldType($objectFieldName), $databaseName);
			$fieldDefault = $this->fieldDefault($objectFieldName);
			$fieldAutoString = '';
			if ($fieldNum == 1) {
				switch ($databaseName) {
					case 'pgsql':
						// Handled by serial type
						break;
					case 'mysql':
					case 'sqlite':
						$fieldAutoString = ' AUTO_INCREMENT';
						break;
				}
			}
			$fieldString = "\t". $this->db->quoteIdentifier($dbFieldName) .' '. $fieldType . (($this->fieldNullable($objectFieldName)) ? '' : ' NOT NULL') . $fieldAutoString . (($fieldDefault !== null) ? ' DEFAULT '. $fieldDefault : '') . (($this->fieldUnique($objectFieldName)) ? ' UNIQUE' : '');
			if ($primaryKeyFieldCount < 2 && isset($primaryKeyFields[$objectFieldName])) {
				$fieldString .= ' PRIMARY KEY';
			}
			foreach (static::$references as $reference) {
				if ($reference[0] == $objectFieldName) {
					$fieldString .= ' REFERENCES '. $reference[1]::getTable() .' ('. $reference[2] .') ON UPDATE CASCADE';
				}
			}
			$fields[] = $fieldString;
			$fieldNum++;
		}
		foreach (static::$references as $reference) {
			if (is_array($reference[0])) {
				$fields[] = 'FOREIGN KEY ('. implode(', ', $reference[0]) .') REFERENCES '. $reference[1] .' ('. implode(', ', $reference[2]) .')';
			}
		}
		$fieldsString = implode(",\n", $fields);

		$constraintsString = '';
		if (static::$constraints) {
			$constraints = array();
			$constraintsString = ",\n". implode(",\n", $constraints);
		}

		return "CREATE TABLE ". static::$table ." (\n". $fieldsString . $constraintsString ."\n)";
	}

	public function createTable()
	{
		$sql = $this->getCreateTableSql();
		$result = $this->db->exec($sql);
		return $result;
	}

	// Magic debug

	/**
	 * Get string representation of Record
	 *
	 * @return string
	 */
	public function __toString()
	{
		$string = '';
		foreach ($this->getArray() as $field => $value) {
			$string .= $field .': '. $value ."\n";
		}

		return $string;
	}

	/**
	 * Get var_dump() representation of Record
	 *
	 * @return array
	 */
	public function __debugInfo() {
		return [
			'data'     => $this->getArray(null, true),
			'isLoaded' => $this->isLoaded(),
			'errorLog' => $this->errorLog,
			'queryLog' => $this->queryLog
		];
	}
}

?>
