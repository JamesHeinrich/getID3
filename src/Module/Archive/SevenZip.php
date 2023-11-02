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
// module.archive.7zip.php                                     //
// module for analyzing 7zip files                             //
//                                                            ///
/////////////////////////////////////////////////////////////////

class SevenZip extends Handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$this->fseek($info['avdataoffset']);
		$z7header = $this->fread(32);

		// https://py7zr.readthedocs.io/en/latest/archive_format.html
		$info['7zip']['header']['magic'] = substr($z7header, 0, 6);
		if ($info['7zip']['header']['magic'] != '7z'."\xBC\xAF\x27\x1C") {
			$this->error('Invalid 7zip stream header magic (expecting 37 7A BC AF 27 1C, found '.Utils::PrintHexBytes($info['7zip']['header']['magic']).') at offset '.$info['avdataoffset']);
			return false;
		}
		$info['fileformat'] = '7zip';

		$info['7zip']['header']['version_major']      = Utils::LittleEndian2Int(substr($z7header,  6, 1)); // always 0x00 (?)
		$info['7zip']['header']['version_minor']      = Utils::LittleEndian2Int(substr($z7header,  7, 1)); // always 0x04 (?)
		$info['7zip']['header']['start_header_crc']   = Utils::LittleEndian2Int(substr($z7header,  8, 4));
		$info['7zip']['header']['next_header_offset'] = Utils::LittleEndian2Int(substr($z7header, 12, 8));
		$info['7zip']['header']['next_header_size']   = Utils::LittleEndian2Int(substr($z7header, 20, 8));
		$info['7zip']['header']['next_header_crc']    = Utils::LittleEndian2Int(substr($z7header, 28, 4));

$this->error('7zip parsing not enabled in this version of getID3() ['.$this->getid3->version().']');
		return false;

	}

}
