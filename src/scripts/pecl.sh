#!/usr/bin/env bash

extension=$1
ref=$2
url=$3
token=$4

SCRIPT_DIRECTORY="$(cd "$(dirname "$0")" && pwd)"

source "$SCRIPT_DIRECTORY/../../.env"
source "$SCRIPT_DIRECTORY/common.sh"

for tool in curl unzip; do
  if ! command -v "$tool" &> /dev/null; then
    echo "$tool not found"
    exit 1
  fi
done

zip_file="/tmp/$extension-$ref.zip"
dest_dir="${BUILDS_DIRECTORY:?}/pecl/releases"
fetch_artifact "$zip_file" "$url" "$token"
if [[ -e "$zip_file" && "$(file --mime-type -b "$zip_file")" = "application/zip" ]]; then
  mkdir -p "$dest_dir"
  if ! unzip -o "$zip_file" -d "$dest_dir"; then
    echo "Failed to unzip the build"
    exit 1
  fi
else
  echo "Failed to fetch the build"
  exit 1
fi
