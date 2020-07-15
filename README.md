MediaWiki2DokuWiki
==================

[Original repo](https://github.com/tetsuo13/MediaWiki-to-DokuWiki-Importer)

## Improtant:

- My work task was to convert 'moded' mediawiki to some alternative and it turned out to be DokuWiki.
I reworked some parts of [original code](https://github.com/tetsuo13/MediaWiki-to-DokuWiki-Importer), but I am too lazy to deal with merges etc. and have better things to do.
- Because of the 'moded stuff' I hardcoded some parts and before publishing this I remove them due to confidental reasons.


[![Build Status](https://api.travis-ci.org/tetsuo13/MediaWiki-to-DokuWiki-Importer.svg)](https://travis-ci.org/tetsuo13/MediaWiki-to-DokuWiki-Importer)

Import MediaWiki into DokuWiki. Will also translate from MediaWiki syntax to
DokuWiki as best as it can (not all modifiers from MediaWiki are available in
DokuWiki). It processes pages and images/files.

Tested against:

* MediaWiki 1.17.1 and DokuWiki 2012-01-25 "Angua".
* MediaWiki 1.16.1 and DokuWiki 2011-05-25a "Rincewind"


Requirements
------------

* Physical access to the MediaWiki and DokuWiki installation on server.
* Read access to MediaWiki's LocalSettings.php script.
* PHP 5.1.0 or greater.
* PDO extension with MySQL/PostgreSQL binding -- whatever DB type MediaWiki is
using.

Usage
-----

Can be run either through a web server or from the command line, if you have
SSH access to the server. For large MediaWiki installations the command line
is preferred as the web server may terminate the process due to maximum
execution time limits, if safe mode is enabled and cannot be disabled.

Copy the ``src`` directory either in a temporary location if you will be using
the command line or in a web accessible location otherwise.

Copy ``settings.php.dist`` to ``settings.php`` and edit the contents to
provide the path to the source MediaWiki and target DokuWiki installations.
Even if running from a web server, the paths cannot be URLs.

Whether you will be using the command line or a web server, the target file
to be called is the same. If using the command line:

    $ php index.php

If using a web server:

    /index.php

