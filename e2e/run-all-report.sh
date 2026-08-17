#!/usr/bin/env bash
# Run the full E2E regression matrix (7 countries × 2 checkout modes = 14
# combinations) and produce a consolidated parseable report.
#
# Why: The default html reporter is not shell-parseable. Closing a regression
# round today requires 14 manual runs, opening 14 separate HTML reports and
# hand-tabulating results. This script automates the full matrix, parses each
# run via jq and writes a single consolidated report to e2e/results/report.md.
#
# PSE (MCO): included in the matrix. On localhost the MP API rejects the
# callback_url — the report shows the real error and suggests run-pse.sh.
# Use --with-pse to have PSE run via mu-plugin (order-received URL override) automatically.
#
# Usage: bash e2e/run-all-report.sh [OPTIONS]
#
# Run with --help for the full list of options and examples.
# Run with --help --interactive for an HTML help page in the browser.
set -u

E2E_DIR="$(cd "$(dirname "$0")" && pwd)"
RESULTS_DIR="${E2E_DIR}/results"
REPORT="${RESULTS_DIR}/report.md"
REPORT_ARCHIVE="${RESULTS_DIR}/report-$(date '+%Y-%m-%d-%H%M').md"
RUN_PSE_SCRIPT="${E2E_DIR}/run-pse.sh"
# Assets do runner (textos de UI separados da lógica): run-all-report/help.html etc.
ASSET_DIR="${E2E_DIR}/run-all-report"

ALL_SITES=(MLA MLB MLC MLM MCO MLU MPE)
ALL_CHECKOUTS=(classic blocks)

FILTER_SITE=""
FILTER_CHECKOUT=""
WITH_PSE=0
PSE_DONE=0
OPEN_REPORT=1
SHOW_HELP=0
INTERACTIVE_HELP=0
WITH_RETRIES=0
RERUN_FAILED=0
DEBUG_MODE=0
CLEAN=0
SHOW_MENU=0
IS_CI="${CI:-}"
# Ambiente MP: 'test' (sandbox, padrão) ou 'prod' (produção). O global-setup lê MP_ENV e configura a loja (test_mode + slots de credencial) conforme o valor. Respeita um MP_ENV já exportado; --prod e o menu sobrescrevem.
MP_ENV_VALUE="${MP_ENV:-test}"

# ── Usage (terminal) ─────────────────────────────────────────────────────────
# Texto do --help vive em run-all-report/usage.md (asset versionado), separado da lógica.
show_usage() {
  cat "${ASSET_DIR}/usage.md"
}

# Abre um arquivo no browser de forma portável: `open` (macOS) ou `xdg-open` (Linux/WSL).
# Sem nenhum dos dois (ex.: container headless), imprime o caminho para abertura manual.
_open_browser() {
  if command -v open >/dev/null 2>&1; then
    open "$1"
  elif command -v xdg-open >/dev/null 2>&1; then
    xdg-open "$1"
  else
    echo "[E2E] Abra manualmente: $1"
  fi
}

# ── Usage (HTML interativo) ───────────────────────────────────────────────────
# Abre o help em HTML no browser. O conteúdo vive em run-all-report/help.html
# (asset versionado) — separa o template de UI da lógica do runner.
# shellcheck disable=SC2329  # chamada via dispatch (--help --interactive)
show_interactive_help() {
  _open_browser "${ASSET_DIR}/help.html"
}

# ── Menu interativo ───────────────────────────────────────────────────────────
# Pergunta cada opção e preenche as variáveis (FILTER_SITE, FILTER_CHECKOUT, etc.),
# seguindo o fluxo normal depois. Inputs inválidos repetem a pergunta.
# shellcheck disable=SC2329  # chamada via dispatch ([ "$SHOW_MENU" -eq 1 ] && run_menu)
run_menu() {
  echo ""
  echo "╭────────────────────────────────────────╮"
  echo "│  E2E Runner — configuração interativa  │"
  echo "╰────────────────────────────────────────╯"

  local choice
  while true; do
    echo ""
    echo "País:"
    echo "  1. Todos   2. MLA   3. MLB   4. MLC"
    echo "  5. MLM     6. MCO   7. MLU   8. MPE"
    echo ""
    printf "> "
    read -r choice
    case "$choice" in
      1) FILTER_SITE=""; break ;;
      2) FILTER_SITE="MLA"; break ;;
      3) FILTER_SITE="MLB"; break ;;
      4) FILTER_SITE="MLC"; break ;;
      5) FILTER_SITE="MLM"; break ;;
      6) FILTER_SITE="MCO"; break ;;
      7) FILTER_SITE="MLU"; break ;;
      8) FILTER_SITE="MPE"; break ;;
      *) echo "[E2E] Não compreendi \"${choice}\". Digite um número de 1 a 8." ;;
    esac
  done

  while true; do
    echo ""
    echo "Checkout:"
    echo "  1. Ambos   2. Classic   3. Blocks"
    echo ""
    printf "> "
    read -r choice
    case "$choice" in
      1) FILTER_CHECKOUT=""; break ;;
      2) FILTER_CHECKOUT="classic"; break ;;
      3) FILTER_CHECKOUT="blocks"; break ;;
      *) echo "[E2E] Não compreendi \"${choice}\". Digite 1, 2 ou 3." ;;
    esac
  done

  while true; do
    echo ""
    echo "Habilitar retries? [S/N]"
    echo ""
    printf "> "
    read -r choice
    case "$choice" in
      ""|n|N) WITH_RETRIES=0; break ;;
      s|S) WITH_RETRIES=1; echo "    → 2 retries por padrão (playwright.config.js)"; break ;;
      *) echo "[E2E] Não compreendi \"${choice}\". Digite s ou n." ;;
    esac
  done

  while true; do
    echo ""
    echo "Gerar relatório HTML ao final? [S/N]"
    echo ""
    printf "> "
    read -r choice
    case "$choice" in
      ""|s|S) OPEN_REPORT=1; break ;;
      n|N) OPEN_REPORT=0; break ;;
      *) echo "[E2E] Não compreendi \"${choice}\". Digite s ou n." ;;
    esac
  done

  while true; do
    echo ""
    echo "Ambiente:"
    echo "  1. Test (sandbox)   2. Produção "
    echo ""
    printf "> "
    read -r choice
    case "$choice" in
      ""|1) MP_ENV_VALUE="test"; break ;;
      2) MP_ENV_VALUE="prod"; break ;;
      *) echo "[E2E] Não compreendi \"${choice}\". Digite 1 ou 2." ;;
    esac
  done

  # PSE só existe no MCO — só pergunta sobre o PSE quando o país é MCO ou Todos.
  if [ -z "$FILTER_SITE" ] || [ "$FILTER_SITE" = "MCO" ]; then
    while true; do
      echo ""
      echo "Habilitar PSE (MCO)? [S/N]"
      echo ""
      printf "> "
      read -r choice
      case "$choice" in
        ""|n|N) WITH_PSE=0; break ;;
        s|S) WITH_PSE=1; break ;;
        *) echo "[E2E] Não compreendi \"${choice}\". Digite s ou n." ;;
      esac
    done
  else
    WITH_PSE=0
  fi

  echo ""
  echo "▶ Configuração:"
  echo "    País:       ${FILTER_SITE:-Todos}"
  echo "    Checkout:   ${FILTER_CHECKOUT:-Ambos}"
  echo "    Retries:    $([ "$WITH_RETRIES" -eq 1 ] && echo sim || echo não)"
  echo "    Report HTML: $([ "$OPEN_REPORT" -eq 1 ] && echo sim || echo não)"
  echo "    Ambiente:   ${MP_ENV_VALUE}$([ "$MP_ENV_VALUE" = "prod" ] && echo " (test_mode=no)")"
  if [ -z "$FILTER_SITE" ] || [ "$FILTER_SITE" = "MCO" ]; then
    echo "    PSE (MCO): $([ "$WITH_PSE" -eq 1 ] && echo sim || echo não)"
  fi

  while true; do
    echo ""
    echo "Rodar agora? [S/N]"
    echo ""
    printf "> "
    read -r choice
    case "$choice" in
      ""|s|S) break ;;
      n|N) echo "[E2E] Operação cancelada."; exit 0 ;;
      *) echo "[E2E] Não compreendi \"${choice}\". Digite s ou n." ;;
    esac
  done
}

# ── Argument parsing ─────────────────────────────────────────────────────────
while [ $# -gt 0 ]; do
  case "$1" in
    --site)
      FILTER_SITE="${2:-}"
      [ -z "$FILTER_SITE" ] && { echo "[E2E] ERROR: --site requires a value (e.g. --site MLB)"; exit 1; }
      shift 2
      ;;
    --checkout)
      FILTER_CHECKOUT="${2:-}"
      [ -z "$FILTER_CHECKOUT" ] && { echo "[E2E] ERROR: --checkout requires a value (classic|blocks)"; exit 1; }
      shift 2
      ;;
    --with-pse)
      WITH_PSE=1
      shift
      ;;
    --release)
      # Regressão oficial: PSE (MCO) + retries (absorve a flakiness do sandbox, ex.: BankTransfers Timeout do PSE). Coerente com a doc "regressão oficial".
      WITH_PSE=1
      WITH_RETRIES=1
      shift
      ;;
    --no-report)
      OPEN_REPORT=0
      shift
      ;;
    --open-report)
      OPEN_REPORT=1
      shift
      ;;
    --with-retries)
      WITH_RETRIES=1
      shift
      ;;
    --prod)
      MP_ENV_VALUE="prod"
      shift
      ;;
    --interactive)
      INTERACTIVE_HELP=1
      shift
      ;;
    --rerun-failed)
      RERUN_FAILED=1
      shift
      ;;
    --debug)
      DEBUG_MODE=1
      shift
      ;;
    --clean)
      CLEAN=1
      shift
      ;;
    --menu|-m)
      SHOW_MENU=1
      shift
      ;;
    --help|-h|-help)
      SHOW_HELP=1
      shift
      ;;
    *)
      echo "[E2E] ERROR: unknown option: $1 — run with --help for usage"
      exit 1
      ;;
  esac
done

# ── Help dispatch ─────────────────────────────────────────────────────────────
if [ "$SHOW_HELP" -eq 1 ]; then
  if [ "$INTERACTIVE_HELP" -eq 1 ]; then
    show_interactive_help
  else
    show_usage
  fi
  exit 0
fi

# ── Menu dispatch ─────────────────────────────────────────────────────────────
# Preenche as variáveis interativamente; o fluxo segue normal depois.
[ "$SHOW_MENU" -eq 1 ] && run_menu

# Exporta o ambiente MP para o global-setup (e o run-pse.sh) — define test_mode e os slots de credencial da loja. Uma vez só, propaga para todas as combinações.
export MP_ENV="$MP_ENV_VALUE"

# ── Valida os filtros ─────────────────────────────────────────────────────────
# Um --site/--checkout inválido (typo, lowercase) faria todo o loop ser pulado e
# o script sairia 0 com um PASS vazio. Falha rápido em vez de no-op silencioso.
if [ -n "$FILTER_SITE" ]; then
  valid_site=0
  for s in "${ALL_SITES[@]}"; do [ "$s" = "$FILTER_SITE" ] && valid_site=1; done
  if [ "$valid_site" -eq 0 ]; then
    echo "[E2E] ERROR: --site inválido: '${FILTER_SITE}'. Valores: ${ALL_SITES[*]}"
    exit 1
  fi
fi
if [ -n "$FILTER_CHECKOUT" ]; then
  case "$FILTER_CHECKOUT" in
    classic|blocks) ;;
    *) echo "[E2E] ERROR: --checkout inválido: '${FILTER_CHECKOUT}'. Valores: classic | blocks"; exit 1 ;;
  esac
fi

# ── Clean dispatch ────────────────────────────────────────────────────────────
# Apaga tudo em results/ (JSONs, logs, blobs, report.md e histórico) e sai.
if [ "$CLEAN" -eq 1 ]; then
  if [ -d "$RESULTS_DIR" ]; then
    rm -rf "${RESULTS_DIR:?}"/*
    echo "[E2E] results/ limpo — todos os relatórios e artefatos removidos."
  else
    echo "[E2E] results/ já está vazio (diretório não existe)."
  fi
  exit 0
fi

# ── Debug (uma combinação no Playwright Inspector) ────────────────────────────
# Abre o Inspector para a combinação informada. Com --rerun-failed junto, usa --last-failed para inspecionar só os que falharam na última execução dela.
if [ "$DEBUG_MODE" -eq 1 ]; then
  if [ -z "$FILTER_SITE" ] || [ -z "$FILTER_CHECKOUT" ]; then
    echo "[E2E] ERROR: --debug requer --site e --checkout"
    echo "[E2E]   Exemplo: bash e2e/run-all-report.sh --site MLB --checkout blocks --debug"
    exit 1
  fi
  site_lower="$(printf '%s' "$FILTER_SITE" | tr '[:upper:]' '[:lower:]')"
  pw_args=("tests/${site_lower}/")
  [ "$RERUN_FAILED" -eq 1 ] && pw_args+=("--last-failed")
  pw_args+=("--debug")
  echo "[E2E] Debug (Inspector)$([ "$RERUN_FAILED" -eq 1 ] && echo " | só os que falharam (--last-failed)")"
  ( cd "$E2E_DIR" || exit 1
    SITE="$FILTER_SITE" CHECKOUT="$FILTER_CHECKOUT" \
      npx playwright test "${pw_args[@]}" )
  exit $?
fi

# ── Rerun-failed: exige uma run completa anterior ─────────────────────────────
# O modo lê os specs que falharam direto dos results/<SITE>-<CHECKOUT>.json da run anterior (em run_combination), então NÃO limpa os JSONs — ao contrário do run normal.
if [ "$RERUN_FAILED" -eq 1 ]; then
  if [ ! -d "$RESULTS_DIR" ] || [ -z "$(find "$RESULTS_DIR" -maxdepth 1 -name '*.json' -print -quit 2>/dev/null)" ]; then
    echo "[E2E] ERROR: --rerun-failed precisa de uma run completa anterior (nenhum results/*.json encontrado)."
    echo "[E2E]   Rode primeiro: bash e2e/run-all-report.sh"
    exit 1
  fi
fi

# Limpa resultados do run anterior antes de iniciar (JSON, logs e blobs stale).
# O report.md e o histórico são preservados.
# No --rerun-failed os JSONs são PRESERVADOS (run_combination lê deles o que falhou); só os blobs são limpos, para o HTML consolidado refletir apenas o que foi re-rodado.
if [ -d "$RESULTS_DIR" ]; then
  if [ "$RERUN_FAILED" -eq 0 ]; then
    find "$RESULTS_DIR" -maxdepth 1 -name "*.json" -delete
    find "$RESULTS_DIR" -maxdepth 1 -name "*.log" -delete
  fi
  rm -rf "${RESULTS_DIR}/blobs"
  echo "[E2E] $([ "$RERUN_FAILED" -eq 1 ] && echo "Blobs limpos (JSONs preservados p/ rerun)." || echo "Resultados anteriores limpos.")"
fi
mkdir -p "$RESULTS_DIR"

# ── Credenciais pre-flight ────────────────────────────────────────────────────
# Verifica se as credenciais MP estão no .env antes de iniciar a matriz.
# Lê o .env diretamente — não expõe valores, só verifica presença.
ENV_FILE="${E2E_DIR}/.env"
preflight_ok=1
# Sufixo das credenciais conforme o ambiente: TEST (sandbox) ou PROD (produção).
CRED_SUFFIX="TEST"
[ "$MP_ENV_VALUE" = "prod" ] && CRED_SUFFIX="PROD"
if [ -f "$ENV_FILE" ]; then
  missing_sites=""
  for site in "${ALL_SITES[@]}"; do
    [ -n "$FILTER_SITE" ] && [ "$site" != "$FILTER_SITE" ] && continue
    # PSE não precisa de credenciais aqui (run-pse.sh gerencia)
    [ "$site" = "MCO" ] && [ "$WITH_PSE" -eq 1 ] && continue
    # grep -qE (ERE) com `+`: o `\+` em BRE não é portável — BSD grep (macOS padrão) o trata como literal, fazendo o preflight falhar mesmo com a credencial presente.
    if ! grep -qE "MP_ACCESS_TOKEN_${CRED_SUFFIX}_${site}=.+" "$ENV_FILE" 2>/dev/null \
       && ! grep -qE "MP_ACCESS_TOKEN_${CRED_SUFFIX}=.+" "$ENV_FILE" 2>/dev/null; then
      missing_sites="${missing_sites} ${site}"
      preflight_ok=0
    fi
  done
  if [ "$preflight_ok" -eq 0 ]; then
    echo "[E2E] ERRO: credenciais MP (${CRED_SUFFIX}) ausentes no e2e/.env para:${missing_sites}"
    echo "[E2E] Adicione MP_ACCESS_TOKEN_${CRED_SUFFIX}_<SITE> e MP_PUBLIC_KEY_${CRED_SUFFIX}_<SITE>"
    echo "[E2E] ou as credenciais genéricas MP_ACCESS_TOKEN_${CRED_SUFFIX} / MP_PUBLIC_KEY_${CRED_SUFFIX}"
    exit 1
  fi
else
  echo "[E2E] AVISO: e2e/.env não encontrado — credenciais serão validadas pelo global-setup"
fi

OVERALL_RC=0
TIMESTAMP="$(date '+%Y-%m-%d %H:%M:%S')"
COMBINATION_INDEX=0
COMBINATION_TOTAL=0
declare -a SUMMARY_ROWS=()
declare -a FAILURE_BLOCKS=()

INTERRUPTED=0
CURRENT_SITE=""
CURRENT_CHECKOUT=""

# ── finish: grava relatório + resumo no terminal (chamado pelo trap EXIT) ─────
# Roda sempre ao sair — normalmente ou após Ctrl+C — garantindo que os dados
# coletados até o momento são preservados como relatório parcial.
# shellcheck disable=SC2329  # invoked via trap EXIT, not directly
finish() {
  local sep="────────────────────────────────────────────────────────────────────"
  local label="completo"
  [ "${INTERRUPTED:-0}" -eq 1 ] && label="parcial — interrompido"

  # Agregação compartilhada: lê as linhas do resumo (| país | checkout | total | ok |
  # err | skip | status |), guarda cada combinação e soma por país. No END, cada
  # país emite suas linhas (classic/blocks) seguidas de uma linha Total com % OK/Erro.
  # Ignora colunas "—" (falha de execução / PSE sem JSON) nos somatórios.
  # shellcheck disable=SC2016  # $2/$4/... são campos do awk, não devem expandir no shell
  local merged_awk='
    function trim(x){ gsub(/^[ \t]+|[ \t]+$/,"",x); return x }
    {
      c=trim($2); ck=trim($3); t=trim($4); o=trim($5); e=trim($6); s=trim($7); st=trim($8)
      if (c=="") next
      nl[c]++; CK[c,nl[c]]=ck; T[c,nl[c]]=t; O[c,nl[c]]=o; E[c,nl[c]]=e; S[c,nl[c]]=s; ST[c,nl[c]]=st
      if (!seen[c]) { seen[c]=1; ord[++n]=c }
      if (t ~ /^[0-9]+$/) tot[c]+=t
      if (o ~ /^[0-9]+$/) ok[c]+=o
      if (e ~ /^[0-9]+$/) err[c]+=e
      if (s ~ /^[0-9]+$/) skip[c]+=s
    }'

  {
    echo "# E2E Report — ${TIMESTAMP} (${label})"
    echo ""
    echo "_Gerado por \`run-all-report.sh\` | JSONs por combinação em \`e2e/results/\`_"
    echo ""
    echo "## Resultado por país"
    echo ""
    echo "| País | Checkout | Total | ✅ OK | ❌ Falhou | ⏭ Skip | % OK | % Erro | % Skip | Status |"
    echo "|------|----------|------:|------:|----------:|------:|-----:|-------:|------:|--------|"
    for row in "${SUMMARY_ROWS[@]+"${SUMMARY_ROWS[@]}"}"; do printf '%s\n' "$row"; done \
      | awk -F'|' "$merged_awk"'
        END {
          for (i=1;i<=n;i++){
            c=ord[i]
            for (j=1;j<=nl[c];j++)
              printf "| %s | %s | %s | %s | %s | %s |  |  |  | %s |\n", c, CK[c,j], T[c,j], O[c,j], E[c,j], S[c,j], ST[c,j]
            ran=tot[c]-skip[c]; if (ran>0){ po=sprintf("%.1f%%",ok[c]*100/ran); pe=sprintf("%.1f%%",err[c]*100/ran) } else { po="—"; pe="—" }; ps=(tot[c]>0)?sprintf("%.1f%%",skip[c]*100/tot[c]):"—"
            printf "| **%s** | **Total** | **%d** | **%d** | **%d** | **%d** | **%s** | **%s** | **%s** |  |\n", c, tot[c], ok[c], err[c], skip[c], po, pe, ps
          }
        }'
    echo ""
    # shellcheck disable=SC2016  # backticks são markdown literal, não command substitution
    echo '> **Nota:** `% OK` e `% Erro` são calculados sobre os testes que **efetivamente rodaram** (Total − Skip). `% Skip` é informativo e calculado sobre o Total.'
    echo ""
    if [ "${#FAILURE_BLOCKS[@]}" -gt 0 ]; then
      echo "## Falhas Detalhadas"
      echo ""
      for block in "${FAILURE_BLOCKS[@]+"${FAILURE_BLOCKS[@]}"}"; do
        echo "$block"
      done
      echo ""
    fi
  } > "$REPORT"

  echo ""
  echo "$sep"
  printf " %-5s  %-9s  %5s  %5s  %6s  %5s  %6s  %7s  %7s  %s\n" \
    "País" "Checkout" "Total" "OK" "Falhou" "Skip" "% OK" "% Erro" "% Skip" "Status"
  echo "$sep"
  for row in "${SUMMARY_ROWS[@]+"${SUMMARY_ROWS[@]}"}"; do printf '%s\n' "$row"; done \
    | awk -F'|' "$merged_awk"'
      END {
        for (i=1;i<=n;i++){
          c=ord[i]
          for (j=1;j<=nl[c];j++)
            printf " %-5s  %-9s  %5s  %5s  %6s  %5s  %6s  %7s  %7s  %s\n", c, CK[c,j], T[c,j], O[c,j], E[c,j], S[c,j], "", "", "", ST[c,j]
          ran=tot[c]-skip[c]; if (ran>0){ po=sprintf("%.1f%%",ok[c]*100/ran); pe=sprintf("%.1f%%",err[c]*100/ran) } else { po="-"; pe="-" }; ps=(tot[c]>0)?sprintf("%.1f%%",skip[c]*100/tot[c]):"-"
          printf " %-5s  %-9s  %5d  %5d  %6d  %5d  %6s  %7s  %7s\n", c, "TOTAL", tot[c], ok[c], err[c], skip[c], po, pe, ps
        }
      }'
  echo "$sep"
  echo ""
  cp "$REPORT" "$REPORT_ARCHIVE" 2>/dev/null || true
  printf " Relatório (.md) : %s\n" "$REPORT"
  printf " Histórico       : %s\n" "$REPORT_ARCHIVE"
  if [ "$OPEN_REPORT" -eq 1 ]; then
    printf " HTML report     : consolidado via merge-reports (abrindo...)\n"
  elif [ -d "${RESULTS_DIR}/blobs" ] && [ "$(find "${RESULTS_DIR}/blobs" -name "*.zip" 2>/dev/null | wc -l | tr -d ' ')" -gt 0 ]; then
    printf " HTML report     : cd e2e && npx playwright merge-reports --reporter=html results/blobs\n"
  else
    printf " HTML report     : desabilitado (--no-report)\n"
  fi
  echo "$sep"

  if [ "$OPEN_REPORT" -eq 1 ]; then
    echo ""
    local blobs_dir="${RESULTS_DIR}/blobs"
    local blob_count=0
    [ -d "$blobs_dir" ] && blob_count="$(find "$blobs_dir" -name "*.zip" 2>/dev/null | wc -l | tr -d ' ')"
    if [ "${blob_count}" -gt 0 ]; then
      echo "[E2E] Gerando relatório HTML consolidado (${blob_count} blob(s))..."
      if ( cd "$E2E_DIR" && npx playwright merge-reports --reporter=html "$blobs_dir" ); then
        if [ "$IS_CI" = "true" ]; then
          echo "[E2E] CI mode — relatório em: ${E2E_DIR}/playwright-report/index.html"
        else
          _open_browser "${E2E_DIR}/playwright-report/index.html"
        fi
      else
        echo "[E2E] cd e2e && npx playwright merge-reports --reporter=html results/blobs"
      fi
    else
      echo "[E2E] Nenhum blob disponível — run interrompido antes de gerar dados."
    fi
  fi
}

# ── handle_interrupt: captura Ctrl+C e garante relatório parcial ──────────────
# shellcheck disable=SC2329  # invoked via trap INT TERM, not directly
handle_interrupt() {
  INTERRUPTED=1
  OVERALL_RC=1
  if [ -n "$CURRENT_SITE" ]; then
    SUMMARY_ROWS+=("| ${CURRENT_SITE} | ${CURRENT_CHECKOUT} | — | — | — | — | ⚠️ interrompido |")
    FAILURE_BLOCKS+=("### ${CURRENT_SITE} — ${CURRENT_CHECKOUT}: INTERROMPIDO")
    FAILURE_BLOCKS+=("  Execução cancelada pelo usuário (Ctrl+C) durante o run.")
  fi
  echo ""
  echo "[E2E] Interrompido — gerando relatório parcial com os dados coletados..."
  exit 130
}

trap handle_interrupt INT TERM
trap finish EXIT

# Lista os arquivos de spec com testes "unexpected" num JSON do Playwright.
# .file já é relativo ao rootDir (e2e/), ex.: tests/mlb/pix/pix_payment.spec.js — não adicionar "tests/" para não produzir tests/tests/... que o Playwright não encontra.
# Usado pelo --rerun-failed para mirar só o que falhou. Silencioso se o JSON não existe.
rerun_failed_specs() {
  jq -r '[.. | .specs? | arrays | .[] | select(any(.tests[]?; .status == "unexpected")) | .file] | unique | .[]' "$1" 2>/dev/null
}

# ── Run one country × checkout combination ───────────────────────────────────
run_combination() {
  local site="$1"
  local checkout="$2"
  local site_lower
  site_lower="$(printf '%s' "$site" | tr '[:upper:]' '[:lower:]')"
  local json_file="${RESULTS_DIR}/${site}-${checkout}.json"
  local log_file="${RESULTS_DIR}/${site}-${checkout}.log"

  CURRENT_SITE="$site"
  CURRENT_CHECKOUT="$checkout"

  # O run-pse.sh cobre os dois modos do MCO numa única passada (PSE_DONE). A 2ª iteração não roda nada — short-circuit ANTES do cabeçalho para não imprimir um header sugerindo execução de "MCO <modo>" quando já foi coberto pelo run-pse.sh.
  if [ "$site" = "MCO" ] && [ "$WITH_PSE" -eq 1 ] && [ "$PSE_DONE" -eq 1 ]; then
    echo ""
    echo "[E2E] MCO ${checkout}: já coberto pelo run-pse.sh — pulando."
    CURRENT_SITE=""
    CURRENT_CHECKOUT=""
    return
  fi

  local retries_label="off"
  [ "$WITH_RETRIES" -eq 1 ] && retries_label="on (playwright.config.js)"
  local report_label="on"
  [ "$OPEN_REPORT" -eq 0 ] && report_label="off (--no-report)"

  # Incrementa o índice só aqui (não no loop externo): combinações que dão short-circuit
  # antes deste ponto (ex.: MCO 2ª passada com --with-pse) não contam no [N/T],
  # mantendo o indicador consistente com o total já descontado.
  COMBINATION_INDEX=$((COMBINATION_INDEX + 1))

  echo ""
  echo "[E2E] [${COMBINATION_INDEX}/${COMBINATION_TOTAL}] ====== ${site} — ${checkout} ======"
  echo "[E2E] retries: ${retries_label} | open-report: ${report_label}"

  # --rerun-failed: descobre os specs que falharam lendo o JSON da run anterior (preservado, pois o modo rerun não limpa os JSONs) ANTES de sobrescrevê-lo.
  # Combinação sem falha anterior → pulada. (MCO via run-pse.sh não dá para subdividir em specs — re-roda a suíte MCO inteira se qualquer modo falhou; tratado no branch abaixo.)
  local rerun_targets=()
  if [ "$RERUN_FAILED" -eq 1 ]; then
    if [ "$site" = "MCO" ] && [ "$WITH_PSE" -eq 1 ]; then
      if [ "$PSE_DONE" -eq 0 ] \
         && [ -z "$(rerun_failed_specs "${RESULTS_DIR}/MCO-classic.json")$(rerun_failed_specs "${RESULTS_DIR}/MCO-blocks.json")" ]; then
        echo "[E2E] MCO: sem falhas na run anterior — pulando o pse."
        CURRENT_SITE=""
        CURRENT_CHECKOUT=""
        return
      fi
    else
      local _failed
      _failed="$(rerun_failed_specs "$json_file")"
      if [ -z "$_failed" ]; then
        echo "[E2E] ${site} ${checkout}: sem falhas na run anterior — pulando."
        CURRENT_SITE=""
        CURRENT_CHECKOUT=""
        return
      fi
      while IFS= read -r _line; do [ -n "$_line" ] && rerun_targets+=("$_line"); done <<< "$_failed"
      echo "[E2E] --rerun-failed: ${#rerun_targets[@]} spec(s) com falha nesta combinação."
    fi
  fi

  # Limpa resultados desta combinação (já lemos o que precisávamos acima) para evitar dados stale e garantir que "sem JSON ao final" signifique falha de execução real.
  rm -f "$json_file" "$log_file"

  # PSE: run-pse.sh handles both classic and blocks internally,
  # so we delegate once and skip subsequent MCO iterations. E2E_MCO_FULL=1 makes it run
  # the WHOLE tests/mco/ suite (chocustom/chopro/ticket + PSE), so MCO
  # gets full coverage — otherwise only PSE would run and the other methods would be
  # silently uncovered while the report still showed "MCO classic/blocks".
  if [ "$site" = "MCO" ] && [ "$WITH_PSE" -eq 1 ]; then
    if [ "$PSE_DONE" -eq 0 ]; then
      PSE_DONE=1
      # (--rerun-failed: a decisão de pular o MCO sem falhas é feita no topo da função.)
      echo "[E2E] Running full MCO suite via mu-plugin/order-received override (run-pse.sh)..."
      # E2E_BLOB_DEST_DIR: o run-pse escreve no blob-report/ default e move cada modo
      # para cá com nome único (MCO-<mode>.zip) — não usamos PLAYWRIGHT_BLOB_OUTPUT_DIR
      # porque o Playwright LIMPA o outputDir antes de escrever (apagaria os blobs já
      # acumulados dos países anteriores).
      local pse_rc=0
      # Respeita o filtro --checkout: roda só o modo pedido (senão ambos). Sem isso,
      # um run "classic-only" ainda executaria/reportaria blocks no run-pse.sh.
      local pse_modes=(classic blocks)
      [ -n "$FILTER_CHECKOUT" ] && pse_modes=("$FILTER_CHECKOUT")
      # Não propaga --retries=0 ao PSE por padrão: run-pse.sh depende de retries=2 (playwright.config.js) para absorver o "BankTransfers Timeout" transitório do PSE. Forçar "0" quando WITH_RETRIES=0 removia essa proteção em toda execução padrão (pse_retries="0" é truthy em bash, adicionando --retries=0 mesmo sem o user pedir). Agora E2E_PSE_RETRIES fica vazio → run-pse usa o config (retries=2).
      local pse_retries=""
      E2E_JSON_OUTPUT_DIR="${RESULTS_DIR}" \
      E2E_BLOB_DEST_DIR="${RESULTS_DIR}/blobs" \
      E2E_MCO_FULL=1 \
      E2E_PSE_MODES="${pse_modes[*]}" \
      E2E_PSE_RETRIES="${pse_retries}" \
        bash "$RUN_PSE_SCRIPT" 2>&1 | tee "${RESULTS_DIR}/MCO-pse.log"
      pse_rc="${PIPESTATUS[0]}"
      # Parseia stats reais por modo — uma linha por modo (respeitando o filtro)
      for pse_mode in "${pse_modes[@]}"; do
        local pse_json="${RESULTS_DIR}/MCO-${pse_mode}.json"
        if [ -f "$pse_json" ] && [ -s "$pse_json" ]; then
          local pse_total pse_ok pse_err pse_skipped
          # 2>/dev/null || echo 0: JSON corrompido/truncado faz jq retornar não-zero e vazio; sem fallback, [ "$var" -gt 0 ] emitiria "integer expression expected" e retornaria falso — marcando a run como ✅ mesmo com falhas reais.
          pse_total="$(jq '(.stats.expected // 0) + (.stats.unexpected // 0) + (.stats.skipped // 0) + (.stats.flaky // 0)' "$pse_json" 2>/dev/null || echo 0)"
          # flaky = passou no retry → conta como OK (senão as colunas não somam o total).
          pse_ok="$(jq '(.stats.expected // 0) + (.stats.flaky // 0)' "$pse_json" 2>/dev/null || echo 0)"
          pse_err="$(jq '.stats.unexpected // 0' "$pse_json" 2>/dev/null || echo 0)"
          pse_skipped="$(jq '.stats.skipped // 0' "$pse_json" 2>/dev/null || echo 0)"
          local pse_icon="✅ via run-pse"
          # Status por modo usa só pse_err (dado real do JSON deste modo).
          # pse_rc é o exit code agregado de classic+blocks — usá-lo aqui faria classic mostrar ❌ quando só blocks falhou, contradizendo as próprias stats.
          if [ "${pse_err:-0}" -gt 0 ]; then
            pse_icon="❌ via run-pse"
            OVERALL_RC=1
            FAILURE_BLOCKS+=("### MCO — ${pse_mode} (pse)")
            FAILURE_BLOCKS+=("  Log: \`${RESULTS_DIR}/MCO-pse.log\`")
          fi
          SUMMARY_ROWS+=("| MCO | ${pse_mode} | ${pse_total} | ${pse_ok} | ${pse_err} | ${pse_skipped} | ${pse_icon} |")
        else
          # Sem JSON = PSE não rodou/reportou (erro de setup, timeout, etc.) → falha.
          OVERALL_RC=1
          FAILURE_BLOCKS+=("### MCO — ${pse_mode} (pse): SEM DADOS")
          FAILURE_BLOCKS+=("  PSE não produziu JSON — verifique \`${RESULTS_DIR}/MCO-pse.log\`")
          SUMMARY_ROWS+=("| MCO | ${pse_mode} | — | — | — | — | ⚠️ sem dados |")
        fi
      done
      # Guarda global (não por-modo): se o processo do run-pse.sh saiu != 0 (ex.: falha de setup) sem que nenhuma falha por-modo tenha sido registrada, sinaliza aqui — o status por-modo acima usa só pse_err (dado real de cada JSON).
      if [ "$pse_rc" -ne 0 ] && [ "$OVERALL_RC" -eq 0 ]; then
        OVERALL_RC=1
        FAILURE_BLOCKS+=("### MCO (pse): processo saiu com código ${pse_rc}")
        FAILURE_BLOCKS+=("  Verifique \`${RESULTS_DIR}/MCO-pse.log\` (run-pse.sh/setup).")
      fi
    fi
    # Both modes are already covered by the single run-pse.sh run — skip.
    CURRENT_SITE=""
    CURRENT_CHECKOUT=""
    return
  fi

  # Standard run: PLAYWRIGHT_JSON_OUTPUT_NAME writes JSON to file so it does
  # not mix with the line reporter progress shown in the terminal.
  # --retries=0 is the default (fast feedback); --with-retries restores config.
  local run_rc=0
  # json  → arquivo parseável para o relatório consolidado
  # line  → progresso no terminal
  # blob  → apenas quando --open-report: acumula dados para merge-reports gerar
  #         um único HTML com todos os países e modos ao final do run
  local reporter="json,line"
  [ "$OPEN_REPORT" -eq 1 ] && reporter="json,line,blob"
  # Alvo: a pasta do país no run normal, ou só os specs que falharam no --rerun-failed.
  local target=("tests/${site_lower}/")
  [ "${#rerun_targets[@]}" -gt 0 ] && target=("${rerun_targets[@]}")
  local playwright_args=("${target[@]}" "--reporter=${reporter}")
  [ "$WITH_RETRIES" -eq 0 ] && playwright_args+=("--retries=0")
  # shellcheck disable=SC2030,SC2031  # exports são intencionalmente locais ao subshell
  # Two-phase run for @serial-store isolation without forcing workers=1 on all tests.
  # Applies to any site that has @serial-store specs (detected via Playwright below).
  # Phase 1 — non-@serial-store specs (workers=2, fast parallel).
  # Phase 2 — @serial-store specs alone (workers=1, no concurrent spec corrupts shared state).
  # The two JSONs are merged into <SITE>-<mode>.json so the stats parser below needs no changes.
  # --rerun-failed falls through to the standard path: the set is small and workers=2 is safe.
  #
  # Detection asks Playwright itself (--list) whether the site has @serial-store tests, so
  # it resolves the real describe titles — including those created by the shared factory
  # (flows/manual_renewal_multicountry.js) — instead of relying on a marker string living
  # in each spec file. --list does not run global-setup, so no store reset happens here.
  if [ "${#rerun_targets[@]}" -eq 0 ] \
     && ( cd "$E2E_DIR" && npx playwright test "tests/${site_lower}/" --grep "@serial-store" --list 2>/dev/null ) | grep -q "@serial-store"; then
    local serial_rc=0
    local serial_retries_arg=()
    [ "$WITH_RETRIES" -eq 0 ] && serial_retries_arg+=(--retries=0)
    local nonserial_json="${json_file%.json}-nonserial.json"
    local serial_json="${json_file%.json}-serial.json"

    # Phase 1 — all ${site} tests EXCEPT @serial-store, workers=2.
    echo "[E2E] ${site} ${checkout} — fase 1/2: testes paralelos, workers=2 (exceto @serial-store)"
    (
      cd "$E2E_DIR" || exit 1
      export PLAYWRIGHT_JSON_OUTPUT_NAME="$nonserial_json"
      export SITE="$site"
      export CHECKOUT="$checkout"
      npx playwright test "tests/${site_lower}/" --grep-invert "@serial-store" \
        --workers=2 "--reporter=${reporter}" "${serial_retries_arg[@]+"${serial_retries_arg[@]}"}"
    ) 2>&1 | tee "$log_file"
    [ "${PIPESTATUS[0]}" -ne 0 ] && serial_rc=1
    if [ "$OPEN_REPORT" -eq 1 ] && [ -d "${E2E_DIR}/blob-report" ]; then
      mkdir -p "${RESULTS_DIR}/blobs"
      find "${E2E_DIR}/blob-report" -name "*.zip" \
        -exec mv {} "${RESULTS_DIR}/blobs/${site}-${checkout}-nonserial.zip" \;
    fi

    # Phase 2 — @serial-store specs alone, workers=1.
    echo "[E2E] ${site} ${checkout} — fase 2/2: @serial-store (store-mutating specs), workers=1"
    (
      cd "$E2E_DIR" || exit 1
      export PLAYWRIGHT_JSON_OUTPUT_NAME="$serial_json"
      export SITE="$site"
      export CHECKOUT="$checkout"
      npx playwright test "tests/${site_lower}/" --grep "@serial-store" \
        --workers=1 "--reporter=${reporter}" "${serial_retries_arg[@]+"${serial_retries_arg[@]}"}"
    ) 2>&1 | tee -a "$log_file"
    [ "${PIPESTATUS[0]}" -ne 0 ] && serial_rc=1
    if [ "$OPEN_REPORT" -eq 1 ] && [ -d "${E2E_DIR}/blob-report" ]; then
      mkdir -p "${RESULTS_DIR}/blobs"
      find "${E2E_DIR}/blob-report" -name "*.zip" \
        -exec mv {} "${RESULTS_DIR}/blobs/${site}-${checkout}-serial.zip" \;
    fi

    # Merge phase JSONs → <SITE>-<mode>.json (expected by the stats parser below).
    if [ -f "$nonserial_json" ] && [ -f "$serial_json" ]; then
      jq -s '{
        config: .[0].config,
        suites: ((.[0].suites // []) + (.[1].suites // [])),
        errors:  ((.[0].errors  // []) + (.[1].errors  // [])),
        stats: {
          expected:   ((.[0].stats.expected   // 0) + (.[1].stats.expected   // 0)),
          unexpected: ((.[0].stats.unexpected // 0) + (.[1].stats.unexpected // 0)),
          flaky:      ((.[0].stats.flaky      // 0) + (.[1].stats.flaky      // 0)),
          skipped:    ((.[0].stats.skipped    // 0) + (.[1].stats.skipped    // 0))
        }
      }' "$nonserial_json" "$serial_json" > "$json_file" 2>/dev/null \
        && rm -f "$nonserial_json" "$serial_json"
    elif [ -f "$nonserial_json" ]; then mv "$nonserial_json" "$json_file"
    elif [ -f "$serial_json"    ]; then mv "$serial_json"    "$json_file"
    fi
    run_rc=$serial_rc
  else
    # Standard run: single invocation for all other countries (and MLB --rerun-failed).
    (
      cd "$E2E_DIR" || exit 1
      export PLAYWRIGHT_JSON_OUTPUT_NAME="$json_file"
      export SITE="$site"
      export CHECKOUT="$checkout"
      npx playwright test "${playwright_args[@]}"
    ) 2>&1 | tee "$log_file"
    run_rc="${PIPESTATUS[0]}"

    # Move blob from default dir (blob-report/) to results/blobs/ so merge-reports
    # accumulates all combinations. Rename to <SITE>-<checkout>.zip: classic and blocks
    # of the same site share the CLI filter, so the default blob name collides — without
    # a unique name the second move overwrites the first and the merged HTML loses a mode.
    if [ "$OPEN_REPORT" -eq 1 ]; then
      local blob_src="${E2E_DIR}/blob-report"
      if [ -d "$blob_src" ]; then
        mkdir -p "${RESULTS_DIR}/blobs"
        find "$blob_src" -name "*.zip" -exec mv {} "${RESULTS_DIR}/blobs/${site}-${checkout}.zip" \;
      fi
    fi
  fi

  # No JSON produced → setup/env failure (credentials missing, Docker down…).
  if [ ! -f "$json_file" ] || [ ! -s "$json_file" ]; then
    OVERALL_RC=1
    local log_excerpt
    log_excerpt="$(tail -10 "$log_file" 2>/dev/null || echo "(log vazio)")"
    SUMMARY_ROWS+=("| ${site} | ${checkout} | — | — | — | — | ⚠️ falha de execução |")
    FAILURE_BLOCKS+=("### ${site} — ${checkout}: FALHA DE EXECUÇÃO")
    FAILURE_BLOCKS+=('```')
    FAILURE_BLOCKS+=("$log_excerpt")
    FAILURE_BLOCKS+=('```')
    FAILURE_BLOCKS+=("Log completo: \`${log_file}\`")
    return
  fi

  # Parse totals via jq.
  # 2>/dev/null || echo 0: JSON corrompido/truncado faz jq retornar não-zero e vazio; sem fallback, [ "$var" -gt 0 ] emitiria "integer expression expected" e retornaria falso — marcando a run como ✅ mesmo com falhas reais.
  local total ok err skipped
  total="$(jq '(.stats.expected // 0) + (.stats.unexpected // 0) + (.stats.skipped // 0) + (.stats.flaky // 0)' "$json_file" 2>/dev/null || echo 0)"
  # flaky = passou no retry → conta como OK (senão as colunas não somam o total).
  ok="$(jq '(.stats.expected // 0) + (.stats.flaky // 0)' "$json_file" 2>/dev/null || echo 0)"
  err="$(jq '.stats.unexpected // 0' "$json_file" 2>/dev/null || echo 0)"
  skipped="$(jq '.stats.skipped // 0' "$json_file" 2>/dev/null || echo 0)"

  local status_icon="✅"
  if [ "$run_rc" -ne 0 ] || [ "$err" -gt 0 ]; then
    status_icon="❌"
    OVERALL_RC=1
  fi

  SUMMARY_ROWS+=("| ${site} | ${checkout} | ${total} | ${ok} | ${err} | ${skipped} | ${status_icon} |")

  # Extract failed test names + first line of error message (verbatim, not interpreted).
  if [ "$err" -gt 0 ]; then
    # Breakdown por método de pagamento (pasta após o país no path do spec).
    local methods
    methods="$(jq -r --arg country "$site_lower" '
      [ .. | .specs? | arrays | .[] |
        # Conta todos os testes da spec (não só tests[0]) para não subnotificar
        # falhas em specs com múltiplos test() onde o primeiro passou mas os demais falharam.
        { method: (((.file // "") | split("/")) as $p
                   | ($p | index($country)) as $ci
                   | (if $ci then $p[$ci + 1] else $p[-2] end)),
          ok:      (.tests | map(select(.status == "expected"))  | length),
          failed:  (.tests | map(select(.status == "unexpected")) | length),
          skipped: (.tests | map(select(.status == "skipped"))   | length) }
      ]
      | group_by(.method)
      | map({
          method:  .[0].method,
          ok:      (map(.ok)      | add // 0),
          failed:  (map(.failed)  | add // 0),
          skipped: (map(.skipped) | add // 0)
        })
      | map(.method + " (" + (.ok|tostring) + " ok"
            + (if .failed > 0 then ", " + (.failed|tostring) + " falhou" else "" end)
            + (if .skipped > 0 then ", " + (.skipped|tostring) + " skip" else "" end) + ")")
      | join(", ")
    ' "$json_file" 2>/dev/null || echo "")"

    local failures
    # O título do teste fica no objeto SPEC, não em .tests[] — por isso iteramos
    # .specs[] e lemos .title de lá; a mensagem vem dos results[] do teste unexpected.
    failures="$(jq -r '
      [ .. | .specs? | arrays | .[]
        | select(any(.tests[]?; .status == "unexpected"))
        | "  - " + (.title // "sem título") + ": "
          + ( [ .tests[]? | select(.status == "unexpected") | .results[]?.error.message? // empty ]
              | (.[0] // "sem mensagem de erro") | split("\n")[0] )
      ] | unique | .[]
    ' "$json_file" 2>/dev/null \
      | sed 's/\x1b\[[0-9;]*m//g' \
      || echo "  (não foi possível extrair detalhes via jq)")"

    FAILURE_BLOCKS+=("### ${site} — ${checkout}")
    [ -n "$methods" ] && FAILURE_BLOCKS+=("  _Métodos testados: ${methods}_")
    FAILURE_BLOCKS+=("$failures")

    # PSE-specific note when running on localhost without the --with-pse flag.
    if [ "$site" = "MCO" ] && [ "$WITH_PSE" -eq 0 ]; then
      FAILURE_BLOCKS+=("")
      FAILURE_BLOCKS+=("> ⚠️ **PSE (MCO)** requer \`callback_url\` público — a API do MP rejeita localhost.")
      FAILURE_BLOCKS+=("> Use \`bash e2e/run-pse.sh\` ou a flag \`--with-pse\`.")
    fi
  fi

  CURRENT_SITE=""
  CURRENT_CHECKOUT=""
}

# ── Iterate the full matrix ──────────────────────────────────────────────────
# Pré-calcula o total de combinações que vão rodar para o indicador [N/T]
for site in "${ALL_SITES[@]}"; do
  [ -n "$FILTER_SITE" ] && [ "$site" != "$FILTER_SITE" ] && continue
  for checkout in "${ALL_CHECKOUTS[@]}"; do
    [ -n "$FILTER_CHECKOUT" ] && [ "$checkout" != "$FILTER_CHECKOUT" ] && continue
    COMBINATION_TOTAL=$((COMBINATION_TOTAL + 1))
  done
done
# Com --with-pse o MCO roda numa única passada (run-pse.sh cobre classic+blocks),
# então a 2ª iteração do MCO é pulada. Desconta 1 do total para o indicador [N/T] não
# sugerir uma combinação faltando. Só quando MCO está na matriz E os dois modos rodariam.
if [ "$WITH_PSE" -eq 1 ] \
   && { [ -z "$FILTER_SITE" ] || [ "$FILTER_SITE" = "MCO" ]; } \
   && [ -z "$FILTER_CHECKOUT" ]; then
  COMBINATION_TOTAL=$((COMBINATION_TOTAL - 1))
fi

for site in "${ALL_SITES[@]}"; do
  [ -n "$FILTER_SITE" ] && [ "$site" != "$FILTER_SITE" ] && continue
  for checkout in "${ALL_CHECKOUTS[@]}"; do
    [ -n "$FILTER_CHECKOUT" ] && [ "$checkout" != "$FILTER_CHECKOUT" ] && continue
    # COMBINATION_INDEX é incrementado dentro de run_combination (após os short-circuits),
    # para o indicador [N/T] contar só as combinações que realmente rodam.
    run_combination "$site" "$checkout"
  done
done

# Matriz concluída — desabilita o trap de interrupção. A partir daqui, Ctrl+C
# é apenas para fechar o servidor HTML do Playwright (merge-reports/show-report)
# e não deve gerar relatório "parcial".
trap - INT TERM

exit "$OVERALL_RC"
