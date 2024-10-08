#!/usr/bin/env bash

cd $(dirname $0)/..

for DIR in $(find assets build i18n src templates -type d); do
	FILE="$DIR/index.php";
	if [ ! -f $FILE ]; then
		cat >> $FILE <<EOL
<?php

/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 *
 * @package MercadoPago
 */

exit;
EOL
		git add $FILE;
	fi
done
