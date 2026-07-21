<?php

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.graphic.jpegxl.php                                   //
// module for analyzing JPEG XL (JXL) Image files              //
// codestream spec: ISO/IEC 18181-1  https://jpeg.org/jpegxl/  //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

if (!defined('GETID3_INCLUDEPATH')) { // prevent path-exposing attacks that access modules directly on public webservers
	exit;
}

class getid3_jpegxl extends getid3_handler
{
	/**
	 * JXL bare codestream signature.
	 */
	const SIG_CODESTREAM = "\xFF\x0A";

	/**
	 * Number of bytes to read when parsing the codestream header.
	 * Real-world headers are at most a few hundred bytes;
	 * a header larger than this fails with a parse error.
	 */
	const HEADER_READ_BYTES = 8192;

	/**
	 * Bit-reader state: buffer of bytes to read bits from.
	 *
	 * @var string
	 */
	private $bitdata = '';

	/**
	 * Bit-reader state: current byte offset into $bitdata.
	 *
	 * @var int
	 */
	private $bytepos = 0;

	/**
	 * Bit-reader state: current bit offset (0-7) within the current byte,
	 * counted LSB-first.
	 *
	 * @var int
	 */
	private $bitpos = 0;

	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$this->fseek($info['avdataoffset']);
		$header = $this->fread(2);

		if (strpos($header, self::SIG_CODESTREAM) === 0) {
			return $this->AnalyzeCodestream();
		}

		$this->error('Expecting "FF 0A" JPEG XL codestream signature at offset '.$info['avdataoffset'].', found "'.getid3_lib::PrintHexBytes($header).'"');
		return false;
	}

	/**
	 * Analyze a bare JXL codestream (starts with "FF 0A").
	 *
	 * @return bool
	 */
	private function AnalyzeCodestream() {
		$info = &$this->getid3->info;

		$info['fileformat']          = 'jpegxl';
		$info['video']['dataformat'] = 'jpegxl';
		$info['jpegxl']['container'] = false;

		$this->fseek($info['avdataoffset']);
		$codestream = $this->fread(self::HEADER_READ_BYTES);

		if (!$this->ParseCodestreamHeader($codestream, 2)) {
			$this->error('Failed to parse JPEG XL codestream header');
			unset($info['fileformat'], $info['jpegxl'], $info['video']['dataformat']);
			if (empty($info['video'])) {
				unset($info['video']);
			}
			return false;
		}

		return true;
	}

	/**
	 * Parse the JXL codestream header (SizeHeader + ImageMetadata),
	 * starting immediately after the 2-byte "FF 0A" codestream signature.
	 *
	 * @param string $data
	 * @param int    $offset
	 *
	 * @return bool
	 */
	private function ParseCodestreamHeader($data, $offset) {
		$info = &$this->getid3->info;

		$this->bitdata = $data;
		$this->bytepos = $offset;
		$this->bitpos  = 0;

		$size = $this->ParseSizeHeader();
		if ($size === false) {
			return false;
		}

		$info['jpegxl']['width']           = $size['width'];
		$info['jpegxl']['height']          = $size['height'];
		$info['jpegxl']['bits_per_sample'] = 8;
		$info['jpegxl']['alpha']           = false;
		$info['jpegxl']['animated']        = false;
		$info['jpegxl']['orientation']     = 1;
		$info['jpegxl']['xyb_encoded']     = true;
		$info['jpegxl']['lossless']        = false; // xyb_encoded implies lossy
		$info['jpegxl']['color']           = array( // ImageMetadata/ColourEncoding default is sRGB
			'icc_profile'      => false,
			'color_space'      => 'RGB',
			'white_point'      => 'D65',
			'primaries'        => 'sRGB',
			'transfer'         => 'sRGB',
			'rendering_intent' => 'Relative',
		);

		$all_default = $this->ReadBits(1);
		if ($all_default === null) {
			return false;
		}
		if (!$all_default && !$this->ParseImageMetadata()) {
			return false;
		}

		$info['video']['resolution_x']    = $info['jpegxl']['width'];
		$info['video']['resolution_y']    = $info['jpegxl']['height'];
		$info['video']['bits_per_sample'] = $info['jpegxl']['bits_per_sample'];
		$info['video']['lossless']        = $info['jpegxl']['lossless'];

		return true;
	}

	/**
	 * Parse SizeHeader as defined in the JPEG XL specification. Only the fields
	 * needed to derive width/height are read.
	 *
	 * Layout (bits, LSB-first):
	 *  - div8:   u(1)
	 *  - h_div8: 1 + u(5)                                   if div8
	 *  - height: U32(1+u(9), 1+u(13), 1+u(18), 1+u(30))     if !div8, else 8*h_div8
	 *  - ratio:  u(3)
	 *  - w_div8: 1 + u(5)                                   if div8 && ratio == 0
	 *  - width:  U32(1+u(9), 1+u(13), 1+u(18), 1+u(30))     if !div8 && ratio == 0,
	 *            else computed from ratio and height.
	 *
	 * @return array|false array('width'=>int, 'height'=>int) or false
	 */
	private function ParseSizeHeader() {
		// A leading 1-bit flag selects how the dimensions are stored. When set,
		// each dimension is a small multiple of 8: a value N is read from a few
		// bits and the real size is 8 * (N + 1). When clear, the full pixel size
		// is read with the variable-width U32 coder.
		$div8 = $this->ReadBits(1);
		if ($div8 === null) {
			return false;
		}

		// Height is stored first.
		if ($div8) {
			$h_div8 = $this->ReadBits(5);
			if ($h_div8 === null) {
				return false;
			}
			$height = 8 * (1 + $h_div8);
		} else {
			// height: U32(1+u(9), 1+u(13), 1+u(18), 1+u(30))
			$height = $this->ReadU32(array(array(1, 9), array(1, 13), array(1, 18), array(1, 30)));
			if ($height === null) {
				return false;
			}
		}

		// A 3-bit aspect-ratio selector decides how the width is stored:
		//   0    = no preset ratio, so the width is stored explicitly (below);
		//   1..7 = a preset aspect ratio, so the width is derived from the height.
		$ratio = $this->ReadBits(3);
		if ($ratio === null) {
			return false;
		}

		if ($ratio === 0) {
			// Width is stored the same way height was (compact multiple-of-8 or full).
			if ($div8) {
				$w_div8 = $this->ReadBits(5);
				if ($w_div8 === null) {
					return false;
				}
				$width = 8 * (1 + $w_div8);
			} else {
				// width: U32(1+u(9), 1+u(13), 1+u(18), 1+u(30))
				$width = $this->ReadU32(array(array(1, 9), array(1, 13), array(1, 18), array(1, 30)));
				if ($width === null) {
					return false;
				}
			}
		} else {
			// Width is not stored; compute it from the height and the preset ratio.
			$width = $this->CalculateWidthFromRatio($height, $ratio);
			if ($width === false) {
				return false;
			}
		}

		if ($width <= 0 || $height <= 0) {
			return false;
		}

		return array('width' => (int) $width, 'height' => (int) $height);
	}

	/**
	 * Derive the width from the height using one of the preset aspect ratios
	 * of the SizeHeader ratio field (1:1, 12:10, 4:3, 3:2, 16:9, 5:4, 2:1).
	 *
	 * @param int $height
	 * @param int $ratio
	 *
	 * @return int|false
	 */
	private function CalculateWidthFromRatio($height, $ratio) {
		switch ($ratio) {
			case 1:
				return $height;
			case 2:
				return (int) floor($height * 12 / 10);
			case 3:
				return (int) floor($height * 4 / 3);
			case 4:
				return (int) floor($height * 3 / 2);
			case 5:
				return (int) floor($height * 16 / 9);
			case 6:
				return (int) floor($height * 5 / 4);
			case 7:
				return $height * 2;
		}
		return false;
	}

	/**
	 * Parse the ImageMetadata bundle up to and including ColourEncoding.
	 * Field order follows the JPEG XL specification. Writes into $info['jpegxl'].
	 *
	 * @return bool
	 */
	private function ParseImageMetadata() {
		$info = &$this->getid3->info;

		$extra_fields = $this->ReadBits(1);
		if ($extra_fields === null) {
			return false;
		}

		if ($extra_fields) {
			$orientation_enum = $this->ReadBits(3);
			if ($orientation_enum === null) {
				return false;
			}
			$info['jpegxl']['orientation'] = 1 + $orientation_enum;

			if ($this->ReadBits(1)) { // have_intrinsic_size
				$this->ParseSizeHeader();
			}
			if ($this->ReadBits(1)) { // have_preview
				$this->ParsePreviewHeader();
			}
			if ($this->ReadBits(1)) { // have_animation
				$info['jpegxl']['animated'] = true;
				// tps_numerator: U32(100, 1000, 1+u(10), 1+u(30))
				$tps_numerator   = $this->ReadU32(array(array(100, 0), array(1000, 0), array(1, 10), array(1, 30)));
				// tps_denominator: U32(1, 1001, 1+u(8), 1+u(10))
				$tps_denominator = $this->ReadU32(array(array(1, 0), array(1001, 0), array(1, 8), array(1, 10)));
				// num_loops: U32(0, u(3), u(16), u(32)); 0 = infinite
				$num_loops       = $this->ReadU32(array(array(0, 0), array(0, 3), array(0, 16), array(0, 32)));
				$have_timecodes  = $this->ReadBits(1);
				if ($tps_numerator !== null && $tps_denominator !== null && $num_loops !== null) {
					$info['jpegxl']['animation'] = array(
						'tps_numerator'   => $tps_numerator,
						'tps_denominator' => $tps_denominator,
						'num_loops'       => $num_loops,
						'has_timecodes'   => (bool) $have_timecodes,
					);
				}
			}
		}

		$bits = $this->ParseBitDepth();
		if ($bits === null) {
			return false;
		}
		$info['jpegxl']['bits_per_sample'] = $bits;

		$this->ReadBits(1); // modular_16bit_buffers

		// num_extra_channels: U32(0, 1, 2+u(4), 1+u(12))
		$num_extra = $this->ReadU32(array(array(0, 0), array(1, 0), array(2, 4), array(1, 12)));
		if ($num_extra === null) {
			return false;
		}
		for ($i = 0; $i < $num_extra; $i++) {
			$channel = $this->ParseExtraChannelInfo();
			if ($channel === null) {
				return false;
			}
			$info['jpegxl']['extra_channels'][$i] = array(
				'type'      => $channel['type'],
				'type_name' => self::ExtraChannelTypeLookup($channel['type']),
			);
			if ($channel['name'] !== '') {
				$info['jpegxl']['extra_channels'][$i]['name'] = $channel['name'];
			}
			if ($channel['type'] === 0) { // 0 = Alpha
				$info['jpegxl']['alpha'] = true;
			}
		}

		// xyb_encoded (lossy XYB transform) follows the extra-channel list;
		// !xyb_encoded keeps the original profile ("possibly lossless") and is
		// reported as lossless.
		$xyb_encoded = $this->ReadBits(1);
		if ($xyb_encoded === null) {
			return false;
		}
		$info['jpegxl']['xyb_encoded'] = (bool) $xyb_encoded;
		$info['jpegxl']['lossless']    = !$xyb_encoded;

		$this->ParseColourEncoding();

		// The rest of ImageMetadata (tone mapping and extensions) is not parsed.
		return true;
	}

	/**
	 * Parse the ColourEncoding bundle that follows xyb_encoded and expose it as
	 * labels under $info['jpegxl']['color']. When an ICC profile is embedded
	 * (want_icc) the profile itself is an entropy-coded stream we do not decode,
	 * so only 'icc_profile' and 'color_space' are known; the remaining fields
	 * (white point / primaries / transfer function / rendering intent) are only
	 * serialised, and thus only exposed, when want_icc is false.
	 *
	 * @return void
	 */
	private function ParseColourEncoding() {
		$info = &$this->getid3->info;

		$all_default = $this->ReadBits(1);
		if ($all_default === null || $all_default) {
			return; // keep the sRGB default seeded by ParseCodestreamHeader()
		}

		$want_icc = $this->ReadBits(1);
		if ($want_icc === null) {
			return;
		}
		$color_space = $this->ReadEnum();
		if ($color_space === null) {
			return;
		}

		$color = array(
			'icc_profile' => (bool) $want_icc,
			'color_space' => self::ColorSpaceLookup($color_space),
		);

		// The remaining enums are only present in the codestream when there is
		// no embedded ICC profile. XYB (color_space 2) implies fixed white point
		// and transfer function, so those are not serialised for it either.
		if (!$want_icc) {
			if ($color_space !== 2) {
				$white_point = $this->ReadEnum();
				if ($white_point === null) { $info['jpegxl']['color'] = $color; return; }
				$color['white_point'] = self::WhitePointLookup($white_point);
				if ($white_point === 2) { // Custom white point chromaticity
					if (($xy = $this->ReadCustomxy()) !== null) {
						$color['white_point_xy'] = $xy;
					}
				}
			}
			if ($color_space !== 1 && $color_space !== 2) { // HasPrimaries: RGB or Unknown
				$primaries = $this->ReadEnum();
				if ($primaries === null) { $info['jpegxl']['color'] = $color; return; }
				$color['primaries'] = self::PrimariesLookup($primaries);
				if ($primaries === 2) { // Custom red/green/blue chromaticities
					$red   = $this->ReadCustomxy();
					$green = $this->ReadCustomxy();
					$blue  = $this->ReadCustomxy();
					if ($red !== null && $green !== null && $blue !== null) {
						$color['primaries_xy'] = array(
							'red'   => $red,
							'green' => $green,
							'blue'  => $blue,
						);
					}
				}
			}
			if ($color_space !== 2) {
				$have_gamma = $this->ReadBits(1);
				if ($have_gamma === null) { $info['jpegxl']['color'] = $color; return; }
				if ($have_gamma) {
					$gamma = $this->ReadBits(24);
					$color['transfer'] = 'gamma';
					if ($gamma !== null && $gamma > 0) {
						$color['gamma'] = round($gamma / 10000000, 7); // stored value is the exponent
					}
				} else {
					$transfer = $this->ReadEnum();
					if ($transfer === null) { $info['jpegxl']['color'] = $color; return; }
					$color['transfer'] = self::TransferFunctionLookup($transfer);
				}
			}
			$rendering_intent = $this->ReadEnum();
			if ($rendering_intent !== null) {
				$color['rendering_intent'] = self::RenderingIntentLookup($rendering_intent);
			}
		}

		$info['jpegxl']['color'] = $color;
	}

	/**
	 * Read a JPEG XL Enum: the same 2-bit-selector U32 the specification uses
	 * for enumerated fields (00->0, 01->1, 10xxxx->2..17, 11yyyyyy->18..81).
	 *
	 * @return int|null
	 */
	private function ReadEnum() {
		// U32(0, 1, 2+u(4), 18+u(6))
		return $this->ReadU32(array(array(0, 0), array(1, 0), array(2, 4), array(18, 6)));
	}

	/**
	 * Read a Customxy bundle: two U32 values holding the x and y CIE chromaticity
	 * coordinates. Each is PackSigned (zig-zag) encoded and scaled by 1/1000000.
	 *
	 * @return array{x: float, y: float}|null Coordinates, or null if the stream is truncated.
	 */
	private function ReadCustomxy() {
		// U32(u(19), 524288+u(19), 1048576+u(20), 2097152+u(21))
		$x = $this->ReadU32(array(array(0, 19), array(524288, 19), array(1048576, 20), array(2097152, 21)));
		$y = $this->ReadU32(array(array(0, 19), array(524288, 19), array(1048576, 20), array(2097152, 21)));
		if ($x === null || $y === null) {
			return null;
		}
		return array(
			'x' => round($this->UnpackSigned($x) / 1000000, 6),
			'y' => round($this->UnpackSigned($y) / 1000000, 6),
		);
	}

	/**
	 * Reverse JPEG XL's PackSigned zig-zag mapping: even values map to the
	 * non-negative integers, odd values to the negative ones,
	 * e.g. 0,1,2,3,4 -> 0,-1,1,-2,2.
	 *
	 * @param int $value
	 *
	 * @return int
	 */
	private function UnpackSigned($value) {
		$value = (int) $value;
		return ($value >> 1) ^ (((~$value) & 1) - 1);
	}

	/**
	 * Skip a PreviewHeader bundle to stay bit-aligned; the preview dimensions
	 * are read but discarded. Possible future improvement: expose them.
	 *
	 * @return void
	 */
	private function ParsePreviewHeader() {
		$div8 = $this->ReadBits(1);
		if ($div8) {
			// h_div8: U32(16, 32, 1+u(5), 33+u(9))
			$this->ReadU32(array(array(16, 0), array(32, 0), array(1, 5), array(33, 9)));
		} else {
			// height: U32(1+u(6), 65+u(8), 321+u(10), 1345+u(12))
			$this->ReadU32(array(array(1, 6), array(65, 8), array(321, 10), array(1345, 12)));
		}
		$ratio = $this->ReadBits(3);
		if ($ratio === 0) {
			if ($div8) {
				// w_div8: U32(16, 32, 1+u(5), 33+u(9))
				$this->ReadU32(array(array(16, 0), array(32, 0), array(1, 5), array(33, 9)));
			} else {
				// width: U32(1+u(6), 65+u(8), 321+u(10), 1345+u(12))
				$this->ReadU32(array(array(1, 6), array(65, 8), array(321, 10), array(1345, 12)));
			}
		}
	}

	/**
	 * Parse a BitDepth bundle and return bits_per_sample.
	 *
	 * @return int|null
	 */
	private function ParseBitDepth() {
		$float_sample = $this->ReadBits(1);
		if ($float_sample === null) {
			return null;
		}
		if ($float_sample) {
			// bits_per_sample: U32(32, 16, 24, 1+u(6))
			$bits = $this->ReadU32(array(array(32, 0), array(16, 0), array(24, 0), array(1, 6)));
			$this->ReadBits(4); // exp_bits
			return $bits;
		}
		// bits_per_sample: U32(8, 10, 12, 1+u(6))
		return $this->ReadU32(array(array(8, 0), array(10, 0), array(12, 0), array(1, 6)));
	}

	/**
	 * Parse an ExtraChannelInfo bundle, consuming all of its bits and returning
	 * its type (0 = Alpha) and name. Field order follows the JPEG XL spec.
	 *
	 * @return array{type: int, name: string}|null
	 */
	private function ParseExtraChannelInfo() {
		$default_alpha = $this->ReadBits(1);
		if ($default_alpha === null) {
			return null;
		}
		if ($default_alpha) {
			return array('type' => 0, 'name' => ''); // Alpha channel with default parameters
		}

		// type: U32(0, 1, 2+u(4), 18+u(6)) (Enum)
		$type = $this->ReadU32(array(array(0, 0), array(1, 0), array(2, 4), array(18, 6)));
		if ($type === null) {
			return null;
		}
		$this->ParseBitDepth();
		// dim_shift: U32(0, 3, 4, 1+u(3))
		$this->ReadU32(array(array(0, 0), array(3, 0), array(4, 0), array(1, 3)));
		$name = $this->ReadName();

		switch ($type) {
			case 0: // Alpha
				$this->ReadBits(1); // alpha_associated
				break;
			case 2: // SpotColour: 4 x f16
				$this->ReadBits(16);
				$this->ReadBits(16);
				$this->ReadBits(16);
				$this->ReadBits(16);
				break;
			case 5: // Cfa
				// cfa_channel: U32(1, u(2), 3+u(4), 19+u(8))
				$this->ReadU32(array(array(1, 0), array(0, 2), array(3, 4), array(19, 8)));
				break;
		}

		return array('type' => $type, 'name' => $name);
	}

	/**
	 * Read a Name bundle: a U32 length followed by that many bytes (UTF-8).
	 *
	 * @return string
	 */
	private function ReadName() {
		// length: U32(0, u(4), 16+u(5), 48+u(10))
		$len = $this->ReadU32(array(array(0, 0), array(0, 4), array(16, 5), array(48, 10)));
		if ($len === null) {
			return '';
		}
		$name = '';
		for ($i = 0; $i < $len; $i++) {
			$byte = $this->ReadBits(8);
			if ($byte === null) {
				break;
			}
			$name .= chr($byte);
		}
		return $name;
	}

	/**
	 * Read a U32 as defined in the JPEG XL spec: a 2-bit selector chooses one of
	 * four distributions. Each distribution is array(offset, bits): the value is
	 * $offset plus an unsigned $bits-bit value ($bits == 0 means the constant
	 * $offset).
	 *
	 * @param array $distributions Four array(offset, bits) pairs.
	 *
	 * @return int|float|null Float only when the value exceeds PHP_INT_MAX (u(32) on 32-bit PHP).
	 */
	private function ReadU32($distributions) {
		$sel = $this->ReadBits(2);
		if ($sel === null) {
			return null;
		}
		list($offset, $bits) = $distributions[$sel];
		if ($bits === 0) {
			return $offset;
		}
		$value = $this->ReadBits($bits);
		if ($value === null) {
			return null;
		}
		return $offset + $value;
	}

	/**
	 * Read $n bits LSB-first from the internal buffer, one bit at a time. The
	 * bits are collected as a string and converted with getid3_lib::Bin2Dec(),
	 * so a read wider than the native integer (e.g. u(32) on 32-bit PHP)
	 * promotes to float instead of overflowing.
	 *
	 * @param int $n
	 *
	 * @return int|float|null Value, or null if there are not enough bits remaining.
	 */
	private function ReadBits($n) {
		if ($n <= 0) {
			return 0;
		}
		$binary = '';
		for ($i = 0; $i < $n; $i++) {
			if ($this->bytepos >= strlen($this->bitdata)) {
				return null;
			}
			// Prepend: bits arrive LSB-first, Bin2Dec() expects MSB-first.
			$binary = (string) ((ord($this->bitdata[$this->bytepos]) >> $this->bitpos) & 1) . $binary;
			if (++$this->bitpos === 8) {
				$this->bitpos = 0;
				$this->bytepos++;
			}
		}
		return getid3_lib::Bin2Dec($binary);
	}

	/**
	 * Map a JPEG XL ExtraChannel enum value to a label.
	 *
	 * @param int $type
	 *
	 * @return string
	 */
	public static function ExtraChannelTypeLookup($type) {
		static $lookup = array(
			0  => 'Alpha',
			1  => 'Depth',
			2  => 'Spot color',
			3  => 'Selection mask',
			4  => 'Black',
			5  => 'Color filter array',
			6  => 'Thermal',
			15 => 'Unknown',
			16 => 'Optional',
		);
		return isset($lookup[$type]) ? $lookup[$type] : 'reserved';
	}

	/**
	 * Map a JPEG XL ColorSpace enum value to a label.
	 *
	 * @param int $value
	 *
	 * @return string
	 */
	public static function ColorSpaceLookup($value) {
		static $lookup = array(
			0 => 'RGB',
			1 => 'Grayscale',
			2 => 'XYB',
			3 => 'Unknown',
		);
		return isset($lookup[$value]) ? $lookup[$value] : 'reserved';
	}

	/**
	 * Map a JPEG XL WhitePoint enum value to a label.
	 *
	 * @param int $value
	 *
	 * @return string
	 */
	public static function WhitePointLookup($value) {
		static $lookup = array(
			1  => 'D65',
			2  => 'Custom',
			10 => 'E',
			11 => 'DCI',
		);
		return isset($lookup[$value]) ? $lookup[$value] : 'reserved';
	}

	/**
	 * Map a JPEG XL Primaries enum value to a label.
	 *
	 * @param int $value
	 *
	 * @return string
	 */
	public static function PrimariesLookup($value) {
		static $lookup = array(
			1  => 'sRGB',
			2  => 'Custom',
			9  => 'BT.2100',
			11 => 'P3',
		);
		return isset($lookup[$value]) ? $lookup[$value] : 'reserved';
	}

	/**
	 * Map a JPEG XL TransferFunction enum value to a label.
	 *
	 * @param int $value
	 *
	 * @return string
	 */
	public static function TransferFunctionLookup($value) {
		static $lookup = array(
			1  => 'BT.709',
			2  => 'Unknown',
			8  => 'Linear',
			13 => 'sRGB',
			16 => 'PQ',
			17 => 'DCI',
			18 => 'HLG',
		);
		return isset($lookup[$value]) ? $lookup[$value] : 'reserved';
	}

	/**
	 * Map a JPEG XL RenderingIntent enum value to a label.
	 *
	 * @param int $value
	 *
	 * @return string
	 */
	public static function RenderingIntentLookup($value) {
		static $lookup = array(
			0 => 'Perceptual',
			1 => 'Relative',
			2 => 'Saturation',
			3 => 'Absolute',
		);
		return isset($lookup[$value]) ? $lookup[$value] : 'reserved';
	}
}
