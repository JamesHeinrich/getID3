<?php

namespace JamesHeinrich\GetID3;

class Mp3Test extends \PHPUnit_Framework_TestCase
{

    public function testRead()
    {
        $filename = __DIR__ . "/files/silence.mp3";
        $band = "Protest The Hero";
        $year = 2010;

        $writer = new WriteTags;
        $writer->filename = $filename;
        $writer->tagformats = ["id3v2.4"];
        $writer->tag_encoding = "UTF-8";
        $writer->overwrite_tags = true;

        $writer->tag_data = [
            "band"              =>  [$band],
            "recording_time"    =>  [$year],
        ];

        $writer->WriteTags();

        $getID3 = new GetID3;
        $tags = $getID3->analyze($filename)["tags"]["id3v2"];
        $this->assertSame($band, $tags["band"][0]);
        $this->assertSame((string) $year, $tags["year"][0]);
        $this->assertSame((string) $year, $tags["recording_time"][0]);
    }
}
