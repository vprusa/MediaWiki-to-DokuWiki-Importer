#!/bin/bash

# this script:
# - expects that yum is upgraded & updated
#    -> python is installed
# - selenium and all its dependencies
# first argument is USER under which selenium will be executed and desktop env is running

USER=""
PREFIX="-rem"
var=$1
if [ ! -z ${var+x} ]; then USER=${var}; echo "USER is set to '${USER}'"; else USER="root"; fi

# Install virtualenv, libcurl-devel, gcc, wget, unzipx
yum install python python-virtualenv virtualenv wget unzip libcurl-devel unzip gcc openssl-devel bzip2-devel -y
# because of installation of python version 3.6
#yum install https://centos7.iuscommunity.org/ius-release.rpm -y
#yum install python37u python36u-pip python36u-devel -y

# lets download and unzip chromedrvier,
# this is version dependent and i am using it right now but it may not work in the future
wget https://chromedriver.storage.googleapis.com/77.0.3865.40/chromedriver_linux64.zip
unzip chromedriver_linux64.zip

# Setup virtual environment
#virtualenv .virtenv
virtualenv --python=/usr/bin/python3.7 .virtenv${PREFIX}
source .virtenv${PREFIX}/bin/activate
PIP_CMD=pip3.7
# Install base requirements
$PIP_CMD install --upgrade setuptools
cat requirements.txt | sed -e '/^\s*#.*$/d' -e '/^\s*$/d' | xargs -n 1 ${PIP_CMD} install

$PIP_CMD install -U pip

export PATH=${PATH}:./
# Install Chromdriver - PATH must include "."

if ls geckodriver-v0.20.1-linux32.tar.gz 1> /dev/null 2>&1; then
echo "firefox-59.0.tar.bz2 exists"
else
wget https://github.com/mozilla/geckodriver/releases/download/v0.20.1/geckodriver-v0.20.1-linux32.tar.gz
tar -xvzf geckodriver-v0.20.1-linux32.tar.gz
fi
#cd /opt
if ls firefox-59.0.tar.bz2 1> /dev/null 2>&1; then
echo "firefox-59.0.tar.bz2 exists"
else
wget https://download-installer.cdn.mozilla.net/pub/firefox/releases/59.0/linux-x86_64/en-US/firefox-59.0.tar.bz2
tar xfj firefox-59.0.tar.bz2
fi

chown -R ${USER}:${USER} ./

echo -e "\nSetup Complete."
#
