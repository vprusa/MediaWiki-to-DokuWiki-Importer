#!/bin/sh

echo "\$1: $1"
# no need to read it from idnex.php anymore...
SETTINGS_FILE=settings.${1}.php

echo "SETTINGS_FILE: ${SETTINGS_FILE}"

DOKUWIKI_HOME=`cat ${SETTINGS_FILE} | grep 'dokuwiki_dir' | sed -e "s/.*dokuwiki_dir.*'\(.*\)';/\1/";`
echo "DOKUWIKI_HOME: ${DOKUWIKI_HOME}"

php ./index.php -s $1

DOKUWIKI_APACHE_DIRS="${DOKUWIKI_HOME}/data"
echo "DOKUWIKI_APACHE_DIRS: ${DOKUWIKI_APACHE_DIRS}"

chown -R apache:apache ${DOKUWIKI_APACHE_DIRS}
