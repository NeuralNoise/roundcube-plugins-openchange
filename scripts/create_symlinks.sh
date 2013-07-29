#!/bin/bash
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
ROUNDCUBE_DIR="/usr/share/roundcube/"
DIRS=(
    "plugins/zentyal_oc_folders"
    "skins/zentyal"
)

for dir in "${DIRS[@]}"
do
    rm -rf $ROUNDCUBE_DIR$dir
    ln -s $SCRIPT_DIR/../$dir $ROUNDCUBE_DIR$dir
    echo "Created the symlink to: "$ROUNDCUBE_DIR$dir
done
