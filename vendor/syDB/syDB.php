<?php
/**
 * This ORM is written for Sybase 12.0 and older which do not support scrolling cursors or the modern "LIMIT"
 * qualifier for fetches. This poses a huge problem for modern applications. Fortunately, this class uses
 * db specific functionality to workaround that limitation.
 *
 *
 * @comment	This is the ORM (Object Relational Mapping) Class that can morph into
 * 			any object with dynamic accessor methods for each of the object elements
 *
 * @date	July 2015 Anno Domini (The Year of our Lord)
 *
 * @original-author: T. Paul Quidera
 *
 * Features:
 * - Dynamic use of Primary Keys for DML operations for maximum performance
 * -
 * @example
 * 		SELECT ALL
 * 			'select-all' - no parameters, which fetch all records when no conditions are present
 *
 * 		ORDER BY
 * 			orderBy => array('column-name'=>'sort-order', [...] )
 * 					- Each element pair of the array is a column name and sort order (.i.e. ASC, DESC)
 * 			OR scalar
 * 			'order-by' => 'col1, col2, col3, col4 ASC [...]'
 *
 * 		TOP
 * 			'top' => 1
 * 					- 'top' Internally becomes 'SELECT TOP n' where n is the integer value of top defined by the user parameter
 * 						Note: This will limit the result set to the TOP value, if you want to limit rows fetched use ROWS parameter instead.
 *
 *
 *   	CONDITIONS/WHERE Clause
 *
 *   	Dynamic Array values
 *   		conditions => array(						// no need for the '?' bind operator, the ORM will dynamicall bind values
 *   					'colName' => $bindValue,       // this will be implicity parameratized as a bind value
 *   					'colName2' => 'literalBindTxt' // this also will be implicity parameratized as a bind value
 *   					[...])
 *
 *   	literal qualifier
 *   		conditions => '[WHERE] col1 = "value1" AND col2 = "value2" [etc...]'
 *
 *   	literal qualifier with bind values, must specify 'bind-values' param
 *   		conditions => '[WHERE] col1 = ? AND col2 = ? [etc...]',
 *   		bindValues => array($bindVar1, 'bindvar2', [...])
 *
 *
 *
 * @limitations:
 *
 * COMMENTS
 * -----------
 * 1)	If comments are present between the SELECT and the First Column Name, an error will follow if the code is optimizing for pagination
 * 	i.e. the following will fail "SELECT -- a comment after the select \n" or  "SELECT /* BLAH ...."
 * 2)	Order by column names must be unique for joins. Because temp tables are used by outline data sets, you may not use correlation suffixes (a.col, b.col, etc)
 * 		because the correlation suffix is out of scope ("a.", 'b."), because it does not exist in the temp (finalized dataset) table, which is already unique.
 *
 * RESERVED PREFIX WORD
 * --------------------
 * XMETA
 * xmeta is a reserved word prefix (lowercase). Do not name any column in tables or object elements with this prefix.
 *
 * 	CUSTOM SQL OVERRIDING
 *  ---------------------
 * 		* $params['query']
 * 			- Do not use the limit n[,n] (i.e. MySQL) in your sql/query override,
 * 			instead use the $params['row-position'], $params['rows'] to specify what row to start on
 * 			and how many rows to get in one fetch
 * 		* $params['select']
 * 			- A user can override the defaults columns that are presented in the object element list
 * 			- However if using functions you must alias the function or an error will occur, you alias the function
 * 			as follows: 'select' => 'SELECT myFunction(myColumn) AS MYALIAS, ...'
 * 			- All custom select list are READ ONLY transactions
 * 			- The SELECT reserved word is optional.
 *
 * 	OBJECT FETCH METHOD
 *  -------------------
 * 		JOIN Conditions for Natural Joins
 * 			o For column identifiers with the array, because we are using named indexes for the array
 * 			we must gaurantee that the values are unique or the last value will overwrite the previous
 * 			o Workaround: for example for table1.col1 = table2.col1 AND table1.col IS NOT NULL
 * 				- The following will not work:
 * 					array(
 * 							'table1.col1' => 'table2.col1',
 * 							'table1.col1' => array('IS NOT','NULL'))
 * 				- Because the named index 'table1.col1' is repeated and the second replaces the first,
 * 				but the following will work:
 * 					array(
 * 							'table2.col1' => 'table1.col1',
 * 							'table1.col1' => array('IS NOT', 'NULL')) // fyi. "IS NOT" is treated as a single operator
 * 			o Column Join equality
 * 				- Column comparison must have the table qualifier for example if in a NATURAL JOIN conditions
 * 				The following where conditions "WHERE ColumnA = ColumnB" where ColumnA is from TableA and ColumnB is from TableB
 * 				These phrases must be written as (in object notation)
 * 						'objects' => array('TableA', 'TableB'),
 * 						'conditions' => array( 'TableA.ColumnA' => 'TableB.ColumnB')
 * 				And for comparisons other than "=" operator then specify the operator with the value/column
 * 						'conditions' => array( 'TableA.ColumnA' => array('>=' => 'TableB.ColumnB'))
 * 				- For SELF JOINS the simple column comparison the same also applies
 * 					The following where condition: "WHERE Column1 = Column1"
 * 					Do the following:
 * 						'conditions' => array('TableName.Column1' => 'TableName.Column2')
 * 					And again in the case of special operators
 * 						'conditions' => array('TableName.Column1' => array('!=' => 'TableName.Column2'))
 *
 * CRUD OPERATIONS ONLY PERMITTED ON SINGLE OBJECTS
 * ------------------------------------------------
 * 			o All Join operations are READ ONLY and all save requests will be ignored. You must fetch the data
 * 			as a single object and then you may perform modifications (i.e objects =>'Customer' works,
 * 			objects =>'Customer,'Address' Will not work
 *
 */
// Extend the PHP Language to avoid use of string misspellings
include_once './constants.php';

class syDB extends morphClass {
	
	private // Alphabetize the variabls make the clean and readable
	$_action		= array(),	// This is the constructed SQL translations from the requests
	$_affected_rows	= null, 	// This is the number of rows affected by the DML request
	$_bindParams	= array(), 	// This is the array of the scalar variables to be bound to the Fetch SQL (if any)
	$_db			= null,		// This is the connection object that contains our connection handle
	$_cache			= false,	// This is the flag for whether the sql should be cached for the Paginator and re-used
	$_char_set		= 'utf8',	// This is the character set we specificed the db transactions to use
	$_conditions	= null,		// This is the "WHERE" condition that will be used for a request
	$_createTempSQL	= null,		// This is the variable for holding the actual SQL created to perform paging/scrolling result sets
	$_database		= null,		// This is the database in the db server (can be seperate from table owner)
	$_dmlStmtHandle	= null,		// This is the statement object for processing db requests
	$_DEFAULT_SC	= 'DEFAULT',// This is our default connection specifier if nothing provided
	$_fetchIter		= 0,		// This is the variable that tracks how many fetches have been completed via Fetch() function
	$_fetchStmt		= null,		// This is the variable containing the parent SQL/Fetch statement for this object
	$_fetchStmtHandle=null,		// This is the statement object for primary select/fetch statement
	$_field_count	= null,		// This is the number of fields in the result set
	$_groupBy		= null,		// This is the sql group by section
	$_hasAutoInc	= FALSE,	// This is the flag/boolean set if Object has an auto-incremented PK field
	$_having		= null,		// This is the sql having clause
	$_isMoreRows 	= false,	// This is the boolean that tracks if more rows are to be fetched
	$_isParameterized = false,	// This is the flag indicating if the SQL contains bound parameters
	$_isRawQuery	= false,	// This is the flag indicating if the sql is to be directly executed treated as "Raw"
	$_join			= null,		// This is the variable that contains join conditions
	$_joinType		= null,  	// This is the variable that contains join type
	$_keys 			= null,		// This is the container for the current primary key for a single object request
	$_lastCount     = 0, 		// This is the variable for the last execution count
	$_limit			= 1,		// This is the limiter for how many rows to populate the object with
	$_message		= array(),	// This is the array for holding status messages, especially when save/update/insert actions
	$_num_rows		= null,		// This is the number of rows in the result set
	$_object		= null,		// This is the object that we are retrieving [one or many]
	$_orderBy		= null,		// This is the ORDER BY clause for any data request
	$_paginate		= false,	// This is the flag which determines in the ORM should populate the Paginator class for display
	$_param_count	= null, 	// This is the number of parameters bound for that sql
	$_preExecute	= true, 	// This is the boolean for tracking if execution has begun for current query
	$_readOnly		= FALSE,	// This is the flag for setting the transaction as ReadOnly
	$_resultHandle  = null,		// This is the result handle after execute for the private member exec() function
	$_rowPosition	= 0,		// This is the integer that keeps track of the fetched row
	$_rowCountSQL 	= null,		// This is the variable for holding any special request of sql to use as the ROW Total value for the paginator
	$_schema		= null,		// This is the current "table owner" in sybase we will access
	$_select		= null, 	// This is the variable for allowing user to override generated select and specify columns, functions, etc.
	$_setClause		= array(),	// This is the array of the key/value pairs for any direct update requests via the Update method
	$_spaceToNull	= FALSE,	// This is the flag for treating spaces as nulls in a "condition"/WHERE/Update clause
	$_tempTable		= null,		// For all selects we create a local temp table for paging the data (i.e like mysql LIMIT)
	$_tempSQL		= null,		// This is the variable for the SQL used to fetch from the #TEMP table
	$_top			= null,		// This is the variable that holds a user defined TOP value if passed
	$_whereClause	= null,		// Internal WHERE clause conditions derived from user inputs
	$_isLoaded		= false;	// Has the data been pulled and loaded from the DB
	
	static private $_tempTabCntr	= 1; // This is the counter variable used to give a unique identifier to the temptable names
	static private $_AtoZ			= 65;// Internal ascii range selector for 65-90 or A-Z
	static private $_ORMID			= 1; // Incrementor for the ORM instantiations for creating uniq identifier
	static private $_FETCH_LIMIT	= 100000; // A saftey net. Sets the maximum inner iterations per fetch request, anti-infinite looper
	
	// Metrix tracking
	public
	$trackerId		= null, // The pk value for the tracker table log
	$traceMetrix	= true, // are metrix to be trace for a given transaction
	$metrixName		= null, // optional name for metrix being recorded
	$queryName		= null, // Optional name for the query executed, internal db stuff.
	$ElapsedTime 	= 0, // Used to report total execution time in seconds with micro precision
	$StartMicroTime = 0, // The initial start time in micro seconds
	$EndMicroTime 	= 0, // The end time of execution in micro seconds
	$StartTimeStamp = false, // The start time of the process request
	$EndTimeStamp	= false; // Then end time fo the process request
	
	/**
	 *
	 * @param array $params
	 * 	params["connection"]	[Optional]
	 *
	 * @example
	 *
	 * 		SELECT ALL
	 * 			'select-all' - no parameters, which fetch all records when no conditions are present
	 *
	 * 		rows
	 * 			Specifies the number of rows to return, default is 1
	 *
	 * 		paginate
	 * 			Tells the ORM to update the Paginator class and use its fetch parameters, such as rows per page. It also updates
	 * 			synchronizes the ORM results with the Paginator class.
	 *
	 * 		ORDER BY
	 * 			orderBy => array('column-name'=>'sort-order', [...] )
	 * 					- Each element pair of the array is a column name and sort order (.i.e. ASC, DESC)
	 * 			OR scalar
	 * 			'order-by' => 'col1, col2, col3, col4 ASC [...]'
	 *
	 * 		TOP
	 * 			'top' => 1
	 * 					- 'top' Internally becomes 'SELECT TOP n' where n is the integer value of top defined by the user parameter
	 * 						Note: This will limit the result set to the TOP value, if you want to limit rows fetched use ROWS parameter instead.
	 *
	 *
	 *   	CONDITIONS/WHERE Clause
	 *
	 *   	Dynamic Array values
	 *   		conditions => array(						// no need for the '?' bind operator, the ORM will dynamicall bind values
	 *   					'colName' => $bindValue,       // this will be implicity parameratized as a bind value
	 *   					'colName2' => 'literalBindTxt' // this also will be implicity parameratized as a bind value
	 *   					[...])
	 *
	 *   	literal qualifier
	 *   		conditions => '[WHERE] col1 = "value1" AND col2 = "value2" [etc...]'
	 *
	 *   	literal qualifier with bind values, must specify 'bind-values' param
	 *   		conditions => '[WHERE] col1 = ? AND col2 = ? [etc...]',
	 *   		bindValues => array($bindVar1, 'bindvar2', [...])
	 *
	 * @throws Exception
	 */
	public function __construct($params = array()) {
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		self::$_ORMID++;
		
		// -----------------------------------------------
		// Establish Connection and Merge Parameters
		// -----------------------------------------------
		$this->_processConnection($params);
		
		// ---------------------------------------------------------------------------
		// Perform all initialization in a private method that can be used elsewhere
		// ---------------------------------------------------------------------------
		$this->_initParams($params);
		
		// ----------------------------------------------------------------------------------
		// If we have an object target and conditions of selection then execute
		// OR if we recieved a SQL override, using only the functionality of the object
		// We want only execute when executable conditions exists
		// ----------------------------------------------------------------------------------
		if ((isset($this->_conditions) && isset($this->_object))
				|| (isset($this->_conditions) && isset($this->_object))
				|| ((isset($this->_select) && isset($this->_object)) || isset($this->_select))
				|| isset($this->_fetchStmt)
				|| isset($this->_join)
				|| (isset($params[0]) && $params[0]=="select-all" && isset($this->_object))){
					// Fetch the object if criteria provided
					$this->_Fetch();
		}
		
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
		return;
	}
	// DESTRUCTOR
	/**
	 * Close the database connection
	 */
	function __destruct() {
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		// Save the current data in memory ONLY IF CHANGED
		if (is_resource($this->_db))// (isset($this->_db) && is_resource($this->_db))
			odbc_close($this->_db);
			Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
			
	}
	
	/**
	 * @abstract Override the Add() function of the Parent Class and add additional processing
	 */
	public function &Add() {
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		// Call the parent to increment the internal counter
		// and set the state indicator, for update or insert actions
		parent::Add();
		
		// if this is not a composite key and the value is set already then unset it
		// The point here is to add the auto-incremented column with a default value to be incremented
		// so hence, if it is an array we don't want to preset those values
		if (!is_array($this->_keys) && isset($this->_data[$this->_position]->{$this->_keys})) {
			// we will set to a null which will be translated to a NULL in the DB
			$this->__set($this->_data[$this->_position]->{$this->_keys}, null);
		}
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
		
		return $this;
	}
	/**
	 * (non-PHPdoc)
	 * @see morphClass::Clear()
	 *
	 * Clear ORM specific data structures as well as call parent to do the same
	 * This is to create a new object scenario. To preserve SQL and internal
	 * data structures @see ORM::Reset()
	 */
	public function Clear(){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		// Clear the framework variables
		parent::Clear();
		
		// clear the connection datas tructures
		$this->_conditions = array();
		$this->_createTempSQL = null;
		$this->_dmlStmtHandle = null;
		$this->_fetchStmt = null;
		$this->_fetchStmtHandle = null;
		$this->_field_count = 0;
		$this->_hasAutoInc = false;
		$this->_join = array();
		$this->_joinType = array();
		$this->_keys = null;
		$this->_object = null;
		$this->_param_count = 0;
		$this->_readOnly = false;
		$this->_resultHandle = null;
		$this->_rowPosition = 0;
		$this->_setClause = null;
		$this->_tempTable = null;
		$this->_tempSQL = null;
		
		// Clear this objects specific variables to statements
		$this->Reset();
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
		
	}
	/**
	 * @Note: Allow for spaces to be Converted to the DB NULL datatype
	 * This is the default behaviour, but can be set to FALSE to save the space
	 */
	public function ConvertSpaceToNull($bool=true) {
		$this->_spaceToNull = $bool;
	}
	/**
	 * Return the number of items currently held in the object
	 * @return number
	 */
	public function Count() {
		return count($this->_data);
	}
	
	/**
	 * Execute an ad-hoc sql request
	 * @param string $sqlCommand SQL/DML statement
	 * @param array $params
	 * @throws Exception
	 * @return Ambigous <NULL, boolean, resource>
	 */
	public function exec($sqlCommand, $params=array()){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		// Bind all variables for execution
		if (!empty($params["bind-variables"])){
			// prepare the statement
			$this->_resultHandle    = odbc_prepare($this->_db, $sqlCommand);
			// Execute the statement
			$status = odbc_execute($this->_resultHandle, $params["bind-variables"]);
			
			// Verify that the execute was successful
			if (!$status){
				$this->_message[$params['position']]= odbc_errormsg();
				throw new Exception('ERROR: '.
						__FILE__.":".
						__CLASS__.":".
						__FUNCTION__.":".
						__LINE__.
						" - Action requested failed: ".
						$sqlCommand.
						" ".$this->_message[$params['position']]);
			}
			else {
				$this->_message[$params['position']] = "Command successful";
			}
			
		}
		else {
			$this->_resultHandle = odbc_execute($this->_db, $sqlCommand);
			// @TODO ERROR HANDLING HERE~!!!
		}
		$this->_affected_rows = odbc_num_rows($this->_resultHandle);
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
		
		return $this->_resultHandle;
	}
	/**
	 * (non-PHPdoc)
	 * @see morphClass::Delete()
	 */
	public function Delete($params=array()){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		// Allow for deletion without a selection first
		if (!empty($params)){
			$this->_initParams($params);
		}
		
		$this->_Delete($params);
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
		
		return $this->_affected_rows;
	}
	
	/**
	 * Allow fetch iteration until all rows fetched
	 * @return
	 * 		TRUE if more rows to fetch
	 * 		FALSE if all rows fetched
	 */
	public function Fetch($params= array()){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		// if we have a new query or first time query processes it
		if (!empty($params) && $this->_fetchIter==0){
			$this->_initParams($params);
		}
		else if (is_array($this->_fetchStmt) && !$this->_preExecute && count($this->_data)){
			// The last query must be the final data set, and is used for iterations
			$lastQuery = $this->_fetchStmt[count($this->_fetchStmt)-1];
			$this->_fetchStmt = $lastQuery[query];
		}
		// Get the data from fetch
		$this->_Fetch();
		
		$this->_fetchIter++;
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
		if ($this->_fetchIter > 1000000){
			throw new Exception("ORM Error: Excessive iteration fetch exceeds defined threshold.");
		}
		return $this->_isMoreRows;
	}
	/**
	 * (non-PHPdoc)
	 * @see morphClass3::Get()
	 */
	public 	function Get($params = array()){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		// Handles all variable assignments internally
		if (!empty($params)){
			$this->_initParams($params);
		}
		// Executes all parameters up to this point
		$this->_Fetch();
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
		
		return $this;
	}
	/**
	 * Get the number of Parameters bound for this SQL transaction
	 * @return Integer
	 */
	public function getAffectedRows(){
		return $this->_affected_rows;
	}
	
	/**
	 * The the internal data structure of the currently held data
	 * @return array of stdClass:
	 */
	public function getData(){
		// If only one element in the array, then pass only root elements of the child
		if (count($this->_data)==1)
			return $this->_data[0];
			else
				return $this->_data;
	}
	
	/**
	 * Get the number of Parameters bound for this SQL transaction
	 * @return Integer
	 */
	public function getFieldCount(){
		return $this->_field_count;
	}
	/**
	 * Returns an array of one or message of the last execution status
	 * @return multitype:
	 */
	public function getMessage(){
		return @$this->_message[$this->_position];
	}
	public function getMetaData(){
		return $this->_metaData;
	}
	/**
	 * Get the number of Parameters bound for this SQL transaction
	 * @return Integer
	 */
	public function getNumRows(){
		if (count($this->_data)==0){
			return 0;
		} else {
			return $this->_num_rows;
		}
	}
	/**
	 * Get the number of Parameters bound for this SQL transaction
	 * @return Integer
	 */
	public function getParamCount(){
		return $this->_param_count;
	}
	/**
	 * Returns the position of the current data element contained with the object.
	 * Data elements can either be fetched with the 'rows' parameter or be
	 * added by the Add() member function.
	 *
	 * See setPosition for setting the position explicitly.
	 *
	 * @return number
	 */
	public function getPosition(){
		return $this->_position+1;
	}
	/**
	 * returns array of the auto-generated SQL internally
	 * @return multitype:
	 */
	public function getSQL($param=""){
		// read the internal variable
		if ($param=="sql"){
			return $this->_fetchStmt;
		}
		else if ($param=="TempCreate"){
			return $this->_createTempSQL;
		}
		else if ($param=="tempSQL"){
			return $this->_tempSQL;
		}
		else if ($param=="dml"){
			return $this->_action;
		}
		else if ($param=="all")
			return array(
					$this->_fetchStmt, // Current user request selection Criteria
					$this->_action, // last DML action
					$this->_createTempSQL, // The sql for the creation of the temporary table for paging if needed
					$this->_tempSQL, // The sql used in retrieving the temporary sql for paging
			);
			else {
				if (is_array($this->_fetchStmt))
					return (print_r($this->_fetchStmt,true));
					else
						return $this->_fetchStmt;
			}
	}
	/**
	 * Return boolean if there is a result set present
	 * @return boolean
	 */
	public function hasResult() {
		
		return (($this->_num_rows)>=1)?true:false;
	}
	/**
	 * Remove a particular element/row/record from the memory stack
	 * for the given index value
	 * @param integer $index of the data element to remove
	 */
	public function removeItem($index){
		// Keep them honest, make sure that it is an integer above zero
		$ix = intval($index);
		if ($ix<0){
			throw Exception("ERROR: ".__CLASS__.":".__FUNCTION__." - Illegal index value.");
		}
		
		// ----------------------
		// remove that element
		// ----------------------
		unset($this->_data[$ix]);
		
		return;
	}
	/**
	 * (non-PHPdoc)
	 * @see morphClass::Reset()
	 */
	public function Reset(){
		parent::Reset();
		
		// Clear variables specific to fetchs and actions (insert/update/delete)
		$this->_action = array();
		$this->_affected_rows = 0;
		$this->_bindParams = array();
		$this->_conditions = null;
		$this->_dirtyBuffer = null;
		$this->_fetchIter = 0;
		$this->_field_count = 0;
		$this->_isMoreRows = false;
		$this->_isParameterized = false;
		$this->_join = array();
		$this->_joinType = array();
		$this->_limit = 1;
		$this->_message = array();
		$this->_num_rows = 0;
		$this->_preExecute = true;
		$this->_rowPosition = 0;
		$this->_setClause = array();
		
	}
	/**
	 * Index starting at 1, NOT zero offset
	 * This only applies with you use specify init params "rows" > 1
	 * It will set the position of the data that has been fetched
	 * the specified position in the argument.
	 *
	 * So for example if you have fetched 30 rows ('rows' => 30)
	 * then you can position at row 15 by calling this function:
	 *
	 * $row = $ORM->setPosition(15);
	 * echo $row;
	 *
	 * this will out put "15", the position you are now located
	 *
	 * If the position is greater than the number of elements, it
	 * will place the position at the last element. So if you set to
	 * 15 and there are only 7 elements, then it will return 7 and
	 * position at number 7 (the last data element)
	 *
	 * @param integer $position
	 * @return number
	 */
	public function setPosition($position){
		$cnt = $this->Count();
		// account for zero offset
		if (($position+0)>=$cnt){
			$this->_position = $cnt-1;
		}
		else
			$this->_position = $position-1;
			
			return $this->_position+1;
	}
	/**
	 * (non-PHPdoc)
	 * @see morphClass3::Save()
	 */
	public	function Save(){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		// ----------------------------------------------------------------
		// Data mutations are only permitted for single object operations
		// ----------------------------------------------------------------
		if ($this->_readOnly){
			error_log("Notice: Invalid request to edit READ ONLY object ".__CLASS__.":".__FUNCTION__.":".print_r($this->_object,true));
			throw new Exception('ERROR: '.__CLASS__.":".__METHOD__.': Save requested for read only operation.'. print_r($this->_object,true));
		}
		
		// ------------------------------------------------------------------------
		// Let's get the object PK if we don't already have it, we will use this
		// for both the update operation and the assignment of the auto-incr key
		// ------------------------------------------------------------------------
		$keyName = $this->_getTableKeys($this->_object);
		
		// --------------------------------------------------
		// Go through each record contained with the object
		// --------------------------------------------------
		foreach ($this->_data as $position => $object){
			
			// Initialize the variables used
			$valueArray=array();
			$delimiter ="";
			$whereDelim="";
			$whereTxtBlock="";
			$whereValueArray = array(); // we need to use this for parameter binding of the qualifiers
			
			// Check if this object is valid, if so don't process and make a mess
			if (empty($object))
				continue; // pass this empty one
				
			// Type Cast the object since foreach cannot handle strange objects
			// Let's cast the object into an array for the use of the foreach iterator
			$arrObject =(array)$object;
	
			// ------------------------------------------------------------
			// Handle all UPDATEs here
			// first examine if this is a an existing record
			// ------------------------------------------------------------
			if ($this->_modIndicator[$position] == self::UPDATE_STATE){
				
				// Before we do anything, since know this is an Update
				// Let's check if anything changed if not, then do nothing and move to the next record
				if (!isset($this->_dirtyBuffer[$position])){
					// This record had nothing changed so we can skip it
					// No point in looking at the columns, so let's break out of the loop
					continue; // we move to the next record if there is one
				}
				
				// perform update operation since we have some changed values
				if (!empty($this->_schema))
					$this->_action[$position] ="UPDATE ".$this->_schema.".".$this->_object." SET ";
				else
					$this->_action[$position] ="UPDATE ".$this->_object." SET ";
				
				// -------------------------------------------------------------------
				// For update we are only concerned with two tasks
				// #1 What has changed (if nothing, do nothing)
				// #2 Do we have PK's or do we need all columns to qualify this request
				// -------------------------------------------------------------------
				// ------------------------------------------------------------------------------
				// If we have primary keys then we don't need to qaulify with non-unique values
				// So check if we have them, otherwise we use the loop below to get values
				// ------------------------------------------------------------------------------
				// Now, IF we know the primary key we don't need to qualify
				// with the use of any other columns, the key is the most optimized method
				// ------------------------------------------------------------------------------
				if (!empty($keyName)){
					
					// Set a where block of text
					$whereTxtBlock =" WHERE ";
					
					// Assign scalar value for the single PK value
					if (!is_array($keyName)){
						// Single Column Primary Key
						// check the dirty buffer for the original, if changed
						if (isset($this->_dirtyBuffer[$position]->{$keyName})) {
							$this->_setColValues(
									$keyName,
									$this->_dirtyBuffer[$position]->{$keyName},
									$whereValueArray,
									$whereTxtBlock);
						}
						else // value hasn't changed
							$this->_setColValues($keyName, $arrObject[$keyName], $whereValueArray, $whereTxtBlock);
					}
					// IF array then we have a concatenated/composite primary key
					else if (is_array($keyName)){
						foreach ($keyName as $PKName){
							if (isset($this->_dirtyBuffer[$position]->{$PKName})) {
								$this->_setColValues(
										$PKName,
										$this->_dirtyBuffer[$position]->{$PKName},
										$whereValueArray,
										$whereTxtBlock);
							}
							else // value hasn't changed
								$this->_setColValues($PKName, $arrObject[$PKName], $whereValueArray, $whereTxtBlock);
								
								$whereDelim=" AND ";
						}
						// Clear the delimiter since the WHERE delimiter is an AND and the SET clause delimiter is ","
						$whereDelim="";
					}
					else // we throw a fit, because the key value(s) are set but they are invalid, likely something else is rotten
						throw new ErrorException("Notice: Update Record request with invalid PK definitions ".
								__CLASS__.":".__FILE__.":".__FUNCTION__.
								" for object `".$this->_object."`");
								
				}
				// ----------------------------------------------------------------------------
				// We don't have keys, so let's use all the information given in the original
				// and limit the change to the unique row, otherwise limit to 1 update to
				// minimize damage for a maverick programmer, who may shoot his own foot
				// ----------------------------------------------------------------------------
				if (empty($whereTxtBlock))
					$getWhere = true;
				else
					$getWhere = false;
				// --------------------------------------------------------------------------
				// So now we know that something has changed but let's only get the changes
				// and update only the modified columns
				// --------------------------------------------------------------------------
				foreach($arrObject as $column => $value){
					// -------------------------------------------------------------------
					// Now we check if there is anything that has changed
					// For this we use our dirtyBuffer So, let's see if this has an entry
					// -------------------------------------------------------------------
					if ( property_exists($this->_dirtyBuffer[$position], $column)) {
						// use the dirty buffer for the SET clause
						// but we must now apply all the rules, since we need it for both
						// inserts and updates, we call a common function private to this class
						// it will take references and update accordingly
						// $valueArray[] = $this->_dirtyBuffer[$position]->{$column};
						$this->_action[$position] .=$delimiter;
						$this->_setColValues($column, $value, $valueArray, $this->_action[$position]);
						$delimiter = ", ";
					}
					// --------------------------------------------------------------
					// get every value for a qualifier since no keys were discerned
					// Hopefully this is a rare occasion, since key access is fast
					// --------------------------------------------------------------
					if ($getWhere) {
						$whereTxtBlock .=$whereDelim;
						$this->_setColValues($column, $value, $whereValueArray, $whereTxtBlock);
						$whereDelim =" AND ";
					}
					
				}
				// Clear for the next round, if any
				$delimiter="";
				$whereDelim="";
				
				// If we have no modified key/value pairs in the object, but it says the dirty bit is set
				// then we have some kinda corrupt/inconsistent state, and likely something has broken
				// so we will throw another fit here! Should have at least one of the two bind values
				if (empty($valueArray) && empty($whereValueArray)) {
					throw new ErrorException("Notice: Update Record request with inconsitent state ".
							__CLASS__.":".__FILE__.":".__FUNCTION__.
							" for object `".$this->_object."`");
				}
				
				// Combine the where condition since it is now complete to the SQL action for this element
				$this->_action[$position] .= $whereTxtBlock;
				
				// Combine the arrays done in parallel to now be serial order for binding
				$valueArray = array_merge($valueArray,$whereValueArray);
					
			}
			// -------------------------------------------------------------
			// Handle all INSERTs here
			// if new then we do and insert only
			// -------------------------------------------------------------
			else if ($this->_modIndicator[$position] == self::INSERT_STATE){
				// Before we do anything, since we know that this is an Insert
				// Let's see if they added any key/value name pairs to same
				if (empty($arrObject)){
					// however we want to not silently move on, let's tell on them for doing bad stuff in the error log
					error_log("Notice: Add Record request for empty object in ".
							__CLASS__.":".__FILE__.":".__FUNCTION__.
							" for object `".$this->_object."`");
							
							// we move to the next record, with a clear conscience that we told on them!
							continue;
				}
				// perform insert operation
				if (!empty($this->_schema))
					$this->_action[$position] ="INSERT INTO ".$this->_schema.".".$this->_object." (";
				else
					$this->_action[$position] ="INSERT INTO ".$this->_object." (";
					
				$valuePlaceHolder = " (";
				
				
				// Create bind variables for all variables since this is new stuff, ignore dirty buffer
				$insertClause=" VALUES (";
				foreach ($arrObject as $column => $value){
					
					$this->_action[$position] .=$delimiter."`".$column."`";
					if (is_null($value)){
						$insertClause .=$delimiter."null";
					}
					else if (is_array($value) && isset($value[raw])){
						$insertClause .=$delimiter.$value[raw];
					}
					else {
						$valueArray[] = $value;
						$insertClause .=$delimiter."?";
					}
					$delimiter=",";
				}
				$insertClause.=")";
				$this->_action[$position] .=")".$insertClause;
			}
			else {
				// -----------------------------------------------------------------------
				// otherwise there is a logical error somewhere and we want to fail
				// so that the issue can be trapped and fixed
				// This type of defensive programming prevents hidden bugs in the future
				// -----------------------------------------------------------------------
				throw new Exception(__FILE__.':'.__CLASS__.':'.__FUNCTION__.": ERROR - Record modification indicator unknown.");
			}
			// ---------------------------------------------------------------------------------------------------------
			// I have seen that the message of prior executions failures appear here for direct odbc calls done prior
			// therefore to avoid failure here for some other reckless odbc call which has not clear the stack
			// I must clear the stack for them to prevent this execution from failing
			// ---------------------------------------------------------------------------------------------------------
			$codeDump = @odbc_error();
			$msgDump = @odbc_errormsg(); // report these errors even though they maybe artifacts
			//if (!empty($codeDump) || !empty($msgDump)) error_log("ORM Buffer Clear Error: [".$codeDump."] ".$msgDump);
			// ------------------------
			// prepare the statement
			// ------------------------
			$this->_dmlStmtHandle    = odbc_prepare($this->_db, $this->_action[$position]);
			// ------------------------
			// Execute the statement
			// ------------------------
			$status = odbc_execute($this->_dmlStmtHandle, $valueArray);
			
			// Verify that the execute was successful
			if (!$status){
				$errCode = odbc_error();
				if ($errCode!="01000"){ // ignore Non-deterministic warnings
					// Cannot use FreeTDS driver on unix because it does not suppor the function for binding
					// https://bugs.php.net/bug.php?id=54343
					$this->_message[$this->_position]= odbc_errormsg();
					if (!empty($errCode) && isset($GLOBALS["debug"])){
						error_log("ORM-ERROR: SQL [".$this->_action[$position]."]");
						error_log("ORM-ERROR: BINDVARS ".print_r($valueArray,true));
						error_log("ORM-ERROR: ODBC ".odbc_errormsg());
					}
					throw new Exception('ERROR: '.
							__FILE__.":".
							__CLASS__.":".
							__FUNCTION__.":".
							__LINE__.
							" - Fetch bind failed: ".
							$this->_action[$position].
							" ".$this->_message[$this->_position]);
				}
			}
			$this->_affected_rows = @odbc_num_rows($this->_dmlStmtHandle);
			// If inserting auto-populate the auto-incrementor
			if ($this->_modIndicator[$position] == self::INSERT_STATE){
				// determine if the key was not an identity column and already populated
				if (isset($keyName) && !is_array($keyName) && !isset($this->_data[$position]->{$keyName})) {
					// an identity column pk will not be an array normally
					// and if the pk name found is empty, more likely a identify column
					$rH = odbc_exec($this->_db, "select @@identity");
					if ($rH){
						$this->_data[$position]->{$keyName} = odbc_result($rH, 1);
						odbc_free_result($rH);
					}
				}
			}
			// set a nice message that the record was saved
			$this->_message[$position][] = "Record has been saved";
			
			// Now reset the buffer indicators to reflect the appropriate state of this record
			// 1) Clear the dirty buffer
			unset($this->_dirtyBuffer[$position]);
			// 2) Insert Becomes Update, because the row exists
			if ($this->_modIndicator[$position] == self::INSERT_STATE){
				$this->_modIndicator[$position] = self::UPDATE_STATE;
			}
			// 3) Modified bit is to be reset
			$this->_modBit[$position] = false;
		}
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
		
	}
	public function Update($params=array()){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		if (!empty($params))
			$this->_initParams($params);
			Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
			
			return $this->_Update(array("position"=>$this->_position));
			
	}
	/**
	 * Used to keep a serial counter across all instantiations of a class
	 * for the generation of temporary tables
	 * @return number
	 */
	static public function tmpCounter(){
		if (self::$_AtoZ >90)
			self::$_AtoZ = 65;
			if (self::$_ORMID==3)
				self::$_ORMID++;
				
				$prefix =self::$_ORMID.chr(intval(self::$_AtoZ++));
				$prefix .= ++self::$_tempTabCntr;
				return $prefix;
	}
	
	/**
	 * If the clause provided does not have the optional target parameter (default WHERE if empty)
	 * then add it to the clause and return the clause
	 * @param unknown $clause
	 * @param string $target
	 * @return string
	 */
	private function _addText($clause, $target="WHERE"){
		$check =  explode(" ", strtoupper(trim($clause)));
		if (trim($check[0]) !=$target)
			$clause = " ".$target." ".$clause;
			
			// return the string with the target string
			return $clause;
	}
	
	/**
	 * Read the WHERE CONDITIONS provided and create the appropriate criteria for the
	 * object WHERE CLAUSE request
	 * @param mixed array $params
	 * 	$params['position'] - Which command to execute within the $this->_action array
	 */
	private function _buildConditions($params=array()){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		if (!is_int($params["position"]))
			throw new Exception("INTERNAL ERROR: Attempt to execute unspecified action position");
			
			$concat="";
			// -----------------------------------
			// BUILD THE CONDITIONAL QUALIFIERS
			// -----------------------------------
			if (isset($this->_conditions) && isset($this->_object)) {
				
				// ---------------------------------------------------------------------------------------------
				// HANDLE USER DEFINED WHERE/CONDITIONS CLAUSE
				// Allow for the caller to use SQL WHERE syntax if they want to
				// check that the _conditions is an array of conditions, if not it is a where condition string
				// ----------------------------------------------------------------------------------------------
				if (!is_array($this->_conditions)){
					// We will not touch a user defined query, although this is a risky thing, if
					// they are pulling conditions from QUERY PARAMETERS (NAMELY THE URI/URL)
					// Add the WHERE clause, and place "WHERE" there if not already
					$this->_whereClause[$params["position"]] = " ".$this->_addText($this->_conditions,"WHERE")." "; // User defined WHERE condition that is scalar
					
					// Check for bind parameters, and set indicators
					if (!isset($this->_bindParams) || empty($this->_bindParams))
						$this->_isParameterized = false;
						elseif (count($this->_bindParams)>=1)
						$this->_isParameterized = true;
				}
				else {
					// only initialize if not already, don't clobber existing stuff
					if (!isset($this->_bindParams))
						$this->_bindParams = array();
						
						$concat .="\n\t\t";
						$this->_whereClause[$params["position"]] ="\n\tWHERE";
						foreach ($this->_conditions as $column => $value) {
							// Lets allow for operator overrides
							// also for the verb operators:
							// IS  -OR- IS NOT
							// LIKE
							$operator=" = ";// set the default with each iteration
							if (is_array($value)){
								// If there is a custom operator then use it instead of defualt "="
								// We will only iterate once, but the advantage is we get the "key" which is the operator
								foreach ($value as $opr => $val) {
									$operator = " ".trim(strtoupper($opr))." "; // upper case incase it is a "IS NOT" qaulifier
									$value = $val; // now we have removed the array while extracting the operator/qualifier
								}
							}
							// ------------------------------------------------------------------------------
							// CHECK FOR NATURAL COLUMN JOINS HERE
							// Let's allow for the preface of NATURAL JOIN COLUMNS by the following syntax:
							// table1.columName = table2.anotherColumnName
							// so let's check for the "." qualifier in the value, if not we know its literal
							// but only applies when the _object is an array (has more than one table/object)
							// ------------------------------------------------------------------------------
							if (is_array($this->_object)) {
								$chunk= null;
								if (stristr($value,".")) {
									$chunk=explode(".",$value);
								}
								// then check if the item is one of the objects if so then create literal
								if (is_array($chunk) && in_array($chunk[0], $this->_object)){
									// then we treate as a colmn qualifier join - literal non parameterized
									$this->_whereClause[$params["position"]] .= $concat.$column.$operator.$value." ";
								}
							}
							else { // nope, so check operator here
								// CHECK FOR NULL QUALIFIERS
								if ($operator==" IS " || $operator==" IS NOT "){
									// DON'T Parametize the "IS [NOT]" conditional qualifiers
									$this->_whereClause[$params["position"]] .= $concat.$column.$operator." NULL ";
								}
								else {
									// if no match then treat as a value, and parametertize
									$this->_whereClause[$params["position"]] .=$concat.$column.$operator."? ";
									$this->_bindParams[] = $value;
									$this->_isParameterized = true;
								}
							}
							$concat ="\n\t\tAND ";
						}
				}
			}
			Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
			
	}
	
	/**
	 * Build the SQL/Fetch to be executed
	 * @param unknown $sql
	 */
	private function _buildSQL(&$sql){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		// --------------------------------------------------------------------
		// BUILD THE SQL STATEMENT IF NO EXISTING ALREADY
		// If we already have a fetch statement (SQL) then we use it
		// This is so that User Defined SQL can be directly passed and execute
		// instead of using the paramaters passed to build the SQL
		// --------------------------------------------------------------------
		if (empty($sql)){
			 // -----------------------------------------------------
			 // BUILD THE FROM CLAUSE
			 // Get object list for the first part of the selection
			 // -----------------------------------------------------
			 if (isset($this->_object) && !empty($this->_object)){
			 	
			 	if (isset($this->_select)){
			 		// ------------------------------------
			 		// USER DEFINED SELECTS ARE READ ONLY
			 		// --------------------------------------
			 		$this->_readOnly = true;
			 		if (empty($top))
			 			$sql = $this->_addText($this->_select,"SELECT")." FROM ";
			 			else {
			 				$pieces = explode(" ", trim($this->_select));
			 				$firstWord = trim($pieces[0]);
			 				if (empty($firstWord)) $firstWord = trim($pieces[1]);
			 				if (strtoupper($firstWord)=="SELECT"){
			 					// @todo - this does not catch all circumstances, add array iterator
			 					$select = $this->_select;
			 					$strTxt = "SELECT ".$top." ";
			 					$count =1;
			 					$select = str_replace($firstWord, $strTxt, $select, $count);
			 					
			 					$sql = $select."\n\tFROM ";
			 				}
			 				else {
			 					$sql = "SELECT $top ".$this->_select." \n\tFROM ";
			 				}
			 			}
			 	}
			 	else
			 		$sql = "SELECT $top * \n\tFROM ";
			 		// ---------------------------------------------
			 		// get object list for NATURAL JOIN CONDITION
			 		// ---------------------------------------------
			 		if (is_array($this->_object)){
			 			// Start the sql statement here
			 			$separator="";
			 			foreach ($this->_object as $table) {
			 				$sql .=$separator.$table;
			 				$separator=", ";
			 			}
			 			// If more than one object fetched then we make this a read only object
			 			$this->_readOnly=TRUE;
			 		}
			 		else {
			 			// SINGLE TABLE SELECTION
			 			// this is a scalar value, only one object which is editable
			 			if (isset($this->_schema))
			 				$prefix = $this->_schema.".";
			 				else
			 					$prefix = "";
			 					// start creating the query to by used
			 					$sql .= $prefix.$this->_object;
			 		}
			 }
			 // -----------------------------------------
			 // Examine all the JOIN clauses if present
			 // -----------------------------------------
			 if (!empty($this->_join)){
			 	if (is_array($this->_join)){
			 		// Let's iterate through the tables that are being JOIN'd and build the SQL
			 		foreach ($this->_join as $indx => $joinData) {
			 			// Lets create the join clause for the object/table
			 			foreach ($joinData as $table => $joinCols){
			 				$sql .= "\n\t".$this->_joinType[$indx]." ";
			 				$sql .= $table." ON (\n\t\t";
			 				$delim = " ";
			 				foreach ($joinCols as $col1 => $col2){
			 					$joinOperator=" = ";
			 					if (is_array($col2)){
			 						$joinOperator = $col2[0];
			 						$col2 = $col[1];
			 					}
			 					$sql .= $delim.$col1." ".$joinOperator." ".$col2;
			 					$delim = "\n\t\t AND ";
			 				}
			 				$sql .= ")";
			 			}
			 		}
			 	}
			 	else {
			 		$sql .= $this->_join;
			 	}
			 	$this->_readOnly = TRUE;
			 }
			 
			 // ---------------------
			 // Get the conditions
			 // ---------------------
			 if (isset($this->_conditions) && isset($this->_object)) {
			 	// Build the WHERE clause
			 	$this->_buildConditions(array('position'=>$this->_position));
			 	
			 	// Check if the fetch statement has been started
			 	if (empty($sql)){
			 		if (!empty($this->_schema))
			 			$sql = "SELECT $top * FROM ".$this->_schema.".".$this->_object."\n".$this->_whereClause[$this->_position]." ";
			 			else
			 				$sql = "SELECT $top * FROM ".$this->_object."\n".$this->_whereClause[$this->_position]." ";
			 	}
			 	else {
			 		// Add the WHERE clause, and place "WHERE" there if not already
			 		$sql .= $this->_whereClause[$this->_position]; // User defined WHERE condition that is scalar
			 	}
			 	
			 }
		}
		
		$this->_buildSQLClause($sql);
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
		
		return;
	}
	/**
	 * Build the sub-clauses for the SQL/Fetch request
	 * @param string $sql
	 */
	private function _buildSQLClause(&$sql){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		
		// -----------------------------------------------
		// BUILD THE CLAUSES FOR THE FETCH REQUEST
		// -----------------------------------------------
		//if ($this->_rowPosition==0 || $this->_rowPosition==1) {
		
		// -------------------------------------------------------
		// Add the GROUP BY Param provided by the user
		// -------------------------------------------------------
		if (isset($this->_groupBy)){
			$groupBy = " GROUP BY ";
			if (!empty($this->_groupBy) && is_array($this->_groupBy)){
				$delim ="";
				$cnt=0;
				foreach ($this->_groupBy as $colValue){
					$groupBy .= $delim.trim($colValue);
					$delim=",";
					$cnt++;
				}
				// -----------------------------------------------
				// If at leat one legitimate value then we use it
				// -----------------------------------------------
				if ($cnt>=1){
					if (!is_array($sql)){
						if (stristr($sql,'GROUP BY')===false){
							$sql .= $groupBy;
						}
					}
					
					// -------------------------------------
					// convert to scalar for optimization
					// -------------------------------------
					$this->_groupBy = $groupBy;
				}
			}
			else if (!empty($this->_groupBy)){
				// this is a scalar value
				if (!is_array($sql)){
					if (stristr($sql,'GROUP BY')===false){
						$sql.=" ".$this->_addText($this->_groupBy,"GROUP BY")." ";
					}
				}
			}
		}
		
		// -------------------------------------------------------
		// Add the HAVING Param provided by the user
		// -------------------------------------------------------
		if (isset($this->_having) && $this->_rowPosition==0){
			$having = " HAVING ";
			if (!empty($this->_having) && is_array($this->_having)){
				$delim ="";
				$cnt=0;
				foreach ($this->_having as $col => $qualifier){
					if (!is_array($qualifier)){
						$having .= trim($col)." ".trim($qualifier).$delim;
					}
					else {
						foreach ($qualifier as $oper => $val){
							$having .= trim($col)." ".trim($oper)." ".$val.$delim;
						}
					}
					$delim=",";
					$cnt++;
				}
				// -----------------------------------------------
				// If at leat one legitimate value that we use it
				// -----------------------------------------------
				if ($cnt>=1){
					if (!is_array($sql)){
						if (stristr($sql,'HAVING')===false){
							$sql .= $having;
						}
					}
					// -------------------------------------
					// convert to scalar for optimization
					// -------------------------------------
					$this->_having = $having;
				}
			}
			else if (!empty($this->_having)){
				// this is a scalar value
				if (!is_array($sql)){
					if (stristr($sql,'HAVING')===false){
						$sql.=" ".$this->_addText($this->_having,"HAVING")." ";
					}
				}
			}
		}
		
		// ------------------------------------------------------------
		// Add the ORDER BY Param provided by the user (once at start)
		// ------------------------------------------------------------
		if (isset($this->_orderBy) && ($this->_rowPosition==0 || $this->_rowPosition==1)){
			$orderBy = "\n ORDER BY ";
			if (!empty($this->_orderBy) && is_array($this->_orderBy)){
				$delim ="";
				$cnt=0;
				foreach ($this->_orderBy as $key => $val){
					if ($key === $cnt){
						$NamedIndex=false;
					}
					else {
						$NamedIndex=true;
					}
					if ($NamedIndex){
						$orderBy .= $delim.trim($key)." ".trim($val);
					}
					else {
						$orderBy .= $delim.trim($val);
					}
					$delim=", ";
					$cnt++;
				}
				// -----------------------------------------------
				// If at leat one legitimate value that we use it
				// -----------------------------------------------
				if ($cnt>=1 && !empty($orderBy)){
					if (!is_array($sql))
						$sql .= $orderBy;
						// -------------------------------------
						// convert to scalar for optimization
						// -------------------------------------
						$this->_orderBy = $orderBy;
				}
			}
			else if (!empty($this->_orderBy)){
				// this is a scalar value
				if (!is_array($sql)){
					// ------------------------------------------------
					// if the order by phrase is missing then add it
					// ------------------------------------------------
					if (stristr($this->_orderBy,'ORDER BY')===false){
						$this->_orderBy = $orderBy.$this->_orderBy." ";
					}
				}
				
			}
			// ------------------------------------
			// if not already present then add it
			// ------------------------------------
			if (stristr($sql,'ORDER BY')===false){
				$sql .= $this->_orderBy;
			}
			
		}
		//}
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
		
		return;
	}
	
	/**
	 * Handle all pre-execution processing for creation of Temp tables for scrolling
	 * @param unknown $params
	 */
	private function _buildTempTables(&$sql){
		try {
			Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
			
			$this->_createTempTabDDL($sql);
			
			// ------------------------------------------
			// HANDLE ROW FETCH COUNT AND POSITIONING
			// ------------------------------------------
			// if there were any conditions or object, then build an action plan
			if (!empty($sql) && !is_array($sql)) {
				
				// -----------------------------
				// PARAMETERIZED REQUESTS
				// -----------------------------
				if ($this->_isParameterized){
					// prepare the statement
					if (!($this->_fetchStmtHandle = odbc_prepare($this->_db, $this->_createTempSQL))){
						$errorMessage = odbc_errormsg();
						throw new Exception("ORM Error: ". $errorMessage." \n QUERY: \n".$this->_createTempSQL."\n Backtrace: \n".print_r(debug_backtrace(),true) );
					}
					// Execute the statement
					Utils::debugTrace(__CLASS__.":".__FUNCTION__.": Temp Table : \n".$this->_createTempSQL,start);
					
					// The @ suppression is for Non-deterministic fetches, which are permissible
					if (!($status = @odbc_execute($this->_fetchStmtHandle, $this->_bindParams))){
						
						$errCode = odbc_error();
						$errorMessage = odbc_errormsg();
						
						Utils::debugLog("BindParameters: ".print_r($this->_bindParams, true));
						Utils::debugTrace(__CLASS__.":".__FUNCTION__.": Temp Table reported problem! ".$errorMessage,end);
						
						if ($errCode=="S0001"){
							throw new Exception('ORM ERROR: Internal error 500, unrecoverable ');
						}
						// non-deterministic is not fatal, but should have an order by clause.
						else if ($errCode=="01000"){
							$status = true; // Recover from error if
							$errCode=null;
						}
						if (!empty($errCode)) {
							Utils::debugLog("ODBC Return code: ".$errCode. " - ". odbc_errormsg()." \n QUERY: \n".$this->_createTempSQL."\n Backtrace: \n".print_r(debug_backtrace(),true) );
						}
						// Verify that the error was recoverable or not
						if ((!empty($status) && $status!=true )|| $status===false){
							$this->_message[$this->_position]= odbc_errormsg();
							throw new Exception('ERROR: '.
									__FILE__.":".
									__CLASS__.":".
									__FUNCTION__.":".
									__LINE__.
									" - Fetch bind failed: ".
									$sql.
									" ".$this->_message[$this->_position]);
						}
					}
					else {
						Utils::debugLog("BindParameters: ".print_r($this->_bindParams, true));
						Utils::debugTrace(__CLASS__.":".__FUNCTION__.": Temp Table created! ",end);
					}
					// -------------------------------------------------
					// Get the number of rows total for this data-set
					// if there is not a result set count sql provided
					// -------------------------------------------------
					if (empty($this->_rowCountSQL)){
						$this->_num_rows = @odbc_num_rows($this->_fetchStmtHandle);
						if ($this->_num_rows==-1){
							$this->_num_rows=0;
							if ($this->_paginate && Paginator::getPageNumber()==1){
								Paginator::setTotalRowCount(0);
							}
						}
					}
				}
				// -----------------------------
				// NON-PARAMETERIZED REQUESTS
				// -----------------------------
				else {
					// -----------------------------------------------------------
					// Execute the query and check the status of the execution
					// -----------------------------------------------------------
					Utils::debugTrace(__CLASS__.":".__FUNCTION__.": Creating Temp Table \n".$this->_createTempSQL ,start);
					
					try {
						$this->_createTempTabDDL($sql);
					}
					catch (Exception $e){
						error_log('ORM ERROR ['.$e->getError()."]".$e->getMessage());
						$result = false;
					}
					$Attempts =1;
					// We must guarantee an unique table identifier or die
					while (self::_tempTabExists($this->_tempTable)) {
						$this->_createTempTabDDL($sql);
						$Attempts++;
						if ($Attempts>100000){
							throw new Exception("ORM ERROR: Could not gaurantee unique temp identifier");
						}
					}
					
					if (!$result = @odbc_exec($this->_db,$this->_createTempSQL)) {
						$errMsg = odbc_errormsg();
						$errNo = odbc_error($this->_db);
						// We will ignore any previously existing temptables, which are rare
						if ($errNo !="S0001"){
							$this->_message[$this->_position]= odbc_errormsg();
							error_log("ORM-ERROR: SQL [".$this->_createTempSQL."]");
							
							// When an error occurs, stop execution and then throw a fit!
							throw new Exception('ERROR: '.
									__FILE__.":".
									__CLASS__.":".
									__FUNCTION__.":".
									__LINE__."".
									$this->_message[$this->_position]);
						}
						else { // since S0001 Can be several non-critical warnings handle here
							
							if (!(stristr($errMsg,'already exists') === FALSE)){
								$this->_createTempTabDDL($sql); // generate new tab name and check until clear
								
								while (!(stristr($errMsg,'already exists') === FALSE)) {
									// generate new tab again
									$this->_createTempTabDDL($sql);
									if (!$result = @odbc_exec($this->_db,$this->_createTempSQL)) {
										$errMsg = odbc_errormsg();
									}
									else {
										break;
									}
									$Attempts++;// prevent infinite looping
									if ($Attempts>self::$_FETCH_LIMIT){
										throw new Exception("ORM ERROR: Could not gaurantee unique temp identifier");
									}
								}
							}
							else {
								error_log(odbc_errormsg());
							}
						}
					}
					else {
						// -----------------------------------
						// get the affected rows on success
						// -----------------------------------
						$this->_lastCount = odbc_num_rows($result);
						
						// Non-Deterministic Results that are empty sometime return -1 for number of results
						// or -1 for non-deterministic, so we could try the old count method
						if ($this->_lastCount==-1){
							$this->_num_rows = 0;
							$this->_lastCount = 0;
						}
						// Take the direct row number values if there isn't a query to identify it
						else if (empty($this->_rowCountSQL)){
							if ($this->_lastCount==-1){
								$this->_lastCount=0;
							}
							$this->_num_rows = $this->_lastCount;
						}
						if ((empty($this->_num_rows)||$this->_num_rows<=0) && isset($GLOBALS["debug"])){
							Utils::debugLog("EMPTY SQL RESULT FOR: ".$this->getSQL());
						}
					}
					Utils::debugTrace(__CLASS__.":".__FUNCTION__.": Creating Temp Table ",end);
				}
				// -------------------------------------------------------------------------------
				// If this is a pagination request, set the total row count to reflect the change
				// -------------------------------------------------------------------------------
				if ($this->_paginate && empty($this->_rowCountSQL)){
					if ( (intval(Paginator::getTotalRowCount()) != intval(Paginator::getCacheRowTotal()))
							|| (empty(Paginator::getTotalRowCount()) && !empty($this->_num_rows))
							|| (Paginator::getTotalRowCount() != $this->_num_rows) ) {
								Paginator::setTotalRowCount($this->_num_rows);
							}
							else if ($this->_paginate && Paginator::getPageNumber()==1 && $this->_num_rows<Paginator::getRowsPerPage()){
								Paginator::setTotalRowCount($this->_num_rows);
							}
							else if ($this->_limit=="1000000" && $this->_paginate && ($this->_num_rows < $this->_limit)){
								Paginator::setTotalRowCount($this->_num_rows);
								Paginator::setRowsPerPage($this->_num_rows);
							}
				}
				else if ($this->_paginate && Paginator::getPageNumber()==1 && $this->_num_rows<Paginator::getRowsPerPage()){
					Paginator::setTotalRowCount($this->_num_rows);
				}
				else if ($this->_num_rows==-1){
					Paginator::setTotalRowCount(0);
				}
			}
			Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
			
			return;
		}
		catch (Exception $e) {
			$msg = $e->getMessage();
			Utils::debugTrace("ERROR: Failed temp object creation: \n".$this->_createTempSQL.":\n".$msg, end);
			error_log("ORM ERROR:".__FILE__.":".__CLASS__.":".__FUNCTION__."-".print_r(debug_backtrace(),true) );
			throw new Exception(__FILE__.":".__CLASS__.":".__FUNCTION__." - Temp table creation failure. ".$msg);
		}
	}
	
	private function _createTempTabDDL($sql){
		try {
			// --------------------------------------------------------------
			// CREATE A TEMPORARY TABLE FOR 'LIMIT' FUNCTIONALITY LIKE MYSQL
			// --------------------------------------------------------------
			$this->_tempTable = "#".substr(str_replace(array("."," "), "", microtime()),0,11);
			$cntr=1;
			while (self::_tempTabExists($this->_tempTable)){
				$this->_tempTable = "#".substr(str_replace(array("."," "), "", microtime()),0,11);
				$cntr++;
				if ($cntr>500){
					$this->_tempTable = "#".substr(self::tmpCounter().str_replace(array("."," "), "", microtime()),0,11);
					if ($cntr>600){
						throw new Exception("ORM Error: Unable to create unique temp table dataset.");
					}
					
				}
			}
			
			// --------------------------------------------------------------
			// IF FIRST ROUND CREATE THE TEMP RECORD SET FOR ITERATION
			// --------------------------------------------------------------
			// verify that the orderby statement is normalized, if perchance something failed
			if (!is_array($this->_orderBy) && !empty($this->_orderBy) && stristr($this->_orderBy,"ORDER BY")===false){
				$this->_orderBy = " ORDER BY ".$this->_orderBy;
			}
			
			// ------------------------------------------------------------
			// SET THE TOP CLAUSE IF ANY FOR THE TEMP TABLE TO BE CREATED
			// AND set the TOP clause for the USER DEFINED SQL
			// ------------------------------------------------------------
			if ($this->_paginate){
				if (Paginator::isIndeterminate() || !empty($this->_rowCountSQL)){
					//$TOP_Clause = " TOP ".intval(Paginator::getTopValue());
					$TOP_Clause = " TOP ".intval(Paginator::IndeterminateMinFetch);// THE MIN FOR THE INDETERMINATE
					$this->_setTopValue($sql, intval(Paginator::getTopValue()));
				}
				else {
					$TOP_Clause ="";
				} // assign nada :), to suppress warnings for unitialized variables
			}
			else if (isset($this->_top) && !empty($this->_top)){
				$TOP_Clause = " TOP ".intval($this->_top);
				$this->_setTopValue($sql, intval($this->_top));
			}
			else
				$TOP_Clause="";
				
			$this->_createTempSQL = "
			SELECT $TOP_Clause IDENTITY(10) AS RowSeq, * INTO ".$this->_tempTable."
			FROM (
			$sql) as UserRequestedDataSet
			".$this->_orderBy;
		}
		catch (Exception $e){
			throw new Exception('ORM ERROR: Temp Table Validation Name failure');
		}
	}
	/**
	 * Function for checking the existence of a temporary table in existence
	 * @param string $tabName
	 * @return boolean
	 */
	private function _tempTabExists($tabName){
		$query ="select object_id('".$tabName."') AS TmpTable";
		// create uniq name and check if exists
		if($result = odbc_exec($this->_db, $query)){
			$obj = odbc_fetch_object($result);
			if (!empty($obj->TmpTable))
				return true;
			else
				return false;
		}
		else
			return false;
	}
	/**
	 * @param If not parameters passed it will delete the current element in which it is viewing
	 * 		If conditions are passed it will delete based upon those conditions
	 */
	private function _Delete($params=array()){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		// object can be either scalar or array, but can only be one object with the container
		if (is_array($this->_object)){
			$table = $this->_object[0];
		}
		else {
			$table = $this->_object;
		}
		
		// ---------------------------------------------
		// HANDLE ANY SPECIAL DELETE OVERRIDES HERE
		// ---------------------------------------------
		if (!empty($params)){
			// Start action statement
			$this->_action[$this->_position] ="DELETE FROM ".$table." WHERE \n";
			$delim = "";
			$bindValues = array();
			$whereClause="";
			
			// Handle OOP style Where CONDITIONS
			if (!empty($params["conditions"]) && is_array($params['conditions'])){
				foreach ($params['conditions'] as $col => $value){
					
					if (!is_array($value)){
						if (is_null($value)){
							$whereClause.=$delim.$col." IS NULL ";
						}
						else {
							$whereClause .=$delim.$col." = ? ";
							$bindValues[] = $value;
						}
					}
					else {
						foreach ($value as $operator => $val2){
							$operator = trim(strtoupper($operator));
							if ($operator=="IS"||$operator=="IS NOT")
								$whereClause .=$delim.$col." ".$operator." NULL ";
							else {
								$whereClause .=$delim.$col." ".$operator." ? ";
								$bindValues[] = $val2;
							}
						}
						
					}
					$delim = "\n AND ";
				}
			}// end the conditions for the array
			else {
				// If they have their WHERE clause written in SQL then we hope they did it right and use it
				$whereClause = $this->_addText($params['conditions'],"WHERE");
			}
			//error_log("DELETE WHERE ".$whereClause);
		}
		else { // if no param overrides, then delete the current element which we are viewing
			// All read-only trans, and joins are prohibited from CRUD operations
			if ($this->_readOnly || count($this->_object)>1 ){
				throw new Exception('ERROR: Transaction is defined as read only and CRUD operations not permitted. '.
						print_r($this->_data[$this->_position],true));
			}
			
			// Start action statement
			$this->_action[$this->_position] ="DELETE FROM ".$table;
			
			//check if we have the primary keys already, if not get them if available
			if (empty($this->_keys)){
				$this->_keys = $this->_getTableKeys($table);// @todo maybe if no results add schema and retry
			}
			
			// Start the where conditions
			$whereClause="";
			$bindValues = array();
			$delim="";
			
			// ------------------------------
			// BUILD THE WHERE CLAUSE HERE
			// ------------------------------
			// check for composite keys
			if (is_array($this->_keys) && !empty($this->_data[$this->_position])) {
				foreach ($this->_keys as $key){
					$whereClause=$this->_addText($whereClause);
					$whereClause .= $delim.$key." = ? ";
					$delim=" AND ";
					$bindValues[] = $this->_data[$this->_position]->{$key};
				}
			} // handle all single colum primary keys here as scalar
			else if (!empty($this->_keys) && !empty($this->_data[$this->_position]->{$this->_keys})) { // is a scalar value
				$whereClause=$this->_addText($whereClause);
				$whereClause .= $this->_keys." = ?";
				$bindValues[] = $this->_data[$this->_position]->{$this->_keys};
			}
			else {// if we don't have an identifiable PK then we use all the columns as a condition for deletion
				// get the object at this current position with all data/value pairs
				$columns = $this->_data[$this->_position];
				// convert to an array
				$columns = (array)$columns;
				
				// If this is not a pre-fetch and conditions are provided use them
				if (empty($columns) && !empty($this->_conditions)){
					$columns = $this->_conditions;
				}
				// If we have input from either the accessors or the init parameters
				// then merge them and use both, but the acessors take precedence
				else if (!empty($columns) && is_array($columns)
						&& !empty($this->_conditions) && is_array($this->_conditions)){
							// combine the two arrays
							$columns = array_merge($columns, $this->_conditions);
				}
				
				// If this has been pre-fetched then there will be column data populated
				if (!empty($columns)){
					
					// check if this is an array of conditions
					if (is_array($columns)){
						foreach ($columns as $col => $value){
							$whereClause=$this->_addText($whereClause);
							// if the value is not empty/null than assign the where condition
							if (!empty($value)){
								$whereClause.=$delim.$col." = ? ";
								$bindValues[] = $value;
							}
							else {// Handle NULL's or empty strings here
								$whereClause.=$delim."(".$col." IS NULL OR ".$col."='')";
							}
							// delim properly becomes constant after first iteration
							$delim = " AND ";
						}
					}
					else { // if we have a scalar variable with a where condition then we use that
						$whereClause = $this->_addText($this->_conditions);
					}
				}
				else { // if no way to determine this deletion then throw a fit
					throw new Exception('ERROR: No Deletion criteria provided. Cannot execute: '.
							print_r($this->_data[$this->_position],true));
				}
				//iterate through each key/pair and create the all inclusive where condition
			}
		} // end of the current element delete
		
		// check for erronious state
		if (empty($whereClause)) {
			throw new Exception('ERROR: Unqualified request for deletion of record. '.
					print_r($this->_data[$this->_position],true));
		}
		else // add the where condition to the current action
			$this->_action[$this->_position].=$whereClause;
			
		
		// Bind all variables for execution
		if (!empty($bindValues)){
			// prepare the statement
			$delHandle    = odbc_prepare($this->_db, $this->_action[$this->_position]);
			// Execute the statement
			$status = odbc_execute($delHandle, $bindValues);
			
			// Verify that the execute was successful
			if (!$status){
				$this->_message[$this->_position]= odbc_errormsg();
				throw new Exception('ERROR: '.
						__FILE__.":".
						__CLASS__.":".
						__FUNCTION__.":".
						__LINE__.
						" - Delete request failed: ".
						$this->_action[$this->_position].
						" ".$this->_message[$this->_position]);
			}
			else {
				$this->_message[$this->_position] = "Deletion successful";
				$this->_affected_rows = odbc_num_rows($delHandle);
			}
			
		}
		else { // if we don't have bind variables, someone is attempting to delete a non-existing record
			// so we are going to teach them a lesson and throw a fit!
			throw new Exception('ERROR: Indiscernable request for deletion of record. '.
					print_r($this->_data[$this->_position],true));
		}
		
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
			
	}
	
	/**
	 * Do the execution of non-raw requests
	 * @param string reference $sql
	 */
	private function _doExec(&$sql){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		// --------------------------------------------------
		// If all things are prepared
		// EXECUTE THE SELECTION AGAINST THE RECORD SET
		// _fetchIter is the indicator if we should display
		// the current buffer in an iteration or perform
		// another fetch, because one could concievably
		// instantiate the class with conditions, edit them
		// and then do " while($ORM->Fetch(){ ... }-
		// --------------------------------------------------
		if (!empty($this->_tempTable) && $this->_preExecute){
			
			// This is for the empty results sets
			if (empty($this->_fetchIter) && $this->_num_rows==0 && !$this->_preExecute){
				Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
				return;
			}
			// Make sure we are within the bounds of the records set limits
			// This addresses a situation of a select and update of row(s)
			if ( $this->_fetchIter >= $this->_num_rows){ // && $this->_fetchIter!=0){
				Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
				$this->_isMoreRows = false;
				return;
			}
			else {
				$this->_isMoreRows = true;
			}
			/* We have already bound the variables, if any, and now we simply fetch.
			 * This is the method to use for fetching the rows as objects in our internal array
			 */
			if ($this->_paginate){
				$this->_tempSQL = "SELECT TOP ".(intval(Paginator::getRowsPerPage()));
			}
			else if (!empty($this->_top)){
				$this->_tempSQL = "SELECT TOP ".(intval($this->_top));
			}
			else {
				$this->_tempSQL = "SELECT ";
			}
			$this->_tempSQL .=
			" * FROM ".$this->_tempTable.
			" WHERE RowSeq > ".($this->_rowPosition).
			" ORDER BY RowSeq ASC "; // no-zero offset, starts at 1
			
			
			Utils::debugLog("Executing fetch requirement: ".$this->_tempSQL);
			// Execute the query and check the status of the execution
			if (!($this->_fetchStmtHandle = odbc_exec($this->_db,$this->_tempSQL))) {
				$this->_message[$this->_position]= odbc_errormsg();
				Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
				// When an error occurs, stop execution and then throw a fit!
				throw new Exception('ERROR: '.
						__FILE__.":".
						__CLASS__.":".
						__FUNCTION__.":".
						__LINE__."".
						$this->_message[$this->_position]);
			}
			// Before fetch let's clear out the previous buffer
			$this->_data = array(); // the gc will remove when no refs exist
			$this->_modIndicator = array();
			
		}
		else if (isset($this->_fetchStmtHandle) && !empty($this->_tempTable)){
			// --------------------------------------------------------------------
			// Since we prefetch on contruction, we don't want to pass the first
			// record already preloaded in a while($orm->Fetch()) {...} scenario
			// --------------------------------------------------------------------
			if (!$this->_accessed && $this->_num_rows && ($this->_rowPosition==1 || $this->_rowPosition==0)){
				Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
				return;
			}
			
		}
		else {// if there is no sql, throw a fit, since it is expected at this point
			Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
			throw new Exception('ERROR: '.
					__FILE__.":".
					__CLASS__.":".
					__FUNCTION__.":".
					__LINE__.
					" - Fetch Failure: Indiscernable Fetch request.");
		}
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
		
	}
	/**
	 * Execute a "raw" sql request, a straight through pass, beware!!
	 * This is only written to support sybase specific functionality
	 * @param string reference $sql
	 *
	 * @WARNING: This is not equipped to handle bind Parameters!
	 */
	private function _doExecRaw(&$sql){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		if (empty($sql))
			return;
		
		Utils::debugLog("ORM: Executing Raw Request start:\n ".$sql);
		$result = @odbc_exec($this->_db, $sql);
		if (!$result){ // @TODO: Add end for trace
			Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]".odbc_errormsg(), end);
			//------------------------------------------------------------------
			// @TODO: Determine which are permissible and those which are not
			// !IMPORTANT! - for now throwing errors always, and seeing effect
			//------------------------------------------------------------------
			// Ignore the table not found error for DROP statements
			//if (!odbc_error()=="S0002")
			throw new Exception('ORM Error: _doRawExec failure. '.odbc_errormsg()."\n Params: \n".print_r($this->_bindParams,true));
			//return $result;
		}
		$this->_affected_rows = odbc_num_rows($result);
		
		Utils::debugLog("Affected Rows from request: ".$this->_affected_rows);
		Utils::debugLog("ORM: Executing Raw Request end");
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
		
		return $result;
	}
	
	/**
	 * Execute the fetch of the already defined sql and temp table
	 */
	private function _doFetch(){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		$indx=0;
		$rowCheck = false;
		$obj=false;
		
		// ------------------------------------------------------------
		// If we are in an iteration and we have polled all the rows
		// we check if we are on the last row and stop processing
		// ------------------------------------------------------------
		if ($this->_rowPosition == $this->_num_rows && $this->_accessed) {
			Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
			//  if we are iterating and we have passed the number of actual data elements report done
			if ($this->_accessed){
				$this->_isMoreRows = false;
				return; // here we are done, dont proceed
			}
			else if (!$this->_accessed && $this->_fetchIter > 0){ // If in Fetch() and we have not accessesed the first record then allow the iteration
				$this->_isMoreRows = true; // here we still need to fetch the first row before leaving
			}
		}
		// --------------------------------------------------
		// This while handles records within result sets
		// --------------------------------------------------
		if (empty($this->_limit)) // min limit 1
			$this->_limit=1;
			
			try {
				
				while ($indx < $this->_limit){
					// one object at a time based on limit
					if ($this->_fetchStmtHandle)
						@$obj = odbc_fetch_object($this->_fetchStmtHandle);
					else
						$obj=null;
					if (!$obj) {
						$errCode = odbc_error();
						$errStatus = odbc_errormsg();
						if (!empty($errCode) && $errCode !='01000'){ // ignore non-deterministic errors
							Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
							throw new Exception('ORM Fetch Error: ['.$errCode."]:[".$errStatus."]");
						}
						else {
							break;// end or record set, stop iterating
						}
					}
					else {
						// All Fetched objects have and update action assigned
						$this->_modIndicator[$indx]=self::UPDATE_STATE;
						$this->_data[$indx] = $obj;
						// indicator if we are here then there is possiblity of more rows
						$rowCheck = true;
					}
					$indx++;
				}
			}
			catch (Exception $e){
				Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
				throw new Exception("ORM Fetch Error: ".$e->getMessage());
			}
			// -----------------------------------------------------------------------------------------
			// Methodology for determining if more rows need to be fetched, to avoid any infinite loops
			// -----------------------------------------------------------------------------------------
			if ($obj==false || ($this->_fetchIter > $this->_num_rows)) {// if did not fall into fetch objects, then there is no more records
				$this->_isMoreRows = false;
			}
			else if ($obj) {
				$this->_isMoreRows = true; // not guaranteed, but next fetch will resolve all doubts
			}
			if ($indx < $this->_num_rows && $this->_paginate && (intval(Paginator::getPageNumber())==1)){
				$pgSize = intval(Paginator::RowsPerPage);
				
				if ($pgSize > $indx) {
					// less records fetched than temp table created, re-adjust for paginator
					Paginator::setTotalRowCount($indx);
				}
			}
			if ($indx>$this->_num_rows)
				$this->_num_rows=$indx;
			
			// take a copy of the locals in the statement before they vanish on close()
			$this->_setStmtLocals($this->_fetchStmtHandle);
			
			
			// Handle the row incrementor after the fetch
			// move forward the number of rows requested for each fetch
			$this->_rowPosition += $this->_limit;
			if ($indx < $this->_limit)
				$this->_rowPosition = $indx;
			
			// flip the bit, to indicate the fetching has begun
			$this->_preExecute = false;
			
			// ---------------------------------------------------
			// check of the sql should be cached, but only for
			// non-array requests, at present
			// ---------------------------------------------------
			if ($this->_cache){
				if (!is_array($this->_fetchStmt)){
					Paginator::setCache(array(
							$this->_cache => $this->_fetchStmt
					));
				}
			}
			Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
					
	}
	/**
	 *
	 * @param string $sqlCommand
	 * @param array $params
	 * @throws Exception
	 */
	private function _exec($sqlCommand, $params= array()){
		try {
			Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
			
			// Bind all variables for execution
			if (!empty($this->_bindParams)){
				// prepare the statement
				$this->_resultHandle    = odbc_prepare($this->_db, $sqlCommand);
				// Execute the statement
				$status = odbc_execute($this->_resultHandle, $this->_bindParams);
				
				// Verify that the execute was successful
				if (!$status){
					$this->_message[$params['position']]= odbc_errormsg();
					throw new Exception('ERROR: '.
							__FILE__.":".
							__CLASS__.":".
							__FUNCTION__.":".
							__LINE__.
							" - Action requested failed: ".
							$sqlCommand.
							" ".$this->_message[$params['position']]);
				}
				else {
					$this->_message[$params['position']] = "Command successful";
				}
				
			}
			else {
				$this->_resultHandle = odbc_execute($this->_db, $sqlCommand);
				// @TODO ERROR HANDLING HERE~!!!
			}
			$this->_affected_rows = odbc_num_rows($this->_resultHandle);
			Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
			
		}
		catch (Exception $e){
			Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
			throw new Exception("ORM Fetch Error: ".$e->getMessage());			
		}
		
		return $this->_resultHandle;
	}
	/**
	 * Digest the fetch request and raw requests
	 *
	 */
	private function _Fetch(){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		//$this->_start();// start the timer for metrix recording
		
		try {
			
			// --------------------------------------------------------------------
			// Validate that we are not fetching again when no more records exist
			// --------------------------------------------------------------------
			if (isset($this->_isMoreRows) && $this->_isMoreRows==false){
				Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
				return;
			}
			if ((!$this->_accessed && $this->_rowPosition==1)){
				return; // the buffer has been pre-fetched, but not accessed
			}
			// ----------------------------------------
			// Build the SQL IF not already existing
			// ----------------------------------------
			if (!is_array($this->_fetchStmt)){
				// ------------------------------------------------------
				// Execute the request and populate the data-structures
				// ------------------------------------------------------
				$this->_processRequest($this->_fetchStmt);
			}
			else {
				// ----------------------------------------------------------------------
				// If we have several request to process, then we process them in order
				// and handle the specific types
				// ----------------------------------------------------------------------
				foreach ($this->_fetchStmt as $key => $request){
					foreach ($request as $type => $sql){
						if ($type=='raw'){
							// ---------------------------------
							// Process the "raw" request here
							// ---------------------------------
							$result_id = $this->_doExecRaw($sql);
							if (!$result_id){
								Utils::debugLog("ORM Error Raw Execute: ".odbc_errormsg()."\nSQL: ".$sql."\n Backtrace Follows: ".print_r(debug_backtrace(),true));
							}
							else if (!empty($result_id)){
								$this->_lastCount = odbc_num_rows($result_id);
							}
						}
						else if ($type==rowCountSQL) {
							// ------------------------------------------------------------------------
							// The paginator keeps track of the totals for specific reports in cache
							// so that it will not need to be fetched with each request, calling the
							// getTotalRowCount will be empty if this report has not been run. Of if
							// has been run already it will return the total count pre-executed.
							// ------------------------------------------------------------------------
							if ($this->_paginate){
								
								if (!is_array($sql)){
									$this->_doExecRaw($sql);
									$this->_num_rows = $this->_affected_rows;
								}
								else {
									// if we have a cache value in the paginator don't re-excute the sql
									if (!empty(Paginator::getCacheRowTotal())){
										$this->_num_rows = intval(Paginator::getCacheRowTotal());
									}
									// Set the SQL even if not executed, to let the temp tables know the _num_rows is already set
									$this->_rowCountSQL = $sql[1];
									// if there is already a value, and it doesn't look right or empty then execute count sql
									$qkLook = Paginator::getTotalRowCount();
									if ((empty($qkLook) || $qkLook <=0 || $qkLook == Paginator::getRowsPerPage())){
										// -------------------------------------------------------------------------------
										// If this is a pagination request, set the total row count to reflect the change
										// -------------------------------------------------------------------------------
										// This expects only one field and that field returns the count value of the data-set
										// the first element $sql[0] is the name of the field that holds the count
										// the second element $sql[1] is the sql to get the count
										$result = $this->_doExecRaw($sql[1]);
										$row = odbc_fetch_array($result);
										$this->_num_rows = intval($row[$sql[0]]);
									}
									else if (!empty($qkLook) && intval($qkLook)>=1){
										$this->_num_rows = $qkLook;
									}
								}
								Paginator::setTotalRowCount($this->_num_rows);
								
							}
							
						}
						else {
							$this->_processRequest($sql);
						}
						
					}
				}
				// -----------------------------------------
				// Reconcile number for pagination request
				// -----------------------------------------
				if ($this->_paginate){
					if (empty($this->_num_rows)){
						// if there is an empty result set, it doesn't matter what the rowCount result numbers were
						// if they do not reconcile with the result set, we must reset the paginator to reflect the data-set reality
						Paginator::setTotalRowCount(0);
					}
					// if the totals are less the page defined value then we must also ignore any higher values for the result count sql and use the lesser
					else if ($this->_lastCount < $this->_num_rows && $this->_lastCount < Paginator::getRowsPerPage()){
						// The paginator will take into account whether the value should change with other factors also
						Paginator::setTotalRowCount($this->_lastCount);
					}
				}
				
			}			
		}
		catch (Exception $e){
			$this->Error = $e->getMessage();
			throw new Exception('ERROR: '.
					__FILE__.":".
					__CLASS__.":".
					__FUNCTION__.":".
					__LINE__.
					" - Fetch Failure: ".$e->getMessage());
		}
		
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
		
		return;
	}
	
	/**
	 * @purpose:
	 * 	Get the column(s) of a primary key for a table
	 * @param
	 * 	The table name including the schema/datbase name
	 * @returns:
	 * 	The number of keys found for the table
	 */
	private function _getTableKeys($table) {
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		// CAUTION:
		// OK... now I know, that your wondering about santizing the table name
		// This is an internal private method for only devlopers, and if a developer
		// passes and exdogenous variable from the public unsanitized into this
		// function, then we need to take him out and hang him high! or at least
		// beat him/her with wet noodles (I think that is chinese water torture?).
		
		if (empty($table))
			throw new Exception(__FILE__.':'.__LINE__." - Missing object/table name, specify param object and try again",'6003');
			
			if ($this->_object == $table && !empty($this->_keys) && $this->_modBit==FALSE) { // this is in the parent class
				return $this->_keys;// already fetched so use it for optimization
			}
			else if ($this->_object != $table) {
				$this->_modBit=TRUE;// tablename change so regard as modified
			}
			// reset for matching tables to prevent a re-fetch
			else if ($this->_object == $table && $this->_modBit==TRUE) {
				$this->_modBit=FALSE;
			}
			
			$KeySel = odbc_exec($this->_db, "sp_pkeys ".$table);
			
			// This is unsupported by too many drivers to even consider :(
			// That is why it is not used and the other "sure method" below is used.
			//$KeySel = odbc_primarykeys($this->_db, $this->_database, $this->_schema, $table);
			
			if (!$KeySel) {
				$this->_message[$this->_position] =" - Unable to fetch table '$table'. Reason: ".odbc_errormsg();
			}
			
			$KeyCol = array();
			$cntr=0;
			while ($KeyRec = odbc_fetch_array($KeySel)){
				if (!empty($KeyRec["column_name"])){
					$KeyCol[] = $KeyRec["column_name"];
				}
			}
			
			// If we have a composite/concatenated key then grab them
			// save the key so that we don't fetch again when we query for this same object, it is too costly
			// Unfortunately there isn't a pre-exec meta-data fetch for this type of operation
			if (count($KeyCol)>1) { // save the value as an array if composite key
				$this->_keys = $KeyCol;
			}
			else {// save the value as a scalar if only one column
				$this->_keys = $KeyCol[0];
			}
			Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
			
			return $this->_keys;
	}
	/**
	 * Get micro time converted to a float for metrix gathering
	 * @return float
	 */
	private function _getMicroTime(){
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}
	/**
	 * Takes the array for assigning the object and its conditions, etc
	 * @param mixed array $params
	 */
	private function _initParams($params=array()){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		// Flag for variable re-initialization
		$this->_preExecute = true;
		// -------------------------------------------------------------------------------------
		// Must allow for a user defined order by clause.
		// -------------------------------------------------------------------------------------
		if (isset($params['order-by'])||isset($params['orderBy'])){
			$this->_orderBy = (isset($params['order-by']))?$params["order-by"]:$params['orderBy'];
			if (is_array($this->_orderBy)){
				$comma ="";
				
				$isInt=false;
				foreach ($this->_orderBy as $col => $direction) {
					if($col==="0"){
						$isInt=true;
					}
					if ($isInt){
						$tmpSort .= $comma.$direction;// this is actually a column name with default sorting
						$comma =",";
					}
					else {
						$tmpSort .= $comma.$col." ".$direction;
						$comma =",";
					}
				}
				$this->_orderBy = $tmpSort;
			}
		}
		// -------------------------------------------------------------------------------------
		// If this is a direct update using the Update method get the set clause
		// -------------------------------------------------------------------------------------
		if (isset($params['set'])){
			$this->_setClause = $params["set"];
		}
		else
			$this->_setClause=array(); // default to empty array
			
			// -------------------------------------------------------------------------------------
			// If there are "?" markers for bind variables, the bind-variables must be included
			// so we check if they are present
			// -------------------------------------------------------------------------------------
			if (isset($params["bind-variables"])||isset($params["bindVariables"])){
				$this->_bindParams = (isset($params["bind-variables"]))?$params["bind-variables"]:$params["bindVariables"];
				if (!is_array($this->_bindParams))
					$this->_bindParams = array($this->_bindParams);
					$this->_isParameterized = true;
					$this->_param_count = count($this->_bindParams);
			}
			else
				$this->_isParameterized = false;
				
		// -------------------------------------------------------------------------------------
		// -------------------------------------------------------------------------------------
		if (isset($params['group-by'])||isset($params['groupBy'])){
			$this->_groupBy = (isset($params['group-by']))?$params['group-by']:$params['groupBy'];
		}
		
		// -------------------------------------------------------------------------------------
		// -------------------------------------------------------------------------------------
		if (isset($params['having'])){
			$this->_having = $params['having'];
		}
		
		// -------------------------------------------------------------------------------------
		// If custom sql was given then use it instead of building from objects and conditions
		// -------------------------------------------------------------------------------------
		if (isset($params["query"])){
			$this->_fetchStmt = $params["query"];
			$this->_rowPosition =0; // if otherwise, below will apply user defines
			$this->_isMoreRows = true;// starting from the top, if any
			$this->_action = array(); // clear all actions
			$this->_message = array(); // clear previous messages
			$this->_readOnly = true; // read only for custom sql, we don't know the objects
		}
		// -------------------------------------------------------------------------------------
		// Here we get the user information for building a fetch statment from the objects/conditions
		// -------------------------------------------------------------------------------------
		else
		{
			// -------------------------------------------------------------------------------------
			// Allow for a user defined SELECT list to be passed
			// -------------------------------------------------------------------------------------
			if (isset($params['select']) && !empty($params['select'])){
				
				if (strtolower(substr(trim($params["select"]),0,6)) =="select"){
					$params["select"] = substr(trim($params["select"]),6);
				}
				$this->_select = $params["select"].' ';
			}
			
			// -------------------------------------------------------------------------------------
			// reset for new query
			// -------------------------------------------------------------------------------------
			$this->_fetchStmt = null; // could add a execution history here if wanted, before clearing
			
			// -------------------------------------------------------------------------------------
			// If we only have one, then direct assignment is used
			// -------------------------------------------------------------------------------------
			if (isset($params["object"])){
				// get the object name
				$this->_object = $params["object"];
			}
			// If we only have one, then direct assignment is used
			else if (isset($params["objects"])){
				// get the object name
				$this->_object = $params["objects"];
			}
			
			// -------------------------------------------------------------------------------------
			// Let's examine various join possibilities
			// -------------------------------------------------------------------------------------
			if (isset($params["join"])){
				// In MySQL, JOIN, CROSS JOIN, and INNER JOIN are syntactic equivalents
				// (they can replace each other). In standard SQL, they are not equivalent.
				// INNER JOIN is used with an ON clause, CROSS JOIN is used otherwise.
				$this->_join[] = $params["join"];
				$this->_joinType[] = " INNER JOIN ";
			}
			if (isset($params["join-inner"])||isset($params["innerJoin"])){
				// In MySQL, JOIN, CROSS JOIN, and INNER JOIN are syntactic equivalents
				// (they can replace each other). In standard SQL, they are not equivalent.
				// INNER JOIN is used with an ON clause, CROSS JOIN is used otherwise.
				$this->_join[] = (isset($params["join-inner"]))?$params["join-inner"]:$params["innerJoin"];
				$this->_joinType[] = " INNER JOIN ";
			}
			
			if (isset($params["join-outer"])||isset($params["outerJoin"])){
				$this->_join[] = (isset($params["join-outer"]))?$params["join-outer"]:$params["outerJoin"];
				$this->_joinType[] = " LEFT OUTER JOIN ";
			}
			if (isset($params["join-left"])||isset($params['leftJoin'])){
				$this->_join[] = ($params["join-left"])?$params["join-left"]:$params['leftJoin'];
				$this->_joinType[] = " LEFT JOIN ";
			}
			if (isset($params["join-right"])||isset($params["rightJoin"])){
				$this->_join[] = (isset($params["join-right"]))?$params["join-right"]:$params["rightJoin"];
				$this->_joinType[] = " RIGHT JOIN ";
			}
			
			// -------------------------------------------------------------------------------------
			// Let's consume the object request "Where" conditions
			// -------------------------------------------------------------------------------------
			if (isset($params["conditions"])||isset($params["where"])) {
				$this->_conditions = (!empty($params["conditions"]))?$params["conditions"]:$params["where"];
			}
			else
				$this->_conditions = null;
		}
		// Reset the fetch counter
		$this->_fetchIter=0;
		
		// -------------------------------------------------------------------------------------
		// The number of rows to return in this fetch
		// -------------------------------------------------------------------------------------
		if (isset($params["top"])){
			$match = array();
			$isFound = preg_match("/[0-9]+/", $params["top"],$match);// the +0 will convert to int and strip all possible injections
			if ($isFound>=1)
				$this->_top = $match[0];
		}
		// -------------------------------------------------------------------------------------
		// -------------------------------------------------------------------------------------
		if (isset($params["rows"]) && !empty($params["rows"])){
			if ($params["rows"]=="all" || $params["rows"] == selectAll)
				$this->_limit = 1000000;
				else
					$this->_limit = intval($params["rows"]);// convert to int and strip all possible injections
		}
		else {
			$this->_limit = 1; // limit to one by default
		}
		// -------------------------------------------------------------------------------------
		// If we are scrolling through records then we need to know the row position
		// -------------------------------------------------------------------------------------
		if (isset($params["row-position"])||isset($params["rowPosition"])){
			// convert to int and strip all possible injections
			$this->_rowPosition = intval((isset($params["row-position"]))?$params["row-position"]:$params["rowPosition"])-1;
		}
		else
			$this->_rowPosition = 0; // start at the first row by default
			
		// -------------------------------------------------------------------------------------
		// Get the database name, which in sybase can be different than owner
		// -------------------------------------------------------------------------------------
		if (isset($params["database"]) && !empty($params["database"]))
			$this->_database = $params["database"];
		else
			$this->_database=null;// @todo determine if default appropriate
						
		// -------------------------------------------------------------------------------------
		// get the owner, if no owner assume it is the same as the database
		// -------------------------------------------------------------------------------------
		if (isset($params["schema"]) && !empty($params["schema"]))
			$this->_schema = $params["schema"];
			
		// ----------------------------
		// CHECK FOR RE-INIT PARAMS
		// ----------------------------
		if ($this->_preExecute){
			// if we have already executed SQL then re-init the variables for the new selection criteria
			if (isset($this->_tempTable)){
				// let remove the temp table for this session to keep its size down
				$bitBucket = odbc_exec($this->_db, "DROP TABLE ".$this->_tempTable);
				// now let's clear the temp table field
				$this->_tempTable = null;
			}
			
			// set all the other fields that don't need qualification
			$this->_accessed = false;
			$this->_action = array();
			$this->_affected_rows = null; // @todo this should be an array of each of the actions perhaps... :/
			$this->_createTempSQL = null;
			//if (isset($this->_dmlStmtHandle)) // This can cause segment faults so better safe to not use
			//odbc_free_result($this->_dmlStmtHandle);
			$this->_dmlStmtHandle = null;
			//if (isset($this->_fetchStmtHandle))
			//odbc_free_result($this->_fetchStmtHandle);
			$this->_fetchStmtHandle = null;
			$this->_field_count = 0;
			$this->_hasAutoInc = null;
			$this->_isMoreRows = null;
			$this->_keys = null;
			$this->_message = null;
			$this->_num_rows = 0;
			$this->_readOnly = false;
			$this->_tempTable = null;
			$this->_createTempSQL = null;
			$this->_preExecute = true;
		}
		
		// -------------------------------------------------------------------------------------
		// Allow for a operation to be read-only defined by the programmer
		// However, only single objects can have CRUD functionality
		// -------------------------------------------------------------------------------------
		if (isset($params["read-only"])||isset($params["readOnly"])){
			$this->_readOnly = true;
		}
		
		// ----------------------------------------------------------
		// Check for the pagination request
		// If the paginator is requested it takes precedence over
		// the 'rows' and 'row-position' parameters
		// ----------------------------------------------------------
		if (isset($params['paginate']) && !empty($params['paginate'])){
			$this->_paginate = true;
			$this->_rowPosition = Paginator::getRowStartRange()-1;// zero offset
			if (empty($params["rows"])) // use default if no override
				$this->_limit = Paginator::getRowsPerPage();
			else // if override always set it over the derived paginator value
				$this->_limit = intval($params["rows"]);
			if (isset($params[paginateStyle]) && $params[paginateStyle]==indeterminate){
				Paginator::initSet(array(paginateStyle => indeterminate));
			}
		}
		
		// ---------------------------------------
		// Check if this sql should be "cached"
		// ---------------------------------------
		if (isset($params['cache']) && !empty($params['cache'])){
			$this->_cache = $params['cache'];
		}
		
		if (isset($params["no-metrix"])){
			$this->traceMetrix = false;
		}
		else {
			$this->traceMetrix = true;
		}
		if (isset($params["metrix-name"])){
			$this->metrixName = $params["metrix-name"];
		}
		if (isset($params["name"])){
			$this->queryName = $params["name"];
		}
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
								
	}
	
	/**
	 * Connect to the database and merge parameters for processing
	 * @param array $params
	 * @throws Exception
	 */
	private function _processConnection(&$params){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		$conn = array();
		// Lets set up some stored connections
		$SCONN = array(
				'READ-ONLY' => array(
						'DSN' 		=>	READ_ONLY_DSN,
						'username'	=>	DB_USER,
						'password'	=>	DB_PWD,
						//'database'	=>	'mydb',
						'schema'	=>	DB_SCHEMA
				),
				'DEFAULT' => array( // THIS VALUE IS SET IF THE "connection" parameter is not passed
						'DSN' 		=>	DSN,
						'username'	=>	DB_USER,
						'password'	=>	DB_PWD,
						//'database'	=>	DEFAULT_DB,
						'schema'	=>	DB_SCHEMA // This is the owner of the objects in a database
				),
		);
		// Check if the Connection was declared
		if (isset($params["connection"])
				&& !is_array(($params["connection"]))
				&& (isset($SCONN[$params["connection"]]))) {
					$conn = $SCONN[$params["connection"]];
					
		}
		else {
			// Use the default Connection if nothing passed
			$conn = $SCONN[$this->_DEFAULT_SC];
		}
		
		// ------------------------------------------------------
		// Now lets make a connection to the dabase
		// We use the global DB connection if avialable first
		// ------------------------------------------------------
		$this->_db = odbc_connect($conn["DSN"], $conn["username"], $conn["password"], SQL_CUR_USE_ODBC);
		
		/* check connection */
		if (!$this->_db) {
			throw new Exception('ERROR:'.
					__FILE__."-".
					__FUNCTION__.": ".
					__LINE__.": ".
					odbc_errormsg().
					" Parameters: ".print_r($params,true).
					" Connection: ".$conn["DSN"].":".$conn["username"]);
		}
		
		// --------------------------------------------------------------
		// Character set should be set in the DSN entry
		// combine the connection information to the input parameters
		// --------------------------------------------------------------
		$params = array_merge($conn, $params);// $params takes precedence for common keys
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
				
	}
	
	/**
	 * Process the request in order of functional steps
	 * @param string reference $sql
	 */
	private function _processRequest(&$sql){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		if ($this->_preExecute){
			$this->_buildSQL($sql);
			$this->_buildTempTables($sql);
			$this->_doExec($sql);
		}
		$this->_doFetch();
		
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
		
		return;
	}
	

	/**
	 * This will apply all the update rules to the key/value pair assignment
	 * for a given object
	 * @param scalar reference $column -  The actual column to be processed
	 * @param scalar reference $value -  The actual value to be processed
	 * @param scalar reference $bindValue - The variable in which to assign (if any) the value to be bound
	 * @param scalar reference $sqlTxt - The location to append the generated sql text
	 */
	private function _setColValues(&$colName, &$value, &$bindValue, &$sqlTxt){
		
		// ----------------------------------------------------------------------------
		// Now does this column have a value, and has not been assigned the null type
		// ----------------------------------------------------------------------------
		if (isset($value) && !is_null($value) && !is_array($value)){
			// ----------------------------------------------------
			// One more check for NULL assignment in the db
			// isset() != empty() these functions are not equal
			// since zero produces true on the empty() we check
			// that there is no zero, which is a legitimate value
			// ----------------------------------------------------
			if ($this->_spaceToNull && empty($value) && $value != '0') {
				$sqlTxt .=  $colName."=NULL ";
				// we don't assign a value to the target, since nothing to bind
			} else {
				$sqlTxt .= $colName."=? ";
				$bindValue[] = $value;
			}
		}
		// If the column is to be passed through and is not a scalar (i.e. function)
		/// then the value will be an array
		else if (is_array($value) && isset($value[raw])){
			$sqlTxt .=  $colName."=".$value[raw];
		}
		// There is no value then we regard it as a null in the database
		// or if it was explicitly assinged a null type we make it null
		else {
			$sqlTxt .=  $colName."=NULL ";
		}
		
	}
	/**
	 * Internal function for assigning the values returned by a statement execution
	 */
	private function _setStmtLocals(&$handStmt){
		
		$this->_field_count = @odbc_num_fields ( $handStmt );
		
	}
	/**
	 * Set the TOP clause for the provided SQL if it is not already present
	 * This will optimize the execution time against large data sets if
	 * there are no other newer features available (.i.e. data partitioning, etc)
	 */
	private function _setTopValue(&$sql, $topValue){
		
		// -------------------------------------------------------------------
		// if someone pulled a fast one, and this is empty, well, do nothing
		// -------------------------------------------------------------------
		if (empty($sql))
			return;
		else {
			// -----------------------------------------
			// copy the text and manipulate it a bit
			// -----------------------------------------
			$txt = str_replace(array("\n","\r","\t","\0","\x0B"), " ", trim($sql));
			
			// -----------------------------------------------------------------------------------
			// Now we have a little business to do here, we will have multiple sequential spaces
			// to try and mess up things, so let's just remove them, and make life simple
			// -----------------------------------------------------------------------------------
			while (stristr($txt, "  ")){
				$txt = str_replace("  ", " ", $txt);
			}
			
		}
		// ----------------------------------------------------------------------------------------
		// First Lets parse the sql out, and try and discern the first & second words in the list
		// ----------------------------------------------------------------------------------------
		$pieces = explode(" ", trim($txt), 3);
		
		// -------------------------------------------------
		// then make sure that this is a select statement
		// -------------------------------------------------
		if (strtoupper(trim($pieces[0]))=="SELECT"){
			
			// -------------------------------------------------------------------------------------------------
			// NOTE: This logic will break apart if there are comments between the SELECT and the first Column
			// -------------------------------------------------------------------------------------------------
			
			// -------------------------------------------------------------
			// then make sure that it doesn't already have a TOP value
			// -------------------------------------------------------------
			if (!empty($pieces[1]) && strtoupper($pieces[1])=="TOP"){
				// -------------------------------------------------------------
				// bingo, top is already there, so do nothing
				// don't override user defines, they might get mad at you!
				// -------------------------------------------------------------
				return;
			}
			else if (!empty($pieces[1]) && strtoupper($pieces[1])!="TOP"){
				// -------------------------------------------------------------
				// And lastly add the top value if not present
				// -------------------------------------------------------------
				$sqlPrefix = "SELECT TOP ".intval($topValue)." ";
				
				// ----------------------------------------------
				// Get the everything past the SELECT statement
				// ----------------------------------------------
				$sqlSuffix = stristr($sql, $pieces[1]);
				
				// --------------------------------------
				// Now assemble it as the final output
				// --------------------------------------
				$sql = $sqlPrefix.$sqlSuffix;
			}
		}
		
		return;
	}
	
	/**
	 *
	 * @param array mixed $params
	 * 		$params["position"] - The position of the action to be executed
	 * @throws Exception
	 * @return integer - The number of rows affected by the Update
	 */
	private function _Update($params=array()){
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", start);
		
		if ($this->_readOnly)
			throw new Exception("INTERNAL ERROR: Read only objects do not permit DML actions");
		else if (empty($this->_setClause))
			throw new Exception("INTERNAL ERROR: Must qualify UPDATE with a SET clause");
				
		// Start building update request
		if (!empty($this->_schema))
			$this->_action[$params["position"]] ="UPDATE ".$this->_schema.".".$this->_object." SET ";
		else
			$this->_action[$params["position"]] ="UPDATE ".$this->_object." SET ";
						
		$delimiter="";
		$valueArray = array();
		// --------------------------------------------------------------------------
		// BUILD THE SET ASSIGNMENTS
		// --------------------------------------------------------------------------
		foreach($this->_setClause as $column => $value){
			// -------------------------------------------------------------------
			// Now we check if there is anything that has changed
			// For this we use our dirtyBuffer! So, let's see if this has an entry
			// -------------------------------------------------------------------
			$this->_action[$params["position"]] .=$delimiter;
			$this->_setColValues($column, $value, $valueArray, $this->_action[$params["position"]]);
			$delimiter = ", ";
		}
		// ------------------------------------------------------------------------------------
		// push the bind variables to the front of the array stack for proper binding sequence
		// ------------------------------------------------------------------------------------
		$this->_bindParams = array_merge($valueArray, $this->_bindParams);
		
		// --------------------------------------------------------------------------
		// BUILD THE WHERE CONDITIONS with any bind variables attached there
		// --------------------------------------------------------------------------
		$this->_buildConditions(array('position'=>$params["position"]));
		$this->_action[$params["position"]] .=$this->_whereClause[$params['position']];
		
		// --------------------------
		// EXECUTE THE UPDATE
		// --------------------------
		$this->_exec($this->_action[$params["position"]], array($params["position"]));
		
		Utils::debugTrace(__FILE__.":".__FUNCTION__.":[".__LINE__."]", end);
		
		return (empty($this->_affected_rows))?0:$this->_affected_rows;
	}
}
