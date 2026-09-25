#!/bin/bash
set -e
mkdir -p scripts/ssl
if [ ! -f scripts/ssl/localhost.pem ] || [ ! -f scripts/ssl/localhost-key.pem ]; then
  mkcert -key-file scripts/ssl/localhost-key.pem -cert-file scripts/ssl/localhost.pem localhost 127.0.0.1 ::1
  echo "Development SSL certificates generated in ./scripts/ssl/"
else
  echo "SSL certificates already exist."
fi 