<?php

namespace JamesHeinrich\GetID3\Module\AudioVideo;

use JamesHeinrich\GetID3\Module\Handler;

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio.wtv.php                                        //
// module for analyzing WTV (Windows Recorded TV Show)         //
//   audio-video files                                         //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

class Wtv extends Handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$info['fileformat']          = 'wtv';
		$info['video']['dataformat'] = 'wtv';

		$this->error('WTV (Windows Recorded TV Show) files not properly processed by this version of getID3() ['.$this->getid3->version().']');

		return true;
	}

}
