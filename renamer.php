<?php

/* get the requested action */
$get_action	= filter_input( INPUT_GET, 'action', FILTER_SANITIZE_STRING );

/* if the query string has action=db */
if ( $get_action == 'db' )
{
	/* connect to the DB */
	try
	{
		$database	= 'my_database';
		$username 	= 'mysql_user';
		$password 	= 'myPassword123';
		$conn 		= new PDO( 'mysql:host=localhost;dbname=' . $database, $username, $password );
		$conn->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	}
	catch( PDOException $e )
	{
		echo 'ERROR: ' . $e->getMessage( );
		$conn = false;
	}

	if ( $conn )
	{
		/* get the records */
		$data = $conn->query( 'SELECT * FROM files_table' );

		foreach( $data as $row )
		{
			$new_uri = rename_file_path( $row[ 'uri' ], true );
			$new_fn = rename_file_path( $row[ 'filename' ] );
			$stmt = $conn->prepare( 'UPDATE files_table SET uri = :uri, filename = :filename WHERE id = :id' );
			$stmt->execute(
				array(
					':id' => $row[ 'id' ],
					':uri' => $new_uri,
					':filename'	=> $new_fn
				)
			);
			echo 'URI -> ' . $new_uri . "\n<br />";
		}
	}
} else { /* otherwise, run the file renamer on the files in the current directory */
	/**
	 * file renamer - to comply with Amazon S3 policies against spaces in filenames
	 */
	$dir = './';
	$dhandle = opendir( $dir );
	$old_files = array( );

	if ( $dhandle )
	{
		while (false !== ( $fname = readdir( $dhandle ) ) ) {
			if ( ( $fname != '.' ) && ( $fname != '..' ) && ! is_dir( './' . $fname ) ){
				$old_files[ ] = $fname;
			}
		}
		closedir( $dhandle );
	}

	foreach( $old_files as $file )
	{
		$new_name = rename_file_path( $file );
		rename( "./" . $file, "./" . $new_name );
		echo 'FILE -> ' . $new_name . "\n<br />";
	}
}

/**
 * reusable file renaming function
 */
function rename_file_path( $input = null, $full_path = false )
{
	if ( $input )
	{
		$ext		= strtolower( pathinfo( $input, PATHINFO_EXTENSION ) );
		$full_file 	= $full_path ? substr( strrchr( $input, '/' ), 1 ) : $input;
		$filename 	= str_replace( '.' . $ext, '', $full_file );
		$dirname	= str_replace( $filename . '.' . $ext, '', $input );
		$new_name 	= str_replace( '\'', '', strtolower( $filename ) ); /* apostrophes to spaces - lone exception to an underscore since "that_s_" looks bad */
		$new_name 	= str_replace( ' ', '_', strtolower( $new_name ) );
		$new_name 	= preg_replace( '/[^a-z0-9\_]+/i', '_', $new_name ); /* convert everything except case-insensitive alpha-numeric to underscores */
		for ( $i=0;$i<3;$i++ )
		{
			$new_name 	= str_replace( '__', '_', $new_name );
		}
		if ( $ext && $new_name )
		{
			$output 	= $dirname . $new_name . '.' . $ext;
			$output 	= str_replace( '_.', '.', $output ); /* remove last underscore before extension, if there is one */
			return $output;
		}
	}
	return false;
}
