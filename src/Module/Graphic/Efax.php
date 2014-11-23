<?php

namespace JamesHeinrich\GetID3\Module\Graphic;

use JamesHeinrich\GetID3\Utils;

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
//          also https://github.com/JamesHeinrich/getID3       //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.archive.efax.php                                     //
// module for analyzing eFax files                             //
//                                                            ///
/////////////////////////////////////////////////////////////////

class Efax extends \JamesHeinrich\GetID3\Module\Handler
{

	public function Analyze() {
		$info = &$this->getid3->info;

		$this->fseek($info['avdataoffset']);
		$efaxheader = $this->fread(1024);

		$info['efax']['header']['magic'] = substr($efaxheader, 0, 2);
		if ($info['efax']['header']['magic'] != "\xDC\xFE") {
			$info['error'][] = 'Invalid eFax byte order identifier (expecting DC FE, found '.Utils::PrintHexBytes($info['efax']['header']['magic']).') at offset '.$info['avdataoffset'];
			return false;
		}
		$info['fileformat'] = 'efax';

		$info['efax']['header']['filesize'] = Utils::LittleEndian2Int(substr($efaxheader, 2, 4));
		if ($info['efax']['header']['filesize'] != $info['filesize']) {
			$info['error'][] = 'Probable '.(($info['efax']['header']['filesize'] > $info['filesize']) ? 'truncated' : 'corrupt').' file, expecting '.$info['efax']['header']['filesize'].' bytes, found '.$info['filesize'].' bytes';
		}
		$info['efax']['header']['software1'] =                        rtrim(substr($efaxheader,  26, 32), "\x00");
		$info['efax']['header']['software2'] =                        rtrim(substr($efaxheader,  58, 32), "\x00");
		$info['efax']['header']['software3'] =                        rtrim(substr($efaxheader,  90, 32), "\x00");

		$info['efax']['header']['pages']      = Utils::LittleEndian2Int(substr($efaxheader, 198, 2));
		$info['efax']['header']['data_bytes'] = Utils::LittleEndian2Int(substr($efaxheader, 202, 4));

$info['error'][] = 'eFax parsing not enabled in this version of getID3() ['.$this->getid3->version().']';
return false;

		return true;
	}

}
