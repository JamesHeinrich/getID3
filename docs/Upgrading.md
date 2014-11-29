Upgrading
=========

Version 1.9.8 -> 2.0.0
----------------------

Several breaking changes were made in version 2 to allow the project to follow modern php standards.  
The most significant of these was renaming all classes to allow for autoloading.  
All classes are now within the namespace JamesHeinrich\GetID3 and their old and new names are shown below:
```php
Old name in root namespace          New name in JamesHeinrich\GetID3
getID3                              GetID3
getid3_lib                          Utils
getid3_writetags                    Writer
getID3_cached_dbm                   Cache\Dbm
getID3_cached_mysql                 Cache\Mysql
getID3_cached_sqlite3               Cache\Sqlite3
getid3_gzip                         Module\Archive\Gzip
getid3_rar                          Module\Archive\Rar
getid3_szip                         Module\Archive\Szip
getid3_tar                          Module\Archive\Tar
getid3_zip                          Module\Archive\Zip
getid3_zip                          Module\Archive\Zip
getid3_aac                          Module\Audio\Aac
getid3_aa                           Module\Audio\Aa
getid3_ac3                          Module\Audio\Ac3
getid3_amr                          Module\Audio\Amr
getid3_au                           Module\Audio\Au
getid3_avr                          Module\Audio\Avr
getid3_bonk                         Module\Audio\Bonk
getid3_dss                          Module\Audio\Dss
getid3_dts                          Module\Audio\Dts
getid3_flac                         Module\Audio\Flac
getid3_la                           Module\Audio\La
getid3_lpac                         Module\Audio\Lpac
getid3_midi                         Module\Audio\Midi
getid3_mod                          Module\Audio\Mod
getid3_monkey                       Module\Audio\Monkey
getid3_mp3                          Module\Audio\Mp3
getid3_mpc                          Module\Audio\Mpc
getid3_ogg                          Module\Audio\Ogg
getid3_optimfrog                    Module\Audio\OptimFrog
getid3_rkau                         Module\Audio\Rkau
getid3_shorten                      Module\Audio\Shorten
getid3_tta                          Module\Audio\Tta
getid3_voc                          Module\Audio\Voc
getid3_vqf                          Module\Audio\Vqf
getid3_wavpack                      Module\Audio\WavPack
getid3_asf                          Module\AudioVideo\Asf
getid3_bink                         Module\AudioVideo\Bink
getid3_flv                          Module\AudioVideo\Flv
getid3_matroska                     Module\AudioVideo\Matroska
getid3_mpeg                         Module\AudioVideo\Mpeg
getid3_nsv                          Module\AudioVideo\Nsv
getid3_quicktime                    Module\AudioVideo\QuickTime
getid3_real                         Module\AudioVideo\Real
getid3_riff                         Module\AudioVideo\Riff
getid3_swf                          Module\AudioVideo\Swf
getid3_ts                           Module\AudioVideo\Ts
getid3_bmp                          Module\Graphic\Bmp
getid3_efax                         Module\Graphic\Efax
getid3_gif                          Module\Graphic\Gif
getid3_jpg                          Module\Graphic\Jpg
getid3_pcd                          Module\Graphic\Pcd
getid3_png                          Module\Graphic\Png
getid3_svg                          Module\Graphic\Svg
getid3_tiff                         Module\Graphic\Tiff
getid3_cue                          Module\Misc\Cue
getid3_exe                          Module\Misc\Exe
getid3_iso                          Module\Misc\Iso
getid3_msoffice                     Module\Misc\MsOffice
getid3_par2                         Module\Misc\Par2
getid3_pdf                          Module\Misc\Pdf
getid3_apetag                       Module\Tag\ApeTag
getid3_id3v1                        Module\Tag\ID3v1
getid3_id3v2                        Module\Tag\ID3v2
getid3_lyrics3                      Module\Tag\Lyrics3
Image_XMP                           Module\Tag\Xmp
getid3_write_apetag                 Write\ApeTag
getid3_write_id3v1                  Write\ID3v1
getid3_write_id3v2                  Write\ID3v2
getid3_write_lyrics3                Write\Lyrics3
getid3_write_metaflac               Write\MetaFlac
getid3_write_real                   Write\Real
getid3_write_vorbiscomment          Write\VorbisComment
```


__Temp Directory__
Previously the temp directory could be set by defining a global constant called GETID3_TEMP_DIR  
This is now done by calling Utils::setTempDirectory()  
```php
# Old way
define("GET_ID3_TEMP_DIR", "/tmp/custom_getid3_stuff");

# New way
use JamesHeinrich\GetID3\Utils;
Utils::setTempDirectory("/tmp/custom_getid3_stuff");
```
