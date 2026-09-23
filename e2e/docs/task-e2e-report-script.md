# PSW-4157 — Script de regressão E2E com relatório consolidado

> SDD da task. Entrega: `e2e/run-all-report.sh` (+ `e2e/run-pse.sh` e os
> assets em `e2e/run-all-report/`). Tipo: tooling de E2E (sem código de produção).

---

## 1. Problema

O reporter padrão do Playwright (HTML) **não é parseável por shell**. Fechar uma rodada de
regressão exigia:
- rodar manualmente cada combinação **país × checkout** (7 países × 2 modos = 14 runs),
- abrir 14 relatórios HTML separados,
- tabular os resultados à mão.

Processo lento, manual e propenso a erro — sem uma visão única de "o que passou / o que
falhou e por quê".

## 2. Objetivo

Um **único script** que roda a matriz completa, parseia cada combinação via `jq` (100% em
shell, determinístico, sem LLM) e gera um **relatório consolidado** com, por país e checkout:
total, sucessos, erros, skips; e, para cada falha, o **nome do teste + a causa real** do erro.

## 3. Escopo

- **Países:** MLA, MLB, MLC, MLM, MCO, MLU, MPE.
- **Checkouts:** `classic` e `blocks`.
- **PSE (MCO):** incluído na matriz (não excluído nem silenciado). Em localhost a API do MP
  rejeita o `callback_url`; com `--with-pse`/`--release` roda via mu-plugin temporário que
  sobrescreve a order-received URL (sem tunnel externo).
- **Plataforma:** plugin WooCommerce Mercado Pago, ambiente Docker (`mp-wc-dev`).

## 4. Critérios de aceite

| # | Critério | Como é atendido |
|---|----------|-----------------|
| CA-1 | Roda a matriz 7×2 com `--reporter=json` por combinação | loop `ALL_SITES × ALL_CHECKOUTS`; `PLAYWRIGHT_JSON_OUTPUT_NAME` por combinação |
| CA-2 | Relatório consolidado com total/ok/erro/skip por país e checkout | `report.md` (tabela "Resultado por país") + resumo no terminal |
| CA-3 | Cada falha mostra **nome do teste + causa real** (verbatim) | parse via `jq` lendo `.specs[].title` + 1ª linha de `results[].error.message` |
| CA-4 | Exit code ≠ 0 quando há falha | `OVERALL_RC=1` em qualquer falha/erro de execução |
| CA-5 | Combinação sem JSON = falha de execução, citando o log | branch "sem JSON" marca falha e referencia o `.log` |
| CA-6 | PSE roda na matriz | branch MCO + `--with-pse` (via `run-pse.sh`, suíte MCO completa via mu-plugin) |
| CA-7 | `shellcheck` limpo | validado; `# shellcheck disable=` escopados e justificados |

### Cenários de aceite

**Cenário: matriz completa, tudo verde**
1. `bash e2e/run-all-report.sh`
2. Roda as 14 combinações, parseia cada JSON.
3. **Esperado:** `report.md` com todas as linhas ✅, exit code 0.

**Cenário: combinação com falha**
1. Uma combinação tem teste `unexpected`.
2. **Esperado:** linha ❌ no resumo; em "Falhas Detalhadas", `- <nome do teste>: <1ª linha do erro>`; exit code 1.

**Cenário: combinação sem JSON (setup/Docker/credencial)**
1. A combinação não produz JSON.
2. **Esperado:** status `⚠️ falha de execução`, trecho do log no relatório, exit code 1.

**Cenário: PSE habilitado**
1. `bash e2e/run-all-report.sh --site MCO --with-pse`
2. **Esperado:** instala o mu-plugin temporário, roda a suíte MCO completa; sem JSON do PSE = falha (não PASS silencioso).

**Cenário: filtro inválido**
1. `bash e2e/run-all-report.sh --site FOO`
2. **Esperado:** erro e exit 1 (não roda matriz vazia reportando PASS).

## 5. Funcionalidades (flags)

| Flag | Função |
|---|---|
| `--site` / `--checkout` | filtra país / modo (validados contra a lista; falha rápido se inválido) |
| `--with-retries` | habilita os retries do `playwright.config.js` (padrão: sem retries) |
| `--release` | regressão oficial: PSE (MCO) **+** retries |
| `--with-pse` | PSE (MCO) via mu-plugin temporário (override da order-received URL) |
| `--prod` | ambiente de produção (`MP_ENV=prod` → `test_mode=no` + credenciais `*_PROD`) |
| `--no-report` | não gera/abre o HTML consolidado |
| `--rerun-failed` | re-roda só os specs que falharam na run anterior (lê os JSONs de `results/`) |
| `--debug` | Playwright Inspector para uma combinação (`--site`+`--checkout`) |
| `--clean` | limpa `results/` e sai |
| `--menu`, `-m` | modo interativo (país/checkout/retries/report/ambiente/PSE) |
| `--help [--interactive]` | ajuda no terminal / em HTML no browser |

## 6. Saída

| Arquivo | Conteúdo |
|---|---|
| `e2e/results/report.md` | relatório consolidado (markdown) + cópia `report-<data>.md` |
| `e2e/results/<SITE>-<MODE>.json` | JSON bruto do Playwright por combinação |
| `e2e/results/<SITE>-<MODE>.log` | log de progresso por combinação |
| `e2e/playwright-report/` | HTML consolidado via `merge-reports` |

Exit codes: `0` tudo passou · `1` falha/erro de execução · `130` interrompido (Ctrl+C, gera relatório parcial).

## 7. Restrições arquiteturais (MUST NOT)

- **Não** alterar o reporter padrão do `playwright.config.js`.
- **Não** modificar nenhum `*.spec.js` nem helpers/flows.
- **Não** hardcodar credenciais — vêm de `e2e/.env`.
- PSE **na** matriz (não excluído/silenciado).

## 8. Organização

- `run-all-report.sh` — orquestrador (matriz, parse, relatório).
- `run-pse.sh` — PSE/MCO via mu-plugin temporário (override da order-received URL, sem tunnel).
- `run-all-report/help.html` — template do help interativo (asset versionado, separado da lógica).

## 9. Notas

- Parsing determinístico em `bash` + `jq`.
- Bash defensivo: `set -u`, `${arr[@]+...}`, `PIPESTATUS`, `trap EXIT` (relatório parcial), nomes de blob únicos por combinação.
- Itens de correção dos próprios testes E2E (flows/specs) são tratados na task separada **PSW-4180**.
