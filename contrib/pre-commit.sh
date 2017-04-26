#!/usr/bin/env bash

# Based on code from http://tech.zumba.com/2014/04/14/control-code-quality/
 
PROJECT=$(php -r "echo dirname(dirname(dirname(realpath('$0'))));")
STAGED_FILES_CMD=$(git diff --cached --name-only --diff-filter=ACMR HEAD | grep \\.php)
 
# Determine if a file list is passed
if [ "$#" -eq 1 ]
then
    oIFS=$IFS
    IFS='
    '
    SFILES="$1"
    IFS=$oIFS
fi
SFILES=${SFILES:-$STAGED_FILES_CMD}
 
echo "Checking PHP Lint..."
for FILE in $SFILES
do
    php -l -d display_errors=0 $PROJECT/$FILE
    if [ $? != 0 ]
    then
        echo "Fix the error before commit."
        exit 1
    fi
    FILES="$FILES $PROJECT/$FILE"
done
 
if [ "$FILES" != "" ]
then
    echo "Running Code Sniffer..."

    command -v uuidgen >/dev/null 2>&1 || { echo >&2 "I require foo but it's not installed.  Aborting."; exit 1; }
    TMP_DIR=/tmp/$(uuidgen)
	echo "Creating $TMP_DIR"
    mkdir -p $TMP_DIR
    for FILE in $SFILES
    do
        mkdir -p $TMP_DIR/$(dirname $FILE)
        git show :$FILE > $TMP_DIR/$FILE
    done
    ./vendor/bin/phpcs --standard=PSR2 --encoding=utf-8 -n -p $TMP_DIR
    PHPCS_ERROR=$?
    rm -rf $TMP_DIR
    if [ $PHPCS_ERROR != 0 ]
    then
        echo "Fix the error before commit."
        exit 1
    fi
fi
 
exit $?