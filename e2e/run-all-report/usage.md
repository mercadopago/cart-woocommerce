# run-all-report.sh

Executa a suíte E2E completa e gera um relatório consolidado.

Roda as **14 combinações** da matriz (7 países × 2 modos de checkout), parseia os
resultados via `jq` e grava um relatório em `e2e/results/report.md`. Por padrão roda
**sem retries** (feedback rápido) — use `--with-retries` para restaurar o comportamento
do `playwright.config.js` (2 tentativas por falha).

## Uso

```bash
bash e2e/run-all-report.sh [OPÇÕES]
```

## Opções

| Flag | Descrição | Padrão |
|------|-----------|--------|
| `--site SITE` | Roda apenas o país: `MLA \| MLB \| MLC \| MLM \| MCO \| MLU \| MPE` | todos |
| `--checkout MODE` | Roda apenas o modo: `classic \| blocks` | ambos |
| `--with-retries` | Habilita retries (config: 2x). Sem a flag, cada teste roda 1 vez | sem retries |
| `--release` | Regressão oficial: PSE (MCO) **+** retries (`--with-pse` + `--with-retries`) | — |
| `--with-pse` | Habilita o PSE (MCO) via mu-plugin temporário que intercepta `woocommerce_get_checkout_order_received_url` (não afeta `home_url()` global). Sem a flag, PSE falha em localhost e a causa real aparece no relatório | PSE desabilitado |
| `--prod` | Roda em **produção** (`test_mode=no`), usando `MP_ACCESS_TOKEN_PROD_<SITE>` / `MP_PUBLIC_KEY_PROD_<SITE>`. Equivale a `MP_ENV=prod` | test (sandbox) |
| `--no-report` | Não gera o HTML consolidado nem abre o browser ao final | HTML gerado |
| `--rerun-failed` | Re-roda **apenas** os specs que falharam na run completa anterior (lê os JSONs de `results/`). Aceita os mesmos filtros; combinações sem falha são puladas | — |
| `--debug` | Abre o Playwright Inspector para **uma** combinação (requer `--site` e `--checkout`). Com `--rerun-failed`, só os que falharam | — |
| `--clean` | Apaga tudo em `e2e/results/` e sai sem rodar testes | — |
| `--menu`, `-m` | Modo interativo (país / checkout / retries / report / ambiente / PSE) | — |
| `--help`, `-h` | Exibe esta mensagem | — |
| `--help --interactive` | Abre o help completo em HTML no browser | — |

## Países

| | | |
|---|---|---|
| **MLA** Argentina | **MLB** Brasil | **MLC** Chile |
| **MLM** México | **MCO** Colômbia | **MLU** Uruguai |
| **MPE** Peru | | |

## Exemplos

```bash
# Matriz completa, sem retries (~10-15 min)
bash e2e/run-all-report.sh

# Só Brasil classic (~2-3 min)
bash e2e/run-all-report.sh --site MLB --checkout classic

# Regressão oficial de release (matriz + PSE + retries)
bash e2e/run-all-report.sh --release

# Matriz completa com retries (~20-30 min)
bash e2e/run-all-report.sh --with-retries

# Brasil sem gerar o HTML (mais rápido)
bash e2e/run-all-report.sh --site MLB --no-report

# Re-rodar só o que falhou na run anterior (todas as combinações com falha)
bash e2e/run-all-report.sh --rerun-failed

# Idem, restrito ao Brasil
bash e2e/run-all-report.sh --site MLB --rerun-failed

# Inspector (step-by-step) de uma combinação
bash e2e/run-all-report.sh --site MLB --checkout blocks --debug

# Inspector só com os que falharam de MLB blocks
bash e2e/run-all-report.sh --site MLB --checkout blocks --rerun-failed --debug

# Limpar results/ e sair
bash e2e/run-all-report.sh --clean

# Help em HTML no browser
bash e2e/run-all-report.sh --help --interactive
```

## Investigando falhas

Após um run com falhas, use o Playwright UI para rodar teste a teste:

```bash
# Todos os que falharam (usa o cache do último run)
SITE=<SITE> CHECKOUT=<MODE> npx playwright test tests/<site>/ --last-failed --ui

# Por arquivo
SITE=MLB CHECKOUT=blocks npx playwright test tests/mlb/chocredits/ --ui
SITE=MLB CHECKOUT=blocks npx playwright test tests/mlb/chopro/modal/ --ui

# Teste específico por linha
SITE=MLB CHECKOUT=blocks npx playwright test tests/mlb/chocustom/checkout_validation_gate.spec.js:33 --ui
```

## Saída

| Arquivo | Conteúdo |
|---------|----------|
| `e2e/results/report.md` | relatório consolidado (markdown) |
| `e2e/results/<SITE>-<MODE>.json` | JSON bruto do Playwright por combinação |
| `e2e/results/<SITE>-<MODE>.log` | log de progresso por combinação |

**Exit codes:** `0` todos passaram · `1` falha/erro de execução · `130` interrompido (Ctrl+C, gera relatório parcial).

## Pré-requisitos

- Docker rodando com o container `mp-wc-dev`
- `e2e/.env` com credenciais MP por país
- Node.js + `npm install` em `e2e/`
- `jq` (`brew install jq`)

> **CI:** com `CI=true`, gera o HTML consolidado mas não abre o browser. Use `--no-report` para desabilitar de vez.
