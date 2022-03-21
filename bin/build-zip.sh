#!/bin/bash

npm ci
npm run build

rm -r ./payload

TMP_DIR="./payload/elasticpress"
mkdir -p $TMP_DIR
rsync -rc --exclude-from=".distignore" --exclude="payload" . "$TMP_DIR/" && cd $TMP_DIR/.. && zip -r "./elasticpress.zip" .
