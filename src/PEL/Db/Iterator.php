<?php

namespace PEL\Db;

/**
 * PEL Db Iterator
 *
 * @package PEL
 */


/**
 * PEL Db Iterator
 *
 * @package PEL
 */
class Iterator implements \Iterator
{
	const TYPE_RECORD = 'record';
	const TYPE_OBJECT = 'object';
	const TYPE_ASSOC = 'assoc';
	const TYPE_ARRAY = 'array';

	/**
	 * Database connection
	 *
	 * @var Connection
	 */
	protected $db;

	/**
	 * Class name of Record
	 *
	 * @var string
	 */
	protected $objectClass = '';

	/**
	 * Instance of $this->objectClass
	 *
	 * @var Record
	 */
	protected $object;

	/**
	 * Database table
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * Field definitions
	 * objectVariableName => fieldDefinition
	 *
	 * @array
	 */
	protected $fields;

	/**
	 * Database field names
	 * objectVariableName => sqlFieldName
	 *
	 * @array
	 */
	protected $fieldDbNames;

	/**
	 * SQL WHERE data
	 *
	 * @var unknown_type
	 */
	protected $wheres = array();

	/**
	 * SQL ORDER BY data
	 *
	 * @var unknown_type
	 */
	protected $orderBys = array();

	/**
	 * Offset in result set
	 *
	 * @var unknown_type
	 */
	protected $offset;

	/**
	 * Result set limit
	 *
	 * @var unknown_type
	 */
	protected $limit;

	/**
	 * Whether the current parameters have been loaded
	 *
	 * @var boolean
	 */
	protected $loaded = false;

	/**
	 * Whether the current parameters will produce a query which cannot have results
	 * Such as a query containing an empty IN.
	 *
	 * @var boolean
	 */
	protected $unreachable = false;

	/**
	 * Current statement
	 *
	 * @var PDOStatement
	 */
	protected $statement;

	/**
	 * Current iterator
	 *
	 * @var IteratorIterator
	 */
	protected $iterator;

	/**
	 * Type of response
	 *
	 * @var self::TYPE_
	 */
	protected $responseType;

	public $errorLog = array();

	public $queryLogging = false;

	public $queryLog = array();

	public $queryTimeLogging = false;

	public $queryTime = 0;

	/**
	 * Iterator constructor
	 *
	 * @param Connection $db
	 * @param string $objectClass
	 */
	public function __construct($objectClass, $responseType = null, Connection $dbConnection = null)
	{
		if ($responseType) {
			$this->responseType = $responseType;
		}

		if (!$dbConnection) {
			$dbConnection = \PEL\Db::getConnection();
			if (!$dbConnection) {
				throw new Exception('Unable to find database connection');
			}
		}

		$this->db = $dbConnection;

		/*
		$objectClassParent = class_parents($objectClass);
		if (!isset($objectClassParent['Record'])) {
			throw new Exception('Object class (' . $this->objectClass . ') must be a \PEL\Db\Record.');
		}
		*/
		$this->objectClass = '\\'. $objectClass; // TODO Better handling of namespaced classes.
		$this->object = new $objectClass($this->db);
		$this->table = $this->object->getTable();
		$this->fields = $this->object->getFieldDefinitions();
		$flatFields = isset($this->fields[0]);
		if ($flatFields) {
			$this->fieldDbNames = array_combine($this->fields, $this->fields);
		} else {
			foreach ($this->fields as $field => $data) {
				$this->fieldDbNames[$field] = (isset($data['dbField'])) ? $data['dbField'] : $field;
			}
		}
	}

	/**
	 * Log query
	 *
	 * Log to $this->queryLog and syslog
	 *
	 * @param string $query
	 * @param mixed  $result
	 *
	 * @return void
	 */
	protected function logQuery($query, $result = null)
	{
		$this->queryLog[] = array('query' => $query, 'result' => $result);
		syslog(LOG_INFO, ltrim($this->objectClass, '\\') .' iterator query: '. $query);
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
		syslog(LOG_ERR, ltrim($this->objectClass, '\\') .' iterator error: '. $error);
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
		\PEL\Db::logSilentError($error, $data);
	}

	/**
	 * Get table
	 *
	 * @return array
	 */
	public function getTable() {
		return $this->table;
	}

	/**
	 * Get field definitions
	 *
	 * @return array
	 */
	public function getFieldDefinitions() {
		return $this->fields;
	}

	/**
	 * Get list of field names
	 *
	 * @return array
	 */
	public function getFieldNames() {
		return array_keys($this->fieldDbNames);
	}

	/**
	 * Check whether a field exists
	 *
	 * @param string $field
	 * @return boolean
	 */
	public function fieldExists($field)
	{
		return isset($this->fieldDbNames[$field]);
	}

	/**
	 * Shorthand for adding multiple wheres
	 *
	 * @param array $wheres
	 *
	 * @return __CLASS__ Instance for chaining
	 */
	public function where()
	{
		$args = func_get_args();
		$argCount = count($args);
		if (isset($args[0]) && is_array($args[0])) {
			$wheres = $args[0];
			if (isset($wheres[0])) {
				foreach ($wheres as $whereParams) {
					call_user_func_array(array($this, 'addWhere'), $whereParams);
				}
			} else {
				foreach ($wheres as $field => $value) {
					$this->addWhere($field, $value);
				}
			}
		} else if ($argCount > 0 && $argCount <= 4) {
			call_user_func_array(array($this, 'addWhere'), $args);
		} else {
			throw new Exception('Invalid where arguments');
		}

		return $this;
	}

	/**
	 * Add a where condition
	 *
	 * @param string $field
	 * @param mixed $value
	 * @param string $comparison
	 * @param string $conjunction
	 *
	 * @return __CLASS__ Instance for chaining
	 */
	public function addWhere($field, $value, $comparison = null, $conjunction = null)
	{
		if (!isset($this->fieldDbNames[$field])) {
			throw new Exception('Unable to add where, undefined field "'. $field .'".');
		}

		if (is_string($value) && strtoupper($value) == \PEL\Db::VALUE_NOT_NULL && $comparison === null) {
			$comparison = \PEL\Db::COMPARISON_IS_NOT_NULL;
		}

		if (is_string($comparison)) {
			$comparison = strtoupper($comparison);
		}
		switch ($comparison) {
			case null:
			case false:
			case \PEL\Db::COMPARISON_EQUAL:
				if ($value !== null) {
					if (is_array($value)) {
						$sqlComparison = \PEL\Db::COMPARISON_IN;
						break;
					}

					$sqlComparison = \PEL\Db::COMPARISON_EQUAL;
					break;
				}
			case \PEL\Db::COMPARISON_IS_NULL:
				$sqlComparison = \PEL\Db::COMPARISON_IS_NULL;
				break;

			case \PEL\Db::COMPARISON_NOT_EQUAL:
				if ($value !== null) {
					$sqlComparison = \PEL\Db::COMPARISON_NOT_EQUAL;
					break;
				}
			case \PEL\Db::COMPARISON_IS_NOT_NULL:
				$sqlComparison = \PEL\Db::COMPARISON_IS_NOT_NULL;
				break;

			case \PEL\Db::COMPARISON_GREATER_THAN:
			case \PEL\Db::COMPARISON_LESS_THAN:
			case \PEL\Db::COMPARISON_GREATER_EQUAL:
			case \PEL\Db::COMPARISON_LESS_EQUAL:
			case \PEL\Db::COMPARISON_LIKE:
			case \PEL\Db::COMPARISON_ILIKE:
			case \PEL\Db::COMPARISON_NOT_LIKE:
			case \PEL\Db::COMPARISON_IN:
			case \PEL\Db::COMPARISON_NOT_IN:
				$sqlComparison = $comparison;
				break;

			default:
				throw new Exception('Invalid comparison "'. $comparison .'".');
				break;
		}

		if (is_string($conjunction)) {
			$conjunction = strtoupper($conjunction);
		}
		switch ($conjunction) {
			case null:
			case false:
			case \PEL\Db::CONJUNCTION_AND:
				$sqlConjunction = \PEL\Db::CONJUNCTION_AND;
				break;

			case \PEL\Db::CONJUNCTION_OR:
				$sqlConjunction = \PEL\Db::CONJUNCTION_OR;
				break;

			default:
				throw new Exception('Invalid conjunction "'. $conjunction .'".');
				break;
		}

		$this->wheres[] = array('field' => $field, 'value' => $value, 'comparison' => $sqlComparison, 'conjunction' => $sqlConjunction);

		$this->loaded = false;
		if (($sqlComparison == \PEL\Db::COMPARISON_IN || $sqlComparison == \PEL\Db::COMPARISON_NOT_IN) && empty($value)) {
			$this->unreachable = true;
		}

		return $this;
	}

	/**
	 * Add a literal unescaped string to the where clause
	 *
	 * @param string $where
	 *
	 * @return __CLASS__ Instance for chaining
	 */
	public function addLiteralWhere($where)
	{
		$this->wheres[] = array('value' => $where);

		$this->loaded = false;

		return $this;
	}

	public function group()
	{
		$this->addLiteralWhere('AND (true');

		return $this;
	}

	public function endGroup()
	{
		$this->addLiteralWhere(')');

		return $this;
	}

	protected function getWhereSqlValue($where)
	{
		if (!isset($where['comparison']) && !isset($where['conjunction'])) { // Literal
			return $where['value'];
		}

		switch ($where['comparison']) {
			default:
			case \PEL\Db::COMPARISON_EQUAL:
				if ($where['value'] === true) {
					$sqlValue = 'TRUE';
					break;
				} else if ($where['value'] === false) {
					$sqlValue = 'FALSE';
					break;
				} else if ($where['value'] !== null) {
					$sqlValue = $this->db->quote($where['value']);
					break;
				}
			case \PEL\Db::COMPARISON_IS_NULL:
				$sqlValue = '';
				break;

			case \PEL\Db::COMPARISON_NOT_EQUAL:
				if ($where['value'] === true) {
					$sqlValue = 'TRUE';
					break;
				} else if ($where['value'] === false) {
					$sqlValue = 'FALSE';
					break;
				} else if ($where['value'] !== null) {
					$sqlValue = $this->db->quote($where['value']);
					break;
				}
			case \PEL\Db::COMPARISON_IS_NOT_NULL:
				$sqlValue = '';
				break;

			case \PEL\Db::COMPARISON_GREATER_THAN:
			case \PEL\Db::COMPARISON_LESS_THAN:
			case \PEL\Db::COMPARISON_GREATER_EQUAL:
			case \PEL\Db::COMPARISON_LESS_EQUAL:
			case \PEL\Db::COMPARISON_LIKE:
			case \PEL\Db::COMPARISON_ILIKE:
			case \PEL\Db::COMPARISON_NOT_LIKE:
				$sqlValue = $this->db->quote($where['value']);
				break;

			case \PEL\Db::COMPARISON_IN:
				$sqlValue = '('. join(', ', array_map(array($this->db, 'quote'), (array) $where['value'])) .')';
				break;

			case \PEL\Db::COMPARISON_NOT_IN:
				$sqlValue = '('. join(', ', array_map(array($this->db, 'quote'), (array) $where['value'])) .')';
				break;
		}

		return $sqlValue;
	}

	/**
	 * Shorthand for adding one or more order bys
	 *
	 * @param array $sorts
	 *
	 * @return __CLASS__ Instance for chaining
	 */
	public function sort()
	{
		$args = func_get_args();
		if (isset($args[0]) && is_array($args[0])) {
			$orderBys = $args[0];
			if (isset($orderBys[0])) {
				foreach ($orderBys as $field) {
					$this->addOrderBy($field);
				}
			} else {
				foreach ($orderBys as $field => $order) {
					$this->addOrderBy($field, $order);
				}
			}
		} else {
			$argsLen = count($args);
			for ($i = 0; $i < $argsLen; $i++) {
				$cur = $args[$i];
				if (isset($args[$i + 1])) {
					$next = strtoupper($args[$i + 1]);
					if ($next === \PEL\Db::ORDER_DESC || $next === \PEL\Db::ORDER_ASC) {
						$this->addOrderBy($cur, $next);
						$i++;
						continue;
					}
				}
				$this->addOrderBy($cur);
			}
		}

		return $this;
	}

	/**
	 * Add order by field
	 *
	 * @param string $field
	 * @param string $order
	 *
	 * @return __CLASS__ Instance for chaining
	 */
	public function addOrderBy($field, $order = null)
	{
		if (!isset($this->fieldDbNames[$field])) {
			throw new Exception('Unable to addOrderBy, field (' . $field . ') does not exist on object set for ' . $this->objectClass . '.');
		}

		switch (strtoupper(trim($order))) {
			default:
			case \PEL\Db::ORDER_ASC:
				$sqlOrder = \PEL\Db::ORDER_ASC;
				break;
			case \PEL\Db::ORDER_DESC:
				$sqlOrder = \PEL\Db::ORDER_DESC;
				break;
		}

		$this->orderBys[] = array('field' => $field, 'order' => $sqlOrder);

		$this->loaded = false;

		return $this;
	}

	/**
	 * Set the offset
	 *
	 * @param integer $offset
	 *
	 * @return __CLASS__ Instance for chaining
	 */
	public function offset($offset)
	{
		$this->setOffset($offset);

		return $this;
	}

	/**
	 * Set the offset
	 *
	 * @param integer $offset
	 *
	 * @return __CLASS__ Instance for chaining
	 */
	public function setOffset($offset)
	{
		if (is_integer($offset)) {
			$this->offset = $offset;

			$this->loaded = false;
		} else {
			throw new Exception('Invalid offset type, integer required for setOffset.');
		}

		return $this;
	}

	/**
	 * Set the result limit
	 *
	 * @param integer $limit
	 *
	 * @return __CLASS__ Instance for chaining
	 */
	public function limit($limit)
	{
		$this->setLimit($limit);

		return $this;
	}

	/**
	 * Set the result limit
	 *
	 * @param integer $limit
	 *
	 * @return __CLASS__ Instance for chaining
	 */
	public function setLimit($limit)
	{
		if (is_integer($limit)) {
			$this->limit = $limit;

			$this->loaded = false;
		} else {
			throw new Exception('Invalid limit type, integer required for setLimit.');
		}

		return $this;
	}

	public function getStatement()
	{
		$query = 'SELECT '. join(', ', array_map(array($this->db, 'quoteIdentifier'), $this->fieldDbNames)) .' FROM '. $this->db->quoteIdentifier($this->table);

		if (count($this->wheres)) {
			$whereSql = '';
			foreach ($this->wheres as $where) {
				if (isset($where['field'])) { // Check for non-literal where
					if ($whereSql != '') {
						$whereSql .= ' '. $where['conjunction'] .' ';
					}
					$whereSql .= $this->db->quoteIdentifier($this->fieldDbNames[$where['field']]) .' '. $where['comparison'] .' '. $this->getWhereSqlValue($where);
				} else {
					$whereSql .= ' '. $where['value'];
				}
			}
			$query .= ' WHERE '. $whereSql;
		}
		if (count($this->orderBys)) {
			$orderBySql = '';
			foreach ($this->orderBys as $orderBy) {
				if ($orderBySql != '') {
					$orderBySql .= ', ';
				}
				$orderBySql .= $this->db->quoteIdentifier($this->fieldDbNames[$orderBy['field']]) .' '. $orderBy['order'];
			}
			$query .= ' ORDER BY '. $orderBySql;
		}
		if (isset($this->limit)) {
			$query .= ' LIMIT '. $this->limit;
		}
		if (isset($this->offset)) {
			$query .= ' OFFSET '. $this->offset;
		}

		if ($this->queryLogging) {
			$this->logQuery($query);
		}

		if ($this->queryTimeLogging) {
			$beforeQuery = microtime(true);
		}
		$statement = $this->db->query($query);
		if ($this->queryTimeLogging) {
			$this->queryTime += microtime(true) - $beforeQuery;
		}
		if ($statement) {
			return $statement;
		} else {
			$this->logError('Unable to create statement for SQL ('. $query .'): '. $this->db->error());
		}

		return null;
	}

	public function getDeleteStatement($deleteWithoutWhere = false)
	{
		$query = 'DELETE FROM '. $this->db->quoteIdentifier($this->table);

		if (count($this->wheres)) {
			$whereSql = '';
			foreach ($this->wheres as $where) {
				if (isset($where['field'])) { // Check for non-literal where
					if ($whereSql != '') {
						$whereSql .= ' '. $where['conjunction'] .' ';
					}
					$whereSql .= $this->db->quoteIdentifier($this->fieldDbNames[$where['field']]) .' '. $where['comparison'] .' '. $this->getWhereSqlValue($where);
				} else {
					$whereSql .= ' '. $where['value'];
				}
			}
			$query .= ' WHERE '. $whereSql;
		} else if (!$deleteWithoutWhere) {
			throw new Exception('Cowardly refusing to delete without where conditions, pass deleteWithoutWhere to override.');
		}
		if (count($this->orderBys)) {
			$orderBySql = '';
			foreach ($this->orderBys as $orderBy) {
				if ($orderBySql != '') {
					$orderBySql .= ', ';
				}
				$orderBySql .= $this->db->quoteIdentifier($this->fieldDbNames[$orderBy['field']]) .' '. $orderBy['order'];
			}
			$query .= ' ORDER BY '. $orderBySql;
		}
		if (isset($this->limit)) {
			$query .= ' LIMIT '. $this->limit;
		}
		if (isset($this->offset)) {
			$query .= ' OFFSET '. $this->offset;
		}

		if ($this->queryLogging) {
			$this->logQuery($query);
		}

		if ($this->queryTimeLogging) {
			$beforeQuery = microtime(true);
		}
		$statement = $this->db->query($query);
		if ($this->queryTimeLogging) {
			$this->queryTime += microtime(true) - $beforeQuery;
		}
		if ($statement) {
			return $statement;
		} else {
			$this->logError('Unable to create statement for SQL ('. $query .'): '. $this->db->error());
		}

		return null;
	}

	/**
	 * Load set
	 *
	 * @return boolean
	 */
	public function load()
	{
		if ($this->unreachable) {
			$this->logSilentError('Cowardly refusing to run load query that will never return results.');
			return false;
		}
		$statement = $this->getStatement();
		if ($statement) {
			$this->statement = $statement;
			$this->statement->setFetchMode(\PDO::FETCH_ASSOC);

			$this->iterator = new \IteratorIterator($this->statement);

			$this->loaded = true;
			return true;
		}
	}

	/**
	 * Delete set
	 *
	 * @return boolean
	 */
	public function delete()
	{
		if ($this->unreachable) {
			$this->logSilentError('Cowardly refusing to run delete query that will never return results.');
			return false;
		}
		$delStatement = $this->getDeleteStatement();
		if ($delStatement) {
			$result = $delStatement->execute();
			if ($result === false) {
				$this->logError($this->db->error());
			}
			return true;
		}
	}

	public function getObjectForRow($row)
	{
		if ($row) {
			$resultObject = clone $this->object;
			$resultObject->restore($row);

			return $resultObject;
		} else {
			return null;
		}
	}

	public function results($keyBy = null)
	{
		if (!$this->loaded) {
			$this->load();
		}

		$results = array();

		if ($this->loaded) {
			foreach ($this as $record) {
				$results[] = $record;
			}

			if ($keyBy) {
				$results = \PEL\ArrayUtil::keyByValues($results, $keyBy);
			}
		}

		return $results;
	}

	public function set()
	{
		return new Set($this);
	}

	// Iterator

	public function current()
	{
		if ($this->iterator) {
			$current = $this->iterator->current();
			foreach ($current as $key => $value) {
				$current[$key] = $this->object->processFieldFromDb($key, $value);
			}

			switch ($this->responseType) {
				default:
				case self::TYPE_RECORD:
					return $this->getObjectForRow($current);
					break;

				case self::TYPE_OBJECT:
					return (object) $current;
					break;

				case self::TYPE_ASSOC: // Native fetch type
					return $current;
					break;

				case self::TYPE_ARRAY:
					return array_values($current);
					break;
			}
		} else {
			return null;
		}
	}

	public function key()
	{
		return ($this->iterator) ? $this->iterator->key() : null;
	}

	public function next()
	{
		if ($this->iterator) {
			$this->iterator->next();
		}
	}

	public function rewind()
	{
		if (!$this->loaded) {
			$this->load();
		}

		if ($this->iterator) {
			$this->iterator->rewind();
		}
	}

	public function valid()
	{
		return ($this->iterator) ? $this->iterator->valid() : false;
	}
}

?>
