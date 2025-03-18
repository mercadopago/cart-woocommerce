#!/usr/bin/env bash

BIN_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &>/dev/null && pwd)

BASE_DIR=$BIN_DIR/..
TMP_DIR="/tmp/woocommerce-mercadopago"

shopt -s extglob

if [ -d "$TMP_DIR" ]; then
	rm -rf $TMP_DIR/*
fi

if [ ! -d "$TMP_DIR" ]; then
	mkdir $TMP_DIR
fi

cd $BASE_DIR
cp -r assets build i18n src templates index.php readme.txt woocommerce-mercadopago.php composer.json composer.lock $TMP_DIR

cd $TMP_DIR
composer install --no-dev
composer dump-autoload -o
rm -rf composer.* vendor/mp-plugins/php-sdk/{examples,tests}

# Find and delete non-minified assets
find ./assets -type f -name "*.css" ! -name "*.min.css" -delete
find ./assets -type f -name "*.js" ! -name "*.min.js" -delete

if [ $? -ne 0 ]; then
	echo "Error copying files"
	exit 1
fi

cd $TMP_DIR/.. && zip -rX woocommerce-mercadopago.zip woocommerce-mercadopago -x "**/.DS_Store" -x "*/.git/*"
mv $TMP_DIR/../woocommerce-mercadopago.zip $BASE_DIR
rm -rf $TMP_DIR

echo "Package created successfully"
