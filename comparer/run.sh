#!/bin/bash

source .virtenv/bin/activate

PROPERTIES_FILE_NAME_USED=""

if [ ! -z ${PROPERTIES_FILE_NAME} ]; then
  PROPERTIES_FILE_NAME_USED=${PROPERTIES_FILE_NAME}
fi

if [[ $1 = *".properties"* ]] ; then
  PROPERTIES_FILE_NAME_USED=$1
elif [[ $1 = *"test"* ]] ; then
  PROPERTIES_FILE_NAME_USED="properties-test.properties"
fi

if [ -z ${PROPERTIES_FILE_NAME_USED} ]; then
PROPERTIES_FILE_NAME_USED="properties.properties"
fi

echo "PROPERTIES_FILE_NAME_USED: ${PROPERTIES_FILE_NAME_USED}"
if [ ! -z ${PROPERTIES_FILE_NAME_USED} ]; then
  DOWNLOAD_DIR_2=`cat ./conf/$PROPERTIES_FILE_NAME_USED | grep "FIREFOX_DOWNLOAD_DIR=" | grep -v '#'`
  export ${DOWNLOAD_DIR_2}
  #source ./conf/${PROPERTIES_FILE_NAME_USED}
fi

echo "Used DOWNLOAD_DIR: ${DOWNLOAD_DIR_2}"

NOW=`date +%Y-%m-%d_%H-%M-%S`

PYTHON_CMD=python3.7

$PYTHON_CMD -c "from run import run
r = run()
r.magic()
r.ses.close_web_driver()
" ${PROPERTIES_FILE_NAME_USED}

NEW_NOW=`date +%Y-%m-%d_%H-%M-%S`
echo "Donloaded into: ${FIREFOX_DOWNLOAD_DIR}"
echo "Start: ${NOW} End: ${NEW_NOW}"
