getID3
======

A PHP library to extract and write useful information to/from popular multimedia file formats.  
If you want to donate, there is a link on <http://www.getid3.org> for PayPal donations.

[![Latest Stable Version](https://poser.pugx.org/james-heinrich/getID3/version.svg)](https://packagist.org/packages/james-heinrich/getid3)
[![Build Status](https://travis-ci.org/JamesHeinrich/getID3.svg?branch=2.0)](https://travis-ci.org/JamesHeinrich/getID3)


Installation
============
Using [composer](https://packagist.org/packages/james-heinrich/getid3):
```bash
$ composer require "james-heinrich/getid3:~2.0.0-dev"
```

__How can I check that getID3() works on my server/files?:__  
  _Unzip getID3() to a directory, then access `/demos/demo.browse.php`_


Usage
=====
See /demos/demo.basic.php for a very basic use of getID3() with no fancy output, just scanning one file.  
For an example of a complete directory-browsing, file-scanning implementation of getID3(), please run /demos/demo.browse.php  

See /demos/demo.mysql.php for a sample recursive scanning code that scans every file in a given directory, and all sub-directories, stores the results in a database and allows various analysis / maintenance operations.  

See /demos/demo.write.php for how to write tags.


Documentation
-------------
* [What does getID3() do?](docs/Features.md)
* [What does the returned data structure look like?](docs/Structure.md)
* [Requirements](docs/Requirements.md)
* [License](LICENSE.md)
* [References](docs/References.md)
* [Known Bugs/Issues in other programs](docs/External-Issues.md)
* [Known Bugs/Issues in getID3() that cannot be fixed](docs/Known-Issues.md)
* [Known Bugs/Issues in getID3() that may be fixed eventually](docs/Outstanding-Issues.md)
* [Future Plans](docs/TODO.md)
