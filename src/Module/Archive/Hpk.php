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
// module.archive.hpk.php                                      //
// module for analyzing HPK files                              //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

class Hpk extends Handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$info['fileformat'] = 'hpk';

		$this->fseek($info['avdataoffset']);
		$HPKheader = $this->fread(36);

		if (substr($HPKheader, 0, 4) == 'BPUL') {

			$info['hpk']['header']['signature']                    =                         substr($HPKheader,  0, 4);
			$info['hpk']['header']['data_offset']                  = Utils::LittleEndian2Int(substr($HPKheader,  4, 4));
			$info['hpk']['header']['fragments_per_file']           = Utils::LittleEndian2Int(substr($HPKheader,  8, 4));
			//$info['hpk']['header']['unknown1']                     = Utils::LittleEndian2Int(substr($HPKheader, 12, 4));
			$info['hpk']['header']['fragments_residual_offset']    = Utils::LittleEndian2Int(substr($HPKheader, 16, 4));
			$info['hpk']['header']['fragments_residual_count']     = Utils::LittleEndian2Int(substr($HPKheader, 20, 4));
			//$info['hpk']['header']['unknown2']                     = Utils::LittleEndian2Int(substr($HPKheader, 24, 4));
			$info['hpk']['header']['fragmented_filesystem_offset'] = Utils::LittleEndian2Int(substr($HPKheader, 28, 4));
			$info['hpk']['header']['fragmented_filesystem_length'] = Utils::LittleEndian2Int(substr($HPKheader, 32, 4));

			$info['hpk']['header']['filesystem_entries'] = $info['hpk']['header']['fragmented_filesystem_length'] / ($info['hpk']['header']['fragments_per_file'] * 8);
			$this->fseek($info['hpk']['header']['fragmented_filesystem_offset']);
			for ($i = 0; $i < $info['hpk']['header']['filesystem_entries']; $i++) {
				$offset = Utils::LittleEndian2Int($this->fread(4));
				$length = Utils::LittleEndian2Int($this->fread(4));
				$info['hpk']['filesystem'][$i] = array('offset' => $offset, 'length' => $length);
			}

$this->error('HPK parsing incomplete (and mostly broken) in this version of getID3() ['.$this->getid3->version().']');

/*
			$filename = '';
			$dirs = array();
			foreach ($info['hpk']['filesystem'] as $key => $filesystemdata) {
				$this->fseek($filesystemdata['offset']);
				$first4 = $this->fread(4);
				if (($first4 == 'LZ4 ') || ($first4 == 'ZLIB')) {
					// actual data, ignore
					$info['hpk']['toc'][$key] = array(
						'filename' => ltrim(implode('/', $dirs).'/'.$filename, '/'),
						'offset'   => $filesystemdata['offset'],
						'length'   => $filesystemdata['length'],
					);
					$filename = '';
					$dirs = array();
				} else {
					$fragment_index = Utils::LittleEndian2Int($first4);
					$fragment_type  = Utils::LittleEndian2Int($this->fread(4)); // file = 0, directory = 1
					$name_length    = Utils::LittleEndian2Int($this->fread(2));
					if ($fragment_type == 1) {
						$dirs[]   = $this->fread($name_length);
					} else {
						$filename = $this->fread($name_length);
					}
				}
			}
*/

		} else {
			$this->error('Expecting "BPUL" at offset '.$info['avdataoffset'].', found "'.Utils::PrintHexBytes(substr($HPKheader, 0, 4)).'"');
			return false;
		}

		return true;
	}

}
