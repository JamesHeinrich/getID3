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
// module.misc.pdf.php                                         //
// module for analyzing PDF files                              //
//                                                            ///
/////////////////////////////////////////////////////////////////

class Pdf extends Handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$info['fileformat'] = 'pdf';

		$this->error('PDF parsing not enabled in this version of getID3() ['.$this->getid3->version().']');
		return false;

	}

}
