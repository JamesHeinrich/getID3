<?php

namespace JamesHeinrich\GetID3\Module\Misc;

use JamesHeinrich\GetID3\Module\Handler;

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.misc.gpx.php                                        //
// module for analyzing gpx files                             //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

class Gpx extends Handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$info['fileformat'] = 'gpx';

		$this->error('gpx parsing not enabled in this version of getID3()');
		return false;

	}

}
