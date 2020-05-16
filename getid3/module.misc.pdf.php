<?php

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.misc.pdf.php                                         //
// module for analyzing PDF files                              //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

if (!defined('GETID3_INCLUDEPATH')) { // prevent path-exposing attacks that access modules directly on public webservers
	exit;
}

class getid3_pdf extends getid3_handler
{
	public $returnXREF = false; // return full details of PDF Cross-Reference Table (XREF)

	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$this->fseek(0);
		if (preg_match('#^%PDF-([0-9\\.]+)$#', trim($this->fgets()), $matches)) {
			$info['pdf']['header']['version'] = floatval($matches[1]);
			$info['fileformat'] = 'pdf';

			// the PDF Cross-Reference Table (XREF) is located near the end of the file
			// the starting offset is specified in the penultimate section, on the two lines just before "%%EOF"
			// the first line is "startxref", the second line is the byte offset of the XREF.
			// We know the length of "%%EOF" and "startxref", but the offset could be 2-10 bytes,
			// and we're not sure if the line ends are one or two bytes, so we might find "startxref" as little as 18(?) bytes
			// from EOF, but it could 30 bytes, so we start 40 bytes back just to be safe and do a search for the data we want.
			$this->fseek(-40, SEEK_END);
			if (preg_match('#[\r\n]startxref[ \r\n]+([0-9]+)[ \r\n]+#', $this->fread(40), $matches)) {
				$info['pdf']['trailer']['startxref'] = intval($matches[1]);
				$this->fseek($info['pdf']['trailer']['startxref']);
				if (trim($this->fgets()) == 'xref') {
					list($firstObjectNumber, $info['pdf']['xref']['count']) = explode(' ', trim($this->fgets()));
					$info['pdf']['xref']['count'] = (int) $info['pdf']['xref']['count'];
					for ($i = 0; $i < $info['pdf']['xref']['count']; $i++) {
						list($offset, $generation, $entry) = explode(' ', trim($this->fgets()));
						$info['pdf']['xref']['offset'][($firstObjectNumber + $i)]     = (int) $offset;
						$info['pdf']['xref']['generation'][($firstObjectNumber + $i)] = (int) $generation;
						$info['pdf']['xref']['entry'][($firstObjectNumber + $i)]      = $entry;
					}
					foreach ($info['pdf']['xref']['offset'] as $objectNumber => $offset) {
						if ($info['pdf']['xref']['entry'][$objectNumber] == 'f') {
							// "free" object means "deleted", ignore
							continue;
						}
						$this->fseek($offset);
						$line = trim($this->fgets());
						if (preg_match('#^'.$objectNumber.' ([0-9]+) obj$#', $line)) {
							$objectData  = '';
							while (true) {
								$line = $this->fgets();
								if (trim($line) == 'endobj') {
									break;
								}
								$objectData .= $line;
							}
							if (strpos($objectData, '/Type /Pages') !== false) {
								if (preg_match('#/Count ([0-9]+)#', $objectData, $matches)) {
									$info['pdf']['pages'] = (int) $matches[1];
									break; // for now this is the only data we're looking for in the PDF not need to loop through every object in the file (and a large PDF may contain MANY objects). And it MAY be possible that there are other objects elsewhere in the file that define additional (or removed?) pages
								}
							}
						} else {
							$this->error('Unexpected structure "'.$line.'" at offset '.$offset);
							break;
						}
					}
					if (!$this->returnXREF) {
						unset($info['pdf']['xref']['offset'], $info['pdf']['xref']['generation'], $info['pdf']['xref']['entry']);
					}

				} else {
					$this->error('Did not find "xref" at offset '.$info['pdf']['trailer']['startxref']);
				}
			} else {
				$this->error('Did not find "startxref" in the last 40 bytes of the PDF');
			}

			$this->warning('PDF parsing incomplete in this version of getID3() ['.$this->getid3->version().']');
			return true;
		}
		$this->error('Did not find "%PDF" at the beginning of the PDF');
		return false;

	}

}
