<?php

use JamesHeinrich\GetID3;

require __DIR__ . "/../vendor/autoload.php";

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//                                                             //
// /demo/demo.cache.mysql.php - part of getID3()               //
// Sample script demonstrating the use of the DBM caching      //
// extension for getID3()                                      //
//  see readme.txt for more details                            //
//                                                            ///
/////////////////////////////////////////////////////////////////

die('For security reasons, this demo has been disabled. It can be enabled by removing line '.__LINE__.' in demos/'.basename(__FILE__));

$getID3 = new GetID3\Cache\Mysql('localhost', 'database', 'username', 'password');

$r = $getID3->analyze('/path/to/files/filename.mp3');

echo '<pre>';
var_dump($r);
echo '</pre>';

// uncomment to clear cache
//$getID3->clear_cache();
