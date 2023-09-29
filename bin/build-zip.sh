#!/bin/bash

npm ci
npm run build

rm ./elasticpress.zip

git archive --output=elasticpress.zip HEAD
zip -ur elasticpress.zip dist vendor-prefixed
