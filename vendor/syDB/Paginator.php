<?php
/**
 * Paginator Class for Bootstrap Integration
 *  
 * @abstract This class is for generating a Bootstrap paginator for a given result set
 * @original-author: T. Paul Quidera
 * @date September 17th, 2015 Anno Domini (The year of our Lord)
 * 
 * @param requires an array with the following required index values
 * 		'TotalRows' is required to compute all the values
 *  [optional parameters]
 * 		'link' is optional with a link to be used for the paginator clicks, otherwise current page
 * 		'size' = lg|sm|xs the size of the paginator to display, default 'xs' to fit current scheme
 *  	.... Hopefully some will bring documentation soon ...
 *
 *		
 * BOOTSTRAP PAGER ELEMENTS
 * example html:
 * 
 *		<ul class="pagination-sm">
 *			<li><a href="#"><span class="glyphicon glyphicon-backward"></span></a></li>
 *			<li><a href="#">1</a></li>
 *			<li class="active"><a href="#">2</a></li>
 *			<li><a href="#">3</a></li>
 *			<li><a href="#">4</a></li>
 *			<li><a href="#">5</a></li>
 *			<li><a href="#"><span class="glyphicon glyphicon-forward"></span></a></li>
 *			<li><a href="#"><span class="glyphicon glyphicon-fast-forward"></span></a></li>
 *		</ul>
 *
 */
class Paginator {
	
	CONST RowsPerPage = 10;	
	CONST PagesPerBar = 6;
	CONST IndeterminateMinFetch = 150;	
	CONST ExporRowLimit = 3500;
	
	/** 
	 * Clear cache for the given optional parameter $id or for all cache
	 * @param string $id
	 */
	static function clearCache($id=""){
		if (empty($id)){
			$_SESSION["Pager"]["Cache"] = null;
		}
		else if (!empty($id)){
			$_SESSION["Pager"]["Cache"][$id] = null;
		}
		$_SESSION["Pager"]["CurrentPage"]=1;
		$_SESSION["Pager"]["CurrentRow"]=1;
	}
	/**
	 * Clear the Paginator cached memory completely
	 */
	static function clearAll(){
		$_SESSION["Pager"]=array();
	}
	
	/**
	 * Set the name/value pair of SQL to be cached due to POST'd data translation storage
	 * @param array $params
	 */
	static function getCache($id){
		if (isset($_SESSION["Pager"]["Cache"][$id]))
			return $_SESSION["Pager"]["Cache"][$id];
		else
			return null;
	}
	/**
	 * Returns the cached total row count for this report or NULL if undefined
	 * @return integer|NULL
	 */
	static function getCacheRowTotal(){
		if (isset($_SESSION['Pager']['Cache'][self::getPageName()]) && !empty($_SESSION['Pager']['Cache'][self::getPageName()]) )
			return $_SESSION['Pager']['Cache'][self::getPageName()];
		else
			return null;
	}
	/**
	 * Get the displayed range of the page results
	 * @return string
	 */
	static function getDisplayedRange(){
		return 
			number_format(self::getRowStartRange()) 
			. "-" .
			number_format(self::getRowEndRange()) 
			. " of " .
			number_format(self::getTotalRowCount());		
	}
	/**
	 * Get the base link for the pagination numbers
	 * @return string
	 */
	static function getLink(){
		return $_SESSION["Pager"]["link"];
	}
	
	/**
	 * Get the number of buttons displayed with each pagination
	 * @return number
	 */
	static function getNumberButtons(){
		return $_SESSION["Pager"]["DisplayedPageRange"];
	}
	
	/**
	 * Get the Name of the current script being paginated
	 * @return The name of the current page being executed
	 */
	static function getPageName(){
		$info = pathinfo($_SERVER["SCRIPT_NAME"]);
		return $info['dirname'];
	}
	/**
	 * Get the current page number being displayed
	 * @return The number of the current page being displayed
	 */
	static function getPageNumber(){
		// ---------------------------------------------------------------------
		// Check if the page value has changed, and digest page change request
		// ---------------------------------------------------------------------
		if (isset($_REQUEST["pg"]) && !empty(intval($_REQUEST["pg"]))) {
			self::_computeValues(array('CurrentPage'=> intval($_REQUEST["pg"])));
		} 
		
		return $_SESSION["Pager"]["CurrentPage"];
	}
	
	/**
	 * Get the first page of the displayed range
	 * @return number
	 */
	static function getPageStartRange(){
		self::_computeValues();
		return $_SESSION["Pager"]["CurrRangeStart"];
	}
	
	/**
	 * Get the first page of the displayed range
	 * @return number
	 */
	static function getPageEndRange(){
		return $_SESSION["Pager"]["CurrRangeEnd"];
	}
	
	/**
	 * Get the Pagination display bar for rendering
	 * @param array $params
	 * @return string/html
	 */
	static function getPager($params=array()){
		
		// ----------------------------------------
		// Remove any zero offset for page number
		// ----------------------------------------
		if ($_REQUEST["pg"]==0)
			$_REQUEST["pg"]=1;
		
		// ----------------------------------------------------------------------
		// Don't display anything if there are no records or only one page 
		// or return a disabled paginator.... for now nothing.
		// ----------------------------------------------------------------------
		if ((empty($_SESSION["Pager"]["TotalRows"]) || $_SESSION["Pager"]["totalPages"]==1) && !self::isIndeterminate()){
			return ""; // nada!~ If only one page in result, or empty data set then no need for paginator (unless 1 page and indeterminate)
		}
		
		// ----------------------------------------------------
		// Determine the class for the size of the Paginator
		// ----------------------------------------------------
		if (isset($params['size']) && !empty($params['size'])){
			$_SESSION["Pager"]["classSize"]=$params["size"];
			$sizeClass = $params['size'];
		}
		else if (isset($_SESSION["Pager"]["classSize"]) && !empty($_SESSION["Pager"]["classSize"])){
			$sizeClass=$_SESSION["Pager"]["classSize"];
		}
		else {
			$sizeClass="xs";
			$_SESSION["Pager"]["classSize"]='xs';
		}
		
		// ----------------------------------------------------------------------
		// Only recompute if there is a user override, otherwise don't mess it up
		// ----------------------------------------------------------------------
		if (!empty($params))
			self::_computeValues($params);
		
		$pager = '<div class="strt-line">
				<ul class="pagination pagination-'.$sizeClass.'">';
		
		// --------------------------------------------------
		// Determine if Back Icons be displayed
		// --------------------------------------------------
		if ($_SESSION["Pager"]["showFBIcon"]){
			$pager .='<li><a href="'.$_SESSION["Pager"]["link"].'pg=1&Index=0&Range='.self::getRowsPerPage().'"><span class="glyphicon glyphicon-fast-backward"></span></a></li>'; 
		}
		if ($_SESSION["Pager"]["showBIcon"]){
			$pager .='<li><a href="'.$_SESSION["Pager"]["link"].'pg='.$_SESSION["Pager"]["BackValue"].'&Index='.($_SESSION["Pager"]["BackValue"]*self::getRowsPerPage()).'&Range='.self::getRowsPerPage().'"><span class="glyphicon glyphicon-backward"></span></a></li>';
		}
		
		// --------------------------------------------
		// Display the grid boxes for each known page
		// --------------------------------------------
		for ($pg=self::getPageStartRange();$pg<=self::getPageEndRange();$pg++){
				
			$Index = ($pg - 1) * self::getRowsPerPage();
				
			if ($pg==$_SESSION["Pager"]["CurrentPage"]){
				$active=' class="active" ';
			}
			else {
				$active='';
			}
			$pager .='<li'.$active.'><a href="'.$_SESSION["Pager"]["link"].'pg='.$pg.'&Index='.($Index).'&Range='.self::getRowsPerPage().'">'.$pg.'</a></li>';
		}
		
		// -----------------------------------------------------------------------
		// If indeterminate results then render the indeterminate bar paginator
		// simply put, should we display the "View More" button
		// -----------------------------------------------------------------------
		if (self::isIndeterminate()){
			if (self::getTotalRowCount() <=0) {
				return "";// display nothing if no records  
			} 
			if (empty($pg)){ 
				$pg=self::getPageNumber();
			}
			// Display only if the Forward & Fast Forward Icons are not present & not only one page result set
			if (	!$_SESSION["Pager"]["showFIcon"] && 
					!$_SESSION["Pager"]["showFFIcon"] && 
					!($_SESSION["Pager"]["TotalRows"] < self::RowsPerPage)){
				$pager .='<li><a href="'.$_SESSION["Pager"]["link"].'pg='.$pg.'&Index='.(intval($pg*self::getRowsPerPage())).'&Range='.self::getRowsPerPage().'">View More...</a></li>';
			}
		}
		// -----------------------------------
		// Next operators 
		// -----------------------------------
		if ($_SESSION["Pager"]["showFIcon"])
			$pager .='<li><a href="'.$_SESSION["Pager"]["link"].'pg='.$_SESSION["Pager"]["pagerForwardValue"].'&Index='.(($_SESSION["Pager"]["pagerForwardValue"]*self::getRowsPerPage())+0).'&Range='.self::getRowsPerPage().'"><span class="glyphicon glyphicon-forward"></span></a></li>';
		if ($_SESSION["Pager"]["showFFIcon"])
			$pager .='<li><a href="'.$_SESSION["Pager"]["link"].'pg='.$_SESSION["Pager"]["totalPages"].'&Index='.($_SESSION["Pager"]["totalPages"]*self::getRowsPerPage()).'&Range='.self::getRowsPerPage().'"><span class="glyphicon glyphicon-fast-forward"></span></a></li>';
			
		$pager .="</ul>
	</div>";
				
		return $pager;
	}

	/**
	 * Get the paginator class size
	 * @return string lg|sm|xs
	 */
	static public function getPagerBarSize(){
		return $_SESSION["Pager"]["classSize"];
	}
	/**
	 * Get the specific report details
	 */
	static public function getReportInfo(){
		$details = self::_getReportDetail();
		return $details;
	}
	
	static public function getReportName(){
		$details = self::_getReportDetail();
		return $details->name;
	} 
	
	/**
	 * Get the number of rows displayed per page of the paginator
	 * @return number
	 */
	static function getRowsPerPage(){
		if (!isset($_SESSION["Pager"]["PageSize"]) || $_SESSION["Pager"]["PageSize"]<=0){
			self::_computeValues(array("PageSize" => self::RowsPerPage));
		}
		return $_SESSION["Pager"]["PageSize"];
	}
	/**
	 * Get the first record number of the displayed range
	 * @return number
	 */
	static function getRowStartRange(){
	
		if (isset($_REQUEST["pg"])){
			// ----------------------------------------
			// Remove any zero offset for page number
			// ----------------------------------------
			if ($_REQUEST["pg"]==0)
				$_REQUEST["pg"]=1;
				
			$_SESSION["Pager"]["CurrentRow"] = ((intval($_REQUEST["pg"]) * $_SESSION["Pager"]["PageSize"] ) - $_SESSION["Pager"]["PageSize"]) +1;
				
			// --------------------------------------------------------------------------------------------
			// IF this is an indeterminate result set and we have just requested another expanisive fetch
			// then we increase the currently display page values for the paginator to render
			// i.e. [1][2][3] if the current page request is 3
			// --------------------------------------------------------------------------------------------
			if (self::isIndeterminate()){
				if (intval($_REQUEST["pg"]) > intval(self::getTotalPageCount()) ){
					self::setIndeterminatePages($_REQUEST["pg"]);
				}
			}
		}
		else if (isset($_REQUEST["Index"]) && !empty($_REQUEST["Index"])){
			$_SESSION["Pager"]["CurrentRow"] = intval($_REQUEST["Index"]);
		}
		else if (isset($_REQUEST["NextIndex"]) && !empty($_REQUEST["NextIndex"])){
			$_SESSION["Pager"]["CurrentRow"] = intval($_REQUEST["NextIndex"]);
		}
		else {
			// we must be instantiating the pager with a new request, so reset to 1
			$_SESSION["Pager"]["CurrentRow"] = 1;
		}
		return $_SESSION["Pager"]["CurrentRow"];
	}
	
	/**
	 * Get the last record number of the displayed range
	 * @return number
	 */
	static function getRowEndRange(){
	
		if (isset($_REQUEST["pg"]) && !empty($_REQUEST["pg"])){
			$endRow = (intval($_REQUEST["pg"]) * intval(self::getRowsPerPage()));
		}
		else if (isset($_REQUEST["Index"]) && !empty($_REQUEST["Index"])){
			$endRow = intval($_REQUEST["Index"]-1 + $_SESSION["Pager"]["PageSize"] );
		}
		else {
			$endRow = $_SESSION["Pager"]["CurrentRow"] + $_SESSION["Pager"]["PageSize"] -1;
		}
		if ($endRow > self::getTotalRowCount() && !self::isIndeterminate()){
			$endRow = self::getTotalRowCount();
		}
		return $endRow;
	}
	/**
	 * This will fetch the column identifier that was set for the 
	 * given report to determine the sorting order of the displayed columns
	 * @param string $col - Column Identifier for column sorting
	 */
	static function getSortColumn($report){
		$col = self::getCache('SortOrderColumn#'.$report);
		return $col;
	}
	/**
	 * This will fetch the sort direction that was set for the 
	 * given report to determine the sorting order of the displayed columns
	 * @param string $report
	 */
	static function getSortVector($report){
		Utils::filterText($report);
		return self::getCache('SortOrderVector#'.$report);
	}
	
	/**
	 * Get the summed results set size based on the current viewed page for use
	 * as a TOP clause to limit the result set
	 */
	static function getTopValue(){
	
		$params = array();
	
		// ---------------------------------
		// Perform the general calculation
		// ---------------------------------
		$setSize = intval(self::getPageNumber()) * intval(self::getRowsPerPage());
	
		// ----------------------------------------------------------------------------
		// if this is indeterminate the total count may change during forward movement
		// ----------------------------------------------------------------------------
		if (self::isIndeterminate()) {
			/* @TODO: If using indeterminate this may need to be active again
			if (intval($setSize) < self::IndeterminateMinFetch) {
			$params['TotalRows'] = (int)self::IndeterminateMinFetch;
			$setSize = (int)self::IndeterminateMinFetch;
			}
			else 
			*/
			$params['TotalRows'] = $setSize;
				
			self::_computeValues($params);
		}
		// ----------------------------------------------------------------------------
		// If we are over the total count limit then reset the total rows size
		// ----------------------------------------------------------------------------
		if (!empty($_SESSION["Pager"]["TotalRows"]) && $_SESSION["Pager"]["TotalRows"]!="-1"  && $setSize > $_SESSION["Pager"]["TotalRows"]){
			// if the forward button was pressed then move the counters forward
			if ($setSize > $_SESSION["Pager"]["TotalRows"] && self::isIndeterminate()){
				self::_computeValues(array("TotalRows" => $setSize));
			}
				
			return $_SESSION["Pager"]["TotalRows"];
		}
		else {
			// ----------------------------------------------------------------------------------
			// if the row pointer is less than the defined page size, get the page size instead
			// ----------------------------------------------------------------------------------
			if ($setSize < intval(self::getRowsPerPage()))
				return self::getRowsPerPage();
			else {
				// otherwise our caclulation applies to the interum pages
				return $setSize;
			}
		}
	}
	
	/**
	 * Set the total number of rows/records in this dataset REQUIRED parameter
	 */
	static function getTotalRowCount(){
	
		// --------------------------------------------
		// Check if we have the report currently run
		//
		if (!empty($_SESSION["Pager"]["Cache"][self::getPageName()]) && !self::isIndeterminate()){
			return $_SESSION["Pager"]["Cache"][self::getPageName()];
		}
		else {
			if (!isset($_SESSION["Pager"]["TotalRows"])){
				return null;
			}
		return $_SESSION["Pager"]["TotalRows"];
		}
	}
	
	/**
	* Get the total pages for the current paginator
	* @return integer
	*/
	static function getTotalPageCount(){
		return $_SESSION["Pager"]["totalPages"];
	}
	
	/**
	 * Initialize the Paginator if necessary for variable changes
	 * @param mixed array $params
	 */
	static public function initialize($params=array()){
		
		// -----------------------------------------------------------------------------
		// Make it required to make life simpler for internal determination of report
		// -----------------------------------------------------------------------------
		if (empty($params['report'])){
			// Don't allow for dynamic assignment of name
			error_log(__CLASS__.":".__FUNCTION__."WARNING: missing expected report name parameter. Please add the report name parameter.");
			throw new Exception(__CLASS__.":".__FUNCTION__.' Missing required argument "report"');
		}
		
		// If this is a new entry into a report page, then we clear and start at 1
		if (!isset($_REQUEST['pg']) 
		|| ($_SESSION['Exception'] != $_REQUEST['ExceptionStatus'] && !empty($_SESSION['Exception']) && !empty($_REQUEST['ExceptionStatus']))  
		){
			Paginator::clearAll(); // coming in the first time clear the cache to avoid any conflicts
		}
		
		// -----------------------------------------------------------------------------
		// Save the current report name for future references
		// -----------------------------------------------------------------------------
		self::setCache(array(
				'currentReport' => $params['report']
		));
		
		if (!empty($params['OrderBy'])){
			self::setSortColumn($params['report'], $params['OrderBy']);
		}
		else if(!empty($_REQUEST['OrderBy'])){
			self::setSortColumn($params['report'], $_REQUEST['OrderBy']);
		}
		else {
			self::setCache(array('sortColChange#'.$params['report'] => false));
		}
		
		if (!empty($params['Vector'])){
			self::setSortVector($params['report'], $params['Vector']);
		}
		else if( !empty($_REQUEST['Vector'])){
			self::setSortVector($params['report'], $_REQUEST['Vector']);
		}
		else {
			self::setCache(array('sortVectorChange#'.$params['report'] => false));
		}
		
		// -----------------------------------------------------------------------------
		// Do any computations if needed
		// -----------------------------------------------------------------------------
		self::_computeValues($params);
	}
	/**
	 * 
	 * @return boolean
	 */
	static function isAtExportLimit(){
			$rowPoint = intval(self::getRowsPerPage() * self::getPageNumber());
			if ($rowPoint >= intval(self::ExporRowLimit))
				return true;
			else
				return false;
	} 
	
	/**
	 * Initialize the Paginator IF the pagination page has changed, otherwise it recomputes if needed
	 * @param array $params
	 */
	static function initSet($params=array()){
		self::_computeValues($params);
	}
	/**
	 * Report whether the current id is cached or not
	 * @param string $id - name of cache element
	 * @return boolean
	 */
	static function isCacheSet($id){
		if (isset($_SESSION["Pager"]["Cache"][$id]) && !empty($_SESSION["Pager"]["Cache"][$id]))
			return true;
		else
			return false;
	}
	
	/**
	 * is the current report rendering the Indeterminate style paginator
	 * @return boolean
	 */
	static function isIndeterminate(){
		if (isset($_SESSION['Pager']['Cache']['BarStyle'][self::getPageName()])
		&& $_SESSION['Pager']['Cache']['BarStyle'][self::getPageName()]=='indeterminate'){
			return true;
		}
		else
			return false;
	}
	
	static function isSortChange(){
		$colChange = self::getCache('sortColChange#'.self::getCache('currentReport'));
		$vecChange = self::getCache('sortVectorChange#'.self::getCache('currentReport'));
		if ( $colChange || $vecChange || !empty($_REQUEST["search"]["value"]))
			return true;
		else
			return false;
	}
	/**
	 * Set the name/value pair of SQL to be cached due to POST'd data translation storage
	 * @param array $params
	 */
	static function setCache($params=array()){
		foreach ($params as $key => $value){
			if (isset($key)){
				$_SESSION["Pager"]["Cache"][$key] = $value;
			}
		}
	}
	
	/**
	 * Display the number of indeterminate pages fetched thus far
	 *
	 */
	static function setIndeterminatePages($pages){
	
		// If the page has not been accounted for then we assign it.
		if (intval($pages) > intval(self::getTotalPageCount())){
				
			$_SESSION['Pager']['totalPages'] = intval($pages);
		}
		else if (empty($_SESSION['Pager']['totalPages'])){
			$_SESSION['Pager']['totalPages'] = intval($pages);
		}
			
		// $_SESSION['Pager']['Cache']['IndeterminatePages'][self::getPageName()] = intval($pages);
	}
	
	/**
	 * Set the number of rows displayed per page of the paginator
	 * @param number
	 */
	static function setRowsPerPage($number){
		//$_SESSION["Pager"]["PageSize"] = intval($number);
		self::_computeValues(array('PageSize'=>intval($number)));
	}
	
	/**
	 * Set the number of buttons displayed with each pagination
	 * @param number
	 */
	static function setNumberButtons($number){
		$_SESSION["Pager"]["DisplayedPageRange"] = intval($number);
		self::_computeValues();
	}

	
	/**
	 * Set the paginator class size
	 * @param string lg|sm|xs
	 */
	static function setPagerBarSize($size){
		$_SESSION["Pager"]["classSize"]=intval($size);
		self::_computeValues();
	}
		
	/**
	 * Set the total number of rows/records in this dataset REQUIRED parameter
	 */
	static function setTotalRowCount($totalRows){
		// Dont re-assign unless page changes or we are starting new search, since the value will decrease as scrolling
		if ( 
			(empty($_REQUEST["pg"]) || (isset($_REQUEST["pg"]) && (empty($_REQUEST["pg"]) || $_REQUEST["pg"]==1)))
			|| ($_SERVER["SCRIPT_NAME"] != $_SESSION["Pager"]["page"])
			|| (intval($_SESSION["Pager"]["TotalRows"]) != intval($totalRows) )
		){
			// the compute will evaluate if indeterminate or determined result set
			if ($totalRows<0) $totalRows =0;
			self::_computeValues(array('TotalRows' => intval($totalRows)));
		}
	}
	
	
	static function setTotalPageCount($pages){
		self::_computeValues(array('totalPages'=>intval($pages)));
	}
	
	/**
	 * Set a Paginator parameter for digestion and immediately apply
	 * @param array $param
	 */
	static function setParameter($param=array()){
		self::_computeValues($param);
	}
	
	/**
	 * This is used to store the sort column identifier at the session level 
	 * for persistence between page selections
	 * @param string $report - Name of the report this column sort is assigned to 
	 * @param string $col - Column Identifier for column sorting 
	 */
	static function setSortColumn($report, $col){
		Utils::filterText($col);
		Utils::filterText($report);
		// Compare before change, and set indicator for reporting to know
		if (self::getCache('SortOrderColumn#'.$report) != $col)
			self::setCache(array('sortColChange#'.$report => true));
		else
			self::setCache(array('sortColChange#'.$report => false));
		
		self::setCache(array('SortOrderColumn#'.$report => $col));
		return;
	}
	
	/**
	 * This is used to store the sort direction (asc, desc) at the session level
	 * for persistence between page selections
	 * @param string $report - Name of the report this column sort is assigned to
	 * @param string $vector - vector/direction for column sorting
	 */
	static function setSortVector($report, $vector){
		Utils::filterText($vector);
		Utils::filterText($report);
		// Compare before change, and set indicator for reporting to know
		if (self::getCache('SortOrderVector#'.$report) != $col)
			self::setCache(array('sortVectorChange#'.$report => true));
		else
			self::setCache(array('sortVectorChange#'.$report => false));
		self::setCache(array('SortOrderVector#'.$report => $vector));
		return;
	}
	
	/**
	 * 
	 * @param integer $int
	 * @param number to round to $n
	 * @return number - Rounded up number
	 */
	static function roundUp($int, $n) {
		
    	return ceil(floatval($int) / floatval($n)) * floatval($n);
    	
	}
	
	private static function _computeValues($params=array()){
		
		// ==============================================================
		// Determine if all values should be reset
		// NOTE: If the page is different from when pagination began
		// the values will be reset to the specific page if required
		// ==============================================================
		if (!empty($_SESSION["Pager"]["page"]) && ($_SESSION["Pager"]["page"] != $_SERVER["SCRIPT_NAME"])){
			// Page has changed the reset all values to avoid erroneious output
			$_SESSION["Pager"]=array();
		}
		// ---------------------------------------------------------
		// First things first, what pagination style are we using
		// ---------------------------------------------------------
		if (isset($params[paginateStyle]) && $params[paginateStyle]==indeterminate)
			$_SESSION['Pager']['Cache']['BarStyle'][self::getPageName()]=='indeterminate';
		
		// -------------------
		// Get Current Page
		// -------------------
		if (isset($params["CurrentPage"]) || !empty($params["CurrentPage"])){ // for initialization
			$_SESSION["Pager"]["CurrentPage"] = intval($params["CurrentPage"]);
		}
		else if (isset($_REQUEST["pg"]) && !empty($_REQUEST["pg"])){ // for pagination scrolling
			$_SESSION["Pager"]["CurrentPage"] = intval($_REQUEST["pg"]);
		}
		else if (isset($_REQUEST["Index"]) && !empty($_REQUEST["Index"])){ // for backward compatibility
			$_SESSION["Pager"]["CurrentPage"] = self::roundUp(intval($_REQUEST["Index"])/$_SESSION["Pager"]["PageSize"],1); 
		}
		else { 
			if (!isset($_SESSION["Pager"]["CurrentPage"]) || empty($_SESSION["Pager"]["CurrentPage"])){
				$_SESSION["Pager"]["CurrentPage"]=1;
			}
		}
		// -------------------------------
		// Check for style display
		// -------------------------------
		if (isset($params['paginateStyle']) && !empty($params['paginateStyle'])){
			if ($params['paginateStyle'] == 'indeterminate'){
				$_SESSION['Pager']['Cache']['BarStyle'][self::getPageName()]='indeterminate';
			}
		}
		
		// -------------------------------
		// Get Total Rows in record set
		// -------------------------------
		if (isset($params["TotalRows"]) && !empty($params["TotalRows"])){
			if ((empty($_SESSION["Pager"]["TotalRows"]) || $_SESSION["Pager"]["CurrentPage"]==1) && !self::isIndeterminate()){
				$_SESSION["Pager"]["TotalRows"] = intval($params["TotalRows"]);
				// Set the cache to hold the total for subsequent calls
				$_SESSION["Pager"]["Cache"][self::getPageName()] = intval($params["TotalRows"]);
					
				// If the result set is smaller than the rows/page then use total rows instead
				if (intval($params["TotalRows"]) < $_SESSION["Pager"]["PageSize"]){
					$_SESSION["Pager"]["PageSize"]  = intval($params["TotalRows"]); 
				}
			}
			else if (self::isIndeterminate()){
				// --------------------------------------------------------
				// If we have a new Total Count then reset the cache value
				// --------------------------------------------------------
				if (intval($params["TotalRows"]) > intval($_SESSION["Pager"]["Cache"][self::getPageName()])){
					$_SESSION["Pager"]["Cache"][self::getPageName()] = intval($params["TotalRows"]);
				}
				// --------------------------------------------------------
				// if new value for session total and on page 1 reset all
				// --------------------------------------------------------
				else if (intval($params["TotalRows"]) != $_SESSION["Pager"]["TotalRows"] 
					&&  intval($_SESSION["Pager"]["CurrentPage"])==1) {
					
					$_SESSION["Pager"]["TotalRows"] = intval($params["TotalRows"]); 
					$_SESSION["Pager"]["Cache"][self::getPageName()] = intval($params["TotalRows"]);
					$_SESSION["Pager"]["PageSize"] = intval($params["TotalRows"]);
					$_SESSION["Pager"]["showFFIcon"]=false;
					$_SESSION["Pager"]["showFIcon"]=false;
				}					
			}
			// --------------------------------------------------------
			// 
			// --------------------------------------------------------
			if (intval($_SESSION["Pager"]["CurrentPage"])*intval($_SESSION["Pager"]["PageSize"]) > intval($_SESSION["Pager"]["TotalRows"]) ){
				if (intval($_SESSION["Pager"]["CurrentPage"]) > $_SESSION["Pager"]["totalPages"]){
					$_SESSION["Pager"]["totalPages"] = $_SESSION["Pager"]["CurrentPage"];
				}
				$_SESSION["Pager"]["TotalRows"] = intval($_SESSION["Pager"]["CurrentPage"] * $_SESSION["Pager"]["PageSize"]);
			}
		}
		else if (isset($params["TotalRows"]) && $params["TotalRows"]==0){
			$_SESSION["Pager"]["TotalRows"] = 0;
		} 
		else {
			if (!isset($_SESSION["Pager"]["TotalRows"]) || empty($_SESSION["Pager"]["TotalRows"])){ 
				// This is a work around to init'ing the values before dataset execution	
				$_SESSION["Pager"]["TotalRows"] = 0; 
			}
		}
		// ----------------------------------------------------
		// Get the amount of records to display on each page
		// ----------------------------------------------------
		if (isset($params["PageSize"]) && !empty($params["PageSize"])){
			$_SESSION["Pager"]["PageSize"] = intval($params["PageSize"]);
		}
		else if (isset($_REQUEST["length"]) && !empty($_REQUEST["length"])){
			$_SESSION["Pager"]["PageSize"] = intval($_REQUEST["length"]);
		} 
		else {
			if (!isset($_SESSION["Pager"]["PageSize"]) || empty($_SESSION["Pager"]["PageSize"])){
				$_SESSION["Pager"]["PageSize"]=self::RowsPerPage; 
			}
		}
		// ------------------------------------------------------------------------------
		// Get the link that will be used to append the specific Paginator information
		// ------------------------------------------------------------------------------
		if (!empty($params["link"])){
			$_SESSION["Pager"]["page"] = $_SERVER["SCRIPT_NAME"];
			if (strstr($params["link"],'?') !== false) {
				$_SESSION["Pager"]["link"] = trim($params["link"])."&";
			} else {
				$_SESSION["Pager"]["link"] = trim($params["link"])."?"; }
		}
		else if (!isset($_SESSION["Pager"]["link"]) && (isset($_SERVER["SCRIPT_NAME"]) && !empty($_SERVER["SCRIPT_NAME"]))) {
			$_SESSION["Pager"]["page"] = $_SERVER["SCRIPT_NAME"];
			$_SESSION["Pager"]["link"] = trim(str_replace(array('index.php','content.php'),'',$_SERVER["SCRIPT_NAME"]))."?";
		}
		else if (!isset($_SESSION["Pager"]["link"]) || empty($_SESSION["Pager"]["link"])){
			throw new Exception('Error: Paginator requires a link definition, and is currently indiscernable');
		}
		// ------------------------------------------
		// Set the default for the display ranges
		// ------------------------------------------
		if (isset($params['DisplayedPageRange'])){
			$_SESSION["Pager"]["DisplayedPageRange"] = intval($params['DisplayedPageRange']);
		}
		else if (!isset($_SESSION["Pager"]["DisplayedPageRange"]) || empty($_SESSION["Pager"]["DisplayedPageRange"])){
			$_SESSION["Pager"]["DisplayedPageRange"] = self::PagesPerBar;
		}
		// ------------------------------------------
		// Total Pages
		// ------------------------------------------
		if (isset($params['totalPages']) && !self::isIndeterminate()){
			$_SESSION["Pager"]["totalPages"] = intval($params['totalPages']);
		}
		else if (self::isIndeterminate()){
			// only evaluating an increased value, because they may move backward/forward through results
			if (isset($params['totalPages']) && intval($params['totalPages']) > intval($_SESSION["Pager"]["totalPages"])){
				$_SESSION["Pager"]["totalPages"] = intval($params['totalPages']); 
			}
			else if (!empty($_REQUEST["pg"])){
				self::setIndeterminatePages($_REQUEST["pg"]);
			}
			else if (!empty($params['TotalRows']) && intval($_SESSION["Pager"]["totalPages"]) <=0 ){
				$_SESSION["Pager"]["totalPages"] = self::roundUp(($_SESSION["Pager"]["TotalRows"]/$_SESSION["Pager"]["PageSize"]), 1);
			}
		}
		else {
			$_SESSION["Pager"]["totalPages"] = self::roundUp(($_SESSION["Pager"]["TotalRows"]/$_SESSION["Pager"]["PageSize"]), 1);
		}
		// ------------------------------------------
		// Last Page in the pager range
		// ------------------------------------------
		if (isset($params['CurrRangeEnd']) && !empty($params['CurrRangeEnd'])){
			$_SESSION["Pager"]["CurrRangeEnd"] = $params['CurrRangeEnd'];
		} 
		else if (self::isIndeterminate()){
			if ($_SESSION["Pager"]["CurrentPage"] < $_SESSION["Pager"]["totalPages"] 
				&& $_SESSION["Pager"]["totalPages"] <= $_SESSION["Pager"]["DisplayedPageRange"] ){
				
				$_SESSION["Pager"]["CurrRangeEnd"] = $_SESSION["Pager"]["totalPages"];
			}
			else {
				
				$_SESSION["Pager"]["CurrRangeEnd"] = (self::roundUp($_SESSION["Pager"]["CurrentPage"]/$_SESSION["Pager"]["DisplayedPageRange"],1))*$_SESSION["Pager"]["DisplayedPageRange"];
			}
			
		}
		else {
			if ($_SESSION["Pager"]["CurrentPage"] < $_SESSION["Pager"]["DisplayedPageRange"]){
				$_SESSION["Pager"]["CurrRangeEnd"] = $_SESSION["Pager"]["DisplayedPageRange"];
			} else {
				$_SESSION["Pager"]["CurrRangeEnd"] = (self::roundUp($_SESSION["Pager"]["CurrentPage"]/$_SESSION["Pager"]["DisplayedPageRange"],1))*$_SESSION["Pager"]["DisplayedPageRange"];
			}			
		}

		// ------------------------------------------
		// Determine the Start Range of the pager
		// ------------------------------------------
		$val = $_SESSION["Pager"]["CurrRangeEnd"] - $_SESSION["Pager"]["DisplayedPageRange"];
		if ($val>=1){
			$_SESSION["Pager"]["CurrRangeStart"] = $val+1;
		}
		else {
			$_SESSION["Pager"]["CurrRangeStart"] = 1;
		}
		// -------------------------------------
		// Process the Current Row Logic
		// -------------------------------------
		if (isset($params["pg"]) && !empty($params["pg"]) || (isset($_REQUEST["pg"]) && !empty($_REQUEST["pg"])) || !empty($params["CurrentPage"])){
			if (!empty($params["pg"]))
				$CurrPage = intval($params["pg"]);
			else if (!empty($_REQUEST["pg"]))
				$CurrPage = intval($_REQUEST["pg"]);
			else if (!empty($params["CurrentPage"])) 
				$CurrPage = intval($params["CurrentPage"]);
			
			if (!empty($CurrPage) && ($CurrPage==1 
					|| (self::isIndeterminate() && ($_SESSION["Pager"]["CurrRangeStart"] < (intval($CurrPage) * intval($_SESSION["Pager"]["PageSize"])))) )){
				$_SESSION["Pager"]["CurrentRow"] = (((intval($CurrPage) * intval($_SESSION["Pager"]["PageSize"])) - intval($_SESSION["Pager"]["PageSize"])) + 1); 
			}
			else {
				if (!empty($CurrPage) && $CurrPage>=1){
					$_SESSION["Pager"]["CurrentRow"] = intval($CurrPage-1)*intval($_SESSION["Pager"]["PageSize"]);
				}
				else 
					// Default to 1 if indiscernable
					$_SESSION["Pager"]["CurrentRow"]=1; //this should never happen
			}
		}
		else if (isset($params["Index"]) && !empty($params["Index"]) || (isset($_REQUEST["Index"]) && !empty($_REQUEST["Index"]))) {
			if (!empty($params["Index"]))
				$_SESSION["Pager"]["CurrentRow"] = intval($params["Index"]);
			else if (!empty($_REQUEST["Index"]))
				$_SESSION["Pager"]["CurrentRow"] = intval($_REQUEST["Index"]);
			else {
				$_SESSION["Pager"]["CurrentRow"]=1; // this should never happen
			}
		}		
		else {
			$_SESSION["Pager"]["CurrentRow"] = (((intval($_SESSION["Pager"]["CurrentPage"]) * intval($_SESSION["Pager"]["PageSize"])) - intval($_SESSION["Pager"]["PageSize"])) + 1);
		}
		
		// ------------------------------------------
		// make sure that the last page in the window is not larger that the total pages
		// ------------------------------------------
		if ($_SESSION["Pager"]["CurrRangeEnd"] > $_SESSION["Pager"]["totalPages"]){
			$_SESSION["Pager"]["CurrRangeEnd"] = $_SESSION["Pager"]["totalPages"];
		}
		// ===============================
		// MANAGE ICON DISPLAY
		// ===============================
		
		// ------------------------------------------
		// Check if we display the FastForward Icon
		// ------------------------------------------
		if ( self::roundUp((($_SESSION["Pager"]["totalPages"] - $_SESSION["Pager"]["CurrRangeEnd"])/$_SESSION["Pager"]["DisplayedPageRange"]), 1) >=2 ){
			$_SESSION["Pager"]["showFFIcon"] = true;
		}
		else {
			$_SESSION["Pager"]["showFFIcon"] = false;
		}
		// ------------------------------------------
		// Check if we display the Forward Icon
		// ------------------------------------------
		if ($_SESSION["Pager"]["CurrRangeEnd"] < $_SESSION["Pager"]["totalPages"]){
			$_SESSION["Pager"]["showFIcon"] = true;
			$_SESSION["Pager"]["pagerForwardValue"] = $_SESSION["Pager"]["CurrRangeEnd"]+1;
		}
		else {
			$_SESSION["Pager"]["showFIcon"] = false;
		}
		// ------------------------------------------
		// Check if we need the FastBackward Icon
		// ------------------------------------------
		if (($_SESSION["Pager"]["CurrRangeStart"]/$_SESSION["Pager"]["DisplayedPageRange"]) >=2){
			if ($_SESSION["Pager"]["CurrRangeStart"] <= $_SESSION["Pager"]["DisplayedPageRange"]){
				$_SESSION["Pager"]["showFBIcon"] = false;
			} else {
				$_SESSION["Pager"]["showFBIcon"] = true; }
		}
		else {
			$_SESSION["Pager"]["showFBIcon"] = false;
		}
		// ------------------------------------------
		// Check if we display the Backward Icon
		// ------------------------------------------
		if ($_SESSION["Pager"]["CurrRangeStart"] > $_SESSION["Pager"]["DisplayedPageRange"]){
			$_SESSION["Pager"]["showBIcon"] = true;
			$_SESSION["Pager"]["BackValue"] = ($_SESSION["Pager"]["CurrRangeStart"]-$_SESSION["Pager"]["DisplayedPageRange"]);
		}
		else {
			$_SESSION["Pager"]["showBIcon"] = false;
		}		
	}
	
}
