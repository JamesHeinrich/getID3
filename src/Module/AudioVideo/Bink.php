<?php

namespace JamesHeinrich\GetID3\Module\AudioVideo;

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
// module.audio.bink.php                                       //
// module for analyzing Bink or Smacker audio-video files      //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

class Bink extends \JamesHeinrich\GetID3\Module\Handler
{

	public function Analyze() {
		$info = &$this->getid3->info;

$info['error'][] = 'Bink / Smacker files not properly processed by this version of getID3() ['.$this->getid3->version().']';

		$this->fseek($info['avdataoffset']);
		$fileTypeID = $this->fread(3);
		switch ($fileTypeID) {
			case 'BIK':
				return $this->ParseBink();
				break;

			case 'SMK':
				return $this->ParseSmacker();
				break;

			default:
				$info['error'][] = 'Expecting "BIK" or "SMK" at offset '.$info['avdataoffset'].', found "'.Utils::PrintHexBytes($fileTypeID).'"';
				return false;
				break;
		}

		return true;

	}

	public function ParseBink() {
		$info = &$this->getid3->info;
		$info['fileformat']          = 'bink';
		$info['video']['dataformat'] = 'bink';

		$fileData = 'BIK'.$this->fread(13);

		$info['bink']['data_size']   = Utils::LittleEndian2Int(substr($fileData, 4, 4));
		$info['bink']['frame_count'] = Utils::LittleEndian2Int(substr($fileData, 8, 2));

		if (($info['avdataend'] - $info['avdataoffset']) != ($info['bink']['data_size'] + 8)) {
			$info['error'][] = 'Probably truncated file: expecting '.$info['bink']['data_size'].' bytes, found '.($info['avdataend'] - $info['avdataoffset']);
		}

		return true;
	}

	public function ParseSmacker() {
		$info = &$this->getid3->info;
		$info['fileformat']          = 'smacker';
		$info['video']['dataformat'] = 'smacker';

		return true;
	}

}
