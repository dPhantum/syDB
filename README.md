
<body style="margin-left: 50px" class="block-center">

<div class="alert alert-danger" role="alert">Documentation is currently is being written</div>

<h1>syDB ORM for Sybase SQLAnywhere version 12 and below</h1>

<p>For those poor weary souls who have to develop with the ancient version of SQLAnywhere version 12 and below, 
there is hope for you! This code was developed out of the need that there were no modern ORM's out there that supported 
that old dinosaur version of SQLAnywhere, and to overcome the serious technical flaw that the database does version 
did not support, namely, scrolling cursors. That version of the database does support the LIMIT clause as modern databases 
due today. But be of good cheer syDB is here!</p>

<h2>Modern Things in and Old Thing</h2>
<p>So, to relieve the pain of having to use version 12 and below of Sybase in PHP, syDB was born.
</p>

<p>
Let's not waste anytime, let's just see what it can do on top of those old clunkity versions of Sybase.
</p><br>


<blockquote>
Good advice is sometimes confusing, but example is always clear.
<p>Guy Doud [Teacher of the Year]</>
</blockquote>
<br>

<h2>Prerequisits</h2>
<p>
	This ORM relies upon the PHP odbc library. You must define DSN entries in your system that correspond to the 
	DSN name enteries in the <code>environment.php</code> file. This file contains all your connect definitions 
	and other critical defintions.
	You may optionally define multiple DSN entries for you application, and communicate/pass data 
	between the two. The default DSN definition is the environment.php file and will be used if not connection
	parameter is assigned when instantiating the ORM. 
	</p>
	<p>
	For example, when instantiating the ORM with the following <code>connection => "READ-ONLY"</code> it will
	use the DSN definition <code>READ_ONLY_DSN</code>in the environment file. This can be because you are using
	a replicated database for load balancing or extra security tha would prohibit any accidental updating of 
	data. 
</p>

<h2>Simplest Usage</h2>
<p>Here is the simplest usage that you could possible have with syDB ORM, although not recommeded,
because it does not take advantage of implicit bind variables to prevent sql injection, bet here it is:</p>


<pre><code>

$dog = new syDB(array(
		query => "SELECT * FROM CANINES WHERE kind='dog' AND breed='chihuahua'"
	));
	
echo "The breed ".
	$dog->name." weighs ".
	$dog->weight." pnds standing ".
	$dog->height." inches.";	
	
</code></pre>
<p>
	Very easy! Essentially one line of code. By default the ORM will return one record, but if within and iteration it will 
	continue to fetch, although a prefetch number can be specified. But in this example, were 
	using the default of a single record fetched. 
</p>

<h3>Security First - Bind variables</h3>
<p>
For security reasons, bind variables should be used. And syDB make it easy, let's take the previous example and 
use bind variables instead. Bind variables can be applied in two differenct ways, in "raw SQL", or implicitly 
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
			'breed => 'chihuahua'
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
	interchangably.
</p>



<h2>Chaining Results</h2>
<p>
	In this example we are going show how to chain results. We will make a query if a user records exists,
	use the method <code>hasResult() to determine if it is not an empty data set, and take some action,
	and all that within a single "IF" statement, yes indeed, very sweet!</code> 
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
The hasResult() will return a boolean true if data was found or false if its empty. 
</p>




<h2>While Loop Example</h2>
<p>
Sometimes you want to fetch all records till the end, for application meta-data for example with
short lists. syDB can be used and an iterator to accomplish this.
</p>

<code><pre>

$Fruits = new ORM(array(
   		object => 'trees',
   		where => array(
   			'tree_type'=>'fruit bearing')
		));

	while ($RemarkType->Fetch()) {
		$Fruit[] = $RemarkType->FruitName;
	}

</pre>
</code>

<h2>Foreach Loop Example</h2>
<code><pre>
$Fruits = new ORM(array(
   		object => 'trees',
   		where => array('tree_type'=>'fruit bearing')
));

foreach ($Fruits as $indx => $fruit ) {
	$Fruit[] = $fruit->fruit_name;
}

</pre>
</code>

<h2>Join Methods</h2>
<p>
		Now let's jump right into an advanced query that has multiple joins, custom column selections,
		and conditions and see how simple it is:
</p>

<pre><code>
		$select = "SELECT ...(somthing really advanced here, like case statements etc)... ";
		// -------------------------------------------------------
        // Build the criteria parameters for the object mapping 
		// -------------------------------------------------------
		$params = array(
				connection => "READ-ONLY", // Use a custom DSN entries in the environment.php file 
         		object => 'users u', // table drive with alias 'u' given
         		select => $select,
         		leftJoin => array(
         				'accounts_details a' 	=> 	array('u.account_id' => 'a.id'),
         				'roles r' 		=>  array('u.role_id' => 'r.id')
         				),
         		rightJoin = array(
         				'photos p' 		=> 	array('u.photo_id' => 'p.id'),
         				'comments c' => 	array('u.id' => 'c.user_id')
         				),
         		outerJoin = array(
         				'orders o' => 	array('u.id' => 'o.user_id'),
         				'clearances cl' => 	array('u.id' => 'cl.user_id')
         		),
         		conditions => array(
         			'user_id => '12345',
         			'SubString(Zip, 1, 5)' => $Zip, // perform function on the column for comparison
					'Inactive' => array('!=' => 1)) // specify operator override
         		),
         		orderBy => array(
							"create_date"=> "DESC", 
							"create_time" => "DESC")
         		schema => 'hr', // attach the tables to the following schema
         		database => 'corporate_db', // use a different database than default
         		readOnly => true, // do not allow this object (users) to be update/mutated
         		paginate => true  // build the pagination bar with links
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
 building of the right/left/outer join requirments without you worrying about SQL syntax. yes sir!
 In the above example, a user defined select clause was passed, instead of the 1:1 column mapping 
 done automatically for you. Table name alias are permitted to be added also (i.e. 'users u' where u is the alias).
 You can ensure that nothing is accidently updated by use of the <code>readOnly</code> parameter
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
					.... and so forth
					
			";
	$stuff = new syDB(array(
		query => $query,
		bindVariables = array('Awesome','Wonderful')
	));
</code></pre>

<p>
	You have ultimate control, nice!
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
	array(raw => $rawQuery1), // passthrough query 1 - creates temp table HawbIds
	array(raw => $rawQuery2), // passthrough query 2 - narrow down the results
	array(raw => $query), // set the final data set as a temp table with the correct sorting, and ensure order query with sort order next
	array(query => $finalQuery) // Actual query to fetch data
  );
$queryParams[orderBy] = array('col1' => 'ASC', 'col2' => 'DESC')
$queryParams[paginate] = true;  
  
$myData = new ORM($queryParams);

foreach ($hawbs as $i => $row){
	... doe something great with this data ...
} 

</code></pre>

<p>
	That is the power and flexlibility of the syDB ORM, but yet there is much more.
</p>

<h2>Messaging or Metadata Assignment</h2>
<p>
	Unique to this ORM is the ability to assign meta-data or messages to the ORM object
	directly which does not affect the cloned copy of the database records.
	Hence, when passing the object by reference to other member function that perform
	some business logic, operation qualifiers can be assigned to the object to carry back
	messages or information with altering or affect the database cloned record.
	You would reference the objects member variable like normal but prefix it with
	"xmeta", and the ORM will assign it as meta-data for messaging. Sweet!
	Let's show an example here:
</p>

<pre>
<code>
	$user = new syDB(array(object => 'users'));
	ShowSomeClass::processIt($user); // by reference parameter
	
	.... meanwhile back at the ShowSomeClass class
	static public function ShowSomeClass(&$user){
		if (checkifsomethingstinks($user))
			$user->xmetaStatus = 'foul breath';
		else
			$user->xmetaStatus = 'Roses';
			
		return;
	}
	... returning back to the main body ...
	
	if ($user->xmetaStatus!='Roses'){
		throw Exception('this guy stinks');
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
Well, we already have seen a taste of the read operations. Now let's do data editing and creation
</p>

<h3>Creation/Inserts</h3>
<p>Life made very simple for adding new record.</p>
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



<h2>Update Operations</h2>
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


<h3>More Documentation coming</h3>
<p>
</p>

<pre><code>
</code></pre>

<p>
</p>


</body>
</html>
