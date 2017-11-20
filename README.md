
<div class="md-readme">


<h1>syDB ORM for Sybase SQLAnywhere in PHP</h1>

<p>
	For those poor weary souls who have to develop with the ancient version of SQLAnywhere version 12 and below, 
	there is hope for you! This code was developed out of the need that there were no modern ORM's out there that supported 
	that old dinosaur version of SQLAnywhere, and to overcome the serious technical flaws that the database version 
	did not support, namely, scrolling cursors. That version of the database does support the
	 <code>LIMIT</code> clause as modern databases due today. But be of good cheer syDB is here!	 
</p>

<h2>Modern Things in and Old Thing</h2>
<p>
	So, to relieve the pain of having to use version 12 and below of Sybase in PHP, syDB was born.
</p>

<p>
	Let's not waste anytime, let's just see what it can do on top of those old clunky versions of Sybase.
</p><br>


<blockquote>
Good advice is sometimes confusing, but example is always clear.<br>
<i>Guy Doud [Teacher of the Year]</i>
</blockquote>
<br>

<h2>Prerequisites</h2>
<p>
	This ORM relies upon the PHP odbc library. You must define DSN entries in your system that correspond to the 
	DSN name entries in the <code>environment.php</code> file. This file contains all your connect definitions 
	and other critical definitions.
	You may optionally define multiple DSN entries for you application, and communicate/pass data 
	between the two. The default DSN definition is the environment.php file and will be used if not connection
	parameter is assigned when instantiating the ORM. 
</p>
<p>
	For example, when instantiating the ORM with the following <code>connection => "READ-ONLY"</code> it will
	use the DSN definition <code>READ_ONLY_DSN</code>in the environment file. This can be because you are using
	a replicated database for load balancing or extra security that would prohibit any accidental updating of 
	data. 
</p>

<h2>Simplest Usage</h2>
<p>
	Here is the simplest usage that you could possible have with syDB ORM, although not recommended,
	because it does not take advantage of implicit bind variables to prevent sql injection, bet here it is:
</p>


<pre><code>

$dog = new syDB(array(
		query => "SELECT * FROM CANINES WHERE kind='dog' AND breed='chihuahua'"
	));
	
echo "The breed ".$dog->breed
	." weighs ".$dog->weight." pnds "
	." standing ".$dog->height." inches, and is actually a rat and not a dog.";	
	
</code></pre>
<p>
	Very easy! Essentially one line of code. By default the ORM will return one record, but if within an iteration it will 
	continue to fetch, although a pre-fetch number can be specified. But in this example, were 
	using the default of a single record fetched. 
</p>

<h3>Security First - Bind variables</h3>
<p>
For security reasons, bind variables should be used. And syDB make it easy, let's take the previous example and 
use bind variables instead. Bind variables can be applied in two different ways, in "raw SQL", or implicitly 
when using the OOP pattern of requests.
</p>

<b>Raw SQL method</b>
<pre><code>
$dog = new syDB(array(
		query => "SELECT * FROM CANINES WHERE kind=? AND breed=?",
		bindVariables => array(
			'dog', 
			'chihuahua'
		)
	));
	$var = $dog->name;
	...
</code></pre>

<p>
	Very sweet and simple! Now the previous sql is more secure.
</p>

<b>OOP method</b>
<pre><code>
$dog = new syDB(array(
		object => "CANINES",
		where => array(
			'kind' => 'dog',
			'breed' => 'chihuahua'
		)
	));
$var = $dog->breed;
</code></pre>

<p>
	Even sweeter, and simpler! NoSQL coming to mind? Yes, syDB is an no SQL interface. Using methods for 
	rapid development.
	No hassle of create connections writing sql binding the variables, executing and then fetching. 
</p>
<p>
	Also implicitly handled for you is the bind operations for all conditional assignments used in the
	"where" clause in the above example. The use of the term "where" and "conditions" can be used 
	interchangeably.
</p>



<h2>Chaining Results</h2>
<p>
	In this example we are going show how to chain results. We will make a query if a user records exists,
	use the method <code>hasResult()</code> to determine if it is not an empty data set, and take some action,
	and all that within a single "IF" statement, yes indeed, very sweet! 
</p>
<pre><code>
// verify that the user ID exists
if (!(new syDB(array(
		object => 'users',
		where => array('id' => $user_id)
)))->hasResult()){
	header("location: http://fbi.gov/?send=them&to=prison");
	exit();
}
</code></pre>
<p>
	The <code>hasResult()</code> will return a boolean true if data was found or false if its empty. 
</p>




<h2>While Loop Example</h2>
<p>
	Sometimes you want to fetch all records till the end, for application meta-data for example with
	short lists. syDB can be used and an iterator to accomplish this.
</p>

<pre><code>

$Fruits = new ORM(array(
	object => 'trees',
	where => array(
		'tree_type'=>'fruit bearing')
));

while ($Fruits->Fetch()) {
	$Fruit[] = $Fruits->FruitName;
}

// If you are getting a finite set of rows and know the value
// You don't need to iterate to gather the array
// You accomplish the same ends by the following
$Fruits = new ORM(array(
	object => 'trees',
	where => array(
		'tree_type'=>'fruit bearing'),
	rows => 100  // Tell the ORM how many rows you want
));
// Now simply ask for them in an array
$Fruits = $Fruits->getData(); // No iteration needed! sweet!

</code>
</pre>
<p>
	When you specificy the <code>rows</code> parameter you don't need to iterate
	the data will be pre-fetched into and internal buffer and given by the 
	<code>getData()</code> function. Making your code very conscise, and
	avoid additional iterations.
</p>

<h2>Foreach Loop Example</h2>
<pre><code>

$Fruits = new ORM(array(
	object => 'trees',
	where => array('tree_type'=>'fruit bearing')
));


foreach ($Fruits as $indx => $fruit ) {
		$Fruit[] = $fruit->fruit_name;
}

</code>
</pre>

<h2>Join Methods</h2>
<p>
		Now let's jump right into an advanced query that has multiple joins, custom column selections,
		and conditions and see how simple it is:
</p>

<pre><code>
$select = "SELECT ...(something really advanced here, like case statements etc)... ";
// -------------------------------------------------------
// Build the criteria parameters for the object mapping 
// -------------------------------------------------------
$params = array(
	connection => "READ-ONLY", // Use a custom DSN entries in the environment.php file 
	object => 				'users u', // table drive with alias 'u' given
	select => 				$select,
	leftJoin => 			array(
		'accounts_details a' => 	
							array('u.account_id' => 'a.id'),
		'roles r' =>		array('u.role_id' => 'r.id')
	),
	rightJoin = array(
		'photos p' =>		array('u.photo_id' => 'p.id'),
		'comments c' =>		array('u.id' => 'c.user_id')
	),
	outerJoin = array(
		'orders o' =>		array('u.id' => 'o.user_id'),
		'clearances cl' =>	array('u.id' => 'cl.user_id')
	),
	conditions => array(
		'user_id => '12345',
		'SubString(Zip, 1, 5)' => $Zip, // perform function on the column for comparison
		'Inactive' =>		array('!=' => 1)) // specify operator override
	),
	orderBy => array(
		"create_date" =>	"DESC", 
		"create_time" =>	"DESC"
	),
	schema => 				'hr', // attach the tables to the following schema
	database => 			'corporate_db', // use a different database than default
	readOnly => 			true, // do not allow this object (users) to be update/mutated
	paginate => 			true  // build the pagination bar with links
);
               
// -------------------------------------------------- 
// Get the result set for the required parameters
// -------------------------------------------------- 
$userResult = new syDB($params);

if($userResult->hasResult()) {
	while($userResult->Fetch()){
		... do something great with each row here ...
	}
}
else {
	... set some default here if no results found ...
}
</code>
</pre>

 <p>
 Very sweet, no 'relations' to define, no hard coded object definitions etc, it will handle the
 building of the right/left/outer join requirements without you worrying about SQL syntax. yes sir!
 In the above example, a user defined select clause was passed, instead of the 1:1 column mapping 
 done automatically for you. Table name alias are permitted to be added also (i.e. 'users u' where u is the alias).
 You can ensure that nothing is accidentally updated by use of the <code>readOnly</code> parameter
 enforces that the object, if modified is only a read only copy.
 
</p>

<p>
	Still to rigid for you? Well, its still more flexible, you can override any of the clauses with 
	your custom operations, let's quickly look.
</p>

<pre>
<code>

// pass your own raw SQL for the where condition or an array for auto binding
$user = new syDB(array(
	object => 'users'
	where => "username not like '%fathead%' category != upper('WEIRDO')" // raw sql for where 
));

// 
$query ="
	SELECT here.this, and.there, here.that
	FROM here, and, there,
		(SELECT more, colums FROM overthere WHERE good='times are here' AND status= ? ) A
	WHERE
		A.more = here.this
		here.id = ?
		.... and so forth";
		
$stuff = new syDB(array(
	query => $query,
	rows => 100, // Prefetch 100 rows in the the buffer
	bindVariables = array('Awesome','Wonderful')
));

// Forget the iteration, just give me all 100 records prefetched into an array
// and now passit to your view/template for a display iteration
$ArrayOfRecords = $stuff->getData();

</code></pre>

<p>
	You have ultimate control, with the luxury of secure bind variables, nice!
	Here we demonstrate that you can set a Pre-fetch amount by the parameter <code>rows</code>
	for each iteration, the default is one. Save iteration time by pre-populating your buffer.
</p>

<h2>Multiple Statement Processing</h2>

<p>
Now if you really want flexible control, with multiple statement operations. In the scenario where you 
want to build temporary tables and optimize the process time for large data set by creating 
temporary join table, you can use the multi-statement query options. Get a load of this:
</p>

<pre>
<code>

$rawQuery1 = "SELECT * INTO #myTempTable WHERE ..."; // Create a subset of a VLDB table
$rawQuery2 = "DELETE FROM #myTempTable WHERE ..."; // Narrow down that temp table further
$query = " SELECT ... INTO #finalData FROM myVeryLargeTable a, #myTempTable b WHERE a.ID = b.ID "; //
$finalQuery = "SELECT * from #finalData";

// Assign all the QUERY requests here
$queryParams[query] = array( // Raw queries do not return any data, but are DML or DCL statments
	array(raw => $rawQuery1), // pass-through query 1 - creates temp table ids
	array(raw => $rawQuery2), // pass-through query 2 - narrow down the results
	array(raw => $query), // set the final data set as a temp table with the correct sorting, and ensure order query with sort order next
	array(query => $finalQuery) // Actual query to fetch data
  );
$queryParams[orderBy] = array('col1' => 'ASC', 'col2' => 'DESC')
$queryParams[paginate] = true;  
  
$myData = new ORM($queryParams);

foreach ($myData as $i => $row){
	... doe something great with this data ...
} 

</code></pre>

<p>
	That is the power and flexibility of the syDB ORM, but yet there is much more.
	You may execute any number of <code>raw</code> queries prior to the <code>query</code>
	parameter, the only restriction is that they will not return any records, only
	one query is permitted to fetch, and that is the <code>query</code> parameter.
	Unlike other ORM's this one doesn't restrict you purely to direct mapping to 
	database objects but also is flexible to serve as a transient database API.
</p>

<h2>Messaging or Metadata Assignment</h2>
<p>
	Unique to this ORM is the ability to assign meta-data or messages to the ORM object
	directly which does not affect the cloned copy of the database records.
	Hence, when passing the object by reference to other member function that perform
	some business logic, operation qualifiers can be assigned to the object to carry back
	messages or information with altering or affect the database cloned record.
	You would reference the objects member variable like normal but prefix it with
	<code>xmeta</code>, and the ORM will assign it as meta-data for messaging. Sweet!
	Let's show an example here:
</p>

<pre>
<code>
$user = new syDB(array(object => 'users'));
ShowSomeClass::processIt($user); // by reference parameter

.... meanwhile back at the ShowSomeClass class
class ShowSomeClass extends SomethingWonderful {
	...
	static public function processIt(&$user){
		if (checkifsomethingstinks($user))
			$user->xmetaStatus = 'foul breath';
		else
			$user->xmetaStatus = 'Roses';
			
		return;
	}
	
... returning back to the main body ...

if ($user->xmetaStatus!='Roses'){
	throw new Exception('this guy stinks');
}
else {
	// You can pass some additional information that can be used for
	// further processing but not actually tied to the user record.
	$user->xmetaMessage ="Display this about the user, 'he smells like roses'";
	Security::createUser($user); // pass by reference to create the user
}
	
</code></pre>

<p>
	You can see that this type of messaging is great when and object is passed by reference
	across many different functions for applying some business logic. I will not affect
	the actual objects cloned data in any way.
</p>


<h2>CRUD Operations</h2>
<p>
	Well, we already have seen a taste of the read operations. Now let's do data editing and creation.
	All the functionality for querying apply to the update and delete operations. These requests
	are internally optimized by the ORM to perform the update operations implicitly by the
	primary key, so you need only change the data definitions and let the ORM update by the key 
	for better performance. How nice is that! As a developer you can just focus on the business
	operation and the ORM take care of the database optimization.
</p>

<h3>Creation/Inserts Operations</h3>
<p>
	Life made very simple for adding new record.
</p>

<pre><code>
$user = new syDB(array(
	'object' => 'users'
));
		
$user->username = "johndoe";
$user->email = "johanan@doey.com";
...
$user->Save();

// For all new records the Primary Key is auto-assigned to the object 
// after insertion, all you need to do is reference it like so:
$UserId = $user->id; 

</code></pre>

<p>
	The ORM will auto assign the auto-incremented or primary key, when the record is created.
	You will no longer have to make a subsequent call to get the key value, it is given to you on a stick! 
</p>



<h3>Update Operations</h3>
<p>
	To update a record, you can fetch as normal and then after making your alterations simply 
	call the Save() function. 
</p>

<pre><code>

$user = new syDB(array(
	object => 'users'
	conditions => array(
		'id' => 123354
	)
));

if ($user->hasResult()){
	// Do and update here
	$user->last_udpated=date('Y-m-d H:i:s');
	$user->Save();
}
else {
	$user->create_date=date('Y-m-d H:i:s');
	$user->name = "Fredrick Furball";
	$user->Save();
}	
	
</code></pre>

<p>
	Notice that the <code>Save()</code> function is used for both the insert and update, this is
	because the ORM keeps track of its state, and will translate the appropriate database action
	when Save is triggered.
</p>
<p>
	If you were to omit the <code>hasResult()</code> function simply assign data and <code>Save()</code>,
	the ORM will create a new row if the record does not exist or update the row if it does. The 
	<code>hasResult()</code> method is shown simply to demonstrate that you have a means of 
	validating the state prior to any modifications or for more granular process flow assignment.
</p>


<h3>Delete Operations</h3>
<p>
	You have the all the functionality of a read operation in qualifying the record to delete. 
	As mentioned the ORM will update or delete based upon the primary key assignment of the 
	table for the most optimal performance. Deleting is as simple of complex as you would have it be. 
</p>


<pre><code>
$user = new syDB(array(
	object => 'users',
	where => array(
		'email' => 'superman@hereos.com'
	)
));

$rowsAffected = $user->Delete(); // goodbye superman :|

// OR could perform the same operation as follows without instantiating the class
$rowsAffected = syDB::Delete(array(
	object => 'users',
	where => array(
		'email' => 'superman@hereos.com'
	)
));

</code></pre>



<h2>Pagination</h2>
<p>
	The syDB ORM communicates with the helper class <code>Paginator</code>, and assigns all the database related
	pagination data. This allow you the freedom of not having to be concerned about page movement
	and pagination selection.
	Those of you who have been using these old versions of Sybase know what a pain it is to work
	in a modern way with pagination for the web. Well, we got your back covered with this one. All you
	need to do to create the pagination bar is one line of code: 
</p>

<pre><code>

$users = new syDB(array(
	object =>'users'
	conditions => array(
		'status' => 'active'
	),
	pagination => true; // now the pagination bar is created and ready for you to use!
));

..... some where else in a template or view far far away in a wanna be MVC world you simply call one function ...
Paginator::getPager(); // this will generate the code for a bootstrap rendered pagination bar
	
</code></pre>

<p>
	When you set the <code>pagination</code> parameter, do not user the <code>rows</code> parameter
	they are mutually exclusive. You control the number of displayed row by setting the 
	<code>Pager_RowsPerPage</code> parameter described below. The ORM works synergistically 
	with the <code>Paginator</code> Class so that you do not have to keep track of where you are in the 
	page selections. The <code>Paginator</code> Class uses a caching mechanism to keep the status persistent. 
</p>

<p>
	The pagination values are controlled in the environment.php file by the following values:
</p>
<pre><code>
CONST Pager_RowsPerPage = 10;	// Controls how many records are displayed on each page
CONST Pager_PagesPerBar = 6;	// Controls displayed page ranges in the paginator bar
</code></pre>



<h2>Joining Relations</h2>
<h3>Column Join Equality</h3> 

<p>
	Column comparison must have the table qualifier for example if in a <code>NATURAL JOIN</code> condition.
</p>


<p>
	The following <code>WHERE</code> conditions <code>WHERE ColumnA = ColumnB</code> where <code>ColumnA</code> is 
	from <code>TableA</code> and <code>ColumnB</code> is from <code>TableB</code>
 </p>
 
<p>
	These phrases must be written as:
</p>

<pre><code>
objects => array('TableA', 'TableB'),
conditions => array( 'TableA.ColumnA' => 'TableB.ColumnB')
</code></pre>

<p>
	And for comparisons other than <code>=</code> operator then specify the operator with the value/column
</p>

<pre><code>
conditions => array( 'TableA.ColumnA' => array('>=' => 'TableB.ColumnB'))
</code></pre>


<p>
	For <code>SELF JOINS</code> and simple column comparison, the same also applies.<br>
	For example, in raw SQL the following <code>WHERE</code> condition: 
</p>

<pre><code>
WHERE Column1 = Column1
</code></pre>

<p>
	Should be done as follows with the ORM:
</p>

<pre><code>
conditions' => array('TableName.Column1' => 'TableName.Column2')
</code></pre>

<p>
	And again in the case of special operators:
</p>

<pre><code>
conditions => array('TableName.Column1' => array('!=' => 'TableName.Column2'))
</code></pre>

<p>
	Note the use of the parameter <code>object</code> singular vs. the <code>objects</code> plural. 
	This distinction tells the ORM that you are fetching more than one object in the internal buffer.
	When more than one object is merged into the internal buffer, the object implicitly becomes
	"read only", and CRUD operations are not allowed.
</p>


<h3>More Documentation coming</h3>
<p>
	We have only began to scratch the surface of functionality documented here...<br>
	There is also query caching, sql generation (create SQL but not execute), implicit caching for paginator,
	and many other groovy things!<br> 
	more coming...
</p>



</div>
<style>
<!--
.md-readme pre  {
	background-color: #000 !important;
	color: #6eff6e !important;
}
.md-readme code  {
	background-color: #000 !important;
	color: #ffc800 !important;
}
-->
</style>
