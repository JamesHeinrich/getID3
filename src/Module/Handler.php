<?php

namespace JamesHeinrich\GetID3\Module;

use JamesHeinrich\GetID3\Exception;
use JamesHeinrich\GetID3\GetID3;
use JamesHeinrich\GetID3\Utils;

abstract class Handler {

	/**
	 * @var GetID3
	 */
	protected $getid3;                       // pointer

	/**
	 * Analyzing filepointer or string.
	 *
	 * @var bool
	 */
	protected $data_string_flag     = false;

	/**
	 * String to analyze.
	 *
	 * @var string
	 */
	protected $data_string          = '';

	/**
	 * Seek position in string.
	 *
	 * @var int
	 */
	protected $data_string_position = 0;

	/**
	 * String length.
	 *
	 * @var int
	 */
	protected $data_string_length   = 0;

	/**
	 * @var string
	 */
	private $dependency_to;

	/**
	 * getid3_handler constructor.
	 *
	 * @param GetID3 $getid3
	 * @param string $call_module
	 */
	public function __construct(GetID3 $getid3, $call_module=null) {
		$this->getid3 = $getid3;

		// get calling class name, without namespace
		if ($call_module) {
			$parts = explode('\\', $call_module);
			$this->dependency_to = $parts[count($parts) - 1];
		}
	}

	/**
	 * Analyze from file pointer.
	 *
	 * @return bool
	 */
	abstract public function Analyze();

	/**
	 * Analyze from string instead.
	 *
	 * @param string $string
	 */
	public function AnalyzeString($string) {
		// Enter string mode
		$this->setStringMode($string);

		// Save info
		$saved_avdataoffset = $this->getid3->info['avdataoffset'];
		$saved_avdataend    = $this->getid3->info['avdataend'];
		$saved_filesize     = (isset($this->getid3->info['filesize']) ? $this->getid3->info['filesize'] : null); // may be not set if called as dependency without openfile() call

		// Reset some info
		$this->getid3->info['avdataoffset'] = 0;
		$this->getid3->info['avdataend']    = $this->getid3->info['filesize'] = $this->data_string_length;

		// Analyze
		$this->Analyze();

		// Restore some info
		$this->getid3->info['avdataoffset'] = $saved_avdataoffset;
		$this->getid3->info['avdataend']    = $saved_avdataend;
		$this->getid3->info['filesize']     = $saved_filesize;

		// Exit string mode
		$this->data_string_flag = false;
	}

	/**
	 * @param string $string
	 */
	public function setStringMode($string) {
		$this->data_string_flag   = true;
		$this->data_string        = $string;
		$this->data_string_length = strlen($string);
	}

	/**
	 * @return int|bool
	 */
	protected function ftell() {
		if ($this->data_string_flag) {
			return $this->data_string_position;
		}
		return ftell($this->getid3->fp);
	}

	/**
	 * @param int $bytes
	 *
	 * @return string|false
	 *
	 * @throws Exception
	 */
	protected function fread($bytes) {
		if ($this->data_string_flag) {
			$this->data_string_position += $bytes;
			return substr($this->data_string, $this->data_string_position - $bytes, $bytes);
		}
		$pos = $this->ftell() + $bytes;
		if (!Utils::intValueSupported($pos)) {
			throw new Exception('cannot fread('.$bytes.' from '.$this->ftell().') because beyond PHP filesystem limit', 10);
		}

		//return fread($this->getid3->fp, $bytes);
		/*
		* https://www.getid3.org/phpBB3/viewtopic.php?t=1930
		* "I found out that the root cause for the problem was how getID3 uses the PHP system function fread().
		* It seems to assume that fread() would always return as many bytes as were requested.
		* However, according the PHP manual (http://php.net/manual/en/function.fread.php), this is the case only with regular local files, but not e.g. with Linux pipes.
		* The call may return only part of the requested data and a new call is needed to get more."
		*/
		$contents = '';
		do {
			//if (($this->getid3->memory_limit > 0) && ($bytes > $this->getid3->memory_limit)) {
			if (($this->getid3->memory_limit > 0) && (($bytes / $this->getid3->memory_limit) > 0.99)) { // enable a more-fuzzy match to prevent close misses generating errors like "PHP Fatal error: Allowed memory size of 33554432 bytes exhausted (tried to allocate 33554464 bytes)"
				throw new Exception('cannot fread('.$bytes.' from '.$this->ftell().') that is more than available PHP memory ('.$this->getid3->memory_limit.')', 10);
			}
			$part = fread($this->getid3->fp, $bytes);
			$partLength  = strlen($part);
			$bytes      -= $partLength;
			$contents   .= $part;
		} while (($bytes > 0) && ($partLength > 0));
		return $contents;
	}

	/**
	 * @param int $bytes
	 * @param int $whence
	 *
	 * @return int
	 *
	 * @throws Exception
	 */
	protected function fseek($bytes, $whence=SEEK_SET) {
		if ($this->data_string_flag) {
			switch ($whence) {
				case SEEK_SET:
					$this->data_string_position = $bytes;
					break;

				case SEEK_CUR:
					$this->data_string_position += $bytes;
					break;

				case SEEK_END:
					$this->data_string_position = $this->data_string_length + $bytes;
					break;
			}
			return 0;
		} else {
			$pos = $bytes;
			if ($whence == SEEK_CUR) {
				$pos = $this->ftell() + $bytes;
			} elseif ($whence == SEEK_END) {
				$pos = $this->getid3->info['filesize'] + $bytes;
			}
			if (!Utils::intValueSupported($pos)) {
				throw new Exception('cannot fseek('.$pos.') because beyond PHP filesystem limit', 10);
			}
		}
		return fseek($this->getid3->fp, $bytes, $whence);
	}

	/**
	 * @return string|false
	 *
	 * @throws Exception
	 */
	protected function fgets() {
		// must be able to handle CR/LF/CRLF but not read more than one lineend
		$buffer   = ''; // final string we will return
		$prevchar = ''; // save previously-read character for end-of-line checking
		if ($this->data_string_flag) {
			while (true) {
				$thischar = substr($this->data_string, $this->data_string_position++, 1);
				if (($prevchar == "\r") && ($thischar != "\n")) {
					// read one byte too many, back up
					$this->data_string_position--;
					break;
				}
				$buffer .= $thischar;
				if ($thischar == "\n") {
					break;
				}
				if ($this->data_string_position >= $this->data_string_length) {
					// EOF
					break;
				}
				$prevchar = $thischar;
			}

		} else {

			// Ideally we would just use PHP's fgets() function, however...
			// it does not behave consistently with regards to mixed line endings, may be system-dependent
			// and breaks entirely when given a file with mixed \r vs \n vs \r\n line endings (e.g. some PDFs)
			//return fgets($this->getid3->fp);
			while (true) {
				$thischar = fgetc($this->getid3->fp);
				if (($prevchar == "\r") && ($thischar != "\n")) {
					// read one byte too many, back up
					fseek($this->getid3->fp, -1, SEEK_CUR);
					break;
				}
				$buffer .= $thischar;
				if ($thischar == "\n") {
					break;
				}
				if (feof($this->getid3->fp)) {
					break;
				}
				$prevchar = $thischar;
			}

		}
		return $buffer;
	}

	/**
	 * @return bool
	 */
	protected function feof() {
		if ($this->data_string_flag) {
			return $this->data_string_position >= $this->data_string_length;
		}
		return feof($this->getid3->fp);
	}

	/**
	 * @param string $module
	 *
	 * @return bool
	 */
	final protected function isDependencyFor($module) {
		return $this->dependency_to == $module;
	}

	/**
	 * @param string $text
	 *
	 * @return bool
	 */
	protected function error($text) {
		$this->getid3->info['error'][] = $text;

		return false;
	}

	/**
	 * @param string $text
	 *
	 * @return bool
	 */
	protected function warning($text) {
		return $this->getid3->warning($text);
	}

	/**
	 * @param string $text
	 */
	protected function notice($text) {
		// does nothing for now
	}

	/**
	 * @param string $name
	 * @param int    $offset
	 * @param int    $length
	 * @param string $image_mime
	 *
	 * @return string|null
	 *
	 * @throws Exception
	 * @throws Exception
	 */
	public function saveAttachment($name, $offset, $length, $image_mime=null) {
		try {

			// do not extract at all
			if ($this->getid3->option_save_attachments === GetID3::ATTACHMENTS_NONE) {

				$attachment = null; // do not set any

			// extract to return array
			} elseif ($this->getid3->option_save_attachments === getID3::ATTACHMENTS_INLINE) {

				$this->fseek($offset);
				$attachment = $this->fread($length); // get whole data in one pass, till it is anyway stored in memory
				if ($attachment === false || strlen($attachment) != $length) {
					throw new Exception('failed to read attachment data');
				}

			// assume directory path is given
			} else {

				// set up destination path
				$dir = rtrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $this->getid3->option_save_attachments), DIRECTORY_SEPARATOR);
				if (!is_dir($dir) || !Utils::isWritable($dir)) { // check supplied directory
					throw new Exception('supplied path ('.$dir.') does not exist, or is not writable');
				}
				$dest = $dir.DIRECTORY_SEPARATOR.$name.($image_mime ? '.'.Utils::ImageExtFromMime($image_mime) : '');

				// create dest file
				if (($fp_dest = fopen($dest, 'wb')) == false) {
					throw new Exception('failed to create file '.$dest);
				}

				// copy data
				$this->fseek($offset);
				$buffersize = ($this->data_string_flag ? $length : $this->getid3->fread_buffer_size());
				$bytesleft = $length;
				while ($bytesleft > 0) {
					if (($buffer = $this->fread(min($buffersize, $bytesleft))) === false || ($byteswritten = fwrite($fp_dest, $buffer)) === false || ($byteswritten === 0)) {
						throw new Exception($buffer === false ? 'not enough data to read' : 'failed to write to destination file, may be not enough disk space');
					}
					$bytesleft -= $byteswritten;
				}

				fclose($fp_dest);
				$attachment = $dest;

			}

		} catch (\Exception $e) {

			// close and remove dest file if created
			if (isset($fp_dest) && is_resource($fp_dest)) {
				fclose($fp_dest);
			}

			if (isset($dest) && file_exists($dest)) {
				unlink($dest);
			}

			// do not set any is case of error
			$attachment = null;
			$this->warning('Failed to extract attachment '.$name.': '.$e->getMessage());

		}

		// seek to the end of attachment
		$this->fseek($offset + $length);

		return $attachment;
	}

}
