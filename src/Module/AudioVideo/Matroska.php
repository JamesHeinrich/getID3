<?php

namespace JamesHeinrich\GetID3\Module\AudioVideo;

use JamesHeinrich\GetID3\Exception;
use JamesHeinrich\GetID3\GetID3;
use JamesHeinrich\GetID3\Module\Audio\Ogg;
use JamesHeinrich\GetID3\Module\Audio\Ac3;
use JamesHeinrich\GetID3\Module\Audio\Dts;
use JamesHeinrich\GetID3\Module\Audio\Mp3;
use JamesHeinrich\GetID3\Module\Audio\Flac;
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
// module.audio-video.matriska.php                             //
// module for analyzing Matroska containers                    //
//                                                            ///
/////////////////////////////////////////////////////////////////

/**
* @tutorial http://www.matroska.org/technical/specs/index.html
*
* @todo Rewrite EBML parser to reduce it's size and honor default element values
* @todo After rewrite implement stream size calculation, that will provide additional useful info and enable AAC/FLAC audio bitrate detection
*/
class Matroska extends \JamesHeinrich\GetID3\Module\Handler
{
    const ID_CHAPTERS =                   0x0043A770; // [10][43][A7][70] -- A system to define basic menus and partition data. For more detailed information, look at the Chapters Explanation.
    const ID_SEEKHEAD =                   0x014D9B74; // [11][4D][9B][74] -- Contains the position of other level 1 elements.
    const ID_TAGS =                       0x0254C367; // [12][54][C3][67] -- Element containing elements specific to Tracks/Chapters. A list of valid tags can be found <http://www.matroska.org/technical/specs/tagging/index.html>.
    const ID_INFO =                       0x0549A966; // [15][49][A9][66] -- Contains miscellaneous general information and statistics on the file.
    const ID_TRACKS =                     0x0654AE6B; // [16][54][AE][6B] -- A top-level block of information with many tracks described.
    const ID_SEGMENT =                    0x08538067; // [18][53][80][67] -- This element contains all other top-level (level 1) elements. Typically a Matroska file is composed of 1 segment.
    const ID_ATTACHMENTS =                0x0941A469; // [19][41][A4][69] -- Contain attached files.
    const ID_EBML =                       0x0A45DFA3; // [1A][45][DF][A3] -- Set the EBML characteristics of the data to follow. Each EBML document has to start with this.
    const ID_CUES =                       0x0C53BB6B; // [1C][53][BB][6B] -- A top-level element to speed seeking access. All entries are local to the segment.
    const ID_CLUSTER =                    0x0F43B675; // [1F][43][B6][75] -- The lower level element containing the (monolithic) Block structure.
    const ID_LANGUAGE =                     0x02B59C; //     [22][B5][9C] -- Specifies the language of the track in the Matroska languages form.
    const ID_TRACKTIMECODESCALE =           0x03314F; //     [23][31][4F] -- The scale to apply on this track to work at normal speed in relation with other tracks (mostly used to adjust video speed when the audio length differs).
    const ID_DEFAULTDURATION =              0x03E383; //     [23][E3][83] -- Number of nanoseconds (i.e. not scaled) per frame.
    const ID_CODECNAME =                    0x058688; //     [25][86][88] -- A human-readable string specifying the codec.
    const ID_CODECDOWNLOADURL =             0x06B240; //     [26][B2][40] -- A URL to download about the codec used.
    const ID_TIMECODESCALE =                0x0AD7B1; //     [2A][D7][B1] -- Timecode scale in nanoseconds (1.000.000 means all timecodes in the segment are expressed in milliseconds).
    const ID_COLOURSPACE =                  0x0EB524; //     [2E][B5][24] -- Same value as in AVI (32 bits).
    const ID_GAMMAVALUE =                   0x0FB523; //     [2F][B5][23] -- Gamma Value.
    const ID_CODECSETTINGS =                0x1A9697; //     [3A][96][97] -- A string describing the encoding setting used.
    const ID_CODECINFOURL =                 0x1B4040; //     [3B][40][40] -- A URL to find information about the codec used.
    const ID_PREVFILENAME =                 0x1C83AB; //     [3C][83][AB] -- An escaped filename corresponding to the previous segment.
    const ID_PREVUID =                      0x1CB923; //     [3C][B9][23] -- A unique ID to identify the previous chained segment (128 bits).
    const ID_NEXTFILENAME =                 0x1E83BB; //     [3E][83][BB] -- An escaped filename corresponding to the next segment.
    const ID_NEXTUID =                      0x1EB923; //     [3E][B9][23] -- A unique ID to identify the next chained segment (128 bits).
    const ID_CONTENTCOMPALGO =                0x0254; //         [42][54] -- The compression algorithm used. Algorithms that have been specified so far are:
    const ID_CONTENTCOMPSETTINGS =            0x0255; //         [42][55] -- Settings that might be needed by the decompressor. For Header Stripping (ContentCompAlgo=3), the bytes that were removed from the beggining of each frames of the track.
    const ID_DOCTYPE =                        0x0282; //         [42][82] -- A string that describes the type of document that follows this EBML header ('matroska' in our case).
    const ID_DOCTYPEREADVERSION =             0x0285; //         [42][85] -- The minimum DocType version an interpreter has to support to read this file.
    const ID_EBMLVERSION =                    0x0286; //         [42][86] -- The version of EBML parser used to create the file.
    const ID_DOCTYPEVERSION =                 0x0287; //         [42][87] -- The version of DocType interpreter used to create the file.
    const ID_EBMLMAXIDLENGTH =                0x02F2; //         [42][F2] -- The maximum length of the IDs you'll find in this file (4 or less in Matroska).
    const ID_EBMLMAXSIZELENGTH =              0x02F3; //         [42][F3] -- The maximum length of the sizes you'll find in this file (8 or less in Matroska). This does not override the element size indicated at the beginning of an element. Elements that have an indicated size which is larger than what is allowed by EBMLMaxSizeLength shall be considered invalid.
    const ID_EBMLREADVERSION =                0x02F7; //         [42][F7] -- The minimum EBML version a parser has to support to read this file.
    const ID_CHAPLANGUAGE =                   0x037C; //         [43][7C] -- The languages corresponding to the string, in the bibliographic ISO-639-2 form.
    const ID_CHAPCOUNTRY =                    0x037E; //         [43][7E] -- The countries corresponding to the string, same 2 octets as in Internet domains.
    const ID_SEGMENTFAMILY =                  0x0444; //         [44][44] -- A randomly generated unique ID that all segments related to each other must use (128 bits).
    const ID_DATEUTC =                        0x0461; //         [44][61] -- Date of the origin of timecode (value 0), i.e. production date.
    const ID_TAGLANGUAGE =                    0x047A; //         [44][7A] -- Specifies the language of the tag specified, in the Matroska languages form.
    const ID_TAGDEFAULT =                     0x0484; //         [44][84] -- Indication to know if this is the default/original language to use for the given tag.
    const ID_TAGBINARY =                      0x0485; //         [44][85] -- The values of the Tag if it is binary. Note that this cannot be used in the same SimpleTag as TagString.
    const ID_TAGSTRING =                      0x0487; //         [44][87] -- The value of the Tag.
    const ID_DURATION =                       0x0489; //         [44][89] -- Duration of the segment (based on TimecodeScale).
    const ID_CHAPPROCESSPRIVATE =             0x050D; //         [45][0D] -- Some optional data attached to the ChapProcessCodecID information. For ChapProcessCodecID = 1, it is the "DVD level" equivalent.
    const ID_CHAPTERFLAGENABLED =             0x0598; //         [45][98] -- Specify wether the chapter is enabled. It can be enabled/disabled by a Control Track. When disabled, the movie should skip all the content between the TimeStart and TimeEnd of this chapter.
    const ID_TAGNAME =                        0x05A3; //         [45][A3] -- The name of the Tag that is going to be stored.
    const ID_EDITIONENTRY =                   0x05B9; //         [45][B9] -- Contains all information about a segment edition.
    const ID_EDITIONUID =                     0x05BC; //         [45][BC] -- A unique ID to identify the edition. It's useful for tagging an edition.
    const ID_EDITIONFLAGHIDDEN =              0x05BD; //         [45][BD] -- If an edition is hidden (1), it should not be available to the user interface (but still to Control Tracks).
    const ID_EDITIONFLAGDEFAULT =             0x05DB; //         [45][DB] -- If a flag is set (1) the edition should be used as the default one.
    const ID_EDITIONFLAGORDERED =             0x05DD; //         [45][DD] -- Specify if the chapters can be defined multiple times and the order to play them is enforced.
    const ID_FILEDATA =                       0x065C; //         [46][5C] -- The data of the file.
    const ID_FILEMIMETYPE =                   0x0660; //         [46][60] -- MIME type of the file.
    const ID_FILENAME =                       0x066E; //         [46][6E] -- Filename of the attached file.
    const ID_FILEREFERRAL =                   0x0675; //         [46][75] -- A binary value that a track/codec can refer to when the attachment is needed.
    const ID_FILEDESCRIPTION =                0x067E; //         [46][7E] -- A human-friendly name for the attached file.
    const ID_FILEUID =                        0x06AE; //         [46][AE] -- Unique ID representing the file, as random as possible.
    const ID_CONTENTENCALGO =                 0x07E1; //         [47][E1] -- The encryption algorithm used. The value '0' means that the contents have not been encrypted but only signed. Predefined values:
    const ID_CONTENTENCKEYID =                0x07E2; //         [47][E2] -- For public key algorithms this is the ID of the public key the the data was encrypted with.
    const ID_CONTENTSIGNATURE =               0x07E3; //         [47][E3] -- A cryptographic signature of the contents.
    const ID_CONTENTSIGKEYID =                0x07E4; //         [47][E4] -- This is the ID of the private key the data was signed with.
    const ID_CONTENTSIGALGO =                 0x07E5; //         [47][E5] -- The algorithm used for the signature. A value of '0' means that the contents have not been signed but only encrypted. Predefined values:
    const ID_CONTENTSIGHASHALGO =             0x07E6; //         [47][E6] -- The hash algorithm used for the signature. A value of '0' means that the contents have not been signed but only encrypted. Predefined values:
    const ID_MUXINGAPP =                      0x0D80; //         [4D][80] -- Muxing application or library ("libmatroska-0.4.3").
    const ID_SEEK =                           0x0DBB; //         [4D][BB] -- Contains a single seek entry to an EBML element.
    const ID_CONTENTENCODINGORDER =           0x1031; //         [50][31] -- Tells when this modification was used during encoding/muxing starting with 0 and counting upwards. The decoder/demuxer has to start with the highest order number it finds and work its way down. This value has to be unique over all ContentEncodingOrder elements in the segment.
    const ID_CONTENTENCODINGSCOPE =           0x1032; //         [50][32] -- A bit field that describes which elements have been modified in this way. Values (big endian) can be OR'ed. Possible values:
    const ID_CONTENTENCODINGTYPE =            0x1033; //         [50][33] -- A value describing what kind of transformation has been done. Possible values:
    const ID_CONTENTCOMPRESSION =             0x1034; //         [50][34] -- Settings describing the compression used. Must be present if the value of ContentEncodingType is 0 and absent otherwise. Each block must be decompressable even if no previous block is available in order not to prevent seeking.
    const ID_CONTENTENCRYPTION =              0x1035; //         [50][35] -- Settings describing the encryption used. Must be present if the value of ContentEncodingType is 1 and absent otherwise.
    const ID_CUEREFNUMBER =                   0x135F; //         [53][5F] -- Number of the referenced Block of Track X in the specified Cluster.
    const ID_NAME =                           0x136E; //         [53][6E] -- A human-readable track name.
    const ID_CUEBLOCKNUMBER =                 0x1378; //         [53][78] -- Number of the Block in the specified Cluster.
    const ID_TRACKOFFSET =                    0x137F; //         [53][7F] -- A value to add to the Block's Timecode. This can be used to adjust the playback offset of a track.
    const ID_SEEKID =                         0x13AB; //         [53][AB] -- The binary ID corresponding to the element name.
    const ID_SEEKPOSITION =                   0x13AC; //         [53][AC] -- The position of the element in the segment in octets (0 = first level 1 element).
    const ID_STEREOMODE =                     0x13B8; //         [53][B8] -- Stereo-3D video mode.
    const ID_OLDSTEREOMODE =                  0x13B9; //         [53][B9] -- Bogus StereoMode value used in old versions of libmatroska. DO NOT USE. (0: mono, 1: right eye, 2: left eye, 3: both eyes).
    const ID_PIXELCROPBOTTOM =                0x14AA; //         [54][AA] -- The number of video pixels to remove at the bottom of the image (for HDTV content).
    const ID_DISPLAYWIDTH =                   0x14B0; //         [54][B0] -- Width of the video frames to display.
    const ID_DISPLAYUNIT =                    0x14B2; //         [54][B2] -- Type of the unit for DisplayWidth/Height (0: pixels, 1: centimeters, 2: inches).
    const ID_ASPECTRATIOTYPE =                0x14B3; //         [54][B3] -- Specify the possible modifications to the aspect ratio (0: free resizing, 1: keep aspect ratio, 2: fixed).
    const ID_DISPLAYHEIGHT =                  0x14BA; //         [54][BA] -- Height of the video frames to display.
    const ID_PIXELCROPTOP =                   0x14BB; //         [54][BB] -- The number of video pixels to remove at the top of the image.
    const ID_PIXELCROPLEFT =                  0x14CC; //         [54][CC] -- The number of video pixels to remove on the left of the image.
    const ID_PIXELCROPRIGHT =                 0x14DD; //         [54][DD] -- The number of video pixels to remove on the right of the image.
    const ID_FLAGFORCED =                     0x15AA; //         [55][AA] -- Set if that track MUST be used during playback. There can be many forced track for a kind (audio, video or subs), the player should select the one which language matches the user preference or the default + forced track. Overlay MAY happen between a forced and non-forced track of the same kind.
    const ID_MAXBLOCKADDITIONID =             0x15EE; //         [55][EE] -- The maximum value of BlockAddID. A value 0 means there is no BlockAdditions for this track.
    const ID_WRITINGAPP =                     0x1741; //         [57][41] -- Writing application ("mkvmerge-0.3.3").
    const ID_CLUSTERSILENTTRACKS =            0x1854; //         [58][54] -- The list of tracks that are not used in that part of the stream. It is useful when using overlay tracks on seeking. Then you should decide what track to use.
    const ID_CLUSTERSILENTTRACKNUMBER =       0x18D7; //         [58][D7] -- One of the track number that are not used from now on in the stream. It could change later if not specified as silent in a further Cluster.
    const ID_ATTACHEDFILE =                   0x21A7; //         [61][A7] -- An attached file.
    const ID_CONTENTENCODING =                0x2240; //         [62][40] -- Settings for one content encoding like compression or encryption.
    const ID_BITDEPTH =                       0x2264; //         [62][64] -- Bits per sample, mostly used for PCM.
    const ID_CODECPRIVATE =                   0x23A2; //         [63][A2] -- Private data only known to the codec.
    const ID_TARGETS =                        0x23C0; //         [63][C0] -- Contain all UIDs where the specified meta data apply. It is void to describe everything in the segment.
    const ID_CHAPTERPHYSICALEQUIV =           0x23C3; //         [63][C3] -- Specify the physical equivalent of this ChapterAtom like "DVD" (60) or "SIDE" (50), see complete list of values.
    const ID_TAGCHAPTERUID =                  0x23C4; //         [63][C4] -- A unique ID to identify the Chapter(s) the tags belong to. If the value is 0 at this level, the tags apply to all chapters in the Segment.
    const ID_TAGTRACKUID =                    0x23C5; //         [63][C5] -- A unique ID to identify the Track(s) the tags belong to. If the value is 0 at this level, the tags apply to all tracks in the Segment.
    const ID_TAGATTACHMENTUID =               0x23C6; //         [63][C6] -- A unique ID to identify the Attachment(s) the tags belong to. If the value is 0 at this level, the tags apply to all the attachments in the Segment.
    const ID_TAGEDITIONUID =                  0x23C9; //         [63][C9] -- A unique ID to identify the EditionEntry(s) the tags belong to. If the value is 0 at this level, the tags apply to all editions in the Segment.
    const ID_TARGETTYPE =                     0x23CA; //         [63][CA] -- An informational string that can be used to display the logical level of the target like "ALBUM", "TRACK", "MOVIE", "CHAPTER", etc (see TargetType).
    const ID_TRACKTRANSLATE =                 0x2624; //         [66][24] -- The track identification for the given Chapter Codec.
    const ID_TRACKTRANSLATETRACKID =          0x26A5; //         [66][A5] -- The binary value used to represent this track in the chapter codec data. The format depends on the ChapProcessCodecID used.
    const ID_TRACKTRANSLATECODEC =            0x26BF; //         [66][BF] -- The chapter codec using this ID (0: Matroska Script, 1: DVD-menu).
    const ID_TRACKTRANSLATEEDITIONUID =       0x26FC; //         [66][FC] -- Specify an edition UID on which this translation applies. When not specified, it means for all editions found in the segment.
    const ID_SIMPLETAG =                      0x27C8; //         [67][C8] -- Contains general information about the target.
    const ID_TARGETTYPEVALUE =                0x28CA; //         [68][CA] -- A number to indicate the logical level of the target (see TargetType).
    const ID_CHAPPROCESSCOMMAND =             0x2911; //         [69][11] -- Contains all the commands associated to the Atom.
    const ID_CHAPPROCESSTIME =                0x2922; //         [69][22] -- Defines when the process command should be handled (0: during the whole chapter, 1: before starting playback, 2: after playback of the chapter).
    const ID_CHAPTERTRANSLATE =               0x2924; //         [69][24] -- A tuple of corresponding ID used by chapter codecs to represent this segment.
    const ID_CHAPPROCESSDATA =                0x2933; //         [69][33] -- Contains the command information. The data should be interpreted depending on the ChapProcessCodecID value. For ChapProcessCodecID = 1, the data correspond to the binary DVD cell pre/post commands.
    const ID_CHAPPROCESS =                    0x2944; //         [69][44] -- Contains all the commands associated to the Atom.
    const ID_CHAPPROCESSCODECID =             0x2955; //         [69][55] -- Contains the type of the codec used for the processing. A value of 0 means native Matroska processing (to be defined), a value of 1 means the DVD command set is used. More codec IDs can be added later.
    const ID_CHAPTERTRANSLATEID =             0x29A5; //         [69][A5] -- The binary value used to represent this segment in the chapter codec data. The format depends on the ChapProcessCodecID used.
    const ID_CHAPTERTRANSLATECODEC =          0x29BF; //         [69][BF] -- The chapter codec using this ID (0: Matroska Script, 1: DVD-menu).
    const ID_CHAPTERTRANSLATEEDITIONUID =     0x29FC; //         [69][FC] -- Specify an edition UID on which this correspondance applies. When not specified, it means for all editions found in the segment.
    const ID_CONTENTENCODINGS =               0x2D80; //         [6D][80] -- Settings for several content encoding mechanisms like compression or encryption.
    const ID_MINCACHE =                       0x2DE7; //         [6D][E7] -- The minimum number of frames a player should be able to cache during playback. If set to 0, the reference pseudo-cache system is not used.
    const ID_MAXCACHE =                       0x2DF8; //         [6D][F8] -- The maximum cache size required to store referenced frames in and the current frame. 0 means no cache is needed.
    const ID_CHAPTERSEGMENTUID =              0x2E67; //         [6E][67] -- A segment to play in place of this chapter. Edition ChapterSegmentEditionUID should be used for this segment, otherwise no edition is used.
    const ID_CHAPTERSEGMENTEDITIONUID =       0x2EBC; //         [6E][BC] -- The edition to play from the segment linked in ChapterSegmentUID.
    const ID_TRACKOVERLAY =                   0x2FAB; //         [6F][AB] -- Specify that this track is an overlay track for the Track specified (in the u-integer). That means when this track has a gap (see SilentTracks) the overlay track should be used instead. The order of multiple TrackOverlay matters, the first one is the one that should be used. If not found it should be the second, etc.
    const ID_TAG =                            0x3373; //         [73][73] -- Element containing elements specific to Tracks/Chapters.
    const ID_SEGMENTFILENAME =                0x3384; //         [73][84] -- A filename corresponding to this segment.
    const ID_SEGMENTUID =                     0x33A4; //         [73][A4] -- A randomly generated unique ID to identify the current segment between many others (128 bits).
    const ID_CHAPTERUID =                     0x33C4; //         [73][C4] -- A unique ID to identify the Chapter.
    const ID_TRACKUID =                       0x33C5; //         [73][C5] -- A unique ID to identify the Track. This should be kept the same when making a direct stream copy of the Track to another file.
    const ID_ATTACHMENTLINK =                 0x3446; //         [74][46] -- The UID of an attachment that is used by this codec.
    const ID_CLUSTERBLOCKADDITIONS =          0x35A1; //         [75][A1] -- Contain additional blocks to complete the main one. An EBML parser that has no knowledge of the Block structure could still see and use/skip these data.
    const ID_CHANNELPOSITIONS =               0x347B; //         [7D][7B] -- Table of horizontal angles for each successive channel, see appendix.
    const ID_OUTPUTSAMPLINGFREQUENCY =        0x38B5; //         [78][B5] -- Real output sampling frequency in Hz (used for SBR techniques).
    const ID_TITLE =                          0x3BA9; //         [7B][A9] -- General name of the segment.
    const ID_CHAPTERDISPLAY =                   0x00; //             [80] -- Contains all possible strings to use for the chapter display.
    const ID_TRACKTYPE =                        0x03; //             [83] -- A set of track types coded on 8 bits (1: video, 2: audio, 3: complex, 0x10: logo, 0x11: subtitle, 0x12: buttons, 0x20: control).
    const ID_CHAPSTRING =                       0x05; //             [85] -- Contains the string to use as the chapter atom.
    const ID_CODECID =                          0x06; //             [86] -- An ID corresponding to the codec, see the codec page for more info.
    const ID_FLAGDEFAULT =                      0x08; //             [88] -- Set if that track (audio, video or subs) SHOULD be used if no language found matches the user preference.
    const ID_CHAPTERTRACKNUMBER =               0x09; //             [89] -- UID of the Track to apply this chapter too. In the absense of a control track, choosing this chapter will select the listed Tracks and deselect unlisted tracks. Absense of this element indicates that the Chapter should be applied to any currently used Tracks.
    const ID_CLUSTERSLICES =                    0x0E; //             [8E] -- Contains slices description.
    const ID_CHAPTERTRACK =                     0x0F; //             [8F] -- List of tracks on which the chapter applies. If this element is not present, all tracks apply
    const ID_CHAPTERTIMESTART =                 0x11; //             [91] -- Timecode of the start of Chapter (not scaled).
    const ID_CHAPTERTIMEEND =                   0x12; //             [92] -- Timecode of the end of Chapter (timecode excluded, not scaled).
    const ID_CUEREFTIME =                       0x16; //             [96] -- Timecode of the referenced Block.
    const ID_CUEREFCLUSTER =                    0x17; //             [97] -- Position of the Cluster containing the referenced Block.
    const ID_CHAPTERFLAGHIDDEN =                0x18; //             [98] -- If a chapter is hidden (1), it should not be available to the user interface (but still to Control Tracks).
    const ID_FLAGINTERLACED =                   0x1A; //             [9A] -- Set if the video is interlaced.
    const ID_CLUSTERBLOCKDURATION =             0x1B; //             [9B] -- The duration of the Block (based on TimecodeScale). This element is mandatory when DefaultDuration is set for the track. When not written and with no DefaultDuration, the value is assumed to be the difference between the timecode of this Block and the timecode of the next Block in "display" order (not coding order). This element can be useful at the end of a Track (as there is not other Block available), or when there is a break in a track like for subtitle tracks.
    const ID_FLAGLACING =                       0x1C; //             [9C] -- Set if the track may contain blocks using lacing.
    const ID_CHANNELS =                         0x1F; //             [9F] -- Numbers of channels in the track.
    const ID_CLUSTERBLOCKGROUP =                0x20; //             [A0] -- Basic container of information containing a single Block or BlockVirtual, and information specific to that Block/VirtualBlock.
    const ID_CLUSTERBLOCK =                     0x21; //             [A1] -- Block containing the actual data to be rendered and a timecode relative to the Cluster Timecode.
    const ID_CLUSTERBLOCKVIRTUAL =              0x22; //             [A2] -- A Block with no data. It must be stored in the stream at the place the real Block should be in display order.
    const ID_CLUSTERSIMPLEBLOCK =               0x23; //             [A3] -- Similar to Block but without all the extra information, mostly used to reduced overhead when no extra feature is needed.
    const ID_CLUSTERCODECSTATE =                0x24; //             [A4] -- The new codec state to use. Data interpretation is private to the codec. This information should always be referenced by a seek entry.
    const ID_CLUSTERBLOCKADDITIONAL =           0x25; //             [A5] -- Interpreted by the codec as it wishes (using the BlockAddID).
    const ID_CLUSTERBLOCKMORE =                 0x26; //             [A6] -- Contain the BlockAdditional and some parameters.
    const ID_CLUSTERPOSITION =                  0x27; //             [A7] -- Position of the Cluster in the segment (0 in live broadcast streams). It might help to resynchronise offset on damaged streams.
    const ID_CODECDECODEALL =                   0x2A; //             [AA] -- The codec can decode potentially damaged data.
    const ID_CLUSTERPREVSIZE =                  0x2B; //             [AB] -- Size of the previous Cluster, in octets. Can be useful for backward playing.
    const ID_TRACKENTRY =                       0x2E; //             [AE] -- Describes a track with all elements.
    const ID_CLUSTERENCRYPTEDBLOCK =            0x2F; //             [AF] -- Similar to SimpleBlock but the data inside the Block are Transformed (encrypt and/or signed).
    const ID_PIXELWIDTH =                       0x30; //             [B0] -- Width of the encoded video frames in pixels.
    const ID_CUETIME =                          0x33; //             [B3] -- Absolute timecode according to the segment time base.
    const ID_SAMPLINGFREQUENCY =                0x35; //             [B5] -- Sampling frequency in Hz.
    const ID_CHAPTERATOM =                      0x36; //             [B6] -- Contains the atom information to use as the chapter atom (apply to all tracks).
    const ID_CUETRACKPOSITIONS =                0x37; //             [B7] -- Contain positions for different tracks corresponding to the timecode.
    const ID_FLAGENABLED =                      0x39; //             [B9] -- Set if the track is used.
    const ID_PIXELHEIGHT =                      0x3A; //             [BA] -- Height of the encoded video frames in pixels.
    const ID_CUEPOINT =                         0x3B; //             [BB] -- Contains all information relative to a seek point in the segment.
    const ID_CRC32 =                            0x3F; //             [BF] -- The CRC is computed on all the data of the Master element it's in, regardless of its position. It's recommended to put the CRC value at the beggining of the Master element for easier reading. All level 1 elements should include a CRC-32.
    const ID_CLUSTERBLOCKADDITIONID =           0x4B; //             [CB] -- The ID of the BlockAdditional element (0 is the main Block).
    const ID_CLUSTERLACENUMBER =                0x4C; //             [CC] -- The reverse number of the frame in the lace (0 is the last frame, 1 is the next to last, etc). While there are a few files in the wild with this element, it is no longer in use and has been deprecated. Being able to interpret this element is not required for playback.
    const ID_CLUSTERFRAMENUMBER =               0x4D; //             [CD] -- The number of the frame to generate from this lace with this delay (allow you to generate many frames from the same Block/Frame).
    const ID_CLUSTERDELAY =                     0x4E; //             [CE] -- The (scaled) delay to apply to the element.
    const ID_CLUSTERDURATION =                  0x4F; //             [CF] -- The (scaled) duration to apply to the element.
    const ID_TRACKNUMBER =                      0x57; //             [D7] -- The track number as used in the Block Header (using more than 127 tracks is not encouraged, though the design allows an unlimited number).
    const ID_CUEREFERENCE =                     0x5B; //             [DB] -- The Clusters containing the required referenced Blocks.
    const ID_VIDEO =                            0x60; //             [E0] -- Video settings.
    const ID_AUDIO =                            0x61; //             [E1] -- Audio settings.
    const ID_CLUSTERTIMESLICE =                 0x68; //             [E8] -- Contains extra time information about the data contained in the Block. While there are a few files in the wild with this element, it is no longer in use and has been deprecated. Being able to interpret this element is not required for playback.
    const ID_CUECODECSTATE =                    0x6A; //             [EA] -- The position of the Codec State corresponding to this Cue element. 0 means that the data is taken from the initial Track Entry.
    const ID_CUEREFCODECSTATE =                 0x6B; //             [EB] -- The position of the Codec State corresponding to this referenced element. 0 means that the data is taken from the initial Track Entry.
    const ID_VOID =                             0x6C; //             [EC] -- Used to void damaged data, to avoid unexpected behaviors when using damaged data. The content is discarded. Also used to reserve space in a sub-element for later use.
    const ID_CLUSTERTIMECODE =                  0x67; //             [E7] -- Absolute timecode of the cluster (based on TimecodeScale).
    const ID_CLUSTERBLOCKADDID =                0x6E; //             [EE] -- An ID to identify the BlockAdditional level.
    const ID_CUECLUSTERPOSITION =               0x71; //             [F1] -- The position of the Cluster containing the required Block.
    const ID_CUETRACK =                         0x77; //             [F7] -- The track for which a position is given.
    const ID_CLUSTERREFERENCEPRIORITY =         0x7A; //             [FA] -- This frame is referenced and has the specified cache priority. In cache only a frame of the same or higher priority can replace this frame. A value of 0 means the frame is not referenced.
    const ID_CLUSTERREFERENCEBLOCK =            0x7B; //             [FB] -- Timecode of another frame used as a reference (ie: B or P frame). The timecode is relative to the block it's attached to.
    const ID_CLUSTERREFERENCEVIRTUAL =          0x7D; //             [FD] -- Relative position of the data that should be in position of the virtual block.
    
    // public options
    public static $hide_clusters    = true;  // if true, do not return information about CLUSTER chunks, since there's a lot of them and they're not usually useful [default: TRUE]
    public static $parse_whole_file = false; // true to parse the whole file, not only header [default: FALSE]

    // private parser settings/placeholders
    private $EBMLbuffer        = '';
    private $EBMLbuffer_offset = 0;
    private $EBMLbuffer_length = 0;
    private $current_offset    = 0;
    private $unuseful_elements = [self::ID_CRC32, self::ID_VOID];

    public function Analyze()
    {
        $info = &$this->getid3->info;

        // parse container
        try {
            $this->parseEBML($info);
        } catch (\Exception $e) {
            $info['error'][] = 'EBML parser: '.$e->getMessage();
        }

        // calculate playtime
        if (isset($info['matroska']['info']) && is_array($info['matroska']['info'])) {
            foreach ($info['matroska']['info'] as $key => $infoarray) {
                if (isset($infoarray['Duration'])) {
                    // TimecodeScale is how many nanoseconds each Duration unit is
                    $info['playtime_seconds'] = $infoarray['Duration'] * ((isset($infoarray['TimecodeScale']) ? $infoarray['TimecodeScale'] : 1000000) / 1000000000);
                    break;
                }
            }
        }

        // extract tags
        if (isset($info['matroska']['tags']) && is_array($info['matroska']['tags'])) {
            foreach ($info['matroska']['tags'] as $key => $infoarray) {
                $this->ExtractCommentsSimpleTag($infoarray);
            }
        }

        // process tracks
        if (isset($info['matroska']['tracks']['tracks']) && is_array($info['matroska']['tracks']['tracks'])) {
            foreach ($info['matroska']['tracks']['tracks'] as $key => $trackarray) {

                $track_info = [];
                $track_info['dataformat'] = self::CodecIDtoCommonName($trackarray['CodecID']);
                $track_info['default'] = (isset($trackarray['FlagDefault']) ? $trackarray['FlagDefault'] : true);
                if (isset($trackarray['Name'])) { $track_info['name'] = $trackarray['Name']; }

                switch ($trackarray['TrackType']) {

                    case 1: // Video
                        $track_info['resolution_x'] = $trackarray['PixelWidth'];
                        $track_info['resolution_y'] = $trackarray['PixelHeight'];
                        $track_info['display_unit'] = self::displayUnit(isset($trackarray['DisplayUnit']) ? $trackarray['DisplayUnit'] : 0);
                        $track_info['display_x']    = (isset($trackarray['DisplayWidth']) ? $trackarray['DisplayWidth'] : $trackarray['PixelWidth']);
                        $track_info['display_y']    = (isset($trackarray['DisplayHeight']) ? $trackarray['DisplayHeight'] : $trackarray['PixelHeight']);

                        if (isset($trackarray['PixelCropBottom'])) { $track_info['crop_bottom'] = $trackarray['PixelCropBottom']; }
                        if (isset($trackarray['PixelCropTop']))    { $track_info['crop_top']    = $trackarray['PixelCropTop']; }
                        if (isset($trackarray['PixelCropLeft']))   { $track_info['crop_left']   = $trackarray['PixelCropLeft']; }
                        if (isset($trackarray['PixelCropRight']))  { $track_info['crop_right']  = $trackarray['PixelCropRight']; }
                        if (isset($trackarray['DefaultDuration'])) { $track_info['frame_rate']  = round(1000000000 / $trackarray['DefaultDuration'], 3); }
                        if (isset($trackarray['CodecName']))       { $track_info['codec']       = $trackarray['CodecName']; }

                        switch ($trackarray['CodecID']) {
                            case 'V_MS/VFW/FOURCC':
                                $parsed = Riff::ParseBITMAPINFOHEADER($trackarray['CodecPrivate']);
                                $track_info['codec'] = Riff::fourccLookup($parsed['fourcc']);
                                $info['matroska']['track_codec_parsed'][$trackarray['TrackNumber']] = $parsed;
                                break;

                            /*case 'V_MPEG4/ISO/AVC':
                                $h264['profile']    = Utils::BigEndian2Int(substr($trackarray['CodecPrivate'], 1, 1));
                                $h264['level']      = Utils::BigEndian2Int(substr($trackarray['CodecPrivate'], 3, 1));
                                $rn                 = Utils::BigEndian2Int(substr($trackarray['CodecPrivate'], 4, 1));
                                $h264['NALUlength'] = ($rn & 3) + 1;
                                $rn                 = Utils::BigEndian2Int(substr($trackarray['CodecPrivate'], 5, 1));
                                $nsps               = ($rn & 31);
                                $offset             = 6;
                                for ($i = 0; $i < $nsps; $i ++) {
                                    $length        = Utils::BigEndian2Int(substr($trackarray['CodecPrivate'], $offset, 2));
                                    $h264['SPS'][] = substr($trackarray['CodecPrivate'], $offset + 2, $length);
                                    $offset       += 2 + $length;
                                }
                                $npps               = Utils::BigEndian2Int(substr($trackarray['CodecPrivate'], $offset, 1));
                                $offset            += 1;
                                for ($i = 0; $i < $npps; $i ++) {
                                    $length        = Utils::BigEndian2Int(substr($trackarray['CodecPrivate'], $offset, 2));
                                    $h264['PPS'][] = substr($trackarray['CodecPrivate'], $offset + 2, $length);
                                    $offset       += 2 + $length;
                                }
                                $info['matroska']['track_codec_parsed'][$trackarray['TrackNumber']] = $h264;
                                break;*/
                        }

                        $info['video']['streams'][] = $track_info;
                        break;

                    case 2: // Audio
                        $track_info['sample_rate'] = (isset($trackarray['SamplingFrequency']) ? $trackarray['SamplingFrequency'] : 8000.0);
                        $track_info['channels']    = (isset($trackarray['Channels']) ? $trackarray['Channels'] : 1);
                        $track_info['language']    = (isset($trackarray['Language']) ? $trackarray['Language'] : 'eng');
                        if (isset($trackarray['BitDepth']))  { $track_info['bits_per_sample'] = $trackarray['BitDepth']; }
                        if (isset($trackarray['CodecName'])) { $track_info['codec']           = $trackarray['CodecName']; }

                        switch ($trackarray['CodecID']) {
                            case 'A_PCM/INT/LIT':
                            case 'A_PCM/INT/BIG':
                                $track_info['bitrate'] = $trackarray['SamplingFrequency'] * $trackarray['Channels'] * $trackarray['BitDepth'];
                                break;

                            case 'A_AC3':
                            case 'A_DTS':
                            case 'A_MPEG/L3':
                            case 'A_MPEG/L2':
                            case 'A_FLAC':
                                if (!isset($info['matroska']['track_data_offsets'][$trackarray['TrackNumber']])) {
                                    $this->warning('Unable to parse audio data ['.basename(__FILE__).':'.__LINE__.'] because $info[matroska][track_data_offsets]['.$trackarray['TrackNumber'].'] not set');
                                    break;
                                }

                                // create temp instance
                                $getid3_temp = new GetID3;
                                if ($track_info['dataformat'] != 'flac') {
                                    $getid3_temp->openfile($this->getid3->filename);
                                }
                                $getid3_temp->info['avdataoffset'] = $info['matroska']['track_data_offsets'][$trackarray['TrackNumber']]['offset'];
                                if ($track_info['dataformat'][0] == 'm' || $track_info['dataformat'] == 'flac') {
                                    $getid3_temp->info['avdataend'] = $info['matroska']['track_data_offsets'][$trackarray['TrackNumber']]['offset'] + $info['matroska']['track_data_offsets'][$trackarray['TrackNumber']]['length'];
                                }

                                // analyze
                                $class = ucfirst($track_info['dataformat'] == 'mp2' ? 'mp3' : $track_info['dataformat']);
                                $header_data_key = $track_info['dataformat'][0] == 'm' ? 'mpeg' : $track_info['dataformat'];
                                $getid3_audio = new $class($getid3_temp, __CLASS__);
                                if ($track_info['dataformat'] == 'flac') {
                                    $getid3_audio->AnalyzeString($trackarray['CodecPrivate']);
                                }
                                else {
                                    $getid3_audio->Analyze();
                                }
                                if (!empty($getid3_temp->info[$header_data_key])) {
                                    $info['matroska']['track_codec_parsed'][$trackarray['TrackNumber']] = $getid3_temp->info[$header_data_key];
                                    if (isset($getid3_temp->info['audio']) && is_array($getid3_temp->info['audio'])) {
                                        foreach ($getid3_temp->info['audio'] as $key => $value) {
                                            $track_info[$key] = $value;
                                        }
                                    }
                                }
                                else {
                                    $this->warning('Unable to parse audio data ['.basename(__FILE__).':'.__LINE__.'] because '.$class.'::Analyze() failed at offset '.$getid3_temp->info['avdataoffset']);
                                }

                                // copy errors and warnings
                                if (!empty($getid3_temp->info['error'])) {
                                    foreach ($getid3_temp->info['error'] as $newerror) {
                                        $this->warning($class.'() says: ['.$newerror.']');
                                    }
                                }
                                if (!empty($getid3_temp->info['warning'])) {
                                    foreach ($getid3_temp->info['warning'] as $newerror) {
                                        $this->warning($class.'() says: ['.$newerror.']');
                                    }
                                }
                                unset($getid3_temp, $getid3_audio);
                                break;

                            case 'A_AAC':
                            case 'A_AAC/MPEG2/LC':
                            case 'A_AAC/MPEG2/LC/SBR':
                            case 'A_AAC/MPEG4/LC':
                            case 'A_AAC/MPEG4/LC/SBR':
                                $this->warning($trackarray['CodecID'].' audio data contains no header, audio/video bitrates can\'t be calculated');
                                break;

                            case 'A_VORBIS':
                                if (!isset($trackarray['CodecPrivate'])) {
                                    $this->warning('Unable to parse audio data ['.basename(__FILE__).':'.__LINE__.'] because CodecPrivate data not set');
                                    break;
                                }
                                $vorbis_offset = strpos($trackarray['CodecPrivate'], 'vorbis', 1);
                                if ($vorbis_offset === false) {
                                    $this->warning('Unable to parse audio data ['.basename(__FILE__).':'.__LINE__.'] because CodecPrivate data does not contain "vorbis" keyword');
                                    break;
                                }
                                $vorbis_offset -= 1;

                                // create temp instance
                                $getid3_temp = new GetID3;

                                // analyze
                                $getid3_ogg = new Ogg($getid3_temp);
                                $oggpageinfo['page_seqno'] = 0;
                                $getid3_ogg->ParseVorbisPageHeader($trackarray['CodecPrivate'], $vorbis_offset, $oggpageinfo);
                                if (!empty($getid3_temp->info['ogg'])) {
                                    $info['matroska']['track_codec_parsed'][$trackarray['TrackNumber']] = $getid3_temp->info['ogg'];
                                    if (isset($getid3_temp->info['audio']) && is_array($getid3_temp->info['audio'])) {
                                        foreach ($getid3_temp->info['audio'] as $key => $value) {
                                            $track_info[$key] = $value;
                                        }
                                    }
                                }

                                // copy errors and warnings
                                if (!empty($getid3_temp->info['error'])) {
                                    foreach ($getid3_temp->info['error'] as $newerror) {
                                        $this->warning('getid3_ogg() says: ['.$newerror.']');
                                    }
                                }
                                if (!empty($getid3_temp->info['warning'])) {
                                    foreach ($getid3_temp->info['warning'] as $newerror) {
                                        $this->warning('getid3_ogg() says: ['.$newerror.']');
                                    }
                                }

                                if (!empty($getid3_temp->info['ogg']['bitrate_nominal'])) {
                                    $track_info['bitrate'] = $getid3_temp->info['ogg']['bitrate_nominal'];
                                }
                                unset($getid3_temp, $getid3_ogg, $oggpageinfo, $vorbis_offset);
                                break;

                            case 'A_MS/ACM':
                                $parsed = Riff::parseWAVEFORMATex($trackarray['CodecPrivate']);
                                foreach ($parsed as $key => $value) {
                                    if ($key != 'raw') {
                                        $track_info[$key] = $value;
                                    }
                                }
                                $info['matroska']['track_codec_parsed'][$trackarray['TrackNumber']] = $parsed;
                                break;

                            default:
                                $this->warning('Unhandled audio type "'.(isset($trackarray['CodecID']) ? $trackarray['CodecID'] : '').'"');
                        }

                        $info['audio']['streams'][] = $track_info;
                        break;
                }
            }

            if (!empty($info['video']['streams'])) {
                $info['video'] = self::getDefaultStreamInfo($info['video']['streams']);
            }
            if (!empty($info['audio']['streams'])) {
                $info['audio'] = self::getDefaultStreamInfo($info['audio']['streams']);
            }
        }

        // process attachments
        if (isset($info['matroska']['attachments']) && $this->getid3->option_save_attachments !== GetID3::ATTACHMENTS_NONE) {
            foreach ($info['matroska']['attachments'] as $i => $entry) {
                if (strpos($entry['FileMimeType'], 'image/') === 0 && !empty($entry['FileData'])) {
                    $info['matroska']['comments']['picture'][] = ['data' => $entry['FileData'], 'image_mime' => $entry['FileMimeType'], 'filename' => $entry['FileName']];
                }
            }
        }

        // determine mime type
        if (!empty($info['video']['streams'])) {
            $info['mime_type'] = ($info['matroska']['doctype'] == 'webm' ? 'video/webm' : 'video/x-matroska');
        } elseif (!empty($info['audio']['streams'])) {
            $info['mime_type'] = ($info['matroska']['doctype'] == 'webm' ? 'audio/webm' : 'audio/x-matroska');
        } elseif (isset($info['mime_type'])) {
            unset($info['mime_type']);
        }

        return true;
    }

    private function parseEBML(&$info)
    {
        // http://www.matroska.org/technical/specs/index.html#EBMLBasics
        $this->current_offset = $info['avdataoffset'];

        while ($this->getEBMLelement($top_element, $info['avdataend'])) {
            switch ($top_element['id']) {

                case self::ID_EBML:
                    $info['matroska']['header']['offset'] = $top_element['offset'];
                    $info['matroska']['header']['length'] = $top_element['length'];

                    while ($this->getEBMLelement($element_data, $top_element['end'], true)) {
                        switch ($element_data['id']) {

                            case self::ID_EBMLVERSION:
                            case self::ID_EBMLREADVERSION:
                            case self::ID_EBMLMAXIDLENGTH:
                            case self::ID_EBMLMAXSIZELENGTH:
                            case self::ID_DOCTYPEVERSION:
                            case self::ID_DOCTYPEREADVERSION:
                                $element_data['data'] = Utils::BigEndian2Int($element_data['data']);
                                break;

                            case self::ID_DOCTYPE:
                                $element_data['data'] = Utils::trimNullByte($element_data['data']);
                                $info['matroska']['doctype'] = $element_data['data'];
                                $info['fileformat'] = $element_data['data'];
                                break;

                            default:
                                $this->unhandledElement('header', __LINE__, $element_data);
                        }

                        unset($element_data['offset'], $element_data['end']);
                        $info['matroska']['header']['elements'][] = $element_data;
                    }
                    break;

                case self::ID_SEGMENT:
                    $info['matroska']['segment'][0]['offset'] = $top_element['offset'];
                    $info['matroska']['segment'][0]['length'] = $top_element['length'];

                    while ($this->getEBMLelement($element_data, $top_element['end'])) {
                        if ($element_data['id'] != self::ID_CLUSTER || !self::$hide_clusters) { // collect clusters only if required
                            $info['matroska']['segments'][] = $element_data;
                        }
                        switch ($element_data['id']) {

                            case self::ID_SEEKHEAD: // Contains the position of other level 1 elements.

                                while ($this->getEBMLelement($seek_entry, $element_data['end'])) {
                                    switch ($seek_entry['id']) {

                                        case self::ID_SEEK: // Contains a single seek entry to an EBML element
                                            while ($this->getEBMLelement($sub_seek_entry, $seek_entry['end'], true)) {

                                                switch ($sub_seek_entry['id']) {

                                                    case self::ID_SEEKID:
                                                        $seek_entry['target_id']   = self::EBML2Int($sub_seek_entry['data']);
                                                        $seek_entry['target_name'] = self::EBMLidName($seek_entry['target_id']);
                                                        break;

                                                    case self::ID_SEEKPOSITION:
                                                        $seek_entry['target_offset'] = $element_data['offset'] + Utils::BigEndian2Int($sub_seek_entry['data']);
                                                        break;

                                                    default:
                                                        $this->unhandledElement('seekhead.seek', __LINE__, $sub_seek_entry);                                                }
                                            }

                                            if ($seek_entry['target_id'] != self::ID_CLUSTER || !self::$hide_clusters) { // collect clusters only if required
                                                $info['matroska']['seek'][] = $seek_entry;
                                            }
                                            break;

                                        default:
                                            $this->unhandledElement('seekhead', __LINE__, $seek_entry);
                                    }
                                }
                                break;

                            case self::ID_TRACKS: // A top-level block of information with many tracks described.
                                $info['matroska']['tracks'] = $element_data;

                                while ($this->getEBMLelement($track_entry, $element_data['end'])) {
                                    switch ($track_entry['id']) {

                                        case self::ID_TRACKENTRY: //subelements: Describes a track with all elements.

                                            while ($this->getEBMLelement($subelement, $track_entry['end'], [self::ID_VIDEO, self::ID_AUDIO, self::ID_CONTENTENCODINGS, self::ID_CODECPRIVATE])) {
                                                switch ($subelement['id']) {

                                                    case self::ID_TRACKNUMBER:
                                                    case self::ID_TRACKUID:
                                                    case self::ID_TRACKTYPE:
                                                    case self::ID_MINCACHE:
                                                    case self::ID_MAXCACHE:
                                                    case self::ID_MAXBLOCKADDITIONID:
                                                    case self::ID_DEFAULTDURATION: // nanoseconds per frame
                                                        $track_entry[$subelement['id_name']] = Utils::BigEndian2Int($subelement['data']);
                                                        break;

                                                    case self::ID_TRACKTIMECODESCALE:
                                                        $track_entry[$subelement['id_name']] = Utils::BigEndian2Float($subelement['data']);
                                                        break;

                                                    case self::ID_CODECID:
                                                    case self::ID_LANGUAGE:
                                                    case self::ID_NAME:
                                                    case self::ID_CODECNAME:
                                                        $track_entry[$subelement['id_name']] = Utils::trimNullByte($subelement['data']);
                                                        break;

                                                    case self::ID_CODECPRIVATE:
                                                        $track_entry[$subelement['id_name']] = $this->readEBMLelementData($subelement['length'], true);
                                                        break;

                                                    case self::ID_FLAGENABLED:
                                                    case self::ID_FLAGDEFAULT:
                                                    case self::ID_FLAGFORCED:
                                                    case self::ID_FLAGLACING:
                                                    case self::ID_CODECDECODEALL:
                                                        $track_entry[$subelement['id_name']] = (bool) Utils::BigEndian2Int($subelement['data']);
                                                        break;

                                                    case self::ID_VIDEO:

                                                        while ($this->getEBMLelement($sub_subelement, $subelement['end'], true)) {
                                                            switch ($sub_subelement['id']) {

                                                                case self::ID_PIXELWIDTH:
                                                                case self::ID_PIXELHEIGHT:
                                                                case self::ID_PIXELCROPBOTTOM:
                                                                case self::ID_PIXELCROPTOP:
                                                                case self::ID_PIXELCROPLEFT:
                                                                case self::ID_PIXELCROPRIGHT:
                                                                case self::ID_DISPLAYWIDTH:
                                                                case self::ID_DISPLAYHEIGHT:
                                                                case self::ID_DISPLAYUNIT:
                                                                case self::ID_ASPECTRATIOTYPE:
                                                                case self::ID_STEREOMODE:
                                                                case self::ID_OLDSTEREOMODE:
                                                                    $track_entry[$sub_subelement['id_name']] = Utils::BigEndian2Int($sub_subelement['data']);
                                                                    break;

                                                                case self::ID_FLAGINTERLACED:
                                                                    $track_entry[$sub_subelement['id_name']] = (bool)Utils::BigEndian2Int($sub_subelement['data']);
                                                                    break;

                                                                case self::ID_GAMMAVALUE:
                                                                    $track_entry[$sub_subelement['id_name']] = Utils::BigEndian2Float($sub_subelement['data']);
                                                                    break;

                                                                case self::ID_COLOURSPACE:
                                                                    $track_entry[$sub_subelement['id_name']] = Utils::trimNullByte($sub_subelement['data']);
                                                                    break;

                                                                default:
                                                                    $this->unhandledElement('track.video', __LINE__, $sub_subelement);
                                                            }
                                                        }
                                                        break;

                                                    case self::ID_AUDIO:

                                                        while ($this->getEBMLelement($sub_subelement, $subelement['end'], true)) {
                                                            switch ($sub_subelement['id']) {

                                                                case self::ID_CHANNELS:
                                                                case self::ID_BITDEPTH:
                                                                    $track_entry[$sub_subelement['id_name']] = Utils::BigEndian2Int($sub_subelement['data']);
                                                                    break;

                                                                case self::ID_SAMPLINGFREQUENCY:
                                                                case self::ID_OUTPUTSAMPLINGFREQUENCY:
                                                                    $track_entry[$sub_subelement['id_name']] = Utils::BigEndian2Float($sub_subelement['data']);
                                                                    break;

                                                                case self::ID_CHANNELPOSITIONS:
                                                                    $track_entry[$sub_subelement['id_name']] = Utils::trimNullByte($sub_subelement['data']);
                                                                    break;

                                                                default:
                                                                    $this->unhandledElement('track.audio', __LINE__, $sub_subelement);
                                                            }
                                                        }
                                                        break;

                                                    case self::ID_CONTENTENCODINGS:

                                                        while ($this->getEBMLelement($sub_subelement, $subelement['end'])) {
                                                            switch ($sub_subelement['id']) {

                                                                case self::ID_CONTENTENCODING:

                                                                    while ($this->getEBMLelement($sub_sub_subelement, $sub_subelement['end'], [self::ID_CONTENTCOMPRESSION, self::ID_CONTENTENCRYPTION])) {
                                                                        switch ($sub_sub_subelement['id']) {

                                                                            case self::ID_CONTENTENCODINGORDER:
                                                                            case self::ID_CONTENTENCODINGSCOPE:
                                                                            case self::ID_CONTENTENCODINGTYPE:
                                                                                $track_entry[$sub_subelement['id_name']][$sub_sub_subelement['id_name']] = Utils::BigEndian2Int($sub_sub_subelement['data']);
                                                                                break;

                                                                            case self::ID_CONTENTCOMPRESSION:

                                                                                while ($this->getEBMLelement($sub_sub_sub_subelement, $sub_sub_subelement['end'], true)) {
                                                                                    switch ($sub_sub_sub_subelement['id']) {

                                                                                        case self::ID_CONTENTCOMPALGO:
                                                                                            $track_entry[$sub_subelement['id_name']][$sub_sub_subelement['id_name']][$sub_sub_sub_subelement['id_name']] = Utils::BigEndian2Int($sub_sub_sub_subelement['data']);
                                                                                            break;

                                                                                        case self::ID_CONTENTCOMPSETTINGS:
                                                                                            $track_entry[$sub_subelement['id_name']][$sub_sub_subelement['id_name']][$sub_sub_sub_subelement['id_name']] = $sub_sub_sub_subelement['data'];
                                                                                            break;

                                                                                        default:
                                                                                            $this->unhandledElement('track.contentencodings.contentencoding.contentcompression', __LINE__, $sub_sub_sub_subelement);
                                                                                    }
                                                                                }
                                                                                break;

                                                                            case self::ID_CONTENTENCRYPTION:

                                                                                while ($this->getEBMLelement($sub_sub_sub_subelement, $sub_sub_subelement['end'], true)) {
                                                                                    switch ($sub_sub_sub_subelement['id']) {

                                                                                        case self::ID_CONTENTENCALGO:
                                                                                        case self::ID_CONTENTSIGALGO:
                                                                                        case self::ID_CONTENTSIGHASHALGO:
                                                                                            $track_entry[$sub_subelement['id_name']][$sub_sub_subelement['id_name']][$sub_sub_sub_subelement['id_name']] = Utils::BigEndian2Int($sub_sub_sub_subelement['data']);
                                                                                            break;

                                                                                        case self::ID_CONTENTENCKEYID:
                                                                                        case self::ID_CONTENTSIGNATURE:
                                                                                        case self::ID_CONTENTSIGKEYID:
                                                                                            $track_entry[$sub_subelement['id_name']][$sub_sub_subelement['id_name']][$sub_sub_sub_subelement['id_name']] = $sub_sub_sub_subelement['data'];
                                                                                            break;

                                                                                        default:
                                                                                            $this->unhandledElement('track.contentencodings.contentencoding.contentcompression', __LINE__, $sub_sub_sub_subelement);
                                                                                    }
                                                                                }
                                                                                break;

                                                                            default:
                                                                                $this->unhandledElement('track.contentencodings.contentencoding', __LINE__, $sub_sub_subelement);
                                                                        }
                                                                    }
                                                                    break;

                                                                default:
                                                                    $this->unhandledElement('track.contentencodings', __LINE__, $sub_subelement);
                                                            }
                                                        }
                                                        break;

                                                    default:
                                                        $this->unhandledElement('track', __LINE__, $subelement);
                                                }
                                            }

                                            $info['matroska']['tracks']['tracks'][] = $track_entry;
                                            break;

                                        default:
                                            $this->unhandledElement('tracks', __LINE__, $track_entry);
                                    }
                                }
                                break;

                            case self::ID_INFO: // Contains miscellaneous general information and statistics on the file.
                                $info_entry = [];

                                while ($this->getEBMLelement($subelement, $element_data['end'], true)) {
                                    switch ($subelement['id']) {

                                        case self::ID_TIMECODESCALE:
                                            $info_entry[$subelement['id_name']] = Utils::BigEndian2Int($subelement['data']);
                                            break;

                                        case self::ID_DURATION:
                                            $info_entry[$subelement['id_name']] = Utils::BigEndian2Float($subelement['data']);
                                            break;

                                        case self::ID_DATEUTC:
                                            $info_entry[$subelement['id_name']]         = Utils::BigEndian2Int($subelement['data']);
                                            $info_entry[$subelement['id_name'].'_unix'] = self::EBMLdate2unix($info_entry[$subelement['id_name']]);
                                            break;

                                        case self::ID_SEGMENTUID:
                                        case self::ID_PREVUID:
                                        case self::ID_NEXTUID:
                                            $info_entry[$subelement['id_name']] = Utils::trimNullByte($subelement['data']);
                                            break;

                                        case self::ID_SEGMENTFAMILY:
                                            $info_entry[$subelement['id_name']][] = Utils::trimNullByte($subelement['data']);
                                            break;

                                        case self::ID_SEGMENTFILENAME:
                                        case self::ID_PREVFILENAME:
                                        case self::ID_NEXTFILENAME:
                                        case self::ID_TITLE:
                                        case self::ID_MUXINGAPP:
                                        case self::ID_WRITINGAPP:
                                            $info_entry[$subelement['id_name']] = Utils::trimNullByte($subelement['data']);
                                            $info['matroska']['comments'][strtolower($subelement['id_name'])][] = $info_entry[$subelement['id_name']];
                                            break;

                                        case self::ID_CHAPTERTRANSLATE:
                                            $chaptertranslate_entry = [];

                                            while ($this->getEBMLelement($sub_subelement, $subelement['end'], true)) {
                                                switch ($sub_subelement['id']) {

                                                    case self::ID_CHAPTERTRANSLATEEDITIONUID:
                                                        $chaptertranslate_entry[$sub_subelement['id_name']][] = Utils::BigEndian2Int($sub_subelement['data']);
                                                        break;

                                                    case self::ID_CHAPTERTRANSLATECODEC:
                                                        $chaptertranslate_entry[$sub_subelement['id_name']] = Utils::BigEndian2Int($sub_subelement['data']);
                                                        break;

                                                    case self::ID_CHAPTERTRANSLATEID:
                                                        $chaptertranslate_entry[$sub_subelement['id_name']] = Utils::trimNullByte($sub_subelement['data']);
                                                        break;

                                                    default:
                                                        $this->unhandledElement('info.chaptertranslate', __LINE__, $sub_subelement);
                                                }
                                            }
                                            $info_entry[$subelement['id_name']] = $chaptertranslate_entry;
                                            break;

                                        default:
                                            $this->unhandledElement('info', __LINE__, $subelement);
                                    }
                                }
                                $info['matroska']['info'][] = $info_entry;
                                break;

                            case self::ID_CUES: // A top-level element to speed seeking access. All entries are local to the segment. Should be mandatory for non "live" streams.
                                if (self::$hide_clusters) { // do not parse cues if hide clusters is "ON" till they point to clusters anyway
                                    $this->current_offset = $element_data['end'];
                                    break;
                                }
                                $cues_entry = [];

                                while ($this->getEBMLelement($subelement, $element_data['end'])) {
                                    switch ($subelement['id']) {

                                        case self::ID_CUEPOINT:
                                            $cuepoint_entry = [];

                                            while ($this->getEBMLelement($sub_subelement, $subelement['end'], [self::ID_CUETRACKPOSITIONS])) {
                                                switch ($sub_subelement['id']) {

                                                    case self::ID_CUETRACKPOSITIONS:
                                                        $cuetrackpositions_entry = [];

                                                        while ($this->getEBMLelement($sub_sub_subelement, $sub_subelement['end'], true)) {
                                                            switch ($sub_sub_subelement['id']) {

                                                                case self::ID_CUETRACK:
                                                                case self::ID_CUECLUSTERPOSITION:
                                                                case self::ID_CUEBLOCKNUMBER:
                                                                case self::ID_CUECODECSTATE:
                                                                    $cuetrackpositions_entry[$sub_sub_subelement['id_name']] = Utils::BigEndian2Int($sub_sub_subelement['data']);
                                                                    break;

                                                                default:
                                                                    $this->unhandledElement('cues.cuepoint.cuetrackpositions', __LINE__, $sub_sub_subelement);
                                                            }
                                                        }
                                                        $cuepoint_entry[$sub_subelement['id_name']][] = $cuetrackpositions_entry;
                                                        break;

                                                    case self::ID_CUETIME:
                                                        $cuepoint_entry[$sub_subelement['id_name']] = Utils::BigEndian2Int($sub_subelement['data']);
                                                        break;

                                                    default:
                                                        $this->unhandledElement('cues.cuepoint', __LINE__, $sub_subelement);
                                                }
                                            }
                                            $cues_entry[] = $cuepoint_entry;
                                            break;

                                        default:
                                            $this->unhandledElement('cues', __LINE__, $subelement);
                                    }
                                }
                                $info['matroska']['cues'] = $cues_entry;
                                break;

                            case self::ID_TAGS: // Element containing elements specific to Tracks/Chapters.
                                $tags_entry = [];

                                while ($this->getEBMLelement($subelement, $element_data['end'], false)) {
                                    switch ($subelement['id']) {

                                        case self::ID_TAG:
                                            $tag_entry = [];

                                            while ($this->getEBMLelement($sub_subelement, $subelement['end'], false)) {
                                                switch ($sub_subelement['id']) {

                                                    case self::ID_TARGETS:
                                                        $targets_entry = [];

                                                        while ($this->getEBMLelement($sub_sub_subelement, $sub_subelement['end'], true)) {
                                                            switch ($sub_sub_subelement['id']) {

                                                                case self::ID_TARGETTYPEVALUE:
                                                                    $targets_entry[$sub_sub_subelement['id_name']] = Utils::BigEndian2Int($sub_sub_subelement['data']);
                                                                    $targets_entry[strtolower($sub_sub_subelement['id_name']).'_long'] = self::TargetTypeValue($targets_entry[$sub_sub_subelement['id_name']]);
                                                                    break;

                                                                case self::ID_TARGETTYPE:
                                                                    $targets_entry[$sub_sub_subelement['id_name']] = $sub_sub_subelement['data'];
                                                                    break;

                                                                case self::ID_TAGTRACKUID:
                                                                case self::ID_TAGEDITIONUID:
                                                                case self::ID_TAGCHAPTERUID:
                                                                case self::ID_TAGATTACHMENTUID:
                                                                    $targets_entry[$sub_sub_subelement['id_name']][] = Utils::BigEndian2Int($sub_sub_subelement['data']);
                                                                    break;

                                                                default:
                                                                    $this->unhandledElement('tags.tag.targets', __LINE__, $sub_sub_subelement);
                                                            }
                                                        }
                                                        $tag_entry[$sub_subelement['id_name']] = $targets_entry;
                                                        break;

                                                    case self::ID_SIMPLETAG:
                                                        $tag_entry[$sub_subelement['id_name']][] = $this->HandleEMBLSimpleTag($sub_subelement['end']);
                                                        break;

                                                    default:
                                                        $this->unhandledElement('tags.tag', __LINE__, $sub_subelement);
                                                }
                                            }
                                            $tags_entry[] = $tag_entry;
                                            break;

                                        default:
                                            $this->unhandledElement('tags', __LINE__, $subelement);
                                    }
                                }
                                $info['matroska']['tags'] = $tags_entry;
                                break;

                            case self::ID_ATTACHMENTS: // Contain attached files.

                                while ($this->getEBMLelement($subelement, $element_data['end'])) {
                                    switch ($subelement['id']) {

                                        case self::ID_ATTACHEDFILE:
                                            $attachedfile_entry = [];

                                            while ($this->getEBMLelement($sub_subelement, $subelement['end'], [self::ID_FILEDATA])) {
                                                switch ($sub_subelement['id']) {

                                                    case self::ID_FILEDESCRIPTION:
                                                    case self::ID_FILENAME:
                                                    case self::ID_FILEMIMETYPE:
                                                        $attachedfile_entry[$sub_subelement['id_name']] = $sub_subelement['data'];
                                                        break;

                                                    case self::ID_FILEDATA:
                                                        $attachedfile_entry['data_offset'] = $this->current_offset;
                                                        $attachedfile_entry['data_length'] = $sub_subelement['length'];

                                                        $attachedfile_entry[$sub_subelement['id_name']] = $this->saveAttachment(
                                                            $attachedfile_entry['FileName'],
                                                            $attachedfile_entry['data_offset'],
                                                            $attachedfile_entry['data_length']);

                                                        $this->current_offset = $sub_subelement['end'];
                                                        break;

                                                    case self::ID_FILEUID:
                                                        $attachedfile_entry[$sub_subelement['id_name']] = Utils::BigEndian2Int($sub_subelement['data']);
                                                        break;

                                                    default:
                                                        $this->unhandledElement('attachments.attachedfile', __LINE__, $sub_subelement);
                                                }
                                            }
                                            $info['matroska']['attachments'][] = $attachedfile_entry;
                                            break;

                                        default:
                                            $this->unhandledElement('attachments', __LINE__, $subelement);
                                    }
                                }
                                break;

                            case self::ID_CHAPTERS:

                                while ($this->getEBMLelement($subelement, $element_data['end'])) {
                                    switch ($subelement['id']) {

                                        case self::ID_EDITIONENTRY:
                                            $editionentry_entry = [];

                                            while ($this->getEBMLelement($sub_subelement, $subelement['end'], [self::ID_CHAPTERATOM])) {
                                                switch ($sub_subelement['id']) {

                                                    case self::ID_EDITIONUID:
                                                        $editionentry_entry[$sub_subelement['id_name']] = Utils::BigEndian2Int($sub_subelement['data']);
                                                        break;

                                                    case self::ID_EDITIONFLAGHIDDEN:
                                                    case self::ID_EDITIONFLAGDEFAULT:
                                                    case self::ID_EDITIONFLAGORDERED:
                                                        $editionentry_entry[$sub_subelement['id_name']] = (bool)Utils::BigEndian2Int($sub_subelement['data']);
                                                        break;

                                                    case self::ID_CHAPTERATOM:
                                                        $chapteratom_entry = [];

                                                        while ($this->getEBMLelement($sub_sub_subelement, $sub_subelement['end'], [self::ID_CHAPTERTRACK, self::ID_CHAPTERDISPLAY])) {
                                                            switch ($sub_sub_subelement['id']) {

                                                                case self::ID_CHAPTERSEGMENTUID:
                                                                case self::ID_CHAPTERSEGMENTEDITIONUID:
                                                                    $chapteratom_entry[$sub_sub_subelement['id_name']] = $sub_sub_subelement['data'];
                                                                    break;

                                                                case self::ID_CHAPTERFLAGENABLED:
                                                                case self::ID_CHAPTERFLAGHIDDEN:
                                                                    $chapteratom_entry[$sub_sub_subelement['id_name']] = (bool)Utils::BigEndian2Int($sub_sub_subelement['data']);
                                                                    break;

                                                                case self::ID_CHAPTERUID:
                                                                case self::ID_CHAPTERTIMESTART:
                                                                case self::ID_CHAPTERTIMEEND:
                                                                    $chapteratom_entry[$sub_sub_subelement['id_name']] = Utils::BigEndian2Int($sub_sub_subelement['data']);
                                                                    break;

                                                                case self::ID_CHAPTERTRACK:
                                                                    $chaptertrack_entry = [];

                                                                    while ($this->getEBMLelement($sub_sub_sub_subelement, $sub_sub_subelement['end'], true)) {
                                                                        switch ($sub_sub_sub_subelement['id']) {

                                                                            case self::ID_CHAPTERTRACKNUMBER:
                                                                                $chaptertrack_entry[$sub_sub_sub_subelement['id_name']] = Utils::BigEndian2Int($sub_sub_sub_subelement['data']);
                                                                                break;

                                                                            default:
                                                                                $this->unhandledElement('chapters.editionentry.chapteratom.chaptertrack', __LINE__, $sub_sub_sub_subelement);
                                                                        }
                                                                    }
                                                                    $chapteratom_entry[$sub_sub_subelement['id_name']][] = $chaptertrack_entry;
                                                                    break;

                                                                case self::ID_CHAPTERDISPLAY:
                                                                    $chapterdisplay_entry = [];

                                                                    while ($this->getEBMLelement($sub_sub_sub_subelement, $sub_sub_subelement['end'], true)) {
                                                                        switch ($sub_sub_sub_subelement['id']) {

                                                                            case self::ID_CHAPSTRING:
                                                                            case self::ID_CHAPLANGUAGE:
                                                                            case self::ID_CHAPCOUNTRY:
                                                                                $chapterdisplay_entry[$sub_sub_sub_subelement['id_name']] = $sub_sub_sub_subelement['data'];
                                                                                break;

                                                                            default:
                                                                                $this->unhandledElement('chapters.editionentry.chapteratom.chapterdisplay', __LINE__, $sub_sub_sub_subelement);
                                                                        }
                                                                    }
                                                                    $chapteratom_entry[$sub_sub_subelement['id_name']][] = $chapterdisplay_entry;
                                                                    break;

                                                                default:
                                                                    $this->unhandledElement('chapters.editionentry.chapteratom', __LINE__, $sub_sub_subelement);
                                                            }
                                                        }
                                                        $editionentry_entry[$sub_subelement['id_name']][] = $chapteratom_entry;
                                                        break;

                                                    default:
                                                        $this->unhandledElement('chapters.editionentry', __LINE__, $sub_subelement);
                                                }
                                            }
                                            $info['matroska']['chapters'][] = $editionentry_entry;
                                            break;

                                        default:
                                            $this->unhandledElement('chapters', __LINE__, $subelement);
                                    }
                                }
                                break;

                            case self::ID_CLUSTER: // The lower level element containing the (monolithic) Block structure.
                                $cluster_entry = [];

                                while ($this->getEBMLelement($subelement, $element_data['end'], [self::ID_CLUSTERSILENTTRACKS, self::ID_CLUSTERBLOCKGROUP, self::ID_CLUSTERSIMPLEBLOCK])) {
                                    switch ($subelement['id']) {

                                        case self::ID_CLUSTERTIMECODE:
                                        case self::ID_CLUSTERPOSITION:
                                        case self::ID_CLUSTERPREVSIZE:
                                            $cluster_entry[$subelement['id_name']] = Utils::BigEndian2Int($subelement['data']);
                                            break;

                                        case self::ID_CLUSTERSILENTTRACKS:
                                            $cluster_silent_tracks = [];

                                            while ($this->getEBMLelement($sub_subelement, $subelement['end'], true)) {
                                                switch ($sub_subelement['id']) {

                                                    case self::ID_CLUSTERSILENTTRACKNUMBER:
                                                        $cluster_silent_tracks[] = Utils::BigEndian2Int($sub_subelement['data']);
                                                        break;

                                                    default:
                                                        $this->unhandledElement('cluster.silenttracks', __LINE__, $sub_subelement);
                                                }
                                            }
                                            $cluster_entry[$subelement['id_name']][] = $cluster_silent_tracks;
                                            break;

                                        case self::ID_CLUSTERBLOCKGROUP:
                                            $cluster_block_group = ['offset' => $this->current_offset];

                                            while ($this->getEBMLelement($sub_subelement, $subelement['end'], [self::ID_CLUSTERBLOCK])) {
                                                switch ($sub_subelement['id']) {

                                                    case self::ID_CLUSTERBLOCK:
                                                        $cluster_block_group[$sub_subelement['id_name']] = $this->HandleEMBLClusterBlock($sub_subelement, self::ID_CLUSTERBLOCK, $info);
                                                        break;

                                                    case self::ID_CLUSTERREFERENCEPRIORITY: // unsigned-int
                                                    case self::ID_CLUSTERBLOCKDURATION:     // unsigned-int
                                                        $cluster_block_group[$sub_subelement['id_name']] = Utils::BigEndian2Int($sub_subelement['data']);
                                                        break;

                                                    case self::ID_CLUSTERREFERENCEBLOCK:    // signed-int
                                                        $cluster_block_group[$sub_subelement['id_name']][] = Utils::BigEndian2Int($sub_subelement['data'], false, true);
                                                        break;

                                                    case self::ID_CLUSTERCODECSTATE:
                                                        $cluster_block_group[$sub_subelement['id_name']] = Utils::trimNullByte($sub_subelement['data']);
                                                        break;

                                                    default:
                                                        $this->unhandledElement('clusters.blockgroup', __LINE__, $sub_subelement);
                                                }
                                            }
                                            $cluster_entry[$subelement['id_name']][] = $cluster_block_group;
                                            break;

                                        case self::ID_CLUSTERSIMPLEBLOCK:
                                            $cluster_entry[$subelement['id_name']][] = $this->HandleEMBLClusterBlock($subelement, self::ID_CLUSTERSIMPLEBLOCK, $info);
                                            break;

                                        default:
                                            $this->unhandledElement('cluster', __LINE__, $subelement);
                                    }
                                    $this->current_offset = $subelement['end'];
                                }
                                if (!self::$hide_clusters) {
                                    $info['matroska']['cluster'][] = $cluster_entry;
                                }

                                // check to see if all the data we need exists already, if so, break out of the loop
                                if (!self::$parse_whole_file) {
                                    if (isset($info['matroska']['info']) && is_array($info['matroska']['info'])) {
                                        if (isset($info['matroska']['tracks']['tracks']) && is_array($info['matroska']['tracks']['tracks'])) {
                                            if (count($info['matroska']['track_data_offsets']) == count($info['matroska']['tracks']['tracks'])) {
                                                return;
                                            }
                                        }
                                    }
                                }
                                break;

                            default:
                                $this->unhandledElement('segment', __LINE__, $element_data);
                        }
                    }
                    break;

                default:
                    $this->unhandledElement('root', __LINE__, $top_element);
            }
        }
    }

    private function EnsureBufferHasEnoughData($min_data = 1024)
    {
        if (($this->current_offset - $this->EBMLbuffer_offset) >= ($this->EBMLbuffer_length - $min_data)) {
            $read_bytes = max($min_data, $this->getid3->fread_buffer_size());

            try {
                $this->fseek($this->current_offset);
                $this->EBMLbuffer_offset = $this->current_offset;
                $this->EBMLbuffer        = $this->fread($read_bytes);
                $this->EBMLbuffer_length = strlen($this->EBMLbuffer);
            } catch (Exception $e) {
                $this->warning('EBML parser: '.$e->getMessage());
                return false;
            }

            if ($this->EBMLbuffer_length == 0 && $this->feof()) {
                return $this->error('EBML parser: ran out of file at offset '.$this->current_offset);
            }
        }
        return true;
    }

    private function readEBMLint()
    {
        $actual_offset = $this->current_offset - $this->EBMLbuffer_offset;

        // get length of integer
        $first_byte_int = ord($this->EBMLbuffer[$actual_offset]);
        if       (0x80 & $first_byte_int) {
            $length = 1;
        } elseif (0x40 & $first_byte_int) {
            $length = 2;
        } elseif (0x20 & $first_byte_int) {
            $length = 3;
        } elseif (0x10 & $first_byte_int) {
            $length = 4;
        } elseif (0x08 & $first_byte_int) {
            $length = 5;
        } elseif (0x04 & $first_byte_int) {
            $length = 6;
        } elseif (0x02 & $first_byte_int) {
            $length = 7;
        } elseif (0x01 & $first_byte_int) {
            $length = 8;
        } else {
            throw new \Exception('invalid EBML integer (leading 0x00) at '.$this->current_offset);
        }

        // read
        $int_value = self::EBML2Int(substr($this->EBMLbuffer, $actual_offset, $length));
        $this->current_offset += $length;

        return $int_value;
    }

    private function readEBMLelementData($length, $check_buffer = false)
    {
        if ($check_buffer && !$this->EnsureBufferHasEnoughData($length)) {
            return false;
        }
        $data = substr($this->EBMLbuffer, $this->current_offset - $this->EBMLbuffer_offset, $length);
        $this->current_offset += $length;
        return $data;
    }

    private function getEBMLelement(&$element, $parent_end, $get_data = false)
    {
        if ($this->current_offset >= $parent_end) {
            return false;
        }

        if (!$this->EnsureBufferHasEnoughData()) {
            $this->current_offset = PHP_INT_MAX; // do not exit parser right now, allow to finish current loop to gather maximum information
            return false;
        }

        $element = [];

        // set offset
        $element['offset'] = $this->current_offset;

        // get ID
        $element['id'] = $this->readEBMLint();

        // get name
        $element['id_name'] = self::EBMLidName($element['id']);

        // get length
        $element['length'] = $this->readEBMLint();

        // get end offset
        $element['end'] = $this->current_offset + $element['length'];

        // get raw data
        $dont_parse = (in_array($element['id'], $this->unuseful_elements) || $element['id_name'] == dechex($element['id']));
        if (($get_data === true || (is_array($get_data) && !in_array($element['id'], $get_data))) && !$dont_parse) {
            $element['data'] = $this->readEBMLelementData($element['length'], $element);
        }

        return true;
    }

    private function unhandledElement($type, $line, $element)
    {
        // warn only about unknown and missed elements, not about unuseful
        if (!in_array($element['id'], $this->unuseful_elements)) {
            $this->warning('Unhandled '.$type.' element ['.basename(__FILE__).':'.$line.'] ('.$element['id'].'::'.$element['id_name'].' ['.$element['length'].' bytes]) at '.$element['offset']);
        }

        // increase offset for unparsed elements
        if (!isset($element['data'])) {
            $this->current_offset = $element['end'];
        }
    }

    private function ExtractCommentsSimpleTag($SimpleTagArray)
    {
        if (!empty($SimpleTagArray['SimpleTag'])) {
            foreach ($SimpleTagArray['SimpleTag'] as $SimpleTagKey => $SimpleTagData) {
                if (!empty($SimpleTagData['TagName']) && !empty($SimpleTagData['TagString'])) {
                    $this->getid3->info['matroska']['comments'][strtolower($SimpleTagData['TagName'])][] = $SimpleTagData['TagString'];
                }
                if (!empty($SimpleTagData['SimpleTag'])) {
                    $this->ExtractCommentsSimpleTag($SimpleTagData);
                }
            }
        }

        return true;
    }

    private function HandleEMBLSimpleTag($parent_end)
    {
        $simpletag_entry = [];

        while ($this->getEBMLelement($element, $parent_end, [self::ID_SIMPLETAG])) {
            switch ($element['id']) {

                case self::ID_TAGNAME:
                case self::ID_TAGLANGUAGE:
                case self::ID_TAGSTRING:
                case self::ID_TAGBINARY:
                    $simpletag_entry[$element['id_name']] = $element['data'];
                    break;

                case self::ID_SIMPLETAG:
                    $simpletag_entry[$element['id_name']][] = $this->HandleEMBLSimpleTag($element['end']);
                    break;

                case self::ID_TAGDEFAULT:
                    $simpletag_entry[$element['id_name']] = (bool)Utils::BigEndian2Int($element['data']);
                    break;

                default:
                    $this->unhandledElement('tag.simpletag', __LINE__, $element);
            }
        }

        return $simpletag_entry;
    }

    private function HandleEMBLClusterBlock($element, $block_type, &$info)
    {
        // http://www.matroska.org/technical/specs/index.html#block_structure
        // http://www.matroska.org/technical/specs/index.html#simpleblock_structure

        $block_data = [];
        $block_data['tracknumber'] = $this->readEBMLint();
        $block_data['timecode']    = Utils::BigEndian2Int($this->readEBMLelementData(2), false, true);
        $block_data['flags_raw']   = Utils::BigEndian2Int($this->readEBMLelementData(1));

        if ($block_type == self::ID_CLUSTERSIMPLEBLOCK) {
            $block_data['flags']['keyframe']  = (($block_data['flags_raw'] & 0x80) >> 7);
            //$block_data['flags']['reserved1'] = (($block_data['flags_raw'] & 0x70) >> 4);
        }
        else {
            //$block_data['flags']['reserved1'] = (($block_data['flags_raw'] & 0xF0) >> 4);
        }
        $block_data['flags']['invisible'] = (bool)(($block_data['flags_raw'] & 0x08) >> 3);
        $block_data['flags']['lacing']    =       (($block_data['flags_raw'] & 0x06) >> 1);  // 00=no lacing; 01=Xiph lacing; 11=EBML lacing; 10=fixed-size lacing
        if ($block_type == self::ID_CLUSTERSIMPLEBLOCK) {
            $block_data['flags']['discardable'] = (($block_data['flags_raw'] & 0x01));
        }
        else {
            //$block_data['flags']['reserved2'] = (($block_data['flags_raw'] & 0x01) >> 0);
        }
        $block_data['flags']['lacing_type'] = self::BlockLacingType($block_data['flags']['lacing']);

        // Lace (when lacing bit is set)
        if ($block_data['flags']['lacing'] > 0) {
            $block_data['lace_frames'] = Utils::BigEndian2Int($this->readEBMLelementData(1)) + 1; // Number of frames in the lace-1 (uint8)
            if ($block_data['flags']['lacing'] != 0x02) {
                for ($i = 1; $i < $block_data['lace_frames']; $i ++) { // Lace-coded size of each frame of the lace, except for the last one (multiple uint8). *This is not used with Fixed-size lacing as it is calculated automatically from (total size of lace) / (number of frames in lace).
                    if ($block_data['flags']['lacing'] == 0x03) { // EBML lacing
                        $block_data['lace_frames_size'][$i] = $this->readEBMLint(); // TODO: read size correctly, calc size for the last frame. For now offsets are deteminded OK with readEBMLint() and that's the most important thing.
                    }
                    else { // Xiph lacing
                        $block_data['lace_frames_size'][$i] = 0;
                        do {
                            $size = Utils::BigEndian2Int($this->readEBMLelementData(1));
                            $block_data['lace_frames_size'][$i] += $size;
                        }
                        while ($size == 255);
                    }
                }
                if ($block_data['flags']['lacing'] == 0x01) { // calc size of the last frame only for Xiph lacing, till EBML sizes are now anyway determined incorrectly
                    $block_data['lace_frames_size'][] = $element['end'] - $this->current_offset - array_sum($block_data['lace_frames_size']);
                }
            }
        }

        if (!isset($info['matroska']['track_data_offsets'][$block_data['tracknumber']])) {
            $info['matroska']['track_data_offsets'][$block_data['tracknumber']]['offset'] = $this->current_offset;
            $info['matroska']['track_data_offsets'][$block_data['tracknumber']]['length'] = $element['end'] - $this->current_offset;
            //$info['matroska']['track_data_offsets'][$block_data['tracknumber']]['total_length'] = 0;
        }
        //$info['matroska']['track_data_offsets'][$block_data['tracknumber']]['total_length'] += $info['matroska']['track_data_offsets'][$block_data['tracknumber']]['length'];
        //$info['matroska']['track_data_offsets'][$block_data['tracknumber']]['duration']      = $block_data['timecode'] * ((isset($info['matroska']['info'][0]['TimecodeScale']) ? $info['matroska']['info'][0]['TimecodeScale'] : 1000000) / 1000000000);

        // set offset manually
        $this->current_offset = $element['end'];

        return $block_data;
    }

    private static function EBML2Int($EBMLstring)
    {
        // http://matroska.org/specs/

        // Element ID coded with an UTF-8 like system:
        // 1xxx xxxx                                  - Class A IDs (2^7 -2 possible values) (base 0x8X)
        // 01xx xxxx  xxxx xxxx                       - Class B IDs (2^14-2 possible values) (base 0x4X 0xXX)
        // 001x xxxx  xxxx xxxx  xxxx xxxx            - Class C IDs (2^21-2 possible values) (base 0x2X 0xXX 0xXX)
        // 0001 xxxx  xxxx xxxx  xxxx xxxx  xxxx xxxx - Class D IDs (2^28-2 possible values) (base 0x1X 0xXX 0xXX 0xXX)
        // Values with all x at 0 and 1 are reserved (hence the -2).

        // Data size, in octets, is also coded with an UTF-8 like system :
        // 1xxx xxxx                                                                              - value 0 to  2^7-2
        // 01xx xxxx  xxxx xxxx                                                                   - value 0 to 2^14-2
        // 001x xxxx  xxxx xxxx  xxxx xxxx                                                        - value 0 to 2^21-2
        // 0001 xxxx  xxxx xxxx  xxxx xxxx  xxxx xxxx                                             - value 0 to 2^28-2
        // 0000 1xxx  xxxx xxxx  xxxx xxxx  xxxx xxxx  xxxx xxxx                                  - value 0 to 2^35-2
        // 0000 01xx  xxxx xxxx  xxxx xxxx  xxxx xxxx  xxxx xxxx  xxxx xxxx                       - value 0 to 2^42-2
        // 0000 001x  xxxx xxxx  xxxx xxxx  xxxx xxxx  xxxx xxxx  xxxx xxxx  xxxx xxxx            - value 0 to 2^49-2
        // 0000 0001  xxxx xxxx  xxxx xxxx  xxxx xxxx  xxxx xxxx  xxxx xxxx  xxxx xxxx  xxxx xxxx - value 0 to 2^56-2

        $first_byte_int = ord($EBMLstring[0]);
        if (0x80 & $first_byte_int) {
            $EBMLstring[0] = chr($first_byte_int & 0x7F);
        } elseif (0x40 & $first_byte_int) {
            $EBMLstring[0] = chr($first_byte_int & 0x3F);
        } elseif (0x20 & $first_byte_int) {
            $EBMLstring[0] = chr($first_byte_int & 0x1F);
        } elseif (0x10 & $first_byte_int) {
            $EBMLstring[0] = chr($first_byte_int & 0x0F);
        } elseif (0x08 & $first_byte_int) {
            $EBMLstring[0] = chr($first_byte_int & 0x07);
        } elseif (0x04 & $first_byte_int) {
            $EBMLstring[0] = chr($first_byte_int & 0x03);
        } elseif (0x02 & $first_byte_int) {
            $EBMLstring[0] = chr($first_byte_int & 0x01);
        } elseif (0x01 & $first_byte_int) {
            $EBMLstring[0] = chr($first_byte_int & 0x00);
        }

        return Utils::BigEndian2Int($EBMLstring);
    }

    private static function EBMLdate2unix($EBMLdatestamp)
    {
        // Date - signed 8 octets integer in nanoseconds with 0 indicating the precise beginning of the millennium (at 2001-01-01T00:00:00,000000000 UTC)
        // 978307200 == mktime(0, 0, 0, 1, 1, 2001) == January 1, 2001 12:00:00am UTC
        return round(($EBMLdatestamp / 1000000000) + 978307200);
    }

    public static function TargetTypeValue($target_type)
    {
        // http://www.matroska.org/technical/specs/tagging/index.html
        static $TargetTypeValue = [];
        if (empty($TargetTypeValue)) {
            $TargetTypeValue[10] = 'A: ~ V:shot';                                           // the lowest hierarchy found in music or movies
            $TargetTypeValue[20] = 'A:subtrack/part/movement ~ V:scene';                    // corresponds to parts of a track for audio (like a movement)
            $TargetTypeValue[30] = 'A:track/song ~ V:chapter';                              // the common parts of an album or a movie
            $TargetTypeValue[40] = 'A:part/session ~ V:part/session';                       // when an album or episode has different logical parts
            $TargetTypeValue[50] = 'A:album/opera/concert ~ V:movie/episode/concert';       // the most common grouping level of music and video (equals to an episode for TV series)
            $TargetTypeValue[60] = 'A:edition/issue/volume/opus ~ V:season/sequel/volume';  // a list of lower levels grouped together
            $TargetTypeValue[70] = 'A:collection ~ V:collection';                           // the high hierarchy consisting of many different lower items
        }
        return (isset($TargetTypeValue[$target_type]) ? $TargetTypeValue[$target_type] : $target_type);
    }

    public static function BlockLacingType($lacingtype)
    {
        // http://matroska.org/technical/specs/index.html#block_structure
        static $BlockLacingType = [];
        if (empty($BlockLacingType)) {
            $BlockLacingType[0x00] = 'no lacing';
            $BlockLacingType[0x01] = 'Xiph lacing';
            $BlockLacingType[0x02] = 'fixed-size lacing';
            $BlockLacingType[0x03] = 'EBML lacing';
        }
        return (isset($BlockLacingType[$lacingtype]) ? $BlockLacingType[$lacingtype] : $lacingtype);
    }

    public static function CodecIDtoCommonName($codecid)
    {
        // http://www.matroska.org/technical/specs/codecid/index.html
        static $CodecIDlist = [];
        if ($CodecIDlist === []) {
            $CodecIDlist = [
                'A_AAC'            => 'aac',
                'A_AAC/MPEG2/LC'   => 'aac',
                'A_AC3'            => 'ac3',
                'A_DTS'            => 'dts',
                'A_FLAC'           => 'flac',
                'A_MPEG/L1'        => 'mp1',
                'A_MPEG/L2'        => 'mp2',
                'A_MPEG/L3'        => 'mp3',
                'A_PCM/INT/LIT'    => 'pcm',       // PCM Integer Little Endian
                'A_PCM/INT/BIG'    => 'pcm',       // PCM Integer Big Endian
                'A_QUICKTIME/QDMC' => 'quicktime', // Quicktime: QDesign Music
                'A_QUICKTIME/QDM2' => 'quicktime', // Quicktime: QDesign Music v2
                'A_VORBIS'         => 'vorbis',
                'V_MPEG1'          => 'mpeg',
                'V_THEORA'         => 'theora',
                'V_REAL/RV40'      => 'real',
                'V_REAL/RV10'      => 'real',
                'V_REAL/RV20'      => 'real',
                'V_REAL/RV30'      => 'real',
                'V_QUICKTIME'      => 'quicktime', // Quicktime
                'V_MPEG4/ISO/AP'   => 'mpeg4',
                'V_MPEG4/ISO/ASP'  => 'mpeg4',
                'V_MPEG4/ISO/AVC'  => 'h264',
                'V_MPEG4/ISO/SP'   => 'mpeg4',
                'V_VP8'            => 'vp8',
                'V_MS/VFW/FOURCC'  => 'vcm', // Microsoft (TM) Video Codec Manager (VCM)
                'A_MS/ACM'         => 'acm', // Microsoft (TM) Audio Codec Manager (ACM)
            ];
        }
        return (isset($CodecIDlist[$codecid]) ? $CodecIDlist[$codecid] : $codecid);
    }

    private static function EBMLidName($value)
    {
        static $EBMLidList = [];
        if ($EBMLidList === []) {
            $EBMLidList = [
                self::ID_ASPECTRATIOTYPE            => 'AspectRatioType',
                self::ID_ATTACHEDFILE               => 'AttachedFile',
                self::ID_ATTACHMENTLINK             => 'AttachmentLink',
                self::ID_ATTACHMENTS                => 'Attachments',
                self::ID_AUDIO                      => 'Audio',
                self::ID_BITDEPTH                   => 'BitDepth',
                self::ID_CHANNELPOSITIONS           => 'ChannelPositions',
                self::ID_CHANNELS                   => 'Channels',
                self::ID_CHAPCOUNTRY                => 'ChapCountry',
                self::ID_CHAPLANGUAGE               => 'ChapLanguage',
                self::ID_CHAPPROCESS                => 'ChapProcess',
                self::ID_CHAPPROCESSCODECID         => 'ChapProcessCodecID',
                self::ID_CHAPPROCESSCOMMAND         => 'ChapProcessCommand',
                self::ID_CHAPPROCESSDATA            => 'ChapProcessData',
                self::ID_CHAPPROCESSPRIVATE         => 'ChapProcessPrivate',
                self::ID_CHAPPROCESSTIME            => 'ChapProcessTime',
                self::ID_CHAPSTRING                 => 'ChapString',
                self::ID_CHAPTERATOM                => 'ChapterAtom',
                self::ID_CHAPTERDISPLAY             => 'ChapterDisplay',
                self::ID_CHAPTERFLAGENABLED         => 'ChapterFlagEnabled',
                self::ID_CHAPTERFLAGHIDDEN          => 'ChapterFlagHidden',
                self::ID_CHAPTERPHYSICALEQUIV       => 'ChapterPhysicalEquiv',
                self::ID_CHAPTERS                   => 'Chapters',
                self::ID_CHAPTERSEGMENTEDITIONUID   => 'ChapterSegmentEditionUID',
                self::ID_CHAPTERSEGMENTUID          => 'ChapterSegmentUID',
                self::ID_CHAPTERTIMEEND             => 'ChapterTimeEnd',
                self::ID_CHAPTERTIMESTART           => 'ChapterTimeStart',
                self::ID_CHAPTERTRACK               => 'ChapterTrack',
                self::ID_CHAPTERTRACKNUMBER         => 'ChapterTrackNumber',
                self::ID_CHAPTERTRANSLATE           => 'ChapterTranslate',
                self::ID_CHAPTERTRANSLATECODEC      => 'ChapterTranslateCodec',
                self::ID_CHAPTERTRANSLATEEDITIONUID => 'ChapterTranslateEditionUID',
                self::ID_CHAPTERTRANSLATEID         => 'ChapterTranslateID',
                self::ID_CHAPTERUID                 => 'ChapterUID',
                self::ID_CLUSTER                    => 'Cluster',
                self::ID_CLUSTERBLOCK               => 'ClusterBlock',
                self::ID_CLUSTERBLOCKADDID          => 'ClusterBlockAddID',
                self::ID_CLUSTERBLOCKADDITIONAL     => 'ClusterBlockAdditional',
                self::ID_CLUSTERBLOCKADDITIONID     => 'ClusterBlockAdditionID',
                self::ID_CLUSTERBLOCKADDITIONS      => 'ClusterBlockAdditions',
                self::ID_CLUSTERBLOCKDURATION       => 'ClusterBlockDuration',
                self::ID_CLUSTERBLOCKGROUP          => 'ClusterBlockGroup',
                self::ID_CLUSTERBLOCKMORE           => 'ClusterBlockMore',
                self::ID_CLUSTERBLOCKVIRTUAL        => 'ClusterBlockVirtual',
                self::ID_CLUSTERCODECSTATE          => 'ClusterCodecState',
                self::ID_CLUSTERDELAY               => 'ClusterDelay',
                self::ID_CLUSTERDURATION            => 'ClusterDuration',
                self::ID_CLUSTERENCRYPTEDBLOCK      => 'ClusterEncryptedBlock',
                self::ID_CLUSTERFRAMENUMBER         => 'ClusterFrameNumber',
                self::ID_CLUSTERLACENUMBER          => 'ClusterLaceNumber',
                self::ID_CLUSTERPOSITION            => 'ClusterPosition',
                self::ID_CLUSTERPREVSIZE            => 'ClusterPrevSize',
                self::ID_CLUSTERREFERENCEBLOCK      => 'ClusterReferenceBlock',
                self::ID_CLUSTERREFERENCEPRIORITY   => 'ClusterReferencePriority',
                self::ID_CLUSTERREFERENCEVIRTUAL    => 'ClusterReferenceVirtual',
                self::ID_CLUSTERSILENTTRACKNUMBER   => 'ClusterSilentTrackNumber',
                self::ID_CLUSTERSILENTTRACKS        => 'ClusterSilentTracks',
                self::ID_CLUSTERSIMPLEBLOCK         => 'ClusterSimpleBlock',
                self::ID_CLUSTERTIMECODE            => 'ClusterTimecode',
                self::ID_CLUSTERTIMESLICE           => 'ClusterTimeSlice',
                self::ID_CODECDECODEALL             => 'CodecDecodeAll',
                self::ID_CODECDOWNLOADURL           => 'CodecDownloadURL',
                self::ID_CODECID                    => 'CodecID',
                self::ID_CODECINFOURL               => 'CodecInfoURL',
                self::ID_CODECNAME                  => 'CodecName',
                self::ID_CODECPRIVATE               => 'CodecPrivate',
                self::ID_CODECSETTINGS              => 'CodecSettings',
                self::ID_COLOURSPACE                => 'ColourSpace',
                self::ID_CONTENTCOMPALGO            => 'ContentCompAlgo',
                self::ID_CONTENTCOMPRESSION         => 'ContentCompression',
                self::ID_CONTENTCOMPSETTINGS        => 'ContentCompSettings',
                self::ID_CONTENTENCALGO             => 'ContentEncAlgo',
                self::ID_CONTENTENCKEYID            => 'ContentEncKeyID',
                self::ID_CONTENTENCODING            => 'ContentEncoding',
                self::ID_CONTENTENCODINGORDER       => 'ContentEncodingOrder',
                self::ID_CONTENTENCODINGS           => 'ContentEncodings',
                self::ID_CONTENTENCODINGSCOPE       => 'ContentEncodingScope',
                self::ID_CONTENTENCODINGTYPE        => 'ContentEncodingType',
                self::ID_CONTENTENCRYPTION          => 'ContentEncryption',
                self::ID_CONTENTSIGALGO             => 'ContentSigAlgo',
                self::ID_CONTENTSIGHASHALGO         => 'ContentSigHashAlgo',
                self::ID_CONTENTSIGKEYID            => 'ContentSigKeyID',
                self::ID_CONTENTSIGNATURE           => 'ContentSignature',
                self::ID_CRC32                      => 'CRC32',
                self::ID_CUEBLOCKNUMBER             => 'CueBlockNumber',
                self::ID_CUECLUSTERPOSITION         => 'CueClusterPosition',
                self::ID_CUECODECSTATE              => 'CueCodecState',
                self::ID_CUEPOINT                   => 'CuePoint',
                self::ID_CUEREFCLUSTER              => 'CueRefCluster',
                self::ID_CUEREFCODECSTATE           => 'CueRefCodecState',
                self::ID_CUEREFERENCE               => 'CueReference',
                self::ID_CUEREFNUMBER               => 'CueRefNumber',
                self::ID_CUEREFTIME                 => 'CueRefTime',
                self::ID_CUES                       => 'Cues',
                self::ID_CUETIME                    => 'CueTime',
                self::ID_CUETRACK                   => 'CueTrack',
                self::ID_CUETRACKPOSITIONS          => 'CueTrackPositions',
                self::ID_DATEUTC                    => 'DateUTC',
                self::ID_DEFAULTDURATION            => 'DefaultDuration',
                self::ID_DISPLAYHEIGHT              => 'DisplayHeight',
                self::ID_DISPLAYUNIT                => 'DisplayUnit',
                self::ID_DISPLAYWIDTH               => 'DisplayWidth',
                self::ID_DOCTYPE                    => 'DocType',
                self::ID_DOCTYPEREADVERSION         => 'DocTypeReadVersion',
                self::ID_DOCTYPEVERSION             => 'DocTypeVersion',
                self::ID_DURATION                   => 'Duration',
                self::ID_EBML                       => 'EBML',
                self::ID_EBMLMAXIDLENGTH            => 'EBMLMaxIDLength',
                self::ID_EBMLMAXSIZELENGTH          => 'EBMLMaxSizeLength',
                self::ID_EBMLREADVERSION            => 'EBMLReadVersion',
                self::ID_EBMLVERSION                => 'EBMLVersion',
                self::ID_EDITIONENTRY               => 'EditionEntry',
                self::ID_EDITIONFLAGDEFAULT         => 'EditionFlagDefault',
                self::ID_EDITIONFLAGHIDDEN          => 'EditionFlagHidden',
                self::ID_EDITIONFLAGORDERED         => 'EditionFlagOrdered',
                self::ID_EDITIONUID                 => 'EditionUID',
                self::ID_FILEDATA                   => 'FileData',
                self::ID_FILEDESCRIPTION            => 'FileDescription',
                self::ID_FILEMIMETYPE               => 'FileMimeType',
                self::ID_FILENAME                   => 'FileName',
                self::ID_FILEREFERRAL               => 'FileReferral',
                self::ID_FILEUID                    => 'FileUID',
                self::ID_FLAGDEFAULT                => 'FlagDefault',
                self::ID_FLAGENABLED                => 'FlagEnabled',
                self::ID_FLAGFORCED                 => 'FlagForced',
                self::ID_FLAGINTERLACED             => 'FlagInterlaced',
                self::ID_FLAGLACING                 => 'FlagLacing',
                self::ID_GAMMAVALUE                 => 'GammaValue',
                self::ID_INFO                       => 'Info',
                self::ID_LANGUAGE                   => 'Language',
                self::ID_MAXBLOCKADDITIONID         => 'MaxBlockAdditionID',
                self::ID_MAXCACHE                   => 'MaxCache',
                self::ID_MINCACHE                   => 'MinCache',
                self::ID_MUXINGAPP                  => 'MuxingApp',
                self::ID_NAME                       => 'Name',
                self::ID_NEXTFILENAME               => 'NextFilename',
                self::ID_NEXTUID                    => 'NextUID',
                self::ID_OUTPUTSAMPLINGFREQUENCY    => 'OutputSamplingFrequency',
                self::ID_PIXELCROPBOTTOM            => 'PixelCropBottom',
                self::ID_PIXELCROPLEFT              => 'PixelCropLeft',
                self::ID_PIXELCROPRIGHT             => 'PixelCropRight',
                self::ID_PIXELCROPTOP               => 'PixelCropTop',
                self::ID_PIXELHEIGHT                => 'PixelHeight',
                self::ID_PIXELWIDTH                 => 'PixelWidth',
                self::ID_PREVFILENAME               => 'PrevFilename',
                self::ID_PREVUID                    => 'PrevUID',
                self::ID_SAMPLINGFREQUENCY          => 'SamplingFrequency',
                self::ID_SEEK                       => 'Seek',
                self::ID_SEEKHEAD                   => 'SeekHead',
                self::ID_SEEKID                     => 'SeekID',
                self::ID_SEEKPOSITION               => 'SeekPosition',
                self::ID_SEGMENT                    => 'Segment',
                self::ID_SEGMENTFAMILY              => 'SegmentFamily',
                self::ID_SEGMENTFILENAME            => 'SegmentFilename',
                self::ID_SEGMENTUID                 => 'SegmentUID',
                self::ID_SIMPLETAG                  => 'SimpleTag',
                self::ID_CLUSTERSLICES              => 'ClusterSlices',
                self::ID_STEREOMODE                 => 'StereoMode',
                self::ID_OLDSTEREOMODE              => 'OldStereoMode',
                self::ID_TAG                        => 'Tag',
                self::ID_TAGATTACHMENTUID           => 'TagAttachmentUID',
                self::ID_TAGBINARY                  => 'TagBinary',
                self::ID_TAGCHAPTERUID              => 'TagChapterUID',
                self::ID_TAGDEFAULT                 => 'TagDefault',
                self::ID_TAGEDITIONUID              => 'TagEditionUID',
                self::ID_TAGLANGUAGE                => 'TagLanguage',
                self::ID_TAGNAME                    => 'TagName',
                self::ID_TAGTRACKUID                => 'TagTrackUID',
                self::ID_TAGS                       => 'Tags',
                self::ID_TAGSTRING                  => 'TagString',
                self::ID_TARGETS                    => 'Targets',
                self::ID_TARGETTYPE                 => 'TargetType',
                self::ID_TARGETTYPEVALUE            => 'TargetTypeValue',
                self::ID_TIMECODESCALE              => 'TimecodeScale',
                self::ID_TITLE                      => 'Title',
                self::ID_TRACKENTRY                 => 'TrackEntry',
                self::ID_TRACKNUMBER                => 'TrackNumber',
                self::ID_TRACKOFFSET                => 'TrackOffset',
                self::ID_TRACKOVERLAY               => 'TrackOverlay',
                self::ID_TRACKS                     => 'Tracks',
                self::ID_TRACKTIMECODESCALE         => 'TrackTimecodeScale',
                self::ID_TRACKTRANSLATE             => 'TrackTranslate',
                self::ID_TRACKTRANSLATECODEC        => 'TrackTranslateCodec',
                self::ID_TRACKTRANSLATEEDITIONUID   => 'TrackTranslateEditionUID',
                self::ID_TRACKTRANSLATETRACKID      => 'TrackTranslateTrackID',
                self::ID_TRACKTYPE                  => 'TrackType',
                self::ID_TRACKUID                   => 'TrackUID',
                self::ID_VIDEO                      => 'Video',
                self::ID_VOID                       => 'Void',
                self::ID_WRITINGAPP                 => 'WritingApp',
            ];
        }

        return (isset($EBMLidList[$value]) ? $EBMLidList[$value] : dechex($value));
    }

    public static function displayUnit($value)
    {
        // http://www.matroska.org/technical/specs/index.html#DisplayUnit
        static $units = [
            0 => 'pixels',
            1 => 'centimeters',
            2 => 'inches',
            3 => 'Display Aspect Ratio'
        ];

        return (isset($units[$value]) ? $units[$value] : 'unknown');
    }

    private static function getDefaultStreamInfo($streams)
    {
        foreach (array_reverse($streams) as $stream) {
            if ($stream['default']) {
                break;
            }
        }

        $unset = ['default', 'name'];
        foreach ($unset as $u) {
            if (isset($stream[$u])) {
                unset($stream[$u]);
            }
        }

        $info = $stream;
        $info['streams'] = $streams;

        return $info;
    }
}
