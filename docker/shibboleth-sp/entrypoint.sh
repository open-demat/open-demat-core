#!/bin/sh
set -eu

mkdir -p /run/apache2

if ! shibd -t; then
  echo "Shibboleth configuration is invalid. Check docker/shibboleth-sp/idp-metadata.xml."
  exit 1
fi

shibd -f &
apachectl -D FOREGROUND
