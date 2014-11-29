<?php

namespace JamesHeinrich\GetID3\Module\Audio;

use JamesHeinrich\GetID3\Module\AudioVideo\Riff;
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
// module.audio.shorten.php                                    //
// module for analyzing Shorten Audio files                    //
//                                                            ///
/////////////////////////////////////////////////////////////////

class Shorten extends \JamesHeinrich\GetID3\Module\Handler
{

	public function Analyze() {
		$info = &$this->getid3->info;

		$this->fseek($info['avdataoffset']);

		$ShortenHeader = $this->fread(8);
		$magic = 'ajkg';
		if (substr($ShortenHeader, 0, 4) != $magic) {
			$info['error'][] = 'Expecting "'.Utils::PrintHexBytes($magic).'" at offset '.$info['avdataoffset'].', found "'.Utils::PrintHexBytes(substr($ShortenHeader, 0, 4)).'"';
			return false;
		}
		$info['fileformat']            = 'shn';
		$info['audio']['dataformat']   = 'shn';
		$info['audio']['lossless']     = true;
		$info['audio']['bitrate_mode'] = 'vbr';

		$info['shn']['version'] = Utils::LittleEndian2Int(substr($ShortenHeader, 4, 1));

		$this->fseek($info['avdataend'] - 12);
		$SeekTableSignatureTest = $this->fread(12);
		$info['shn']['seektable']['present'] = (bool) (substr($SeekTableSignatureTest, 4, 8) == 'SHNAMPSK');
		if ($info['shn']['seektable']['present']) {
			$info['shn']['seektable']['length'] = Utils::LittleEndian2Int(substr($SeekTableSignatureTest, 0, 4));
			$info['shn']['seektable']['offset'] = $info['avdataend'] - $info['shn']['seektable']['length'];
			$this->fseek($info['shn']['seektable']['offset']);
			$SeekTableMagic = $this->fread(4);
			$magic = 'SEEK';
			if ($SeekTableMagic != $magic) {

				$info['error'][] = 'Expecting "'.Utils::PrintHexBytes($magic).'" at offset '.$info['shn']['seektable']['offset'].', found "'.Utils::PrintHexBytes($SeekTableMagic).'"';
				return false;

			} else {

				// typedef struct tag_TSeekEntry
				// {
				//   unsigned long SampleNumber;
				//   unsigned long SHNFileByteOffset;
				//   unsigned long SHNLastBufferReadPosition;
				//   unsigned short SHNByteGet;
				//   unsigned short SHNBufferOffset;
				//   unsigned short SHNFileBitOffset;
				//   unsigned long SHNGBuffer;
				//   unsigned short SHNBitShift;
				//   long CBuf0[3];
				//   long CBuf1[3];
				//   long Offset0[4];
				//   long Offset1[4];
				// }TSeekEntry;

				$SeekTableData = $this->fread($info['shn']['seektable']['length'] - 16);
				$info['shn']['seektable']['entry_count'] = floor(strlen($SeekTableData) / 80);
				//$info['shn']['seektable']['entries'] = array();
				//$SeekTableOffset = 0;
				//for ($i = 0; $i < $info['shn']['seektable']['entry_count']; $i++) {
				//	$SeekTableEntry['sample_number'] = Utils::LittleEndian2Int(substr($SeekTableData, $SeekTableOffset, 4));
				//	$SeekTableOffset += 4;
				//	$SeekTableEntry['shn_file_byte_offset'] = Utils::LittleEndian2Int(substr($SeekTableData, $SeekTableOffset, 4));
				//	$SeekTableOffset += 4;
				//	$SeekTableEntry['shn_last_buffer_read_position'] = Utils::LittleEndian2Int(substr($SeekTableData, $SeekTableOffset, 4));
				//	$SeekTableOffset += 4;
				//	$SeekTableEntry['shn_byte_get'] = Utils::LittleEndian2Int(substr($SeekTableData, $SeekTableOffset, 2));
				//	$SeekTableOffset += 2;
				//	$SeekTableEntry['shn_buffer_offset'] = Utils::LittleEndian2Int(substr($SeekTableData, $SeekTableOffset, 2));
				//	$SeekTableOffset += 2;
				//	$SeekTableEntry['shn_file_bit_offset'] = Utils::LittleEndian2Int(substr($SeekTableData, $SeekTableOffset, 2));
				//	$SeekTableOffset += 2;
				//	$SeekTableEntry['shn_gbuffer'] = Utils::LittleEndian2Int(substr($SeekTableData, $SeekTableOffset, 4));
				//	$SeekTableOffset += 4;
				//	$SeekTableEntry['shn_bit_shift'] = Utils::LittleEndian2Int(substr($SeekTableData, $SeekTableOffset, 2));
				//	$SeekTableOffset += 2;
				//	for ($j = 0; $j < 3; $j++) {
				//		$SeekTableEntry['cbuf0'][$j] = Utils::LittleEndian2Int(substr($SeekTableData, $SeekTableOffset, 4));
				//		$SeekTableOffset += 4;
				//	}
				//	for ($j = 0; $j < 3; $j++) {
				//		$SeekTableEntry['cbuf1'][$j] = Utils::LittleEndian2Int(substr($SeekTableData, $SeekTableOffset, 4));
				//		$SeekTableOffset += 4;
				//	}
				//	for ($j = 0; $j < 4; $j++) {
				//		$SeekTableEntry['offset0'][$j] = Utils::LittleEndian2Int(substr($SeekTableData, $SeekTableOffset, 4));
				//		$SeekTableOffset += 4;
				//	}
				//	for ($j = 0; $j < 4; $j++) {
				//		$SeekTableEntry['offset1'][$j] = Utils::LittleEndian2Int(substr($SeekTableData, $SeekTableOffset, 4));
				//		$SeekTableOffset += 4;
				//	}
				//
				//	$info['shn']['seektable']['entries'][] = $SeekTableEntry;
				//}

			}

		}

		if (preg_match('#(1|ON)#i', ini_get('safe_mode'))) {
			$info['error'][] = 'PHP running in Safe Mode - backtick operator not available, cannot run shntool to analyze Shorten files';
			return false;
		}

		if (Utils::isWindows()) {

			$RequiredFiles = array('shorten.exe', 'cygwin1.dll', 'head.exe');
			foreach ($RequiredFiles as $required_file) {
				if (!is_readable(Utils::getHelperAppDirectory() . $required_file)) {
					$info['error'][] = Utils::getHelperAppDirectory() . $required_file . ' does not exist';
					return false;
				}
			}
			$commandline = Utils::getHelperAppDirectory() . 'shorten.exe -x "' . $info['filenamepath'] . '" - | ' . Utils::getHelperAppDirectory() . 'head.exe -c 64';
			$commandline = str_replace('/', '\\', $commandline);

		} else {

			static $shorten_present;
			if (!isset($shorten_present)) {
				$shorten_present = file_exists('/usr/local/bin/shorten') || `which shorten`;
			}
			if (!$shorten_present) {
				$info['error'][] = 'shorten binary was not found in path or /usr/local/bin';
				return false;
			}
			$commandline = (file_exists('/usr/local/bin/shorten') ? '/usr/local/bin/' : '' ) . 'shorten -x '.escapeshellarg($info['filenamepath']).' - | head -c 64';

		}

		$output = `$commandline`;

		if (!empty($output) && (substr($output, 12, 4) == 'fmt ')) {
			$fmt_size = Utils::LittleEndian2Int(substr($output, 16, 4));
			$DecodedWAVFORMATEX = Riff::parseWAVEFORMATex(substr($output, 20, $fmt_size));
			$info['audio']['channels']        = $DecodedWAVFORMATEX['channels'];
			$info['audio']['bits_per_sample'] = $DecodedWAVFORMATEX['bits_per_sample'];
			$info['audio']['sample_rate']     = $DecodedWAVFORMATEX['sample_rate'];

			if (substr($output, 20 + $fmt_size, 4) == 'data') {

				$info['playtime_seconds'] = Utils::LittleEndian2Int(substr($output, 20 + 4 + $fmt_size, 4)) / $DecodedWAVFORMATEX['raw']['nAvgBytesPerSec'];

			} else {

				$info['error'][] = 'shorten failed to decode DATA chunk to expected location, cannot determine playtime';
				return false;

			}

			$info['audio']['bitrate'] = (($info['avdataend'] - $info['avdataoffset']) / $info['playtime_seconds']) * 8;

		} else {

			$info['error'][] = 'shorten failed to decode file to WAV for parsing';
			return false;

		}

		return true;
	}

}
