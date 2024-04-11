#!/usr/bin/env bash

SUBMODULE_DIRECTORY="packages/sdk"

if [[ $# -ne 1 ]]; then
    echo "Usage: $0 <tag_name>"
    exit 1
fi

TAG_NAME="$1"

if [[ ! -d "$SUBMODULE_DIRECTORY" ]]; then
    echo "Error: Submodule directory '$SUBMODULE_DIRECTORY' not found."
    exit 1
fi

if [[ ! -e "$SUBMODULE_DIRECTORY/.git" ]]; then
    echo "Error: '$SUBMODULE_DIRECTORY' is not a Git submodule."
    exit 1
fi

git submodule deinit -f "$SUBMODULE_DIRECTORY"

git submodule sync --recursive -- "$SUBMODULE_DIRECTORY"
git submodule update --init --recursive --reference "$TAG_NAME" -- "$SUBMODULE_DIRECTORY" || true
git -C "$SUBMODULE_DIRECTORY" checkout "$TAG_NAME"
