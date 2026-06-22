# Super Token E2E — instruções para o agente

Testes E2E do Super Token na **visão do comprador** (app MP logado/enrolled → loja no Chrome
→ cartões salvos no checkout). **Antes de mexer, leia** [docs/how-it-works.md](docs/how-it-works.md)
e [docs/limitations.md](docs/limitations.md).

## Regras duras (não erre)

- **App MP tem que ser GENUÍNO (Play Store)**, nunca sideload. O build do Meli Store declara
  asset_statements só `share_location` → a detecção (`getInstalledRelatedApps`/PRAPI) falha.
  Conferir: `adb shell pm dump com.mercadopago.wallet | grep installerPackageName` = `com.android.vending`.
- **1 device (AVD) por país** (`mp-st-<site>`), configurado uma vez via snapshot "golden".
- **NÃO tente automatizar** login Google, login do MP nem o enrollment — são anti-bot por design
  (Fase A, manual). Só a Fase B (rodar o teste) é automatizada.
- **Imagem do AVD** deve ser `google_apis_playstore` (Play-certified) — a `google_apis` quebra o PRAPI.
- O **comprador precisa estar enrolled** (cartão salvo elegível) e o **email do checkout = conta
  logada no app**, senão `is_not_simplified_auth`.

## Estrutura dos testes — Playwright

- **Playwright over CDP**: `fixtures.js` conecta no Chrome do emulador (`connectOverCDP`) e expõe
  `page` + `faults`. Config em `playwright.config.js` (baseURL = SHOP_URL ou o túnel do make store).
- **`suites/<grupo>.js`**: os cenários de regressão (15 testes em 5 grupos) em Given/When/Then
  (en-US), parametrizados por país. **`tests/<país>/<grupo>.spec.js`** é a entrada fina que chama a
  suite (ex.: `eligibilityScenarios("mlb")`). Cenários deferidos/manuais: `docs/scenarios.md`.
- **`flows/super-token.js`**: ações + asserções reutilizáveis. Fault injection vem do fixture
  `faults` (`fixtures.js`). **`selectors.js`**: seletores. **`data/country.js`**: comprador por país.
- **`helpers/device.js`**: adb (biometria). Matriz dos cenários em [docs/scenarios.md](docs/scenarios.md).

## Ao escrever/depurar cenários

- **Distinção por país**: pasta `tests/<país>/` + `skipIfNotSite` (lê o site da loja no ar). Não passe `SITE` ao Playwright.
- **Cenário que exige comprador apto**: `test.skip(!buyer.email, PENDING_BUYER)` — configurar um `buyerEmail` no país pressupõe comprador apto. Diferença por país: mova o cenário divergente para o arquivo do país.
- **Checkout é Blocks (React)**: a seção de pagamento só aparece após endereço/frete; o `startCustomCheckout` cuida do fluxo.
- **Email por digitação real** (`pressSequentially`), não `fill` — é o que aciona o gatilho do Super Token.
- **Asserção por ELEMENTO/hidden input** (`SELECTORS`), nunca por `body.innerText` (o ST renderiza em iframe).
- **Fault injection** via fixture `faults` (`inject`/`failUrl`/`respondUrl`/`throttle3G`) — desfeito ao fim de cada teste.
- **Não modificar a lib do Super Token** (`assets/js/checkouts/super-token/`) — esta seção só testa.

## Comandos

- `make setup SITE=mlb` — device + loja (1x por país) · `make store SITE=mlb` — só loja/túnel
- `make list SITE=mlb` — lista numerada dos cenários (offline, sem device)
- `make test SITE=mlb` — os cenários do país · `N=<n>` foca um teste · `GROUP=<grupo>`/`GREP=<texto>` focam
- `make status` / `make clean`

## Convenções

- `config/countries.json` é **gitignored** (emails de test users). Template: `countries.example.json`.
- Senha do comprador nunca em arquivo — digitada manualmente na Fase A.
- A exploração antiga via **LTP `@webview`** foi **descartada** (seletor do Developer Mode
  ausente no app de release) — ver `docs/limitations.md`. Esta seção (CDP/golden device) a substitui.
