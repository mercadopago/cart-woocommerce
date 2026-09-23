# E2E no Safari do iOS Simulator

Esta suíte valida o Custom Checkout no Safari real do iOS Simulator. Playwright fornece o runner e o relatório; `selenium-webdriver` envia comandos ao Appium/XCUITest. A mesma sessão alterna entre `WEBVIEW` para o checkout e `NATIVE_APP` para preencher os campos PCI cross-origin como controles nativos do Safari.

## Cobertura inicial

- MLB Classic: regressão de sincronização da parcela auto-selecionada, sem tocar no select.
- MLB Classic: cartão de crédito aprovado.
- MLB Classic: cartão de crédito rejeitado com erro visível no checkout.
- MLB Blocks: regressão de sincronização da parcela auto-selecionada, sem tocar no select.
- MLB Blocks: cartão de crédito aprovado.

Os testes são seriais, sem retry, e reutilizam as credenciais, o comprador, o cartão e a preparação de loja da suíte principal.

## Pré-requisitos

- macOS com Xcode e ao menos um runtime de iOS Simulator instalado.
- Docker Desktop.
- Node.js 20.19 ou superior.
- `e2e/.env` configurado para MLB conforme o [README principal](../README.md).

Os principais comandos estão centralizados no Makefile. Para consultar a lista:

```bash
cd e2e/ios
make help
```

Instale as dependências e valide a máquina:

```bash
cd e2e/ios
make install
make doctor
```

O `make install` registra `appium-xcuitest-driver@12.2.1` no `APPIUM_HOME` local do diretório `e2e`; o `make doctor` exige essa mesma versão e executa os diagnósticos do driver.

## Executar

```bash
cd e2e/ios
make test
```

O runner executa duas fases porque a configuração Classic/Blocks é global na loja:

1. Classic: parcelas + cartões aprovado e rejeitado.
2. Blocks: parcelas + cartão aprovado.

Para o gate de estabilidade com três Simulators limpos:

```bash
make stability
```

Comandos para execução e diagnóstico:

| Comando | Finalidade |
|---|---|
| `make list` | Lista os cinco cenários disponíveis sem executá-los |
| `make test-classic` | Executa parcelas e pagamentos aprovado/rejeitado no Checkout Classic |
| `make test-blocks` | Executa parcelas e pagamento aprovado no Checkout Blocks |
| `make test-installments` | Executa as regressões de sincronização das parcelas em Classic e Blocks |
| `make report` | Abre o último relatório HTML do Playwright |
| `make clean` | Remove metadados do Simulator, relatórios e evidências geradas localmente |

Os aliases `npm run doctor:ios`, `npm run test:ios:mlb` e `npm run test:ios:stability`, executados a partir de `e2e/`, continuam disponíveis.

Na regressão de release, o autor executa manualmente `bash e2e/run-all-report.sh --release` em
um Mac. O runner inclui esta suíte uma vez e propaga qualquer falha ao relatório consolidado;
essa etapa ignora qualquer `IOS_TEST_GREP` herdado para nunca aprovar uma release com zero cenários e não é disparada automaticamente pela CI.

Variáveis opcionais:

| Variável | Padrão | Uso |
|---|---|---|
| `IOS_DEVICE_NAME` | `mp-ios-e2e` | Nome do Simulator dedicado |
| `IOS_DEVICE_TYPE_ID` | iPhone 16 disponível | Device type explícito do CoreSimulator |
| `IOS_RUNTIME_ID` | runtime iOS mais recente | Runtime explícito do CoreSimulator |
| `IOS_HTTPS_PORT` | `8443` | Porta HTTPS local |
| `PORT` | `8080` | Porta HTTP do ambiente Docker restaurada no teardown |
| `APPIUM_PORT` | `4723` | Porta loopback do Appium |
| `IOS_ERASE` | `1` | Apaga o Simulator antes da execução |
| `IOS_KEEP_RUNNING` | `0` | Mantém o Simulator ligado ao terminar |
| `IOS_TEST_GREP` | vazio | Filtra cenários por título durante diagnóstico local |

## HTTPS sem túnel público

O profile `ios` do Docker Compose inicia Caddy em `127.0.0.1:8443`. O Runner copia somente a CA pública gerada pelo Caddy e a instala no keychain do Simulator com `simctl`; a chave privada permanece no volume Docker. Assim o Safari recebe um contexto HTTPS válido sem ngrok, trycloudflare ou exposição pública desse novo entrypoint. A porta HTTP preexistente do ambiente Docker mantém sua configuração normal para uso no host.

O teardown restaura a URL HTTP local do WordPress e encerra Appium, Caddy e o Simulator mesmo quando um teste falha.

## Evidências

Cada execução recebe um UUID aleatório e cada cenário grava em `e2e/ios/evidence/<execution-id>/<scenario>/`:

- `contact-filled.png`: contato preenchido no Blocks;
- `customer-filled.png`: endereço do cliente preenchido;
- `card-number-filled.png`: número do cartão de teste preenchido;
- `cardholder-filled.png`: nome do titular preenchido;
- `card-security-filled.png`: validade e CVV preenchidos;
- `document-filled.png`: documento preenchido, quando exigido pelo meio de pagamento;
- `installments-filled.png`: parcela selecionada;
- `order-summary.png`: produtos, totais e método de pagamento antes da submissão;
- `before-submit.json`: estado não sensível do checkout e das parcelas;
- `order-received.png`: recorte da confirmação final.
- `payment-rejected.png`: recorte do erro de pagamento rejeitado, sem dados do formulário.

Os artefatos têm permissão `0600` e são ignorados pelo Git. As capturas são divididas por campos e seções para permanecerem legíveis e evitar o preenchimento preto que o Safari aplica fora da região renderizada. Dados do comprador e do cartão permanecem visíveis porque são fixtures sintéticas definidas exclusivamente para a suíte; credenciais, tokens e respostas internas não são incluídos nas imagens. O relatório HTML do runner fica em `e2e/ios/playwright-report/`.

## Limite do cenário de parcelas

O teste não interage com o select nem altera DOM/eventos para criar a precondição. Se o runtime escolhido não apresentar naturalmente `select=1` com hidden vazio/stale, ele falha como ambiente incompatível. Playwright WebKit não é um substituto, pois não controla o Safari nem seu picker nativo.

## Super Token no futuro

Uma build `.app` compilada para `iphonesimulator` pode ser pré-instalada com `xcrun simctl install`. O Appium/XCUITest e o Simulator já usados aqui permitem criar um cenário separado para controlar deep links, o app e biometria simulada. O principal esforço futuro será obter uma build de Simulator testável, expor identificadores de acessibilidade estáveis e confirmar que o produto oferece um fluxo Super Token compatível com iOS; a build por si só não torna o fluxo Android/Payment Request portável.
