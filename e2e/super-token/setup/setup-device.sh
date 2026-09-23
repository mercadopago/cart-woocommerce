#!/usr/bin/env bash
# ============================================================================
# Fase A — Setup do "golden device" para Super Token (idempotente).
# Detecta o que já está pronto e pula; NUNCA destrói um device configurado
# (a não ser RECREATE=1). Salva um snapshot 'golden' reutilizável na Fase B.
# Os passos MANUAIS (login Google/MP, enrollment) são anti-bot — ver docs/limitations.md.
#
# Uso:  ./setup/setup-device.sh <site>          (ex.: mlb)
#       RECREATE=1 ./setup/setup-device.sh mlb   (recria o AVD do zero)
# ============================================================================
set -euo pipefail

SITE="${1:-}"; [ -n "$SITE" ] || { echo "uso: $0 <site> (ex.: mlb)"; exit 1; }
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG="$HERE/config/countries.json"
[ -f "$CONFIG" ] || { echo "!! crie $CONFIG a partir de config/countries.example.json"; exit 1; }

cfg() { python3 -c "import json,sys;print(json.load(open(sys.argv[1]))[sys.argv[2]][sys.argv[3]])" "$CONFIG" "$SITE" "$1"; }
AVD="$(cfg avdName)"; BUYER="$(cfg buyerEmail)"
SYSIMG="system-images;android-36;google_apis_playstore;arm64-v8a"
SNAPSHOT="golden"

export ANDROID_HOME="${ANDROID_HOME:-$HOME/Library/Android/sdk}"
export PATH="$ANDROID_HOME/platform-tools:$ANDROID_HOME/emulator:$ANDROID_HOME/cmdline-tools/latest/bin:$PATH"

log()    { printf '\033[1;34m>>\033[0m %s\n' "$*"; }
ok()     { printf '\033[1;32m  ✓\033[0m %s\n' "$*"; }
manual() { printf '\033[1;33m  ✋ [MANUAL]\033[0m %s\n' "$*"; }
pause()  { read -r -p "     ↳ ENTER quando terminar..." _ || true; }

avd_exists()        { emulator -list-avds 2>/dev/null | grep -qx "$AVD"; }
emu_running()       { adb devices 2>/dev/null | grep -q emulator; }
snapshot_exists()   { [ -d "$HOME/.android/avd/$AVD.avd/snapshots/$SNAPSHOT" ]; }
has_google()        { [ "$(adb shell dumpsys account 2>/dev/null | grep -c 'name=.*@')" -gt 0 ]; }
mp_genuine()        { adb shell pm dump com.mercadopago.wallet 2>/dev/null | grep -q 'installerPackageName=com.android.vending'; }

log "país=$SITE | AVD=$AVD | comprador=$BUYER"

# 1. AVD (não recria se já existe, salvo RECREATE=1) ----------------------------
if avd_exists && [ "${RECREATE:-0}" != "1" ]; then
  ok "AVD '$AVD' já existe (use RECREATE=1 para recriar do zero)."
else
  [ "${RECREATE:-0}" = "1" ] && avd_exists && log "RECREATE=1 → recriando '$AVD'..."
  log "garantindo imagem Play-certified ($SYSIMG)..."
  yes | sdkmanager "$SYSIMG" >/dev/null 2>&1 || true
  log "criando AVD '$AVD' (Pixel 6, Play-certified)..."
  echo "no" | avdmanager create avd -n "$AVD" -k "$SYSIMG" --device "pixel_6" --force >/dev/null
fi

# Habilitar teclado físico do host (padrão do avdmanager é hw.keyboard=no)
AVD_CFG="$HOME/.android/avd/$AVD.avd/config.ini"
if [ -f "$AVD_CFG" ]; then
  # Arquivo temporário com nome aleatório (evita link-following/CWE-377 em /tmp), consistente
  # com setup-store.sh. Recriado a cada filtragem porque o `mv` consome o anterior.
  TMP_CFG="$(mktemp)"
  grep -v "^hw\.keyboard\b" "$AVD_CFG" > "$TMP_CFG" && mv "$TMP_CFG" "$AVD_CFG"
  echo "hw.keyboard=yes" >> "$AVD_CFG"
  ok "teclado físico habilitado (hw.keyboard=yes)"

  # RAM/heap maiores: o perfil pixel_6 sobe com só ~1.5GB, insuficiente para os testes pesados de
  # autorização (Chrome + iframes do SDK + app MP carteira + 2 checkouts) — o guest matava o Chrome
  # e o goto seguinte estourava "Target page/browser has been closed". 4GB dão folga. (A RAM faz
  # parte do snapshot → tem que estar setada ANTES de salvar o golden, por isso só vale ao recriar.)
  TMP_CFG="$(mktemp)"
  grep -vE "^(hw\.ramSize|vm\.heapSize)\b" "$AVD_CFG" > "$TMP_CFG" && mv "$TMP_CFG" "$AVD_CFG"
  echo "hw.ramSize=4096" >> "$AVD_CFG"
  echo "vm.heapSize=512" >> "$AVD_CFG"
  ok "RAM=4096M, heap=512M (folga para os testes de autorização)"
fi

# 2. Boot (preserva config; carrega snapshot se existir; NUNCA -wipe-data aqui) -
if emu_running; then
  ok "emulador já está rodando."
else
  if snapshot_exists; then
    log "bootando '$AVD' do snapshot '$SNAPSHOT'..."
    nohup emulator -avd "$AVD" -snapshot "$SNAPSHOT" > "/tmp/emulator-$AVD.log" 2>&1 &
  else
    log "bootando '$AVD' (cold boot, preservando userdata)..."
    nohup emulator -avd "$AVD" -no-snapshot-load > "/tmp/emulator-$AVD.log" 2>&1 &
  fi
  adb wait-for-device
  until [ "$(adb shell getprop sys.boot_completed 2>/dev/null | tr -d '\r')" = "1" ]; do sleep 3; done
fi
log "device no ar."

# 3. Passos idempotentes (só pede o que falta) ----------------------------------
if has_google; then ok "conta Google já presente.";
else
  adb shell am start -a android.settings.ADD_ACCOUNT_SETTINGS >/dev/null 2>&1 || true
  manual "Logue uma CONTA GOOGLE de teste no emulador (tela 'Add an account')."
  pause
fi

if mp_genuine; then ok "Mercado Pago genuíno (Play Store) já instalado.";
else
  adb shell am start -n com.android.vending/com.google.android.finsky.activities.MainActivity >/dev/null 2>&1 || true
  manual "Na PLAY STORE: instale o 'Mercado Pago' e ATUALIZE o 'Chrome'."
  pause
fi

manual "Confirme no app MP: comprador '$BUYER' LOGADO e com >=1 CARTÃO SALVO elegível (enrolled)."
manual "  (sem isso → 'is_not_simplified_auth'; ver docs/limitations.md.)"
pause

# 4. Validação + snapshot -------------------------------------------------------
log "validando..."
mp_genuine && ok "MP installer = com.android.vending" || manual "MP NÃO veio da Play Store — a detecção pode falhar."
CHROME="$(adb shell dumpsys package com.android.chrome 2>/dev/null | grep -m1 versionName | tr -d ' \r')"
ok "Chrome: ${CHROME#versionName=}"

log "salvando snapshot '$SNAPSHOT' (golden device)..."
adb emu avd snapshot save "$SNAPSHOT" && ok "snapshot salvo." || manual "falha ao salvar snapshot (verifique o emu console)."

log "device '$AVD' pronto. Próximo: ./setup/setup-store.sh $SITE  e  ./run/run-e2e.sh $SITE"
