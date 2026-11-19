#!/bin/sh
# Builds build/satelit-moldova-data.zip from the last commit (files marked
# export-ignore in .gitattributes stay out). Usage: sh bin/build-zip.sh
set -eu
cd "$(dirname "$0")/.."
mkdir -p build
git archive --format=zip --prefix=satelit-moldova-data/ -o build/satelit-moldova-data.zip HEAD
echo "build/satelit-moldova-data.zip"
