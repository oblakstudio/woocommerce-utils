#!/bin/bash

NEXT_VERSION=$1
CURRENT_VERSION=$(grep version < composer.json | head -1 | awk -F= "{ print $2 }" | sed 's/[version:,\",]//g' | tr -d '[:blank:]')

sed -i "s/\"version\": \"$CURRENT_VERSION\"/\"version\": \"$NEXT_VERSION\"/g" composer.json

zip -qr /tmp/release.zip README.md CHANGELOG.md LICENSE composer.json composer.lock src
