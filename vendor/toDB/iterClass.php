<?php 
/**
 * This is an interface for allowing iteration of an object with accessors
 * @original-author: T. Paul Quidera
 * @date 	July 16th, 2015 Anno Domini (The year of the LORD)
 */
class iterClass implements Iterator {

	public function __construct() {
		$this->_position = 0;
	}
	
	// ================================================
	// BEGIN ABSTRACT METHODS THAT MUST BE OVERWRITTEN
	// ================================================
	/**
	 * (non-PHPdoc)
	 * @see Iterator::current()
	 */
	function current() {
		// var_dump(__METHOD__);
		return $this->_data[$this->_position];
	}
	/**
	 * (non-PHPdoc)
	 * @see Iterator::key()
	 */
	function key() {
		// var_dump(__METHOD__);
		return $this->_position;
	}
	/**
	 * (non-PHPdoc)
	 * @see Iterator::next()
	 */
	function next() {
		// var_dump(__METHOD__);
		++$this->_position;
	}
	/**
	 * (non-PHPdoc)
	 * @see Iterator::rewind()
	 */
	function rewind() {
		$this->_position = 0;
	}
	/**
	 * (non-PHPdoc)
	 * @see Iterator::valid()
	 */
	function valid() {
		return isset($this->_data[$this->_position]);
	}
	// ================================================
	// END ABSTRACT METHODS THAT MUST BE OVERWRITTEN
	// ================================================
	
	// Reversable scrolling cursor
	function back() {
		// Don't allow it to go below zero, the end limit
		if ($this->_position>0){
			$this->_position--; // the post decrement operator for moving backward in the list
		}
	}
}
