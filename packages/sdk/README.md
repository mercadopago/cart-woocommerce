# Introdução

O objetivo desta SDK é proporcionar agilidade no processo de desenvolvimento e comunicação com as APIs de pagamento, preferências e notificações.

## Instalação

Instale a biblioteca utilizando o seguinte comando:

`composer require mp-plugins/php-sdk`

Com uma versão específica:

`composer require mp-plugins/php-sdk 1.4.0`

## Consultando versões da SDK

Para consultar as versões disponíveis basta acesssar o seguinte [link](https://github.com/mercadopago/pp-php-sdk/tags).

## Configuração

Para incluir a biblioteca em seu projeto, basta fazer o seguinte:

```php
<?php
require('vendor/autoload.php');

$sdk = new Sdk( 'accessToken', 'platformId', 'productId', 'integratorId' );

```

## Criando um pagamento

```php
<?php
require('vendor/autoload.php');

$sdk = new Sdk( 'accessToken', 'platformId', 'productId', 'integratorId' );

$payment = $sdk->getPaymentInstance();

$payment->token = "034215d05985b328683ec816607b2a5d";
$payment->transaction_amount = 230;
$payment->description = "Ergonomic Silk Shirt";
$payment->installments = 1;
$payment->payment_method_id = "master";
$payment->payer->email = "test_user_98934401@testuser.com";
.
.
.

$payment->save();

```

## Criando uma preferência

```php
<?php
require('vendor/autoload.php');

$sdk = new Sdk( 'accessToken', 'platformId', 'productId', 'integratorId' );

$preference = $sdk->getPreferenceInstance();

$items = ["items" =>  
            [
                "title" => "Dummy Title",
                "description" => "Dummy description",
                "picture_url" => "http://www.myapp.com/myimage.jpg",
                "category_id" => "car_electronics",
                "quantity" => 1,
                "currency_id" => "BRL",
                "unit_price" => 10.5
            ]
        ];

$preference->items = $items;
$preference->notification_url = "notification_url";
$preference->external_reference = "external_reference";

$preference->save();

```

## Consultando uma notificação

O parâmetro para consulta deve seguir o modelo abaixo:

`P-{idPayment}`

```php
<?php
require('vendor/autoload.php');

$sdk = new Sdk( 'accessToken', 'platformId', 'productId', 'integratorId' );

$notification = $sdk->getNotificationInstance();

$notification->read(array("id" => "P-1316643861"));

```

## Registrando um erro no Melidata

```php
<?php
require('vendor/autoload.php');

$sdk = new Sdk( 'accessToken', 'platformId', 'productId', 'integratorId' );

$melidataError = $sdk->getMelidataErrorInstance();

$details = [
    "payment_id" => "123456"
];

$melidataError->name = "nome do erro";
$melidataError->message = "mensagem do erro";
$melidataError->target = "error_name";
$melidataError->plugin->version = "error_name";
$melidataError->platform->name = "error_name";
$melidataError->platform->version = "error_name";
$melidataError->platform->uri = "error_name";
$melidataError->platform->location = "error_name";
$melidataError->details = $details;
   
$melidataError->registerError();
```

## Executando os testes de Integração

Os testes de integração se encontram em tests/integration, para executa-los é necessário efetuar uma copia do arquivo .env.sample
que está na raiz do projeto e criar um .env também na raiz do projeto, feito isso, você deve popular os valores dentro do .env

Documentação dos testes integrados: https://mercadolibre.atlassian.net/wiki/spaces/PLU/pages/2280065838/Testes+Integrados+pp-php-sdk