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
cd $TMP_DIR/ && composer install --no-dev && composer dump-autoload -o && rm composer.*
cd $BASE_DIR
mkdir -p $TMP_DIR/packages/sdk
cp -r packages/sdk/src packages/sdk/composer.json packages/sdk/composer.lock $TMP_DIR/packages/sdk
cd $TMP_DIR/packages/sdk && composer install --no-dev && composer dump-autoload -o && rm composer.*

if [ $? -ne 0 ]; then
	echo "Error copying files"
	exit 1
fi

cd $TMP_DIR/.. && zip -rX woocommerce-mercadopago.zip woocommerce-mercadopago -x "**/.DS_Store" -x "*/.git/*"
mv $TMP_DIR/../woocommerce-mercadopago.zip $BASE_DIR && rm -rf $TMP_DIR

echo "Package created successfully"
