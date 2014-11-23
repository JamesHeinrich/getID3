<?php

namespace JamesHeinrich\GetID3\Module\Audio;

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
// module.audio.aa.php                                         //
// module for analyzing Audible Audiobook files                //
//                                                            ///
/////////////////////////////////////////////////////////////////

class Aa extends \JamesHeinrich\GetID3\Module\Handler
{

	public function Analyze() {
		$info = &$this->getid3->info;

		$this->fseek($info['avdataoffset']);
		$AAheader  = $this->fread(8);

		$magic = "\x57\x90\x75\x36";
		if (substr($AAheader, 4, 4) != $magic) {
			$info['error'][] = 'Expecting "'.Utils::PrintHexBytes($magic).'" at offset '.$info['avdataoffset'].', found "'.Utils::PrintHexBytes(substr($AAheader, 4, 4)).'"';
			return false;
		}

		// shortcut
		$info['aa'] = array();
		$thisfile_aa = &$info['aa'];

		$info['fileformat']            = 'aa';
		$info['audio']['dataformat']   = 'aa';
$info['error'][] = 'Audible Audiobook (.aa) parsing not enabled in this version of getID3() ['.$this->getid3->version().']';
return false;
		$info['audio']['bitrate_mode'] = 'cbr'; // is it?
		$thisfile_aa['encoding']       = 'ISO-8859-1';

		$thisfile_aa['filesize'] = Utils::BigEndian2Int(substr($AUheader,  0, 4));
		if ($thisfile_aa['filesize'] > ($info['avdataend'] - $info['avdataoffset'])) {
			$info['warning'][] = 'Possible truncated file - expecting "'.$thisfile_aa['filesize'].'" bytes of data, only found '.($info['avdataend'] - $info['avdataoffset']).' bytes"';
		}

		$info['audio']['bits_per_sample'] = 16; // is it?
		$info['audio']['sample_rate'] = $thisfile_aa['sample_rate'];
		$info['audio']['channels']    = $thisfile_aa['channels'];

		//$info['playtime_seconds'] = 0;
		//$info['audio']['bitrate'] = 0;

		return true;
	}

}
