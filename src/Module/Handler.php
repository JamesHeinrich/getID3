<?php

namespace JamesHeinrich\GetID3\Module;

use JamesHeinrich\GetID3\Exception;
use JamesHeinrich\GetID3\GetID3;
use JamesHeinrich\GetID3\Utils;

abstract class Handler {

    /**
    * @var getID3
    */
    protected $getid3;                       // pointer

    protected $data_string_flag     = false; // analyzing filepointer or string
    protected $data_string          = '';    // string to analyze
    protected $data_string_position = 0;     // seek position in string
    protected $data_string_length   = 0;     // string length

    private $dependency_to = null;


    public function __construct(getID3 $getid3, $call_module=null) {
        $this->getid3 = $getid3;

        if ($call_module) {
            $this->dependency_to = str_replace('getid3_', '', $call_module);
        }
    }


    // Analyze from file pointer
    abstract public function Analyze();


    // Analyze from string instead
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

    public function setStringMode($string) {
        $this->data_string_flag   = true;
        $this->data_string        = $string;
        $this->data_string_length = strlen($string);
    }

    protected function ftell() {
        if ($this->data_string_flag) {
            return $this->data_string_position;
        }
        return ftell($this->getid3->fp);
    }

    protected function fread($bytes) {
        if ($this->data_string_flag) {
            $this->data_string_position += $bytes;
            return substr($this->data_string, $this->data_string_position - $bytes, $bytes);
        }
        $pos = $this->ftell() + $bytes;
        if (!Utils::intValueSupported($pos)) {
            throw new Exception('cannot fread('.$bytes.' from '.$this->ftell().') because beyond PHP filesystem limit', 10);
        }
        return fread($this->getid3->fp, $bytes);
    }

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

    protected function feof() {
        if ($this->data_string_flag) {
            return $this->data_string_position >= $this->data_string_length;
        }
        return feof($this->getid3->fp);
    }

    final protected function isDependencyFor($module) {
        return $this->dependency_to == $module;
    }

    protected function error($text) {
        $this->getid3->info['error'][] = $text;

        return false;
    }

    protected function warning($text) {
        return $this->getid3->warning($text);
    }

    protected function notice($text) {
        // does nothing for now
    }

    public function saveAttachment($name, $offset, $length, $image_mime=null) {
        try {

            // do not extract at all
            if ($this->getid3->option_save_attachments === GetID3::ATTACHMENTS_NONE) {

                $attachment = null; // do not set any

            // extract to return array
            } elseif ($this->getid3->option_save_attachments === GetID3::ATTACHMENTS_INLINE) {

                $this->fseek($offset);
                $attachment = $this->fread($length); // get whole data in one pass, till it is anyway stored in memory
                if ($attachment === false || strlen($attachment) != $length) {
                    throw new \Exception('failed to read attachment data');
                }

            // assume directory path is given
            } else {

                // set up destination path
                $dir = rtrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $this->getid3->option_save_attachments), DIRECTORY_SEPARATOR);
                if (!is_dir($dir) || !is_writable($dir)) { // check supplied directory
                    throw new \Exception('supplied path ('.$dir.') does not exist, or is not writable');
                }
                $dest = $dir.DIRECTORY_SEPARATOR.$name.($image_mime ? '.'.Utils::ImageExtFromMime($image_mime) : '');

                // create dest file
                if (($fp_dest = fopen($dest, 'wb')) == false) {
                    throw new \Exception('failed to create file '.$dest);
                }

                // copy data
                $this->fseek($offset);
                $buffersize = ($this->data_string_flag ? $length : $this->getid3->fread_buffer_size());
                $bytesleft = $length;
                while ($bytesleft > 0) {
                    if (($buffer = $this->fread(min($buffersize, $bytesleft))) === false || ($byteswritten = fwrite($fp_dest, $buffer)) === false || ($byteswritten === 0)) {
                        throw new \Exception($buffer === false ? 'not enough data to read' : 'failed to write to destination file, may be not enough disk space');
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
