<?php

namespace JamesHeinrich\GetID3\Module\Archive;

use JamesHeinrich\GetID3\Module\Handler;
use JamesHeinrich\GetID3\Utils;

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.archive.xz.php                                       //
// module for analyzing XZ files                               //
//                                                            ///
/////////////////////////////////////////////////////////////////

class Xz extends Handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$this->fseek($info['avdataoffset']);
		$xzheader = $this->fread(6);

		// https://tukaani.org/xz/xz-file-format-1.0.4.txt
		$info['xz']['stream_header']['magic'] = substr($xzheader, 0, 6);
		if ($info['xz']['stream_header']['magic'] != "\xFD".'7zXZ'."\x00") {
			$this->error('Invalid XZ stream header magic (expecting FD 37 7A 58 5A 00, found '.Utils::PrintHexBytes($info['xz']['stream_header']['magic']).') at offset '.$info['avdataoffset']);
			return false;
		}
		$info['fileformat'] = 'xz';
		$this->error('XZ parsing not enabled in this version of getID3() ['.$this->getid3->version().']');
		return false;

	}

}
