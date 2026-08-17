#!/usr/bin/env bash
#
# rerun-failed.sh — re-executa APENAS os testes que falharam no último run completo
# (./run-all-report.sh de 2026-08-12). Agrupa por país+checkout para minimizar os
# resets de store (~30s cada) e roda com workers=1 (seguro para os @serial-store:
# refund / amount_config).
#
# Uso:
#   ./rerun-failed.sh                 # roda todas as falhas em TEST mode (sandbox)
#   ./rerun-failed.sh MLB             # roda só as falhas de um país
#   ./rerun-failed.sh MLB MLU         # roda as falhas de vários países
#   MP_ENV=prod ./rerun-failed.sh     # roda em PROD (credenciais APP_USR- do .env)
#   MP_ENV=prod ./rerun-failed.sh MCO # combina os dois
#
# Editar a lista: cada entrada de RERUN é "SITE|CHECKOUT|alvo1 alvo2 ...", onde o
# alvo é no formato Playwright arquivo:linha. Ajuste conforme o run mais recente.

set -uo pipefail
cd "$(dirname "$0")"

MP_ENV="${MP_ENV:-test}"

# Ordenado para agrupar o mesmo SITE em sequência (classic antes de blocks) e evitar
# reset de Docker desnecessário entre checkouts do mesmo país.
# NB: NÃO usar o nome "GROUPS" — é uma variável especial read-only do bash (GIDs do
# usuário). Usamos RERUN.
RERUN=(
  # ---- MLA ----
  "MLA|classic|tests/mla/ticket/ticket_payment.spec.js:16"

  # ---- MLB ---- (cartão/pix approved + @serial-store refund/amount_config)
  "MLB|classic|tests/mlb/chocustom/credit_card_payment_binary_off.spec.js:12 tests/mlb/chocustom/credit_card_payment_binary_off.spec.js:16 tests/mlb/chocustom/credit_card_payment_binary_off.spec.js:20 tests/mlb/pix/pix_payment.spec.js:8 tests/mlb/chocustom/refund.spec.js:75 tests/mlb/chocustom/refund.spec.js:150 tests/mlb/chocustom/amount_config.spec.js:94"
  "MLB|blocks|tests/mlb/chocustom/checkout_validation_gate.spec.js:32 tests/mlb/pix/pix_payment.spec.js:8 tests/mlb/chocustom/refund.spec.js:51 tests/mlb/chocustom/refund.spec.js:150 tests/mlb/chocustom/amount_config.spec.js:75"

  # ---- MLC ----
  "MLC|classic|tests/mlc/chocustom/chocustom_credit_card_payment_binary_off.spec.js:25"

  # ---- MCO ---- (debit visa approved/pending — falhou em classic E blocks)
  "MCO|classic|tests/mco/chocustom/chocustom_debit_card_payment_binary_off.spec.js:16 tests/mco/chocustom/chocustom_debit_card_payment_binary_off.spec.js:20"
  "MCO|blocks|tests/mco/chocustom/chocustom_debit_card_payment_binary_off.spec.js:16 tests/mco/chocustom/chocustom_debit_card_payment_binary_off.spec.js:20"

  # ---- MLU ----
  "MLU|classic|tests/mlu/chocustom/credit_card_payment_binary_off.spec.js:17 tests/mlu/chocustom/credit_card_payment_binary_off.spec.js:29"
  "MLU|blocks|tests/mlu/chocustom/credit_card_payment_binary_off.spec.js:17 tests/mlu/chocustom/credit_card_payment_binary_off.spec.js:29 tests/mlu/chocustom/credit_card_payment_binary_off.spec.js:33"

  # ---- MPE ----
  "MPE|classic|tests/mpe/ticket/ticket_payment.spec.js:12"
  # MPE blocks: o log do run foi truncado no bloco 14/14. Se o ticket falhou lá também,
  # descomente a linha abaixo:
  # "MPE|blocks|tests/mpe/ticket/ticket_payment.spec.js:12"
)

# Filtro opcional por país (args). Sem args = todos.
FILTER=("$@")
site_selected() {
  [ "${#FILTER[@]}" -eq 0 ] && return 0
  local s="$1"
  for f in "${FILTER[@]}"; do
    [ "$(echo "$f" | tr '[:lower:]' '[:upper:]')" = "$s" ] && return 0
  done
  return 1
}

echo "════════════════════════════════════════════════════════════"
echo " Re-run das falhas | MP_ENV=$MP_ENV | filtro=${FILTER[*]:-<todos>}"
echo "════════════════════════════════════════════════════════════"

declare -a PASSED_GROUPS=()
declare -a FAILED_GROUPS=()

for entry in "${RERUN[@]}"; do
  IFS='|' read -r SITE CHECKOUT TARGETS <<< "$entry"
  site_selected "$SITE" || continue

  echo ""
  echo "────── $SITE — $CHECKOUT ──────"
  # shellcheck disable=SC2086
  if SITE="$SITE" CHECKOUT="$CHECKOUT" MP_ENV="$MP_ENV" \
       npx playwright test $TARGETS --workers=1 --reporter=list; then
    PASSED_GROUPS+=("$SITE/$CHECKOUT")
  else
    FAILED_GROUPS+=("$SITE/$CHECKOUT")
  fi
done

echo ""
echo "════════════════════════════════════════════════════════════"
echo " Resumo do re-run (MP_ENV=$MP_ENV)"
echo "════════════════════════════════════════════════════════════"
echo " Grupos OK      : ${#PASSED_GROUPS[@]}  ${PASSED_GROUPS[*]:-—}"
echo " Grupos c/ falha: ${#FAILED_GROUPS[@]}  ${FAILED_GROUPS[*]:-—}"
echo "────────────────────────────────────────────────────────────"

# exit 1 se algum grupo ainda falhou (útil para CI / encadeamento)
[ "${#FAILED_GROUPS[@]}" -eq 0 ]
