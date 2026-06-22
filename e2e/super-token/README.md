# Super Token — Testes E2E (visão do comprador)

Testes E2E do **Super Token** ("Pague com um toque" / cartões salvos) na visão do
comprador: um device Android com o app **Mercado Pago genuíno** logado e *enrolled*,
abrindo a loja num navegador real e verificando se o checkout carrega os cartões salvos.

> **Card:** [PSW-3906](https://mercadolibre.atlassian.net/browse/PSW-3906) · Épico: PSW-3760

---

## TL;DR — como executar

```bash
# 1. Configurar device (golden) + biometria (PIN 1234) + loja/túnel do país — UMA VEZ (tem passos manuais)
make setup SITE=mlb

# 2. Rodar os testes E2E no device daquele país (repetível)
make test SITE=mlb
```

Atalhos extras: `make store SITE=mlb` (só re-subir a loja/túnel quando o túnel cair),
`make setup-device SITE=mlb` (só o device), `make status`, `make clean`. Veja `make help`.

Detalhes de cada passo abaixo. **Leia [docs/limitations.md](docs/limitations.md) antes** —
há partes que, por design anti-fraude do Google/MP, **não dá para automatizar 100%**.

---

## Por que o Super Token é diferente de testar

O Super Token não preenche dados de cartão: ele **detecta a sessão do app MP no device**
e renderiza os **cartões salvos** do comprador no checkout. Isso exige um ambiente fiel:

- App **Mercado Pago genuíno** (instalado pela **Play Store** — o build sideloaded NÃO funciona, ver [docs/how-it-works.md](docs/how-it-works.md)).
- Comprador **logado** no app **e** *enrolled* para fast-payment (cartão salvo elegível).
- **Chrome atualizado** (a detecção usa a W3C Payment Request API).
- **Imagem Android Play-certified** (`google_apis_playstore`) — Play Services completo.

A cadeia técnica completa (PRAPI → `hasEnrolledInstrument` → `authLevel`) está em
[docs/how-it-works.md](docs/how-it-works.md).

---

## Modelo: 2 fases + 1 device por país

Por design anti-bot, o login Google, o login do MP e o *enrollment* do comprador **não são
automatizáveis de forma robusta**. A solução é separar em duas fases e usar um **device
configurado por país** (golden device), reutilizado entre execuções:

| Fase | O que faz | Frequência | Automação |
|------|-----------|------------|-----------|
| **A — Setup** | AVD Play-certified + login Google + MP genuíno + Chrome + login comprador + enrollment → **snapshot** | 1x por país (refresh periódico) | semi-manual (guiado) |
| **B — Execução** | Boota do snapshot → loja+túnel → dirige o Chrome → valida cartões salvos | toda run | **100% automatizado** |

> **1 device por país:** cada país tem um AVD próprio (`mp-st-<site>`) com seu comprador
> *enrolled*. Configurado uma vez, reutilizado em todas as execuções daquele país.

---

## Pré-requisitos (uma vez por máquina)

> **⚠️ Node 22 (não 20):** o restante do repo roda em Node 20, mas esta seção exige **Node 22** —
> os drivers CDP do Playwright usam o `WebSocket` nativo do Node 22. Os scripts de execução fazem
> `nvm use 22`; sem ele instalado, eles avisam e seguem com o Node ativo (pode quebrar de forma
> difícil de diagnosticar). Instale com `nvm install 22` antes de rodar.

| Ferramenta | Como |
|---|---|
| Node 22 | `nvm install 22` (os drivers CDP usam `WebSocket` nativo do Node 22) |
| Dependências Node | `npm install` em `e2e/` (instala `@playwright/test` e `dotenv`, compartilhados com a suíte E2E principal) |
| Android SDK + `ANDROID_HOME` | já no `~/.zshrc` |
| Imagem Play-certified | `sdkmanager "system-images;android-36;google_apis_playstore;arm64-v8a"` |
| cloudflared | `brew install cloudflared` (túnel da loja local) |
| Conta Google de teste | para logar no emulador e usar a Play Store |
| Docker + ambiente local | `docker-flexible-environment/` (loja por país) |

---

## Estrutura

```
e2e/super-token/
├── README.md                 # este arquivo
├── CLAUDE.md                  # instruções curtas p/ agente (regras duras + ponteiros)
├── Makefile                  # atalhos: make setup / test / store / status
├── playwright.config.js       # baseURL = SHOP_URL ou o túnel do make store; 1 worker
├── docs/
│   ├── how-it-works.md        # mecanismo do SDK (PRAPI, hasEnrolledInstrument, authLevel)
│   ├── limitations.md         # o que NÃO dá pra automatizar e por quê + golden device
│   └── scenarios.md           # matriz de cenários: grupo → CSV → pré-condição
├── config/
│   └── countries.example.json # template por país (copie p/ countries.json — gitignored)
├── setup/
│   ├── setup-device.sh        # Fase A: cria/boota AVD, guia login Google+MP, salva snapshot
│   └── setup-store.sh         # Fase A: sobe loja local + túnel + seller no allow-list
├── run/
│   └── run-e2e.sh             # Fase B: boota snapshot + garante loja + roda o Playwright
├── fixtures.js                # page (connectOverCDP) + faults (inject/route/throttle, limpos por teste)
├── selectors.js               # seletores nomeados do checkout e do Super Token (lib v2.1)
├── data/country.js            # comprador por país (de countries.json): email/productId
├── helpers/device.js          # adb: biometria (approve/cancel)
├── flows/
│   └── super-token.js         # ações + asserções reutilizáveis (fault injection vem do fixture)
├── suites/<grupo>.js          # cenários Given/When/Then de cada grupo (parametrizado por país)
└── tests/<país>/<grupo>.spec.js   # entrada fina: chama a suite do grupo p/ aquele país
```

## Cenários

Os cenários de regressão da planilha (23) estão automatizados em **Playwright** (connectOverCDP no
Chrome do emulador) como **15 testes** em 5 grupos × 3 países (MLB, MLA, MLM); os demais ficam
deferidos/manuais com justificativa. A matriz completa (grupo → linha do CSV → pré-condição) e a
seção "Não automatizados em E2E" estão em [docs/scenarios.md](docs/scenarios.md).

```bash
make list  SITE=mlb                  # lista numerada dos cenários (offline, sem device)
make test  SITE=mlb                  # todos os grupos no device MLB
make test  SITE=mlb N=1              # só o teste de número 1 (da `make list`)
make test  SITE=mlb GROUP=reset      # só um grupo
make test  SITE=mlb GREP="3G"        # filtra por trecho do título
```

A distinção de país vem da pasta `tests/<país>/` + `skipIfNotSite` (lê o site da loja no ar) —
não é preciso passar `SITE` ao Playwright. Cenários que exigem **comprador apto ao Super Token**
fazem `test.skip` quando o país não tem `buyerEmail` configurado em `countries.json` (configurar
um buyer pressupõe que ele é apto).

> **Nota histórica:** a exploração inicial usou **LTP `@webview`** (abrir a loja dentro da
> WebView do app via *Developer Mode*). Esse caminho foi **descartado** — o seletor do
> Developer Mode não existe no app MP de release — em favor desta abordagem (CDP/Chrome +
> golden device), mais simples e fiel à visão do comprador. Ver
> [docs/limitations.md](docs/limitations.md) (seção *Alternativas consideradas e descartadas*).

---

## Configuração por país

Copie o template e preencha (o arquivo real é gitignored — contém emails de test users):

```bash
cp config/countries.example.json config/countries.json
# editar config/countries.json: buyerEmail, avdName, sellerAppId, productId por país
```

A **senha** do comprador NÃO fica em arquivo — é digitada manualmente no app durante a Fase A.

---

## Fase A — Setup do device (uma vez por país)

```bash
make setup-device SITE=mlb      # (ou `make setup SITE=mlb` para device + loja juntos)
```

O script automatiza o que dá e **pausa nos pontos manuais** (anti-bot):
1. Cria/recria o AVD `mp-st-mlb` na imagem Play-certified e boota. *(auto)*
2. **[manual]** Você loga uma conta Google no emulador.
3. **[manual]** Instala o **Mercado Pago** pela Play Store e atualiza o **Chrome**.
4. **[manual]** Loga o comprador no MP (email de `countries.json`) e garante **cartão salvo**.
5. Valida que o device está pronto (app genuíno via `vending`, Chrome novo). *(auto)*
6. Salva um **snapshot** do emulador (o "golden device"). *(auto)*

Ver [docs/limitations.md](docs/limitations.md) para por que os passos 2–4 são manuais.

### Autorização (B8/B16) — biometria no app (requer `make biometrics`)

Os cenários de autorização finalizam o pedido via **biometria pedida pelo app Mercado Pago** (o app
vem para frente no momento do pagamento). O teste autentica **automaticamente** simulando o toque no
sensor (`adb emu finger touch`) — mas isso só funciona se o golden tiver a **digital cadastrada** e
um **PIN de bloqueio**.

> ⚠️ **O PIN do device TEM que ser `1234`.** O boot do `run-e2e.sh` e o desbloqueio durante o teste
> digitam esse PIN; com outro valor a tela de bloqueio não abre e a autorização falha "celular
> bloqueado". (Dá pra sobrescrever via env `ST_LOCK_PIN`, mas o padrão de todo o setup é `1234`.)

**`make biometrics` é obrigatório para o grupo `authorization`** — e desde então **já está incluso no
`make setup`**. Rodar de novo também é a cura quando a autorização começa a travar/bloquear a tela
(sinal de que o golden perdeu o enrollment):

```bash
make biometrics SITE=mlb   # define PIN 1234 + cadastra a digital (guiado) e re-salva o golden
```

> A digital **tem que** ser cadastrada pelos toques que o `make biometrics` envia (`adb emu finger
> touch 1`) — **não** pelo botão *Touch Sensor* dos Extended Controls, que usa outra numeração e a
> digital cadastrada por ali nunca autentica nos testes. O `run-e2e.sh` desbloqueia a tela no boot.

---

## Fase A — Setup da loja (por país)

```bash
make store SITE=mlb
```
1. `make up SITE=mlb` (loja local) e `make tunnel` (URL HTTPS pública via cloudflared).
2. Habilita o seller no allow-list do Super Token (`add-sellers-to-super-token`). **A application
   precisa estar autorizada a usar o Super Token** — ver [Ativar o Super Token por Application](https://mercadolibre.atlassian.net/wiki/spaces/PLU/pages/3495690302/Ativa+o+Super+Token+por+Application).
3. Aponta `siteurl`/`home`/`_mp_custom_domain` para a URL do túnel.

---

## Fase B — Execução (repetível)

```bash
make test SITE=mlb
```
1. Boota o device `mp-st-mlb` a partir do snapshot (já logado/apto ao ST).
2. Garante loja + túnel no ar (reusa ou sobe).
3. Expõe o DevTools do Chrome do emulador em `:9333` (adb forward) e roda
   `npx playwright test tests/mlb` (connectOverCDP). O Playwright reporta pass/fail/skip.

Use `N=<número>` (da `make list SITE=mlb`) para rodar um único teste, `GROUP=<grupo>` para focar
um grupo ou `GREP=<texto>` para filtrar por título.
A finalização do pedido (grupo authorization) usa biometria via `adb emu finger touch 1`.

---

## Limitações (resumo)

- **Não é 100% automatizável ponta a ponta.** Login Google, login MP e enrollment do
  comprador são anti-bot por design → ficam na Fase A (manual, 1x por país).
- O **golden device/snapshot** torna a Fase B 100% automatizada, mas exige **refresh
  periódico** (tokens expiram, Chrome/app dão drift).
- **Test users** podem não ser *enrollable* para fast-payment — confirme com o time dono
  do Super Token se há comprador de teste provisionado como *enrolled*.

Detalhes completos em [docs/limitations.md](docs/limitations.md).
