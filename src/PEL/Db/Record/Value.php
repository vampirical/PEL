<?php

namespace PEL\Db\Record;

/**
 * PEL Db Record Field Value
 *
 * @package PEL
 */


/**
 * PEL Db Record Field Value
 *
 * @package PEL
 */
class Value
{
	/**
	 * Value
	 *
	 * @var mixed
	 */
	protected $value;

	/**
	 * Comparison to use
	 *
	 * @var string
	 */
	protected $comparison;

	/**
	 * Construct
	 *
	 * @param mixed $value
	 * @param bool  $comparison
	 */
	public function __construct($value, $comparison = null)
	{
		$this->setValue($value);
		$this->setComparison(($comparison) ? $comparison : $this->getDefaultComparisonForValue());
	}

	/**
	 * Get value
	 *
	 * @return mixed
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * Set value
	 *
	 * @param mixed $value
	 */
	public function setValue($value)
	{
		$this->value = $value;
	}

	/**
	 * Get comparison to use in queries
	 *
	 * @return string
	 */
	public function getComparison()
	{
		return $this->comparison;
	}

	/**
	 * Set comparison to use in queries
	 *
	 * @param string $comparison
	 */
	public function setComparison($comparison)
	{
		$this->comparison = $comparison;
	}

	/**
	 * Get the default comparison for the current value
	 *
	 * @return string
	 */
	public function getDefaultComparisonForValue()
	{
		if ($this->value === null) {
			return \PEL\Db::COMPARISON_IS_NULL;
		} else if (strtoupper($this->value) === \PEL\Db::VALUE_NOT_NULL) {
			return \PEL\Db::COMPARISON_IS_NOT_NULL;
		} else if (is_array($this->value)) {
			return \PEL\Db::COMPARISON_IN;
		} else {
			return \PEL\Db::COMPARISON_EQUAL;
		}
	}

	/**
	 * Get SQL value
	 *
	 * @return string
	 */
	public function getSqlValue()
	{
		return $this->value;
	}

	/**
	 * Get SQL comparison
	 *
	 * @return string
	 */
	public function getSqlComparison()
	{
		$comparison = (isset($this->comparison)) ? $this->comparison : $this->getDefaultComparisonForValue();

		// Morph explicit equal/not-equal to null comparisons when approriate
		if ($this->value === null || strtoupper($this->value) === \PEL\Db::VALUE_NOT_NULL) {
			if ($comparison === \PEL\Db::COMPARISON_EQUAL) {
				$comparison = \PEL\Db::COMPARISON_IS_NULL;
			} else if ($comparison === \PEL\Db::COMPARISON_NOT_EQUAL) {
				$comparison = \PEL\Db::COMPARISON_IS_NOT_NULL;
			}
		}

		return $comparison;
	}

	/**
	 * To string
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->getSqlValue();
	}
}

?>
