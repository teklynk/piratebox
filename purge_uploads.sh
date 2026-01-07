#!/bin/bash

# The directory to clean
TARGET_DIR="/var/www/html"

# Check if the directory exists
if [ -d "$TARGET_DIR" ]; then
    # Remove all files and folders inside the target directory
    # The :? syntax prevents running on root if the variable is unset
    rm -rf "${TARGET_DIR:?}"/uploads/*

    # Remove messages json file
    rm "${TARGET_DIR:?}"/messages.json

    # Set ownership to www-data user and group
    chown www-data:www-data "$TARGET_DIR"

    # Set permissions to 0755 (rwxr-xr-x)
    chmod 0755 "$TARGET_DIR"/uploads
fi

echo $(date) ": Ran purge_uploads.sh"