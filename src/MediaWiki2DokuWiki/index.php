<?php
/**
 * MediaWiki2DokuWiki importer.
 * Copyright (C) 2011-2013  Andrei Nicholson
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package   MediaWiki2DokuWiki
 * @author    Andrei Nicholson
 * @copyright Copyright (C) 2011-2013 Andrei Nicholson
 * @link      https://github.com/tetsuo13/MediaWiki-to-DokuWiki-Importer
 */

//ini_set('display_errors', '1');
//error_reporting(E_ALL | E_STRICT);

$SETTINGS_FILE="settings.php";

global $ARG_OPTIONS, $ARG_OPTIONS_I, $ARG_OPTIONS_PAGES,$SPECIFICS_DATA_DIR;
$ARG_OPTIONS_I=0;
$ARG_OPTIONS= getopt( "s:p::",[], $ARG_OPTIONS_I);
$ARG_OPTIONS_PAGES=array_slice($argv, $ARG_OPTIONS_I);

if(!empty($ARG_OPTIONS['s'])){
  if(strpos($argv[1], '.php') !== 0){
    echo "Using fix ${ARG_OPTIONS['s']}\n";
    $SETTINGS_FILE = str_replace(".php", ".${ARG_OPTIONS['s']}.php",$SETTINGS_FILE);
    echo "$SETTINGS_FILE\n";
  } else {
    $SETTINGS_FILE=${ARG_OPTIONS['s']};
  }
}


echo "Using config file: $SETTINGS_FILE\n";

if(!file_exists($SETTINGS_FILE)){
  echo "File $SETTINGS_FILE does not exists\n";
  exit(1);
}

require "$SETTINGS_FILE";

// this should contain just short string leading to ./Specifics/ or its subdir
$SPECIFICS_DATA_DIR = "./Specifics/" . $settings['wikiName'];

require 'autoload.php';

new MediaWiki2DokuWiki_Environment($settings);
