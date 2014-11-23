Known Bugs/Issues in other programs
===================================

<http://www.getid3.org/phpBB3/viewtopic.php?t=25>

* Windows Media Player (up to v11) and iTunes (up to v10+) do
    not correctly handle ID3v2.3 tags with UTF-16BE+BOM
    encoding (they assume the data is UTF-16LE+BOM and either
    crash (WMP) or output Asian character set (iTunes)
* Winamp (up to v2.80 at least) does not support ID3v2.4 tags,
    only ID3v2.3
    see: http://forums.winamp.com/showthread.php?postid=387524
* Some versions of Helium2 (www.helium2.com) do not write
    ID3v2.4-compliant Frame Sizes, even though the tag is marked
    as ID3v2.4)  (detected by getID3())
* MP3ext V3.3.17 places a non-compliant padding string at the end
    of the ID3v2 header. This is supposedly fixed in v3.4b21 but
    only if you manually add a registry key. This fix is not yet
    confirmed.  (detected by getID3())
* CDex v1.40 (fixed by v1.50b7) writes non-compliant Ogg comment
    strings, supposed to be in the format "NAME=value" but actually
    written just "value"  (detected by getID3())
* Oggenc 0.9-rc3 flags the encoded file as ABR whether it's
    actually ABR or VBR.
* iTunes (versions "X v2.0.3", "v3.0.1" are known-guilty, probably
    other versions are too) writes ID3v2.3 comment tags using a
    frame name 'COM ' which is not valid for ID3v2.3+ (it's an
    ID3v2.2-style frame name)  (detected by getID3())
* MP2enc does not encode mono CBR MP2 files properly (half speed
    sound and double playtime)
* MP2enc does not encode mono VBR MP2 files properly (actually
    encoded as stereo)
* tooLAME does not encode mono VBR MP2 files properly (actually
    encoded as stereo)
* AACenc encodes files in VBR mode (actually ABR) even if CBR is
   specified
* AAC/ADIF - bitrate_mode = cbr for vbr files
* LAME 3.90-3.92 prepends one frame of null data (space for the
  LAME/VBR header, but it never gets written) when encoding in CBR
  mode with the DLL
* Ahead Nero encodes TwinVQF with a DSIZ value (which is supposed
  to be the filesize in bytes) of "0" for TwinVQF v1.0 and "1" for
  TwinVQF v2.0  (detected by getID3())
* Ahead Nero encodes TwinVQF files 1 second shorter than they
  should be
* AAC-ADTS files are always actually encoded VBR, even if CBR mode
  is specified (the CBR-mode switches on the encoder enable ABR
  mode, not CBR as such, but it's not possible to tell the
  difference between such ABR files and true VBR)
* STREAMINFO.audio_signature in OggFLAC is always null. "The reason
  it's like that is because there is no seeking support in
  libOggFLAC yet, so it has no way to go back and write the
  computed sum after encoding. Seeking support in Ogg FLAC is the
  #1 item for the next release." - Josh Coalson (FLAC developer)
  NOTE: getID3() will calculate md5_data in a method similar to
  other file formats, but that value cannot be compared to the
  md5_data value from FLAC data in a FLAC file format.
* STREAMINFO.audio_signature is not calculated in FLAC v0.3.0 &
  v0.4.0 - getID3() will calculate md5_data in a method similar to
  other file formats, but that value cannot be compared to the
  md5_data value from FLAC v0.5.0+
* RioPort (various versions including 2.0 and 3.11) tags ID3v2 with
  a WCOM frame that has no data portion
* Earlier versions of Coolplayer adds illegal ID3 tags to Ogg Vorbis
  files, thus making them corrupt.
* Meracl ID3 Tag Writer v1.3.4 (and older) incorrectly truncates the
  last byte of data from an MP3 file when appending a new ID3v1 tag.
  (detected by getID3())
* Lossless-Audio files encoded with and without the -noseek switch
  do actually differ internally and therefore cannot match md5_data
* iTunes has been known to append a new ID3v1 tag on the end of an
  existing ID3v1 tag when ID3v2 tag is also present
  (detected by getID3())
* MediaMonkey may write a blank RGAD ID3v2 frame but put actual
  replay gain adjustments in a series of user-defined TXXX frames
  (detected and handled by getID3() since v1.9.2)
