<?php

namespace JamesHeinrich\GetID3\Module\Misc;

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.misc.par2.php                                        //
// module for analyzing PAR2 files                             //
//                                                            ///
/////////////////////////////////////////////////////////////////

use JamesHeinrich\GetID3\Module\Handler;

class Par2 extends Handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$info['fileformat'] = 'par2';

		$this->error('PAR2 parsing not enabled in this version of getID3()');
		return false;

	}

}
