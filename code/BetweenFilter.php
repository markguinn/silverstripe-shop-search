<?php
/**
 * Checks if the value is within a range (inclusive). Equivalent
 * of mysql's between statement but doesn't use that for compatibility.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 10.18.2013
 * @package shop_Search
 */
class BetweenFilter extends SearchFilter
{
	/**
	 * @param DataQuery $query
	 * @return DataQuery
	 */
	protected function applyOne(DataQuery $query) {
		$this->model = $query->applyRelation($this->relation);
		$value = $this->getDbFormattedValue();

		if(is_numeric($value)) $filter = sprintf("%s > %s", $this->getDbName(), Convert::raw2sql($value));
		else $filter = sprintf("%s > '%s'", $this->getDbName(), Convert::raw2sql($value));

		return $query->where($filter);
	}

	/**
	 * @param DataQuery $query
	 * @return DataQuery
	 */
	protected function excludeOne(DataQuery $query) {
		$this->model = $query->applyRelation($this->relation);
		$value = $this->getDbFormattedValue();

		if(is_numeric($value)) $filter = sprintf("%s <= %s", $this->getDbName(), Convert::raw2sql($value));
		else $filter = sprintf("%s <= '%s'", $this->getDbName(), Convert::raw2sql($value));

		return $query->where($filter);
	}

	/**
	 * @param DataQuery $query
	 * @return DataQuery
	 */
	protected function applyMany(DataQuery $query) {
		$this->model = $query->applyRelation($this->relation);

		$filters = array();
		$ops = array('>=', '<=');
		foreach($this->getValue() as $i => $value) {
			if(is_numeric($value)) {
				$filters[] = sprintf("%s %s %s", $this->getDbName(), $ops[$i], Convert::raw2sql($value));
			} else {
				$filters[] = sprintf("%s %s '%s'", $this->getDbName(), $ops[$i], Convert::raw2sql($value));
			}
		}

		return $query->where( implode(' AND ', $filters) );
	}

	/**
	 * @param DataQuery $query
	 * @return DataQuery
	 */
	protected function excludeMany(DataQuery $query) {
		$this->model = $query->applyRelation($this->relation);

		$filters = array();
		$ops = array('<', '>');
		foreach($this->getValue() as $i => $value) {
			if(is_numeric($value)) {
				$filters[] = sprintf("%s %s %s", $this->getDbName(), $ops[$i], Convert::raw2sql($value));
			} else {
				$filters[] = sprintf("%s %s '%s'", $this->getDbName(), $ops[$i], Convert::raw2sql($value));
			}
		}

		return $query->where( implode(' OR ', $filters) );
	}

	/**
	 * @return bool
	 */
	public function isEmpty() {
		return $this->getValue() === array() || $this->getValue() === null || $this->getValue() === '';
	}
}
