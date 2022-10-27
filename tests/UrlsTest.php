<?php

namespace JamesHeinrich\GetID3\Tests;

use ExternalUrlTest;
use JamesHeinrich\GetID3\Exception;
use JamesHeinrich\GetID3\GetID3;
use PHPUnit\Framework\TestCase;

class UrlsTest extends TestCase
{

	public function testHttps()
	{
		try{
			$getID3 = new GetID3();
			$info = $getID3->analyze("https://example.com/test.mp3");
			$this->assertArrayHasKey('error', $info);
			$this->assertContains('Remote files are not supported - please copy the file locally first', $info['error']);
		}catch (Exception $e){
			$this->assertFalse(true, "Exception: ".$e->getMessage());
		}
	}

	public function testHttp()
	{
		try{
			$getID3 = new GetID3();
			$info = $getID3->analyze("http://example.com/test.mp3");
			$this->assertArrayHasKey('error', $info);
			$this->assertContains('Remote files are not supported - please copy the file locally first', $info['error']);
		}catch (Exception $e){
			$this->assertFalse(true, "Exception: ".$e->getMessage());
		}
	}

	public function testFtp()
	{
		try{
			$getID3 = new GetID3();
			$info = $getID3->analyze("ftp://example.com/test.mp3");
			$this->assertArrayHasKey('error', $info);
			$this->assertContains('Remote files are not supported - please copy the file locally first', $info['error']);
		}catch (Exception $e){
			$this->assertFalse(true, "Exception: ".$e->getMessage());
		}
	}

	public function testFtps()
	{
		try{
			$getID3 = new GetID3();
			$info = $getID3->analyze("ftps://example.com/test.mp3");
			$this->assertArrayHasKey('error', $info);
			$this->assertContains('Remote files are not supported - please copy the file locally first', $info['error']);
		}catch (Exception $e){
			$this->assertFalse(true, "Exception: ".$e->getMessage());
		}
	}

}
