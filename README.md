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
Let's not waste anytime, let's just see what it can do on top of that old clunkity version of Sybase.
</p><br>


<quote>
Good advice is sometimes confusing, but example is always clear.
<p>Guy Doud [Teacher of the Year]</>
</quote>
<br>
<code>
<pre>
// verify that the AccountID exists
	if (!(new syDB(array(
			object => 'Users',
			where => array('id' => $user_id)
	)))->hasResult()){
		header("location: ../?send=them&to=prison");
		exit();
	}
</pre>
</code>

<p>
 Pretty awsome! A few lines of code and you have checked if user id exists, and safely, because it is using parameter binding
 behind the scene, so, that means... no sql injection to break you here. OK so what else? Let's look at lots of examples here...
</p>

