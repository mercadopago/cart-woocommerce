#!/usr/bin/env bash
# ============================================================================
# Cadastra PIN + digital no golden device e re-salva o snapshot 'golden'.
# Necessário para os cenários de autorização (B8/B16), que autorizam o pagamento
# via biometria (adb emu finger touch só funciona com uma digital cadastrada).
# Roda UMA vez por device. Uso: ./setup/setup-biometrics.sh <site>
# ============================================================================
set -euo pipefail

SITE="${1:-}"; [ -n "$SITE" ] || { echo "uso: $0 <site> (ex.: mlb)"; exit 1; }
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG="$HERE/config/countries.json"
[ -f "$CONFIG" ] || { echo "!! crie $CONFIG a partir de config/countries.example.json"; exit 1; }

cfg() { python3 -c "import json,sys;print(json.load(open(sys.argv[1]))[sys.argv[2]].get(sys.argv[3],''))" "$CONFIG" "$SITE" "$1"; }
AVD="$(cfg avdName)"; SNAPSHOT="golden"; PIN="1234"

export ANDROID_HOME="${ANDROID_HOME:-$HOME/Library/Android/sdk}"
export PATH="$ANDROID_HOME/platform-tools:$ANDROID_HOME/emulator:$ANDROID_HOME/cmdline-tools/latest/bin:$PATH"
log()    { printf '\033[1;34m>>\033[0m %s\n' "$*"; }
ok()     { printf '\033[1;32m  ✓\033[0m %s\n' "$*"; }
manual() { printf '\033[1;33m  ✋ [MANUAL]\033[0m %s\n' "$*"; }
pause()  { read -r -p "     ↳ ENTER quando terminar..." _ || true; }

# Boota o golden se não estiver no ar ------------------------------------------
if ! adb devices | grep -q emulator; then
  log "bootando '$AVD' do snapshot '$SNAPSHOT'..."
  nohup emulator -avd "$AVD" -snapshot "$SNAPSHOT" > "/tmp/emulator-bio-$AVD.log" 2>&1 &
  adb wait-for-device
  until [ "$(adb shell getprop sys.boot_completed 2>/dev/null | tr -d '\r')" = "1" ]; do sleep 3; done
fi
log "device pronto ($AVD). PIN usado nos testes: $PIN"

# 1. PIN de bloqueio (pré-requisito da digital no Android) ----------------------
log "definindo PIN de bloqueio ($PIN)..."
if adb shell locksettings set-pin "$PIN" >/dev/null 2>&1; then
  ok "PIN definido via locksettings."
else
  manual "Defina manualmente um PIN '$PIN' em Ajustes → Segurança → Bloqueio de tela."
  pause
fi

# 2. Cadastro da digital (guiado + toques automáticos) -------------------------
# ATENÇÃO: o cadastro TEM que ser feito pelos toques que este script envia (adb emu finger
# touch 1). NÃO use o botão "Touch Sensor" do Extended Controls — a GUI usa uma numeração de
# dedo diferente do `adb emu finger`, e a digital cadastrada por ali NUNCA autentica nos testes
# (o `emu finger touch 1` é rejeitado com wasSuccessful=false).
adb shell am start -a android.settings.SECURITY_SETTINGS >/dev/null 2>&1 || true
manual "Em Segurança → Impressão digital → 'Adicionar digital' (informe o PIN $PIN)."
manual "Quando a tela pedir para TOCAR o sensor, dê ENTER aqui que eu envio os toques (NÃO toque pela GUI)."
pause
log "enviando toques no sensor..."
for _ in $(seq 1 12); do adb emu finger touch 1 >/dev/null 2>&1 || true; sleep 1; done
manual "Conclua o cadastro (Concluir/Done). Se faltaram toques, rode de novo. ENTER quando salvo."
pause

# 3. Re-salva o snapshot golden (agora com PIN + digital) -----------------------
log "re-salvando snapshot '$SNAPSHOT' (golden com biometria)..."
adb emu avd snapshot save "$SNAPSHOT" && ok "snapshot atualizado." || manual "falha ao salvar snapshot."
log "pronto. Rode: make test SITE=$SITE GROUP=authorization"
