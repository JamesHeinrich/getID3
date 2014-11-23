<?php

use JamesHeinrich\GetID3;

require __DIR__ . "/../vendor/autoload.php";

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
//          also https://github.com/JamesHeinrich/getID3       //
/////////////////////////////////////////////////////////////////
//                                                             //
// /demo/demo.basic.php - part of getID3()                     //
// Sample script showing most basic use of getID3()            //
// See readme.txt for more details                             //
//                                                            ///
/////////////////////////////////////////////////////////////////

die('Due to a security issue, this demo has been disabled. It can be enabled by removing line '.__LINE__.' in '.$_SERVER['PHP_SELF']);


// Initialize getID3 engine
$getID3 = new GetID3\GetID3;

// Analyze file and store returned data in $ThisFileInfo
$ThisFileInfo = $getID3->analyze($filename);

/*
 Optional: copies data from all subarrays of [tags] into [comments] so
 metadata is all available in one location for all tag formats
 metainformation is always available under [tags] even if this is not called
*/
GetID3\Utils::CopyTagsToComments($ThisFileInfo);

/*
 Output desired information in whatever format you want
 Note: all entries in [comments] or [tags] are arrays of strings
 See structure.txt for information on what information is available where
 or check out the output of /demos/demo.browse.php for a particular file
 to see the full detail of what information is returned where in the array
 Note: all array keys may not always exist, you may want to check with isset()
 or empty() before deciding what to output
*/

//echo $ThisFileInfo['comments_html']['artist'][0]; // artist from any/all available tag formats
//echo $ThisFileInfo['tags']['id3v2']['title'][0];  // title from ID3v2
//echo $ThisFileInfo['audio']['bitrate'];           // audio bitrate
//echo $ThisFileInfo['playtime_string'];            // playtime in minutes:seconds, formatted string

/*
 if you want to see ALL the output, uncomment this line:
*/
//echo '<pre>'.htmlentities(print_r($ThisFileInfo, true)).'</pre>';
