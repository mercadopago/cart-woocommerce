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
    
    $sdk = new Sdk('accessToken', 'platformId', 'productId', 'integratorId', 'publicKey');

```

## Criando um pagamento

```php
<?php
    require('vendor/autoload.php');
    
    $sdk = new Sdk('accessToken', 'platformId', 'productId', 'integratorId', 'publicKey');
    
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
## Obtendo pagamento

```php
<?php
    require('vendor/autoload.php');
    
    $sdk = new Sdk('accessToken', 'platformId', 'productId', 'integratorId', 'publicKey');
    
    $payment = $sdk->getPaymentInstance();
    
    $payment->read(array("id" => 'payment_id'));
```

## Criando uma preferência

```php
<?php
    require('vendor/autoload.php');
    
    $sdk = new Sdk('accessToken', 'platformId', 'productId', 'integratorId', 'publicKey');
    
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
    
    $sdk = new Sdk('accessToken', 'platformId', 'productId', 'integratorId', 'publicKey');
    
    $notification = $sdk->getNotificationInstance();
    
    $notification->read(array("id" => "P-1316643861"));

```

## Registrando um evento no Datadog

```php
<?php
    require('vendor/autoload.php');
    
    $sdk = new Sdk('accessToken', 'platformId', 'productId', 'integratorId', 'publicKey');
    
    $datadogEvent = $sdk->getDatadogEventInstance();
    
    $details = [
        "payment_id" => "123456"
    ];
    
    $datadogEvent->value = "success";
    $datadogEvent->message = "mensagem vinda do teste de integração da SDK de PHP";
    $datadogEvent->plugin_version = "1.0.0";
    $datadogEvent->platform->name = "core";
    $datadogEvent->platform->version = "1.2.0";
    $datadogEvent->platform->uri = "/integration_test";
    $datadogEvent->platform->url = "https://...";
    $datadogEvent->details = $details;
       
    $datadogEvent->register(array("team" => "core", "event_type"=> "unit_test"));
    
```

## Registrando um log de erro

```php
<?php
    require('vendor/autoload.php');
    
    $sdk = new Sdk('accessToken', 'platformId', 'productId', 'integratorId', 'publicKey');
    
    $registerErrorLog = $sdk->getRegisterErrorLogInstance();
    
    $registerErrorLog->message = 'Sample error message';
    $registerErrorLog->stacktrace = 'monitoring_regiter_log.php';
    $registerErrorLog->location = 'registerErrorLog';
    $registerErrorLog->platform_version = phpversion();
    $registerErrorLog->module_version = "1.0.0";
    $registerErrorLog->user_agent = 'PHP SDK';
    $registerErrorLog->flow = 'sample-php-sdk';
    $registerErrorLog->runtime_version = phpversion();
    $registerErrorLog->os_version = "10";
    $registerErrorLog->browser_version = "Chrome";
    $registerErrorLog->uri = 'http://localhost';
    $registerErrorLog->url = 'http://localhost';
    $registerErrorLog->details = [
        'payment_id' => '123456789',
    ];
    
    $registerErrorLog->save();
    
```

## Obtendo métodos de pagamento

```php
<?php
    require('vendor/autoload.php');
    
    $sdk = new Sdk('accessToken', 'platformId', 'productId', 'integratorId', 'publicKey');
    
    $paymentMethods = $sdk->getPaymentMethodsInstance();
    
    $paymentMethods->getPaymentMethodsByGroupBy('id');
    
```

## Obtendo métodos de pagamento agrupados

Exemplo de requisição agrupando os meios de pagamento pelo campo id:

```php
<?php
    require('vendor/autoload.php');
    
    $sdk = new Sdk('accessToken', 'platformId', 'productId', 'integratorId', 'publicKey');
    
    $paymentMethods = $sdk->getPaymentMethodsInstance();
    
    $paymentMethods->getPaymentMethodsByGroupBy('id');
    
```

## Obtendo todas as Merchant Orders de acordo com o access token

```php
<?php
    use MercadoPago\PP\Sdk\Sdk;

    require_once(__DIR__ . '/vendor/autoload.php');

    function debug($value){
        echo "<pre>";
        print_r($value);
        echo "</pre>";
    }

    $sdk = new Sdk( 'accessToken', 'platformId', 'productId', 'integratorId', 'publicKey' );

    $merchantOrder = $sdk->getMerchantOrderInstance();

    debug(json_encode($merchantOrder->getMerchantOrders()));
    
```

## Criando um novo id de integração para funil

```php
<?php

    use MercadoPago\PP\Sdk\Sdk;
    
    require_once(__DIR__ . '/vendor/autoload.php');
    
    function debug($value){
        echo "<pre>";
        print_r($value);
        echo "</pre>";
    }
    
    $sdk = new Sdk( 'accessToken', 'platformId', 'productId', 'integratorId', 'publicKey' );
    
    $createSellerFunnelBase = $sdk->getCreateSellerFunnelBaseInstance();
    
    $createSellerFunnelBase->platform_id = "123";
    $createSellerFunnelBase->shop_url = "http://localhost";
    $createSellerFunnelBase->platform_version = "1.0.0";
    $createSellerFunnelBase->plugin_version = "1.0.0";
    $createSellerFunnelBase->site_id = "MLB";
    
    $response = $createSellerFunnelBase->save();
    
    $response->id
    $response->cpp_token
    
```

## Atualizando um id de integração para funil

```php
<?php

    use MercadoPago\PP\Sdk\Sdk;

    require_once(__DIR__ . '/vendor/autoload.php');

    function debug($value){
        echo "<pre>";
        print_r($value);
        echo "</pre>";
    }

    $sdk = new Sdk( 'accessToken', 'platformId', 'productId', 'integratorId', 'publicKey' );

    $updateSellerFunnelBase = $sdk->getUpdateSellerFunnelBaseInstance();

    $updateSellerFunnelBase->id = "id";
    $updateSellerFunnelBase->cpp_token = "token";
    $updateSellerFunnelBase->is_added_production_credential = true;
    $updateSellerFunnelBase->is_added_test_credential = true;
    $updateSellerFunnelBase->product_id = "4das56";
    $updateSellerFunnelBase->cust_id = "123";
    $updateSellerFunnelBase->application_id = "123";
    $updateSellerFunnelBase->plugin_mode = "prod";
    $updateSellerFunnelBase->is_deleted = false;
    $updateSellerFunnelBase->accepted_payments = ["bolbradesco", "pix"];
    $updateSellerFunnelBase->is_disabled = false;

    debug(json_encode($updateSellerFunnelBase->update()));
```

## Executando os testes de Integração

Os testes de integração se encontram em tests/integration, para executa-los é necessário efetuar uma copia do arquivo
.env.sample
que está na raiz do projeto e criar um .env também na raiz do projeto, feito isso, você deve popular os valores dentro
do .env

Documentação dos testes
integrados: https://mercadolibre.atlassian.net/wiki/spaces/PLU/pages/2280065838/Testes+Integrados+pp-php-sdk