# Cenários — Super Token E2E

Os cenários da planilha de regressão ("Cenários Super Token"), automatizados em Playwright
(connectOverCDP no Chrome do emulador), organizados em **5 grupos**. Cada grupo é uma *suite*
compartilhada (`suites/<grupo>.js`) chamada por uma entrada fina por país
(`tests/<país>/<grupo>.spec.js`). Super Token roda em **MLB, MLA e MLM**.

> **Consolidação:** os 23 cenários da planilha foram automatizados em **15 testes** (5 grupos × 3
> países). As métricas são afirmadas **junto ao comportamento funcional**, no caminho real que as
> emite — 4 cobertas em E2E: `can_use_super_token` e `error_to_build_authenticator` (eligibility),
> `super_token_reset_on_amount_change` (reset) e `error_to_authorize_payment` (authorization). Os
> cenários puramente de métrica/erro **sem caminho funcional determinístico** em E2E ficam
> deferidos/manuais — ver "Não automatizados em E2E" no fim (atende ao CA "23 cobertos *ou*
> justificativa + residual documentado").

## Estrutura

```
e2e/super-token/
├── playwright.config.js     # baseURL = SHOP_URL ou o túnel do make store; 1 worker
├── fixtures.js              # page (connectOverCDP) + faults (inject/route/throttle, limpos por teste)
├── selectors.js             # seletores nomeados do checkout e do Super Token (lib v2.1)
├── data/country.js          # comprador por país (de countries.json): email/productId
├── helpers/device.js        # adb: biometria (approve/cancel)
├── flows/
│   └── super-token.js        # ações + asserções reutilizáveis (fault injection vem do fixture)
├── suites/<grupo>.js         # cenários Given/When/Then de cada grupo (parametrizado por país)
└── tests/<país>/<grupo>.spec.js   # entrada fina: chama a suite do grupo p/ aquele país
```

## Como rodar

```bash
make test  SITE=mlb                  # todos os grupos no device MLB
make test  SITE=mlb GROUP=reset      # só um grupo
make test  SITE=mlb GREP="3G"        # filtra por trecho do título
```

Sem `SITE` no comando do Playwright: a pasta do país + `skipIfNotSite` (lê o site da loja no
ar) já fazem a distinção. `SHOP_URL` é opcional — cai no túnel do `make store`.

> **Autorização (grupo `authorization`) é AUTOMATIZADO:** a autorização acontece **dentro do app
> Mercado Pago** (ele vem para frente e pede biometria). O `helpers/device.js` espera o app vir ao
> primeiro plano e envia `adb emu finger touch <fingerId>` (aprovar) ou `KEYCODE_BACK` (cancelar),
> trazendo o Chrome de volta ao foco depois. Pré-requisito do device, **uma vez**: PIN `1234` +
> uma digital cadastrada **pelos toques do `adb emu`** (NÃO pela GUI do Extended Controls — a
> numeração não casa) e o golden re-salvo. Use `make biometrics SITE=<país>`. O `fingerId` (default
> 1) vem do `countries.json`. O `run-e2e.sh` desbloqueia a tela no boot.

## Pré-condições

| Tag | Significado | Comportamento |
|-----|-------------|---------------|
| **eligible** | Exige comprador apto ao Super Token | `test.skip` quando o país não tem `buyerEmail` configurado em `countries.json` (configurar um buyer pressupõe que ele é apto) |
| **fault** | Roda sempre via fault injection (não depende de comprador apto) | — |
| **env** | Depende de plugin/loja específicos | o teste faz `test.skip` se o pré-requisito não existir |

## Matriz (15 cenários · grupo → pré-condição)

As métricas de erro deixaram de usar fault injection (inviável — o SDK isola a instância) e são
verificadas nos **caminhos reais**, fundidas nos cenários funcionais correspondentes.

**eligibility** (3) — `suites/eligibility.js`
- Comprador apto → exibe os cartões salvos **+ métrica `can_use_super_token`** · **eligible**
- Comprador não elegível (email que não é conta MP) → cai no checkout padrão **+ métrica `error_to_build_authenticator`** · **eligible**
- Device sem o app ST → cai no checkout padrão · **fault**

**authorization** (3) — `suites/authorization.js` · todos **eligible**
- Aprova a biometria → pedido finalizado (`#authorized_pseudotoken` preenchido, `/order-received/`)
- Cancela a biometria → não finaliza, cartões permanecem **+ métrica `error_to_authorize_payment`** (USER_CANCELLED)
- Dupla tentativa (cancela → reabre o checkout → retry) → 2ª tentativa cria a order

**reset** (4) — `suites/reset.js`
- Troca de meio e volta → reuso sem nova elegibilidade · **eligible**
- Cupom aplicado → loading + `super_token_reset_on_amount_change` · **eligible** (cupom criado pelo setup)
- Sessão MP expira → fallback p/ checkout padrão · **eligible+fault**
- Erro na Order API → não finaliza · **eligible+fault**

**validation** (3) — `suites/validation.js`
- Submit sem CVV/parcelas → bloqueia sem reload · **eligible**
- Campo obrigatório vazio → app não abre · **eligible**
- Termos obrigatórios desmarcado (Classic) → bloqueia · **env** — o checkbox `#terms` só existe no Classic (no Blocks o bloco de termos é só texto). O teste **troca a loja p/ checkout Classic + termos via WP-CLI** durante a execução e restaura o Blocks no `finally` (skip se WP-CLI/loja docker ausente)

**resilience** (2) — `suites/resilience.js`
- Plugin de checkout de terceiro (**Fluid Checkout**, ativado/desativado via WP-CLI no teste) → o MP integra (multistep + radio custom no DOM) · **env** (skip se WP-CLI/plugin ausente; o setup instala o FC desativado)
- Rede 3G → Super Token inicia · **eligible**

## Não automatizados em E2E (justificativa)

Cenários da planilha que **não** viram teste E2E determinístico — registrados aqui como
deferido/manual (atende ao CA "23 cobertos *ou* justificativa + residual documentado"):

- **Erro de inicialização** (classe do ST ausente → `SUPER_TOKEN_CLASSES_NOT_EXISTS`): o init-check
  roda 1×/sessão (`mp_card_form_mounted`), as instâncias do ST ficam em closures (zerar `window.*`
  não quebra) e a métrica vai por `navigator.sendBeacon` (path `mp_super_token_init_error`). **Cobrir
  por teste de unidade (Jest)**, não por E2E.
- **Métrica de erro no submit** (`error_on_submit_super_token`): sem caminho funcional determinístico
  que a emita isoladamente em E2E (a validação client-side bloqueia antes). **Deferido** — a não
  conclusão do pedido já é afirmada no grupo `validation`.
- **Reset por troca de email** (`super_token_reset_on_email_change`): inviável como cenário isolado —
  o notice de erro do ST só aparece em erro de authorize/identidade (cancelamento da biometria) e
  selecionar um cartão não dispara request (comprovado via CDP). A exibição do notice é coberta pelo
  cancelamento de biometria (grupo `authorization`).

## Observabilidade

Métricas assertadas via `POST https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big/{metric}`
(tanto a classe de métricas do ST quanto o `window.sendMetric` do plugin usam esse endpoint).

## Notas de manutenção

- Asserções por **elemento/hidden input**, nunca por `body.innerText` (o ST renderiza em iframe).
- O `faults` desfaz tudo (scripts injetados, `page.route`, throttling) ao fim de cada teste —
  o contexto do Chrome é compartilhado, então nada vaza de um cenário para o outro.
- Diferença por país: hoje não há; quando surgir, mova o cenário divergente da suite para o
  arquivo `tests/<país>/<grupo>.spec.js` daquele país.
- Escritos contra a lib **v2.1**; seletores/métricas podem mudar com a versão.
