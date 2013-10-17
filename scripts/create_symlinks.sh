#!/bin/bash
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
ROUNDCUBE_DIR="/usr/share/roundcubemail/"
DIRS=(
    "plugins/calendar"
    "plugins/libcalendaring"
    "plugins/tasklist"
    "plugins/zentyal_lib"
    "plugins/zentyal_oc_contacts"
    "plugins/zentyal_oc_login"
)

for dir in "${DIRS[@]}"
do
    rm -rf $ROUNDCUBE_DIR$dir
    ln -s $SCRIPT_DIR/../$dir $ROUNDCUBE_DIR$dir
    echo "Created the symlink to: "$ROUNDCUBE_DIR$dir
done
