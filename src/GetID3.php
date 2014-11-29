<?php

namespace JamesHeinrich\GetID3;

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
//          also https://github.com/JamesHeinrich/getID3       //
/////////////////////////////////////////////////////////////////
//                                                             //
// Please see readme.txt for more information                  //
//                                                            ///
/////////////////////////////////////////////////////////////////

class GetID3
{
	// public: Settings
	public $encoding        = 'UTF-8';        // CASE SENSITIVE! - i.e. (must be supported by iconv()). Examples:  ISO-8859-1  UTF-8  UTF-16  UTF-16BE
	public $encoding_id3v1  = 'ISO-8859-1';   // Should always be 'ISO-8859-1', but some tags may be written in other encodings such as 'EUC-CN' or 'CP1252'

	// public: Optional tag checks - disable for speed.
	public $option_tag_id3v1         = true;  // Read and process ID3v1 tags
	public $option_tag_id3v2         = true;  // Read and process ID3v2 tags
	public $option_tag_lyrics3       = true;  // Read and process Lyrics3 tags
	public $option_tag_apetag        = true;  // Read and process APE tags
	public $option_tags_process      = true;  // Copy tags to root key 'tags' and encode to $this->encoding
	public $option_tags_html         = true;  // Copy tags to root key 'tags_html' properly translated from various encodings to HTML entities

	// public: Optional tag/comment calucations
	public $option_extra_info        = true;  // Calculate additional info such as bitrate, channelmode etc

	// public: Optional handling of embedded attachments (e.g. images)
	public $option_save_attachments  = true; // defaults to true (ATTACHMENTS_INLINE) for backward compatibility

	// public: Optional calculations
	public $option_md5_data          = false; // Get MD5 sum of data part - slow
	public $option_md5_data_source   = false; // Use MD5 of source file if availble - only FLAC and OptimFROG
	public $option_sha1_data         = false; // Get SHA1 sum of data part - slow
	public $option_max_2gb_check     = null;  // Check whether file is larger than 2GB and thus not supported by 32-bit PHP (null: auto-detect based on PHP_INT_MAX)

	// public: Read buffer size in bytes
	public $option_fread_buffer_size = 32768;

	// Public variables
	public $filename;                         // Filename of file being analysed.
	public $fp;                               // Filepointer to file being analysed.
	public $info;                             // Result array.
	public $memory_limit = 0;

	// Protected variables
	protected $startup_error   = '';
	protected $startup_warning = '';

	const VERSION           = '1.9.9-20141121';
	const FREAD_BUFFER_SIZE = 32768;

	const ATTACHMENTS_NONE   = false;
	const ATTACHMENTS_INLINE = true;

	// public: constructor
	public function __construct() {

		// Check for PHP version
		$required_php_version = '5.3.0';
		if (version_compare(PHP_VERSION, $required_php_version, '<')) {
			$this->startup_error .= 'getID3() requires PHP v'.$required_php_version.' or higher - you are running v'.PHP_VERSION;
			return false;
		}

		// Check memory
		$this->memory_limit = ini_get('memory_limit');
		if (preg_match('#([0-9]+)M#i', $this->memory_limit, $matches)) {
			// could be stored as "16M" rather than 16777216 for example
			$this->memory_limit = $matches[1] * 1048576;
		} elseif (preg_match('#([0-9]+)G#i', $this->memory_limit, $matches)) { // The 'G' modifier is available since PHP 5.1.0
			// could be stored as "2G" rather than 2147483648 for example
			$this->memory_limit = $matches[1] * 1073741824;
		}
		if ($this->memory_limit <= 0) {
			// memory limits probably disabled
		} elseif ($this->memory_limit <= 4194304) {
			$this->startup_error .= 'PHP has less than 4MB available memory and will very likely run out. Increase memory_limit in php.ini';
		} elseif ($this->memory_limit <= 12582912) {
			$this->startup_warning .= 'PHP has less than 12MB available memory and might run out if all modules are loaded. Increase memory_limit in php.ini';
		}

		// Check safe_mode off
		if (preg_match('#(1|ON)#i', ini_get('safe_mode'))) {
			$this->warning('WARNING: Safe mode is on, shorten support disabled, md5data/sha1data for ogg vorbis disabled, ogg vorbos/flac tag writing disabled.');
		}

		if (intval(ini_get('mbstring.func_overload')) > 0) {
			$this->warning('WARNING: php.ini contains "mbstring.func_overload = '.ini_get('mbstring.func_overload').'", this may break things.');
		}

		// Check for magic_quotes_runtime
		if (function_exists('get_magic_quotes_runtime')) {
			if (get_magic_quotes_runtime()) {
				return $this->startup_error('magic_quotes_runtime must be disabled before running getID3(). Surround getid3 block by set_magic_quotes_runtime(0) and set_magic_quotes_runtime(1).');
			}
		}

		// Check for magic_quotes_gpc
		if (function_exists('magic_quotes_gpc')) {
			if (get_magic_quotes_gpc()) {
				return $this->startup_error('magic_quotes_gpc must be disabled before running getID3(). Surround getid3 block by set_magic_quotes_gpc(0) and set_magic_quotes_gpc(1).');
			}
		}

		if ($this->option_max_2gb_check === null) {
			$this->option_max_2gb_check = (PHP_INT_MAX <= 2147483647);
		}
	}

	public function version() {
		return self::VERSION;
	}

	public function fread_buffer_size() {
		return $this->option_fread_buffer_size;
	}


	// public: setOption
	public function setOption($optArray) {
		if (!is_array($optArray) || empty($optArray)) {
			return false;
		}
		foreach ($optArray as $opt => $val) {
			if (isset($this->$opt) === false) {
				continue;
			}
			$this->$opt = $val;
		}
		return true;
	}


	public function openfile($filename) {
		try {
			if (!empty($this->startup_error)) {
				throw new Exception($this->startup_error);
			}
			if (!empty($this->startup_warning)) {
				$this->warning($this->startup_warning);
			}

			// init result array and set parameters
			$this->filename = $filename;
			$this->info = array();
			$this->info['GETID3_VERSION']   = $this->version();
			$this->info['php_memory_limit'] = (($this->memory_limit > 0) ? $this->memory_limit : false);

			// remote files not supported
			if (preg_match('/^(ht|f)tp:\/\//', $filename)) {
				throw new Exception('Remote files are not supported - please copy the file locally first');
			}

			$filename = str_replace('/', DIRECTORY_SEPARATOR, $filename);
			$filename = preg_replace('#(.+)'.preg_quote(DIRECTORY_SEPARATOR).'{2,}#U', '\1'.DIRECTORY_SEPARATOR, $filename);

			// open local file
			//if (is_readable($filename) && is_file($filename) && ($this->fp = fopen($filename, 'rb'))) { // see http://www.getid3.org/phpBB3/viewtopic.php?t=1720
			if ((is_readable($filename) || file_exists($filename)) && is_file($filename) && ($this->fp = fopen($filename, 'rb'))) {
				// great
			} else {
				$errormessagelist = array();
				if (!is_readable($filename)) {
					$errormessagelist[] = '!is_readable';
				}
				if (!is_file($filename)) {
					$errormessagelist[] = '!is_file';
				}
				if (!file_exists($filename)) {
					$errormessagelist[] = '!file_exists';
				}
				if (empty($errormessagelist)) {
					$errormessagelist[] = 'fopen failed';
				}
				throw new Exception('Could not open "'.$filename.'" ('.implode('; ', $errormessagelist).')');
			}

			$this->info['filesize'] = filesize($filename);
			// set redundant parameters - might be needed in some include file
			// filenames / filepaths in getID3 are always expressed with forward slashes (unix-style) for both Windows and other to try and minimize confusion
			$filename = str_replace('\\', '/', $filename);
			$this->info['filepath']     = str_replace('\\', '/', realpath(dirname($filename)));
			$this->info['filename']     = Utils::mb_basename($filename);
			$this->info['filenamepath'] = $this->info['filepath'].'/'.$this->info['filename'];


			// option_max_2gb_check
			if ($this->option_max_2gb_check) {
				// PHP (32-bit all, and 64-bit Windows) doesn't support integers larger than 2^31 (~2GB)
				// filesize() simply returns (filesize % (pow(2, 32)), no matter the actual filesize
				// ftell() returns 0 if seeking to the end is beyond the range of unsigned integer
				$fseek = fseek($this->fp, 0, SEEK_END);
				if (($fseek < 0) || (($this->info['filesize'] != 0) && (ftell($this->fp) == 0)) ||
					($this->info['filesize'] < 0) ||
					(ftell($this->fp) < 0)) {
						$real_filesize = Utils::getFileSizeSyscall($this->info['filenamepath']);

						if ($real_filesize === false) {
							unset($this->info['filesize']);
							fclose($this->fp);
							throw new Exception('Unable to determine actual filesize. File is most likely larger than '.round(PHP_INT_MAX / 1073741824).'GB and is not supported by PHP.');
						} elseif (Utils::intValueSupported($real_filesize)) {
							unset($this->info['filesize']);
							fclose($this->fp);
							throw new Exception('PHP seems to think the file is larger than '.round(PHP_INT_MAX / 1073741824).'GB, but filesystem reports it as '.number_format($real_filesize, 3).'GB, please report to info@getid3.org');
						}
						$this->info['filesize'] = $real_filesize;
						$this->warning('File is larger than '.round(PHP_INT_MAX / 1073741824).'GB (filesystem reports it as '.number_format($real_filesize, 3).'GB) and is not properly supported by PHP.');
				}
			}

			// set more parameters
			$this->info['avdataoffset']        = 0;
			$this->info['avdataend']           = $this->info['filesize'];
			$this->info['fileformat']          = '';                // filled in later
			$this->info['audio']['dataformat'] = '';                // filled in later, unset if not used
			$this->info['video']['dataformat'] = '';                // filled in later, unset if not used
			$this->info['tags']                = array();           // filled in later, unset if not used
			$this->info['error']               = array();           // filled in later, unset if not used
			$this->info['warning']             = array();           // filled in later, unset if not used
			$this->info['comments']            = array();           // filled in later, unset if not used
			$this->info['encoding']            = $this->encoding;   // required by id3v2 and iso modules - can be unset at the end if desired

			return true;

		} catch (\Exception $e) {
			$this->error($e->getMessage());
		}
		return false;
	}

	// public: analyze file
	public function analyze($filename) {
		try {
			if (!$this->openfile($filename)) {
				return $this->info;
			}

			// Handle tags
			foreach (array('id3v2'=>'ID3v2', 'id3v1'=>'ID3v1', 'apetag'=>'ApeTag', 'lyrics3'=>'Lyrics3') as $tag_name => $tag_class_name) {
				$option_tag = 'option_tag_'.$tag_name;
				if ($this->$option_tag) {
					try {
						$tag_class = __NAMESPACE__ . "\\Module\\Tag\\" . $tag_class_name;
						$tag = new $tag_class($this);
						$tag->Analyze();
					}
					catch (Exception $e) {
						throw $e;
					}
				}
			}
			if (isset($this->info['id3v2']['tag_offset_start'])) {
				$this->info['avdataoffset'] = max($this->info['avdataoffset'], $this->info['id3v2']['tag_offset_end']);
			}
			foreach (array('id3v1'=>'id3v1', 'apetag'=>'ape', 'lyrics3'=>'lyrics3') as $tag_name => $tag_key) {
				if (isset($this->info[$tag_key]['tag_offset_start'])) {
					$this->info['avdataend'] = min($this->info['avdataend'], $this->info[$tag_key]['tag_offset_start']);
				}
			}

			// ID3v2 detection (NOT parsing), even if ($this->option_tag_id3v2 == false) done to make fileformat easier
			if (!$this->option_tag_id3v2) {
				fseek($this->fp, 0);
				$header = fread($this->fp, 10);
				if ((substr($header, 0, 3) == 'ID3') && (strlen($header) == 10)) {
					$this->info['id3v2']['header']        = true;
					$this->info['id3v2']['majorversion']  = ord($header{3});
					$this->info['id3v2']['minorversion']  = ord($header{4});
					$this->info['avdataoffset']          += Utils::BigEndian2Int(substr($header, 6, 4), 1) + 10; // length of ID3v2 tag in 10-byte header doesn't include 10-byte header length
				}
			}

			// read 32 kb file data
			fseek($this->fp, $this->info['avdataoffset']);
			$formattest = fread($this->fp, 32774);

			// determine format
			$determined_format = $this->GetFileFormat($formattest, $filename);

			// unable to determine file format
			if (!$determined_format) {
				fclose($this->fp);
				return $this->error('unable to determine file format');
			}

			// check for illegal ID3 tags
			if (isset($determined_format['fail_id3']) && (in_array('id3v1', $this->info['tags']) || in_array('id3v2', $this->info['tags']))) {
				if ($determined_format['fail_id3'] === 'ERROR') {
					fclose($this->fp);
					return $this->error('ID3 tags not allowed on this file type.');
				} elseif ($determined_format['fail_id3'] === 'WARNING') {
					$this->warning('ID3 tags not allowed on this file type.');
				}
			}

			// check for illegal APE tags
			if (isset($determined_format['fail_ape']) && in_array('ape', $this->info['tags'])) {
				if ($determined_format['fail_ape'] === 'ERROR') {
					fclose($this->fp);
					return $this->error('APE tags not allowed on this file type.');
				} elseif ($determined_format['fail_ape'] === 'WARNING') {
					$this->warning('APE tags not allowed on this file type.');
				}
			}

			// set mime type
			$this->info['mime_type'] = $determined_format['mime_type'];

			// module requires iconv support
			// Check encoding/iconv support
			if (!empty($determined_format['iconv_req']) && !function_exists('iconv') && !in_array($this->encoding, array('ISO-8859-1', 'UTF-8', 'UTF-16LE', 'UTF-16BE', 'UTF-16'))) {
				$errormessage = 'iconv() support is required for this module ('.$determined_format['include'].') for encodings other than ISO-8859-1, UTF-8, UTF-16LE, UTF16-BE, UTF-16. ';
				if (Utils::isWindows()) {
					$errormessage .= 'PHP does not have iconv() support. Please enable php_iconv.dll in php.ini, and copy iconv.dll from c:/php/dlls to c:/windows/system32';
				} else {
					$errormessage .= 'PHP is not compiled with iconv() support. Please recompile with the --with-iconv switch';
				}
				return $this->error($errormessage);
			}

			// instantiate module class
			$class_name = __NAMESPACE__ . "\\Module\\" . $determined_format['module'];
			$class = new $class_name($this);
			$class->Analyze();
			unset($class);

			// close file
			fclose($this->fp);

			// process all tags - copy to 'tags' and convert charsets
			if ($this->option_tags_process) {
				$this->HandleAllTags();
			}

			// perform more calculations
			if ($this->option_extra_info) {
				$this->ChannelsBitratePlaytimeCalculations();
				$this->CalculateCompressionRatioVideo();
				$this->CalculateCompressionRatioAudio();
				$this->CalculateReplayGain();
				$this->ProcessAudioStreams();
			}

			// get the MD5 sum of the audio/video portion of the file - without ID3/APE/Lyrics3/etc header/footer tags
			if ($this->option_md5_data) {
				// do not calc md5_data if md5_data_source is present - set by flac only - future MPC/SV8 too
				if (!$this->option_md5_data_source || empty($this->info['md5_data_source'])) {
					$this->getHashdata('md5');
				}
			}

			// get the SHA1 sum of the audio/video portion of the file - without ID3/APE/Lyrics3/etc header/footer tags
			if ($this->option_sha1_data) {
				$this->getHashdata('sha1');
			}

			// remove undesired keys
			$this->CleanUp();

		} catch (\Exception $e) {
			$this->error('Caught exception: '.$e->getMessage());
		}

		// return info array
		return $this->info;
	}


	// private: error handling
	public function error($message) {
		$this->CleanUp();
		if (!isset($this->info['error'])) {
			$this->info['error'] = array();
		}
		$this->info['error'][] = $message;
		return $this->info;
	}


	// private: warning handling
	public function warning($message) {
		$this->info['warning'][] = $message;
		return true;
	}


	// private: CleanUp
	private function CleanUp() {

		// remove possible empty keys
		$AVpossibleEmptyKeys = array('dataformat', 'bits_per_sample', 'encoder_options', 'streams', 'bitrate');
		foreach ($AVpossibleEmptyKeys as $dummy => $key) {
			if (empty($this->info['audio'][$key]) && isset($this->info['audio'][$key])) {
				unset($this->info['audio'][$key]);
			}
			if (empty($this->info['video'][$key]) && isset($this->info['video'][$key])) {
				unset($this->info['video'][$key]);
			}
		}

		// remove empty root keys
		if (!empty($this->info)) {
			foreach ($this->info as $key => $value) {
				if (empty($this->info[$key]) && ($this->info[$key] !== 0) && ($this->info[$key] !== '0')) {
					unset($this->info[$key]);
				}
			}
		}

		// remove meaningless entries from unknown-format files
		if (empty($this->info['fileformat'])) {
			if (isset($this->info['avdataoffset'])) {
				unset($this->info['avdataoffset']);
			}
			if (isset($this->info['avdataend'])) {
				unset($this->info['avdataend']);
			}
		}

		// remove possible duplicated identical entries
		if (!empty($this->info['error'])) {
			$this->info['error'] = array_values(array_unique($this->info['error']));
		}
		if (!empty($this->info['warning'])) {
			$this->info['warning'] = array_values(array_unique($this->info['warning']));
		}

		// remove "global variable" type keys
		unset($this->info['php_memory_limit']);

		return true;
	}


	// return array containing information about all supported formats
	public function GetFileFormatArray() {
		static $format_info = array();
		if (empty($format_info)) {
			$format_info = array(

				// Audio formats

				// AC-3   - audio      - Dolby AC-3 / Dolby Digital
				'ac3'  => array(
							'pattern'   => '^\x0B\x77',
							'module'    => 'Audio\\Ac3',
							'mime_type' => 'audio/ac3',
						),

				// AAC  - audio       - Advanced Audio Coding (AAC) - ADIF format
				'adif' => array(
							'pattern'   => '^ADIF',
							'module'    => 'Audio\\Aac',
							'mime_type' => 'application/octet-stream',
							'fail_ape'  => 'WARNING',
						),

/*
				// AA   - audio       - Audible Audiobook
				'aa'   => array(
							'pattern'   => '^.{4}\x57\x90\x75\x36',
							'module'    => 'Audio\\Aa',
							'mime_type' => 'audio/audible',
						),
*/
				// AAC  - audio       - Advanced Audio Coding (AAC) - ADTS format (very similar to MP3)
				'adts' => array(
							'pattern'   => '^\xFF[\xF0-\xF1\xF8-\xF9]',
							'module'    => 'Audio\\Aac',
							'mime_type' => 'application/octet-stream',
							'fail_ape'  => 'WARNING',
						),


				// AU   - audio       - NeXT/Sun AUdio (AU)
				'au'   => array(
							'pattern'   => '^\.snd',
							'module'    => 'Audio\\Au',
							'mime_type' => 'audio/basic',
						),

				// AMR  - audio       - Adaptive Multi Rate
				'amr'  => array(
							'pattern'   => '^\x23\x21AMR\x0A', // #!AMR[0A]
							'module'    => 'Audio\\Amr',
							'mime_type' => 'audio/amr',
						),

				// AVR  - audio       - Audio Visual Research
				'avr'  => array(
							'pattern'   => '^2BIT',
							'module'    => 'Audio\\Avr',
							'mime_type' => 'application/octet-stream',
						),

				// BONK - audio       - Bonk v0.9+
				'bonk' => array(
							'pattern'   => '^\x00(BONK|INFO|META| ID3)',
							'module'    => 'Audio\\Bonk',
							'mime_type' => 'audio/xmms-bonk',
						),

				// DSS  - audio       - Digital Speech Standard
				'dss'  => array(
							'pattern'   => '^[\x02-\x03]ds[s2]',
							'module'    => 'Audio\\Dss',
							'mime_type' => 'application/octet-stream',
						),

				// DTS  - audio       - Dolby Theatre System
				'dts'  => array(
							'pattern'   => '^\x7F\xFE\x80\x01',
							'module'    => 'Audio\\Dts',
							'mime_type' => 'audio/dts',
						),

				// FLAC - audio       - Free Lossless Audio Codec
				'flac' => array(
							'pattern'   => '^fLaC',
							'module'    => 'Audio\\Flac',
							'mime_type' => 'audio/x-flac',
						),

				// LA   - audio       - Lossless Audio (LA)
				'la'   => array(
							'pattern'   => '^LA0[2-4]',
							'module'    => 'Audio\\La',
							'mime_type' => 'application/octet-stream',
						),

				// LPAC - audio       - Lossless Predictive Audio Compression (LPAC)
				'lpac' => array(
							'pattern'   => '^LPAC',
							'module'    => 'Audio\\Lpac',
							'mime_type' => 'application/octet-stream',
						),

				// MIDI - audio       - MIDI (Musical Instrument Digital Interface)
				'midi' => array(
							'pattern'   => '^MThd',
							'module'    => 'Audio\\Midi',
							'mime_type' => 'audio/midi',
						),

				// MAC  - audio       - Monkey's Audio Compressor
				'mac'  => array(
							'pattern'   => '^MAC ',
							'module'    => 'Audio\\Monkey',
							'mime_type' => 'application/octet-stream',
						),

// has been known to produce false matches in random files (e.g. JPEGs), leave out until more precise matching available
//				// MOD  - audio       - MODule (assorted sub-formats)
//				'mod'  => array(
//							'pattern'   => '^.{1080}(M\\.K\\.|M!K!|FLT4|FLT8|[5-9]CHN|[1-3][0-9]CH)',
//							'module'    => 'Audio\\Mod',
//							'option'    => 'mod',
//							'mime_type' => 'audio/mod',
//						),

				// MOD  - audio       - MODule (Impulse Tracker)
				'it'   => array(
							'pattern'   => '^IMPM',
							'module'    => 'Audio\\Mod',
							//'option'    => 'it',
							'mime_type' => 'audio/it',
						),

				// MOD  - audio       - MODule (eXtended Module, various sub-formats)
				'xm'   => array(
							'pattern'   => '^Extended Module',
							'module'    => 'Audio\\Mod',
							//'option'    => 'xm',
							'mime_type' => 'audio/xm',
						),

				// MOD  - audio       - MODule (ScreamTracker)
				's3m'  => array(
							'pattern'   => '^.{44}SCRM',
							'module'    => 'Audio\\Mod',
							//'option'    => 's3m',
							'mime_type' => 'audio/s3m',
						),

				// MPC  - audio       - Musepack / MPEGplus
				'mpc'  => array(
							'pattern'   => '^(MPCK|MP\+|[\x00\x01\x10\x11\x40\x41\x50\x51\x80\x81\x90\x91\xC0\xC1\xD0\xD1][\x20-37][\x00\x20\x40\x60\x80\xA0\xC0\xE0])',
							'module'    => 'Audio\\Mpc',
							'mime_type' => 'audio/x-musepack',
						),

				// MP3  - audio       - MPEG-audio Layer 3 (very similar to AAC-ADTS)
				'mp3'  => array(
							'pattern'   => '^\xFF[\xE2-\xE7\xF2-\xF7\xFA-\xFF][\x00-\x0B\x10-\x1B\x20-\x2B\x30-\x3B\x40-\x4B\x50-\x5B\x60-\x6B\x70-\x7B\x80-\x8B\x90-\x9B\xA0-\xAB\xB0-\xBB\xC0-\xCB\xD0-\xDB\xE0-\xEB\xF0-\xFB]',
							'module'    => 'Audio\\Mp3',
							'mime_type' => 'audio/mpeg',
						),

				// OFR  - audio       - OptimFROG
				'ofr'  => array(
							'pattern'   => '^(\*RIFF|OFR)',
							'module'    => 'Audio\\OptimFrog',
							'mime_type' => 'application/octet-stream',
						),

				// RKAU - audio       - RKive AUdio compressor
				'rkau' => array(
							'pattern'   => '^RKA',
							'module'    => 'Audio\\Rkau',
							'mime_type' => 'application/octet-stream',
						),

				// SHN  - audio       - Shorten
				'shn'  => array(
							'pattern'   => '^ajkg',
							'module'    => 'Audio\\Shorten',
							'mime_type' => 'audio/xmms-shn',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),

				// TTA  - audio       - TTA Lossless Audio Compressor (http://tta.corecodec.org)
				'tta'  => array(
							'pattern'   => '^TTA',  // could also be '^TTA(\x01|\x02|\x03|2|1)'
							'module'    => 'Audio\\Tta',
							'mime_type' => 'application/octet-stream',
						),

				// VOC  - audio       - Creative Voice (VOC)
				'voc'  => array(
							'pattern'   => '^Creative Voice File',
							'module'    => 'Audio\\Voc',
							'mime_type' => 'audio/voc',
						),

				// VQF  - audio       - transform-domain weighted interleave Vector Quantization Format (VQF)
				'vqf'  => array(
							'pattern'   => '^TWIN',
							'module'    => 'Audio\\Vqf',
							'mime_type' => 'application/octet-stream',
						),

				// WV  - audio        - WavPack (v4.0+)
				'wv'   => array(
							'pattern'   => '^wvpk',
							'module'    => 'Audio\\WavPack',
							'mime_type' => 'application/octet-stream',
						),


				// Audio-Video formats

				// ASF  - audio/video - Advanced Streaming Format, Windows Media Video, Windows Media Audio
				'asf'  => array(
							'pattern'   => '^\x30\x26\xB2\x75\x8E\x66\xCF\x11\xA6\xD9\x00\xAA\x00\x62\xCE\x6C',
							'module'    => 'AudioVideo\\Asf',
							'mime_type' => 'video/x-ms-asf',
							'iconv_req' => false,
						),

				// BINK - audio/video - Bink / Smacker
				'bink' => array(
							'pattern'   => '^(BIK|SMK)',
							'module'    => 'AudioVideo\\Bink',
							'mime_type' => 'application/octet-stream',
						),

				// FLV  - audio/video - FLash Video
				'flv' => array(
							'pattern'   => '^FLV\x01',
							'module'    => 'AudioVideo\\Flv',
							'mime_type' => 'video/x-flv',
						),

				// MKAV - audio/video - Mastroka
				'matroska' => array(
							'pattern'   => '^\x1A\x45\xDF\xA3',
							'module'    => 'AudioVideo\\Matroska',
							'mime_type' => 'video/x-matroska', // may also be audio/x-matroska
						),

				// MPEG - audio/video - MPEG (Moving Pictures Experts Group)
				'mpeg' => array(
							'pattern'   => '^\x00\x00\x01(\xBA|\xB3)',
							'module'    => 'AudioVideo\\Mpeg',
							'mime_type' => 'video/mpeg',
						),

				// NSV  - audio/video - Nullsoft Streaming Video (NSV)
				'nsv'  => array(
							'pattern'   => '^NSV[sf]',
							'module'    => 'AudioVideo\\Nsv',
							'mime_type' => 'application/octet-stream',
						),

				// Ogg  - audio/video - Ogg (Ogg-Vorbis, Ogg-FLAC, Speex, Ogg-Theora(*), Ogg-Tarkin(*))
				'ogg'  => array(
							'pattern'   => '^OggS',
							'module'    => 'Audio\\Ogg',
							'mime_type' => 'application/ogg',
							'fail_id3'  => 'WARNING',
							'fail_ape'  => 'WARNING',
						),

				// QT   - audio/video - Quicktime
				'quicktime' => array(
							'pattern'   => '^.{4}(cmov|free|ftyp|mdat|moov|pnot|skip|wide)',
							'module'    => 'AudioVideo\\QuickTime',
							'mime_type' => 'video/quicktime',
						),

				// RIFF - audio/video - Resource Interchange File Format (RIFF) / WAV / AVI / CD-audio / SDSS = renamed variant used by SmartSound QuickTracks (www.smartsound.com) / FORM = Audio Interchange File Format (AIFF)
				'riff' => array(
							'pattern'   => '^(RIFF|SDSS|FORM)',
							'module'    => 'AudioVideo\\Riff',
							'mime_type' => 'audio/x-wave',
							'fail_ape'  => 'WARNING',
						),

				// Real - audio/video - RealAudio, RealVideo
				'real' => array(
							'pattern'   => '^(\\.RMF|\\.ra)',
							'module'    => 'AudioVideo\\Real',
							'mime_type' => 'audio/x-realaudio',
						),

				// SWF - audio/video - ShockWave Flash
				'swf' => array(
							'pattern'   => '^(F|C)WS',
							'module'    => 'AudioVideo\\Swf',
							'mime_type' => 'application/x-shockwave-flash',
						),

				// TS - audio/video - MPEG-2 Transport Stream
				'ts' => array(
							'pattern'   => '^(\x47.{187}){10,}', // packets are 188 bytes long and start with 0x47 "G".  Check for at least 10 packets matching this pattern
							'module'    => 'AudioVideo\\Ts',
							'mime_type' => 'video/MP2T',
						),


				// Still-Image formats

				// BMP  - still image - Bitmap (Windows, OS/2; uncompressed, RLE8, RLE4)
				'bmp'  => array(
							'pattern'   => '^BM',
							'module'    => 'Graphic\\Bmp',
							'mime_type' => 'image/bmp',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),

				// GIF  - still image - Graphics Interchange Format
				'gif'  => array(
							'pattern'   => '^GIF',
							'module'    => 'Graphic\\Gif',
							'mime_type' => 'image/gif',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),

				// JPEG - still image - Joint Photographic Experts Group (JPEG)
				'jpg'  => array(
							'pattern'   => '^\xFF\xD8\xFF',
							'module'    => 'Graphic\\Jpg',
							'mime_type' => 'image/jpeg',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),

				// PCD  - still image - Kodak Photo CD
				'pcd'  => array(
							'pattern'   => '^.{2048}PCD_IPI\x00',
							'module'    => 'Graphic\\Pcd',
							'mime_type' => 'image/x-photo-cd',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),


				// PNG  - still image - Portable Network Graphics (PNG)
				'png'  => array(
							'pattern'   => '^\x89\x50\x4E\x47\x0D\x0A\x1A\x0A',
							'module'    => 'Graphic\\Png',
							'mime_type' => 'image/png',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),


				// SVG  - still image - Scalable Vector Graphics (SVG)
				'svg'  => array(
							'pattern'   => '(<!DOCTYPE svg PUBLIC |xmlns="http:\/\/www\.w3\.org\/2000\/svg")',
							'module'    => 'Graphic\\Svg',
							'mime_type' => 'image/svg+xml',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),


				// TIFF - still image - Tagged Information File Format (TIFF)
				'tiff' => array(
							'pattern'   => '^(II\x2A\x00|MM\x00\x2A)',
							'module'    => 'Graphic\\Tiff',
							'mime_type' => 'image/tiff',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),


				// EFAX - still image - eFax (TIFF derivative)
				'efax'  => array(
							'pattern'   => '^\xDC\xFE',
							'module'    => 'Graphic\\Efax',
							'mime_type' => 'image/efax',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),


				// Data formats

				// ISO  - data        - International Standards Organization (ISO) CD-ROM Image
				'iso'  => array(
							'pattern'   => '^.{32769}CD001',
							'module'    => 'Misc\\Iso',
							'mime_type' => 'application/octet-stream',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
							'iconv_req' => false,
						),

				// RAR  - data        - RAR compressed data
				'rar'  => array(
							'pattern'   => '^Rar\!',
							'module'    => 'Archive\\Rar',
							'mime_type' => 'application/octet-stream',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),

				// SZIP - audio/data  - SZIP compressed data
				'szip' => array(
							'pattern'   => '^SZ\x0A\x04',
							'module'    => 'Archive\\Szip',
							'mime_type' => 'application/octet-stream',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),

				// TAR  - data        - TAR compressed data
				'tar'  => array(
							'pattern'   => '^.{100}[0-9\x20]{7}\x00[0-9\x20]{7}\x00[0-9\x20]{7}\x00[0-9\x20\x00]{12}[0-9\x20\x00]{12}',
							'module'    => 'Archive\\Tar',
							'mime_type' => 'application/x-tar',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),

				// GZIP  - data        - GZIP compressed data
				'gz'  => array(
							'pattern'   => '^\x1F\x8B\x08',
							'module'    => 'Archive\\Gzip',
							'mime_type' => 'application/x-gzip',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),

				// ZIP  - data         - ZIP compressed data
				'zip'  => array(
							'pattern'   => '^PK\x03\x04',
							'module'    => 'Archive\\Zip',
							'mime_type' => 'application/zip',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),


				// Misc other formats

				// PAR2 - data        - Parity Volume Set Specification 2.0
				'par2' => array (
							'pattern'   => '^PAR2\x00PKT',
							'module'    => 'Misc\\Par2',
							'mime_type' => 'application/octet-stream',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),

				// PDF  - data        - Portable Document Format
				'pdf'  => array(
							'pattern'   => '^\x25PDF',
							'module'    => 'Misc\\Pdf',
							'mime_type' => 'application/pdf',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),

				// MSOFFICE  - data   - ZIP compressed data
				'msoffice' => array(
							'pattern'   => '^\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1', // D0CF11E == DOCFILE == Microsoft Office Document
							'module'    => 'Misc\\MsOffice',
							'mime_type' => 'application/octet-stream',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),

				 // CUE  - data       - CUEsheet (index to single-file disc images)
				 'cue' => array(
							'pattern'   => '', // empty pattern means cannot be automatically detected, will fall through all other formats and match based on filename and very basic file contents
							'module'    => 'Misc\\Cue',
							'mime_type' => 'application/octet-stream',
						   ),

			);
		}

		return $format_info;
	}



	public function GetFileFormat(&$filedata, $filename='') {
		// this function will determine the format of a file based on usually
		// the first 2-4 bytes of the file (8 bytes for PNG, 16 bytes for JPG,
		// and in the case of ISO CD image, 6 bytes offset 32kb from the start
		// of the file).

		// Identify file format - loop through $format_info and detect with reg expr
		foreach ($this->GetFileFormatArray() as $format_name => $info) {
			// The /s switch on preg_match() forces preg_match() NOT to treat
			// newline (0x0A) characters as special chars but do a binary match
			if (!empty($info['pattern']) && preg_match('#'.$info['pattern'].'#s', $filedata)) {
				return $info;
			}
		}


		if (preg_match('#\.mp[123a]$#i', $filename)) {
			// Too many mp3 encoders on the market put gabage in front of mpeg files
			// use assume format on these if format detection failed
			$GetFileFormatArray = $this->GetFileFormatArray();
			$info = $GetFileFormatArray['mp3'];
			return $info;
		} elseif (preg_match('/\.cue$/i', $filename) && preg_match('#FILE "[^"]+" (BINARY|MOTOROLA|AIFF|WAVE|MP3)#', $filedata)) {
			// there's not really a useful consistent "magic" at the beginning of .cue files to identify them
			// so until I think of something better, just go by filename if all other format checks fail
			// and verify there's at least one instance of "TRACK xx AUDIO" in the file
			$GetFileFormatArray = $this->GetFileFormatArray();
			$info = $GetFileFormatArray['cue'];
			return $info;
		}

		return false;
	}


	// converts array to $encoding charset from $this->encoding
	public function CharConvert(&$array, $encoding) {

		// identical encoding - end here
		if ($encoding == $this->encoding) {
			return;
		}

		// loop thru array
		foreach ($array as $key => $value) {

			// go recursive
			if (is_array($value)) {
				$this->CharConvert($array[$key], $encoding);
			}

			// convert string
			elseif (is_string($value)) {
				$array[$key] = trim(Utils::iconv_fallback($encoding, $this->encoding, $value));
			}
		}
	}


	public function HandleAllTags() {

		// key name => array (tag name, character encoding)
		static $tags;
		if (empty($tags)) {
			$tags = array(
				'asf'       => array('asf'           , 'UTF-16LE'),
				'midi'      => array('midi'          , 'ISO-8859-1'),
				'nsv'       => array('nsv'           , 'ISO-8859-1'),
				'ogg'       => array('vorbiscomment' , 'UTF-8'),
				'png'       => array('png'           , 'UTF-8'),
				'tiff'      => array('tiff'          , 'ISO-8859-1'),
				'quicktime' => array('quicktime'     , 'UTF-8'),
				'real'      => array('real'          , 'ISO-8859-1'),
				'vqf'       => array('vqf'           , 'ISO-8859-1'),
				'zip'       => array('zip'           , 'ISO-8859-1'),
				'riff'      => array('riff'          , 'ISO-8859-1'),
				'lyrics3'   => array('lyrics3'       , 'ISO-8859-1'),
				'id3v1'     => array('id3v1'         , $this->encoding_id3v1),
				'id3v2'     => array('id3v2'         , 'UTF-8'), // not according to the specs (every frame can have a different encoding), but getID3() force-converts all encodings to UTF-8
				'ape'       => array('ape'           , 'UTF-8'),
				'cue'       => array('cue'           , 'ISO-8859-1'),
				'matroska'  => array('matroska'      , 'UTF-8'),
				'flac'      => array('vorbiscomment' , 'UTF-8'),
				'divxtag'   => array('divx'          , 'ISO-8859-1'),
				'iptc'      => array('iptc'          , 'ISO-8859-1'),
			);
		}

		// loop through comments array
		foreach ($tags as $comment_name => $tagname_encoding_array) {
			list($tag_name, $encoding) = $tagname_encoding_array;

			// fill in default encoding type if not already present
			if (isset($this->info[$comment_name]) && !isset($this->info[$comment_name]['encoding'])) {
				$this->info[$comment_name]['encoding'] = $encoding;
			}

			// copy comments if key name set
			if (!empty($this->info[$comment_name]['comments'])) {
				foreach ($this->info[$comment_name]['comments'] as $tag_key => $valuearray) {
					foreach ($valuearray as $key => $value) {
						if (is_string($value)) {
							$value = trim($value, " \r\n\t"); // do not trim nulls from $value!! Unicode characters will get mangled if trailing nulls are removed!
						}
						if ($value) {
							if (!is_numeric($key)) {
								$this->info['tags'][trim($tag_name)][trim($tag_key)][$key] = $value;
							} else {
								$this->info['tags'][trim($tag_name)][trim($tag_key)][]     = $value;
							}
						}
					}
					if ($tag_key == 'picture') {
						unset($this->info[$comment_name]['comments'][$tag_key]);
					}
				}

				if (!isset($this->info['tags'][$tag_name])) {
					// comments are set but contain nothing but empty strings, so skip
					continue;
				}

				if ($this->option_tags_html) {
					foreach ($this->info['tags'][$tag_name] as $tag_key => $valuearray) {
						$this->info['tags_html'][$tag_name][$tag_key] = Utils::recursiveMultiByteCharString2HTML($valuearray, $encoding);
					}
				}

				$this->CharConvert($this->info['tags'][$tag_name], $encoding);           // only copy gets converted!
			}

		}

		// pictures can take up a lot of space, and we don't need multiple copies of them
		// let there be a single copy in [comments][picture], and not elsewhere
		if (!empty($this->info['tags'])) {
			$unset_keys = array('tags', 'tags_html');
			foreach ($this->info['tags'] as $tagtype => $tagarray) {
				foreach ($tagarray as $tagname => $tagdata) {
					if ($tagname == 'picture') {
						foreach ($tagdata as $key => $tagarray) {
							$this->info['comments']['picture'][] = $tagarray;
							if (isset($tagarray['data']) && isset($tagarray['image_mime'])) {
								if (isset($this->info['tags'][$tagtype][$tagname][$key])) {
									unset($this->info['tags'][$tagtype][$tagname][$key]);
								}
								if (isset($this->info['tags_html'][$tagtype][$tagname][$key])) {
									unset($this->info['tags_html'][$tagtype][$tagname][$key]);
								}
							}
						}
					}
				}
				foreach ($unset_keys as $unset_key) {
					// remove possible empty keys from (e.g. [tags][id3v2][picture])
					if (empty($this->info[$unset_key][$tagtype]['picture'])) {
						unset($this->info[$unset_key][$tagtype]['picture']);
					}
					if (empty($this->info[$unset_key][$tagtype])) {
						unset($this->info[$unset_key][$tagtype]);
					}
					if (empty($this->info[$unset_key])) {
						unset($this->info[$unset_key]);
					}
				}
				// remove duplicate copy of picture data from (e.g. [id3v2][comments][picture])
				if (isset($this->info[$tagtype]['comments']['picture'])) {
					unset($this->info[$tagtype]['comments']['picture']);
				}
				if (empty($this->info[$tagtype]['comments'])) {
					unset($this->info[$tagtype]['comments']);
				}
				if (empty($this->info[$tagtype])) {
					unset($this->info[$tagtype]);
				}
			}
		}
		return true;
	}

	public function getHashdata($algorithm) {
		switch ($algorithm) {
			case 'md5':
			case 'sha1':
				break;

			default:
				return $this->error('bad algorithm "'.$algorithm.'" in getHashdata()');
				break;
		}

		if (!empty($this->info['fileformat']) && !empty($this->info['dataformat']) && ($this->info['fileformat'] == 'ogg') && ($this->info['audio']['dataformat'] == 'vorbis')) {

			// We cannot get an identical md5_data value for Ogg files where the comments
			// span more than 1 Ogg page (compared to the same audio data with smaller
			// comments) using the normal getID3() method of MD5'ing the data between the
			// end of the comments and the end of the file (minus any trailing tags),
			// because the page sequence numbers of the pages that the audio data is on
			// do not match. Under normal circumstances, where comments are smaller than
			// the nominal 4-8kB page size, then this is not a problem, but if there are
			// very large comments, the only way around it is to strip off the comment
			// tags with vorbiscomment and MD5 that file.
			// This procedure must be applied to ALL Ogg files, not just the ones with
			// comments larger than 1 page, because the below method simply MD5's the
			// whole file with the comments stripped, not just the portion after the
			// comments block (which is the standard getID3() method.

			// The above-mentioned problem of comments spanning multiple pages and changing
			// page sequence numbers likely happens for OggSpeex and OggFLAC as well, but
			// currently vorbiscomment only works on OggVorbis files.

			if (preg_match('#(1|ON)#i', ini_get('safe_mode'))) {

				$this->warning('Failed making system call to vorbiscomment.exe - '.$algorithm.'_data is incorrect - error returned: PHP running in Safe Mode (backtick operator not available)');
				$this->info[$algorithm.'_data'] = false;

			} else {

				// Prevent user from aborting script
				$old_abort = ignore_user_abort(true);

				// Create empty file
				$empty = tempnam(Utils::getTempDirectory(), 'getID3');
				touch($empty);

				// Use vorbiscomment to make temp file without comments
				$temp = tempnam(Utils::getTempDirectory(), 'getID3');
				$file = $this->info['filenamepath'];

				if (Utils::isWindows()) {

					if (file_exists(Utils::getHelperAppDirectory() . 'vorbiscomment.exe')) {

						$commandline = '"' . Utils::getHelperAppDirectory() . 'vorbiscomment.exe" -w -c "'.$empty.'" "'.$file.'" "'.$temp.'"';
						$VorbisCommentError = `$commandline`;

					} else {

						$VorbisCommentError = 'vorbiscomment.exe not found in ' . Utils::getHelperAppDirectory();

					}

				} else {

					$commandline = 'vorbiscomment -w -c "'.$empty.'" "'.$file.'" "'.$temp.'" 2>&1';
					$commandline = 'vorbiscomment -w -c '.escapeshellarg($empty).' '.escapeshellarg($file).' '.escapeshellarg($temp).' 2>&1';
					$VorbisCommentError = `$commandline`;

				}

				if (!empty($VorbisCommentError)) {

					$this->info['warning'][]         = 'Failed making system call to vorbiscomment(.exe) - '.$algorithm.'_data will be incorrect. If vorbiscomment is unavailable, please download from http://www.vorbis.com/download.psp and put in the getID3() directory. Error returned: '.$VorbisCommentError;
					$this->info[$algorithm.'_data']  = false;

				} else {

					// Get hash of newly created file
					switch ($algorithm) {
						case 'md5':
							$this->info[$algorithm.'_data'] = md5_file($temp);
							break;

						case 'sha1':
							$this->info[$algorithm.'_data'] = sha1_file($temp);
							break;
					}
				}

				// Clean up
				unlink($empty);
				unlink($temp);

				// Reset abort setting
				ignore_user_abort($old_abort);

			}

		} else {

			if (!empty($this->info['avdataoffset']) || (isset($this->info['avdataend']) && ($this->info['avdataend'] < $this->info['filesize']))) {

				// get hash from part of file
				$this->info[$algorithm.'_data'] = Utils::hash_data($this->info['filenamepath'], $this->info['avdataoffset'], $this->info['avdataend'], $algorithm);

			} else {

				// get hash from whole file
				switch ($algorithm) {
					case 'md5':
						$this->info[$algorithm.'_data'] = md5_file($this->info['filenamepath']);
						break;

					case 'sha1':
						$this->info[$algorithm.'_data'] = sha1_file($this->info['filenamepath']);
						break;
				}
			}

		}
		return true;
	}


	public function ChannelsBitratePlaytimeCalculations() {

		// set channelmode on audio
		if (!empty($this->info['audio']['channelmode']) || !isset($this->info['audio']['channels'])) {
			// ignore
		} elseif ($this->info['audio']['channels'] == 1) {
			$this->info['audio']['channelmode'] = 'mono';
		} elseif ($this->info['audio']['channels'] == 2) {
			$this->info['audio']['channelmode'] = 'stereo';
		}

		// Calculate combined bitrate - audio + video
		$CombinedBitrate  = 0;
		$CombinedBitrate += (isset($this->info['audio']['bitrate']) ? $this->info['audio']['bitrate'] : 0);
		$CombinedBitrate += (isset($this->info['video']['bitrate']) ? $this->info['video']['bitrate'] : 0);
		if (($CombinedBitrate > 0) && empty($this->info['bitrate'])) {
			$this->info['bitrate'] = $CombinedBitrate;
		}
		//if ((isset($this->info['video']) && !isset($this->info['video']['bitrate'])) || (isset($this->info['audio']) && !isset($this->info['audio']['bitrate']))) {
		//	// for example, VBR MPEG video files cannot determine video bitrate:
		//	// should not set overall bitrate and playtime from audio bitrate only
		//	unset($this->info['bitrate']);
		//}

		// video bitrate undetermined, but calculable
		if (isset($this->info['video']['dataformat']) && $this->info['video']['dataformat'] && (!isset($this->info['video']['bitrate']) || ($this->info['video']['bitrate'] == 0))) {
			// if video bitrate not set
			if (isset($this->info['audio']['bitrate']) && ($this->info['audio']['bitrate'] > 0) && ($this->info['audio']['bitrate'] == $this->info['bitrate'])) {
				// AND if audio bitrate is set to same as overall bitrate
				if (isset($this->info['playtime_seconds']) && ($this->info['playtime_seconds'] > 0)) {
					// AND if playtime is set
					if (isset($this->info['avdataend']) && isset($this->info['avdataoffset'])) {
						// AND if AV data offset start/end is known
						// THEN we can calculate the video bitrate
						$this->info['bitrate'] = round((($this->info['avdataend'] - $this->info['avdataoffset']) * 8) / $this->info['playtime_seconds']);
						$this->info['video']['bitrate'] = $this->info['bitrate'] - $this->info['audio']['bitrate'];
					}
				}
			}
		}

		if ((!isset($this->info['playtime_seconds']) || ($this->info['playtime_seconds'] <= 0)) && !empty($this->info['bitrate'])) {
			$this->info['playtime_seconds'] = (($this->info['avdataend'] - $this->info['avdataoffset']) * 8) / $this->info['bitrate'];
		}

		if (!isset($this->info['bitrate']) && !empty($this->info['playtime_seconds'])) {
			$this->info['bitrate'] = (($this->info['avdataend'] - $this->info['avdataoffset']) * 8) / $this->info['playtime_seconds'];
		}
		if (isset($this->info['bitrate']) && empty($this->info['audio']['bitrate']) && empty($this->info['video']['bitrate'])) {
			if (isset($this->info['audio']['dataformat']) && empty($this->info['video']['resolution_x'])) {
				// audio only
				$this->info['audio']['bitrate'] = $this->info['bitrate'];
			} elseif (isset($this->info['video']['resolution_x']) && empty($this->info['audio']['dataformat'])) {
				// video only
				$this->info['video']['bitrate'] = $this->info['bitrate'];
			}
		}

		// Set playtime string
		if (!empty($this->info['playtime_seconds']) && empty($this->info['playtime_string'])) {
			$this->info['playtime_string'] = Utils::PlaytimeString($this->info['playtime_seconds']);
		}
	}


	public function CalculateCompressionRatioVideo() {
		if (empty($this->info['video'])) {
			return false;
		}
		if (empty($this->info['video']['resolution_x']) || empty($this->info['video']['resolution_y'])) {
			return false;
		}
		if (empty($this->info['video']['bits_per_sample'])) {
			return false;
		}

		switch ($this->info['video']['dataformat']) {
			case 'bmp':
			case 'gif':
			case 'jpeg':
			case 'jpg':
			case 'png':
			case 'tiff':
				$FrameRate = 1;
				$PlaytimeSeconds = 1;
				$BitrateCompressed = $this->info['filesize'] * 8;
				break;

			default:
				if (!empty($this->info['video']['frame_rate'])) {
					$FrameRate = $this->info['video']['frame_rate'];
				} else {
					return false;
				}
				if (!empty($this->info['playtime_seconds'])) {
					$PlaytimeSeconds = $this->info['playtime_seconds'];
				} else {
					return false;
				}
				if (!empty($this->info['video']['bitrate'])) {
					$BitrateCompressed = $this->info['video']['bitrate'];
				} else {
					return false;
				}
				break;
		}
		$BitrateUncompressed = $this->info['video']['resolution_x'] * $this->info['video']['resolution_y'] * $this->info['video']['bits_per_sample'] * $FrameRate;

		$this->info['video']['compression_ratio'] = $BitrateCompressed / $BitrateUncompressed;
		return true;
	}


	public function CalculateCompressionRatioAudio() {
		if (empty($this->info['audio']['bitrate']) || empty($this->info['audio']['channels']) || empty($this->info['audio']['sample_rate']) || !is_numeric($this->info['audio']['sample_rate'])) {
			return false;
		}
		$this->info['audio']['compression_ratio'] = $this->info['audio']['bitrate'] / ($this->info['audio']['channels'] * $this->info['audio']['sample_rate'] * (!empty($this->info['audio']['bits_per_sample']) ? $this->info['audio']['bits_per_sample'] : 16));

		if (!empty($this->info['audio']['streams'])) {
			foreach ($this->info['audio']['streams'] as $streamnumber => $streamdata) {
				if (!empty($streamdata['bitrate']) && !empty($streamdata['channels']) && !empty($streamdata['sample_rate'])) {
					$this->info['audio']['streams'][$streamnumber]['compression_ratio'] = $streamdata['bitrate'] / ($streamdata['channels'] * $streamdata['sample_rate'] * (!empty($streamdata['bits_per_sample']) ? $streamdata['bits_per_sample'] : 16));
				}
			}
		}
		return true;
	}


	public function CalculateReplayGain() {
		if (isset($this->info['replay_gain'])) {
			if (!isset($this->info['replay_gain']['reference_volume'])) {
				$this->info['replay_gain']['reference_volume'] = (double) 89.0;
			}
			if (isset($this->info['replay_gain']['track']['adjustment'])) {
				$this->info['replay_gain']['track']['volume'] = $this->info['replay_gain']['reference_volume'] - $this->info['replay_gain']['track']['adjustment'];
			}
			if (isset($this->info['replay_gain']['album']['adjustment'])) {
				$this->info['replay_gain']['album']['volume'] = $this->info['replay_gain']['reference_volume'] - $this->info['replay_gain']['album']['adjustment'];
			}

			if (isset($this->info['replay_gain']['track']['peak'])) {
				$this->info['replay_gain']['track']['max_noclip_gain'] = 0 - Utils::RGADamplitude2dB($this->info['replay_gain']['track']['peak']);
			}
			if (isset($this->info['replay_gain']['album']['peak'])) {
				$this->info['replay_gain']['album']['max_noclip_gain'] = 0 - Utils::RGADamplitude2dB($this->info['replay_gain']['album']['peak']);
			}
		}
		return true;
	}

	public function ProcessAudioStreams() {
		if (!empty($this->info['audio']['bitrate']) || !empty($this->info['audio']['channels']) || !empty($this->info['audio']['sample_rate'])) {
			if (!isset($this->info['audio']['streams'])) {
				foreach ($this->info['audio'] as $key => $value) {
					if ($key != 'streams') {
						$this->info['audio']['streams'][0][$key] = $value;
					}
				}
			}
		}
		return true;
	}
}
