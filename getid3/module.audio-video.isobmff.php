<?php

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio-video.isobmff.php                              //
// module for analyzing ISO Base Media File Format box         //
// structure (ISO/IEC 14496-12), the container shared by MP4,  //
// QuickTime, HEIF and JPEG XL. Provides a generic box walker  //
// for other modules to build on.                              //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

if (!defined('GETID3_INCLUDEPATH')) { // prevent path-exposing attacks that access modules directly on public webservers
	exit;
}

class getid3_isobmff extends getid3_handler
{
	/**
	 * Maximum box nesting depth to recurse into.
	 *
	 * @var int
	 */
	public $max_depth = 20;

	/**
	 * Box types whose payload is itself a sequence of child boxes, as defined
	 * by ISO/IEC 14496-12 itself. Only plain container boxes are listed;
	 * FullBox-style containers (e.g. 'meta') carry a version/flags prefix and
	 * are handled by the format-specific modules that need them.
	 *
	 * Modules layering a format flavour on top provide their own additions
	 * (e.g. QuickTime's 'clip'/'matt'/'gmhd'/'wave', HEIF's 'iprp'/'ipco') by
	 * extending this list on their instance or overriding it in a subclass.
	 *
	 * @var string[]
	 */
	public $container_boxes = array(
		'moov', 'trak', 'mdia', 'minf', 'stbl', 'dinf', 'edts', 'udta',
		'mvex', 'moof', 'traf', 'mfra', 'sinf', 'schi', 'rinf', 'tref',
	);

	/**
	 * Walk the file as a flat ISO BMFF box structure, exposing the result at
	 * $info['isobmff']. This is the entry point used when the module is run
	 * directly (or as a dependency, e.g. by getid3_jpegxl).
	 *
	 * Note for format modules that extend this class: those with their own
	 * box-iteration loop (e.g. getid3_quicktime) do not call this method, so
	 * they produce neither the $info['isobmff'] tree nor the 'No ISO BMFF
	 * boxes found' error - their existing output is unchanged.
	 *
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$info['isobmff']['boxes'] = $this->ParseBoxList((int) $info['avdataoffset'], (int) $info['avdataend'], 0);

		if (empty($info['isobmff']['boxes'])) {
			$this->error('No ISO BMFF boxes found');
			return false;
		}

		$ftyp = $this->GetSubBox($info['isobmff']['boxes'], 'ftyp');
		if ($ftyp !== false && isset($ftyp['major_brand'])) {
			$info['isobmff']['major_brand']       = $ftyp['major_brand'];
			$info['isobmff']['minor_version']     = $ftyp['minor_version'];
			$info['isobmff']['compatible_brands'] = $ftyp['compatible_brands'];
		}

		return true;
	}

	/**
	 * Walk the sequence of boxes in the byte range [$offset, $end), recursing
	 * into container boxes up to $this->max_depth.
	 *
	 * @param int $offset
	 * @param int $end
	 * @param int $depth
	 *
	 * @return array[] List of box structures (see ReadBoxHeader()).
	 */
	public function ParseBoxList($offset, $end, $depth) {
		$boxes = array();
		while ($offset < $end) {
			if (!getid3_lib::intValueSupported($offset)) {
				$this->error('Unable to parse box at offset '.$offset.' because beyond '.round(PHP_INT_MAX / 1073741824).'GB limit of PHP filesystem functions');
				break;
			}
			$box = $this->ReadBoxHeader($offset, $end);
			if ($box === false) {
				break;
			}
			$box_end = $box['offset'] + $box['size']; // capture before $box is mutated below

			if (in_array($box['name'], $this->container_boxes) && ($depth < $this->max_depth)) {
				$box['subatoms'] = $this->ParseBoxList($box['data_offset'], $box_end, $depth + 1);
			} elseif ($box['name'] === 'ftyp') {
				$this->ParseFileTypeBox($box);
			}

			$boxes[] = $box;
			$offset = $box_end;
		}
		return $boxes;
	}

	/**
	 * Read the raw box/atom header at $offset: the 32-bit size and 4-byte type,
	 * plus the 64-bit extended size when the size field is 1. No range or policy
	 * checks are applied and the size field is returned verbatim (0 stays 0), so
	 * that callers can apply their own end-of-file semantics. Leaves the file
	 * pointer positioned immediately after the header.
	 *
	 * @param int $offset
	 *
	 * @return array{name: string, size: int|float, offset: int, header_length: int}|false Header, or false on truncated/malformed input.
	 */
	public function ReadBoxHeaderRaw($offset) {
		$this->fseek($offset);
		$header = $this->fread(8);
		if (strlen($header) < 8) {
			return false;
		}

		$size          = getid3_lib::BigEndian2Int(substr($header, 0, 4));
		$name          = substr($header, 4, 4);
		$header_length = 8;
		if ($size === false) {
			return false;
		}

		if ($size === 1) {
			// 64-bit size follows the 8-byte header.
			$extended = $this->fread(8);
			if (strlen($extended) < 8) {
				return false;
			}
			$size = getid3_lib::BigEndian2Int($extended);
			if ($size === false) {
				return false;
			}
			$header_length = 16;
		}

		return array(
			'name'          => $name,
			'size'          => $size,
			'offset'        => $offset,
			'header_length' => $header_length,
		);
	}

	/**
	 * Read the header of a single box at $offset, applying ISO BMFF range
	 * policy: the "extends to end of range" case (size field == 0) is resolved
	 * against $end, and boxes that are too small or overflow their container are
	 * rejected.
	 *
	 * @param int $offset
	 * @param int $end
	 *
	 * @return array{name: string, size: int|float, offset: int, header_length: int, data_offset: int, data_size: int|float}|false Box structure, or false on malformed/truncated header.
	 */
	public function ReadBoxHeader($offset, $end) {
		$box = $this->ReadBoxHeaderRaw($offset);
		if ($box === false) {
			return false;
		}
		$name          = $box['name'];
		$size          = $box['size'];
		$header_length = $box['header_length'];

		if ($size === 0) {
			// Box extends to the end of the enclosing range.
			$size = $end - $offset;
		}

		if ($size < $header_length) {
			$this->warning('Invalid ISO BMFF box "'.$this->printableType($name).'" size '.$size.' at offset '.$offset);
			return false;
		}
		if (($offset + $size) > $end) {
			$this->warning('ISO BMFF box "'.$this->printableType($name).'" at offset '.$offset.' claims to extend beyond its container (size: '.$size.' bytes)');
			return false;
		}

		return array(
			'name'          => $name,
			'size'          => $size,
			'offset'        => $offset,
			'header_length' => $header_length,
			'data_offset'   => $offset + $header_length,
			'data_size'     => $size - $header_length,
		);
	}

	/**
	 * Read the payload bytes of a box (up to $maxbytes).
	 *
	 * @param array $box
	 * @param int   $maxbytes
	 *
	 * @return string
	 */
	public function ReadBoxData($box, $maxbytes = 0) {
		$length = $box['data_size'];
		if (($maxbytes > 0) && ($length > $maxbytes)) {
			$length = $maxbytes;
		}
		if ($length <= 0) {
			return '';
		}
		$this->fseek($box['data_offset']);
		return $this->fread($length);
	}

	/**
	 * Find the first box of a given type in a box list (as produced by
	 * ParseBoxList()). $path may be a single type, or an array of types to
	 * descend through nested subatoms, e.g.
	 *   $this->GetSubBox($info['isobmff']['boxes'], array('moov', 'mvhd'))
	 *
	 * @param array        $boxes
	 * @param string|array $path
	 *
	 * @return array|false The matching box structure, or false if not found.
	 */
	public function GetSubBox($boxes, $path) {
		$path  = (array) $path;
		$found = false;
		foreach ($path as $name) {
			$found = false;
			foreach ($boxes as $box) {
				if ($box['name'] === $name) {
					$found = $box;
					break;
				}
			}
			if ($found === false) {
				return false;
			}
			$boxes = isset($found['subatoms']) ? $found['subatoms'] : array();
		}
		return $found;
	}

	/**
	 * Parse an 'ftyp' (FileType) box into major/minor brand and compatible
	 * brands, annotating the box. The brand is promoted to $info['isobmff'] by
	 * Analyze(); ParseBoxList() itself leaves $info untouched, so callers can
	 * walk the box tree without side effects.
	 *
	 * @param array $box
	 *
	 * @return void
	 */
	public function ParseFileTypeBox(&$box) {
		// Cap the read: brands are 4 bytes each, real ftyp boxes are tiny; the cap
		// keeps a bogus multi-GB ftyp size from being slurped into memory whole.
		$data = $this->ReadBoxData($box, 4096);
		if (strlen($data) < 8) {
			return;
		}

		$box['major_brand']   = substr($data, 0, 4);
		$box['minor_version'] = getid3_lib::BigEndian2Int(substr($data, 4, 4));
		$box['compatible_brands'] = array();
		$datalength = strlen($data);
		for ($i = 8; ($i + 4) <= $datalength; $i += 4) {
			$box['compatible_brands'][] = substr($data, $i, 4);
		}
	}

	/**
	 * Render a 4-byte box type printable for diagnostics.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public function printableType($name) {
		return preg_replace('#[^a-zA-Z0-9 _\\-]#', '?', $name);
	}
}
