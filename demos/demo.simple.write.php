<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
//          also https://github.com/JamesHeinrich/getID3       //
/////////////////////////////////////////////////////////////////
//                                                             //
// /demo/demo.simple.write.php - part of getID3()              //
// Sample script showing basic syntax for writing tags         //
// See readme.txt for more details                             //
//                                                            ///
/////////////////////////////////////////////////////////////////

//die('Due to a security issue, this demo has been disabled. It can be enabled by removing line '.__LINE__.' in '.$_SERVER['PHP_SELF']);

$TextEncoding = 'UTF-8';

require_once('../getid3/getid3.php');
// Initialize getID3 engine
$getID3 = new getID3;
$getID3->setOption(array('encoding'=>$TextEncoding));

require_once('../getid3/write.php');
// Initialize getID3 tag-writing module
$tagwriter = new getid3_writetags;
//$tagwriter->filename = '/path/to/file.mp3';
$tagwriter->filename = 'c:/file.mp3';

//$tagwriter->tagformats = array('id3v1', 'id3v2.3');
$tagwriter->tagformats = array('id3v2.3');

// set various options (optional)
$tagwriter->overwrite_tags = true;
$tagwriter->overwrite_tags = false;
$tagwriter->tag_encoding = $TextEncoding;
$tagwriter->remove_other_tags = true;

// populate data array
$TagData = array(
	'title'         => array('My Song'),
	'artist'        => array('The Artist'),
	'album'         => array('Greatest Hits'),
	'year'          => array('2004'),
	'genre'         => array('Rock'),
	'comment'       => array('excellent!'),
	'track'         => array('04/16'),
	'popularimeter' => array('email'=>'user@example.net', 'rating'=>128, 'data'=>0),
);
$tagwriter->tag_data = $TagData;

// write tags
if ($tagwriter->WriteTags()) {
	echo 'Successfully wrote tags<br>';
	if (!empty($tagwriter->warnings)) {
		echo 'There were some warnings:<br>'.implode('<br><br>', $tagwriter->warnings);
	}
} else {
	echo 'Failed to write tags!<br>'.implode('<br><br>', $tagwriter->errors);
}
