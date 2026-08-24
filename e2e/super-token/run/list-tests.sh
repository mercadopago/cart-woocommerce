#!/usr/bin/env bash
# ============================================================================
# Lista numerada dos cenários E2E de um país — fonte ÚNICA da numeração.
# Usa `playwright --list` (offline: não boota device nem precisa de túnel) e
# numera 1..N na ordem do Playwright (arquivo → linha), que é determinística.
# Os números só mudam se cenários forem adicionados/removidos/reordenados.
#
# O `--list` aponta a localização na SUITE (`../suites/<grupo>.js`), não na spec
# do país — então rodar "só o #N" é por título (--grep), não por arquivo:linha.
#
# Uso:  ./run/list-tests.sh <site>                imprime a tabela numerada
#       ./run/list-tests.sh <site> --resolve N    imprime o regex de --grep do #N
# ============================================================================
set -euo pipefail

SITE="${1:-}"; [ -n "$SITE" ] || { echo "uso: $0 <site> [--resolve N]" >&2; exit 1; }
MODE="${2:-}"; IDX="${3:-}"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

export NVM_DIR="$HOME/.nvm"
if [ -s "$NVM_DIR/nvm.sh" ]; then
  . "$NVM_DIR/nvm.sh"
  nvm use 22 >/dev/null 2>&1 || echo "[aviso] Node 22 não disponível via nvm — usando $(node -v)" >&2
fi

# Cada teste é uma linha com ' › ': `../suites/<grupo>.js:L:C › <describe> › <título>`.
# (sem `mapfile` — compatível com o bash 3.2 do macOS)
ROWS=()
while IFS= read -r line; do ROWS+=("$line"); done < <(
  cd "$HERE" && npx playwright test "tests/$SITE" --list 2>/dev/null | grep -- ' › '
)
[ "${#ROWS[@]}" -gt 0 ] || { echo "!! nenhum teste encontrado em tests/$SITE" >&2; exit 1; }

leaf()  { printf '%s' "${1##* › }"; }                                   # título após o último ' › '
group() { echo "$1" | grep -oE 'suites/[a-z-]+\.js' | head -1 | sed -E 's#suites/##;s#\.js##'; }

if [ "$MODE" = "--resolve" ]; then
  [[ "$IDX" =~ ^[0-9]+$ ]] && [ "$IDX" -ge 1 ] && [ "$IDX" -le "${#ROWS[@]}" ] \
    || { echo "!! N inválido: use 1..${#ROWS[@]}" >&2; exit 1; }
  # Regex p/ --grep: escapa metacaracteres (tudo que não é alfanumérico/espaço) e ancora no fim,
  # casando o título-folha único do cenário no fim do título completo do teste.
  esc="$(leaf "${ROWS[$((IDX-1))]}" | sed 's/[^a-zA-Z0-9 ]/\\&/g')"
  printf '%s$\n' "$esc"
  exit 0
fi

echo "Super Token E2E — testes de $(echo "$SITE" | tr '[:lower:]' '[:upper:]'):"
i=0
for row in "${ROWS[@]}"; do
  i=$((i+1))
  printf '  %2d  %-14s %s\n' "$i" "$(group "$row")" "$(leaf "$row")"
done
echo ""
echo "Rode um só:  make test SITE=$SITE N=<número>"
