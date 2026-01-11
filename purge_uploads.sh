#!/bin/bash

# The directory to clean
TARGET_DIR="/var/www/html"

# Check if the directory exists
if [ -d "$TARGET_DIR" ]; then
    # Remove all files and folders inside the target directory
    # The :? syntax prevents running on root if the variable is unset
    rm -rf "${TARGET_DIR:?}"/public/uploads/*

    # Remove messages json file
    rm "${TARGET_DIR:?}"/data/messages.json

    # Remove chat messages json file
    rm "${TARGET_DIR:?}"/data/chat.json

    # Set ownership to www-data user and group
    chown www-data:www-data "$TARGET_DIR"

    # Set permissions to 0755 (rwxr-xr-x)
    chmod 0755 "$TARGET_DIR"/public/uploads
fi

echo $(date) ": Ran purge_uploads.sh"