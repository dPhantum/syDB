<?php
/**
* @original-author: T. Paul Quidera
* @Notes This is a generic base class that allows for Polymorphism 
* 		allowing type transparency
*
*/
abstract class morphClass extends iterClass {
    
    /* this will contain our actual data pair values */
    protected // We want these variables in alphabetical order for readability
        $_accessed  =   false,		// If the record has been accessed or read set this flag
        $_data      =   array(), 	// used to hold the current data of the object in usage
        $_dirtyBuffer = array(), 	// used to keep the prior snapshot of the original values
        $_metaData	=	null, 		// Used to save meta-data about this object and what it contains
        $_modBit    =   array(),	// If the record has been modified set this flag
    	$_modIndicator = array(),	// This is the indicator for what action to perform on Save for this record (update|insert)
    	$_NULL		=	null,		// Used for passing a value by reference which is null
    	$_position	=	0; 			// the current position if an array of objects is being used
    	   
    const INSERT_STATE = 'INSERT';		// The status of the record, if new then perform insert action
    const UPDATE_STATE = 'UPDATE';		// The status of the reocrd, if previously existing, then update action
    
    function __construct() {    	
    	// This class can contain meta-data about its contents for usage and identification 
    	$this->_metaData = new stdClass();
    }
    
    /**
     * @abstract Add a new array element/object
     */
    public function &Add() {
    	// Increase the internal record position, since we can hold
    	// multiple records in one object
    	$this->_position++;
    	// We are adding a new record, so we set the state indicator to decide 
    	// if we are doing and insert or update by the inheriting class
    	$this->_modIndicator[$this->_position] = self::INSERT_STATE;
    	
    	return $this;
    }

    /**
     * @Notes Completely remove all data and its elements
     */
    public function Clear(){
    	$this->_metaData = new stdClass();
    	$this->_data = array();
    	$this->_position=0;
    }
    /**
     * Allow the option to clone a record and modify it and save it
     * Effectively a glamerous "copy a record" and save it with your own changes, 
     * un-affecting the original. A "create like" functionality
     */
    // abstract function CloneIt(); @TODO
    
    /**
     * Discard the particular record, which is only a local trash
     * it will not delete the database record.
     * @Note: We will move to the prior position if there is one
     */
    public function Discard($idx=null){
    	if (empty($idx))
    		$idx = $this->_position;
		// delete the element within the array if there is several
		if ($idx >=1){   // zero base index 	
    		unset($this->_data[$idx]);
    		// then back up one level since this position is gone
    		$this->_position--;
		}
		else {
			unset($this->_data[$this->_position]);
			// use this if the entire array is unset
			// $this->_data[$this->_position]= null;
		}
    }
    /*
     * Delete the current record at the current index
    */
    abstract function Delete();
    /**
     * Fetch() is a function for iterating through record sets
     * @return bool
     * 		TRUE = More records to fetch
     * 		FALSE = No more records to fetch
     * @param array $params
     */
    abstract function Fetch($params=array());
    /**
     * Get by primary key identifier for a specific object - SINGLE OBJECT ONLY
     * @param array $params
     */
    abstract function Get($params=array());
    
    /**
     * @abstract Remove the data but leave the structure elements (field names)
     */
    public function Reset(){
    
    	foreach ($this->_data as $key => $value){
    		foreach ($value as $name => $data){
    
    			$this->_data[$key]->{$name} = null;
    		}
    	}
    	$this->_position=0;
    }
    /**
     * Save all current modified or created records added to the current object - SINGLE OBJECT ONLY
     * All joins are read-only objects and cannot be modified
     */
    abstract function Save();
    
     /**
      * @purpose:
      * 
      * Magic methods which allow the access to the object by column names 
      * and assignment of the column names with a specific value to save
      *
      */
     public function __set($pName, $pValue)
     {
         // If irrelivent assignment
         if (empty($pName)) return false;
         //if (property_exists('myORM5', $pName)) { return false; }
         if (substr($pName,0,5)=="xmeta"){
         	// If not initialized then set it
         	if (!isset($this->_metaData))
         		$this->_metaData = new stdClass();
         	
         	$this->_metaData->{substr($pName,5)} = $pValue;
         }
         // Assign the value if not found and regard as new record 
         else if (!isset($this->_data[$this->_position])) {
         	$newObject = new stdClass();
         	$newObject->{$pName} =$pValue;
         	$this->_data[$this->_position] = $newObject;
         	$this->_modBit[$this->_position]=true;
         	
         	// Now we indicate that this record is a new record requiring an update
         	// We only modify this indicator once, so that if we change a new record again the bit wont be flipped to update
         	if (!isset($this->_modIndicator[$this->_position])){
         		$this->_modIndicator[$this->_position] = self::INSERT_STATE;
         	}
         	
         } // if already object then assign the value to the object
         else if (is_object($this->_data[$this->_position])) {
         	
         	// first thing, do we already have a dirty buffer? if not create a buffer object 
         	// and take a snapshot of the before image
         	// This will give us the original values so we can build a unique identifier to update the original record
         	if ((!isset($this->_dirtyBuffer[$this->_position]) || !is_object($this->_dirtyBuffer[$this->_position]))){ 
         		//&& (isset($this->_data[$this->_position]->{$pName}))){
         		$this->_dirtyBuffer[$this->_position] = new stdClass();
         	}
         	
         	// Now check if this value has already been assigned as modified
         	if (!isset($this->_dirtyBuffer[$this->_position]->{$pName})){ // could be previously null
         		
         		// If we have an existing definition, the dirty buffer is actually the original snapshot
         		if (isset($this->_data[$this->_position]->{$pName})){
         			$this->_dirtyBuffer[$this->_position]->{$pName} = $this->_data[$this->_position]->{$pName}; // assign prev value even if empty/null
         		} 
         		else {
         			$this->_dirtyBuffer[$this->_position]->{$pName} = null;
         		}
         		// Now check if this was values are empty and set to null for the DB to pick them up as NULL
         		// if (!isset($this->_dirtyBuffer[$this->_position]->{$pName})){
         		//	$this->_dirtyBuffer[$this->_position]->{$pName} = "";// this allows for the check for null/empty
         		// }
         	}
         	
         	// Assign the new value to the data key
         	$this->_data[$this->_position]->{$pName} = $pValue;
         	$this->_modBit[$this->_position]=true;
         	
         	// Now we indicate that this record is a new record requiring an update
         	// We only modify this indicator once, so that if we change a new record again the bit wont be flipped to update
         	if (!isset($this->_modIndicator[$this->_position])){
         		$this->_modIndicator[$this->_position] = self::UPDATE_STATE;
         	}
         	
         }

         return true;
     }

     //----------------------------------------------------------------------
     // Purpose:
     // Provide accessors (getter methods) for private members like public
     // variables
     //----------------------------------------------------------------------
	public  function &__get($pName)
	{
		if (substr($pName,0,5)=="xmeta"){
			// if exists then send reference
			return $this->_metaData->{substr($pName,5)};
		}
		// Assign the value if not found and regard as new record
		else if (@is_object($this->_data[$this->_position])
				&& @isset($this->_data[$this->_position]->{$pName})) {
			$this->_accessed=true;
			return $this->_data[$this->_position]->{$pName};
		}
		else {
			$this->_accessed=true;
			// why not just return NULL? because this will be used by Reference
			// we cannot get a reference of NULL if returned, but we can point to the address
			// of the variable _NULL which has the value of NULL otherwise a notice/error will be generated.
			return $this->_NULL;
		}

	}

     /**
      * Overload so that private members can be passed to isset as a variable
      * This function is implicitly called by PHP's isset function to verify
      * private variable members
      * @param string $pName
      * @return boolean
      */
     public function __isset($pName) 
     {
     	if (substr($pName,0,5)=="xmeta"){
	     	if (isset($this->_metaData->{substr($pName,5)})){
	     		return true;
	     	}
	     	else 
	     		return false; 
     	} elseif (isset($this->_data[$this->_position]->{$pName})) {
        	return true;
     	} else { 
     		return false;
     	}
     }

     /**
      * @Purpose:
      * 	Overload so that private members can be passed to unset as a variable
      * 	but the actual element in the array still should exist to commit an
      * 	empty column record
      */
     /**  As of PHP 5.1.0  */
     public function __unset($pName) 
     {
         unset($this->_data[$this->_position]->{$pName});
     }
    
    public function __call($name, $args) 
    {
        
        $functionType = strtolower(substr($name,0,3));
        
        if ( $functionType == "get") {
            return $this->_data[$this->_position]->{substr($name,3)};
        }
        else if ( $functionType == "set") {
         	// Pass all modifications through the magic set function
            return $this->__set(substr($name,3), $args[0]);
        }
        else 
        	throw new Exception("ERROR: [".__CLASS__."] Call to undefined function ".$name);
        
    }
    
}
