const { execSync } = require("child_process");

function adb(args) {
  try {
    return execSync(`adb ${args}`, { encoding: "utf-8", timeout: 15000, stdio: ["ignore", "pipe", "ignore"] });
  } catch {
    return "";
  }
}

function focusedPackage() {
  // `dumpsys window` (não `dumpsys window windows`, que nesta versão do Android não traz a
  // linha mCurrentFocus) → "mCurrentFocus=Window{hash u0 <pacote>/<activity>}".
  const out = adb("shell dumpsys window");
  const match = out.match(/mCurrentFocus.*?([a-zA-Z0-9_.]+)\//) || out.match(/mFocusedApp.*?([a-zA-Z0-9_.]+)\//);
  return match ? match[1] : "";
}

// Tela de bloqueio (keyguard) no ar? `mDreamingLockscreen=true` é o sinal mais estável no emulador;
// reforça com "keyguard showing". Retorna false quando não conseguimos ler o dumpsys — assim nunca
// arriscamos digitar o PIN às cegas (ele só é digitado quando temos CERTEZA de que está bloqueado).
function isLocked() {
  const out = adb("shell dumpsys window");
  if (!out) return false;
  return /mDreamingLockscreen=true/.test(out) || /(?:isKeyguardShowing|KeyguardShowing)=true/.test(out);
}

// Polling assíncrono: libera o event loop entre checagens para o Playwright continuar
// processando eventos da página (Payment Request, CDP, etc.).
function waitForAppAsync(pkg, timeoutMs = 30000, intervalMs = 500) {
  return new Promise((resolve) => {
    const deadline = Date.now() + timeoutMs;
    const timer = setInterval(() => {
      if (focusedPackage() === pkg) { clearInterval(timer); resolve(true); return; }
      if (Date.now() >= deadline) { clearInterval(timer); resolve(false); }
    }, intervalMs);
  });
}

const MP_PACKAGE = "com.mercadopago.wallet";
const CHROME_PACKAGE = "com.android.chrome";

// PIN de fallback do golden (mesmo de setup-biometrics.sh). Override via env se mudar.
const LOCK_PIN = process.env.ST_LOCK_PIN || "1234";

// Acorda a tela e desbloqueia. A biometria exige PIN lock; mesmo com `svc power stayon`, em runs
// longos o keyguard re-engata (e o lockout após N biometrias falhas força a tela de PIN). O
// `dismiss-keyguard` só vence keyguard INSEGURO — quando o seguro está no ar é preciso DIGITAR o
// PIN, senão os toques do sensor batem na tela de bloqueio e o teste fica preso "celular bloqueado".
// Só digita o PIN quando há certeza de bloqueio (isLocked) — nunca às cegas, p/ não poluir a home
// com "1234" nas chamadas pré-goto. Idempotente e best-effort.
function wakeAndUnlock() {
  adb("shell input keyevent KEYCODE_WAKEUP");
  adb("shell wm dismiss-keyguard");
  if (!isLocked()) return;
  adb("shell input swipe 540 1600 540 600"); // revela a entrada de PIN
  adb(`shell input text ${LOCK_PIN}`);
  adb("shell input keyevent 66"); // ENTER → confirma o PIN
}

// Traz o Chrome de volta ao primeiro plano. Essencial após interagir com o app MP: o
// authorizePayment (Payment Request) só invoca o app quando a aba que o chama está em foreground.
function bringChromeToFront(timeoutMs = 10000) {
  wakeAndUnlock(); // garante tela acordada + sem keyguard antes de trazer o Chrome
  adb("shell monkey -p com.android.chrome -c android.intent.category.LAUNCHER 1");
  return waitForAppAsync(CHROME_PACKAGE, timeoutMs);
}

module.exports = {
  // Aprova a biometria: espera o app MP abrir (async) e toca o sensor até o Chrome reassumir.
  // O prompt do app MP só passa a "escutar" o sensor ~1-2s depois que o app vem ao primeiro
  // plano, então um único toque se perde. Reenvia o mesmo dedo (sem risco de bloqueio) em loop.
  // fingerId deve corresponder a uma digital cadastrada (Extended Controls → Fingerprint).
  // Falha rápido: se o app não vier ao 1º plano em appTimeoutMs (o place order não disparou o
  // Payment Request), retorna false sem pendurar. Total no pior caso ≈ appTimeoutMs + touchWindowMs.
  async approveBiometrics(fingerId = 1, appTimeoutMs = 15000, touchWindowMs = 12000) {
    wakeAndUnlock(); // keyguard seguro na frente impede o prompt do MP de assumir o foreground
    if (!await waitForAppAsync(MP_PACKAGE, appTimeoutMs)) return false;
    const deadline = Date.now() + touchWindowMs;
    await new Promise((r) => setTimeout(r, 1500));
    while (Date.now() < deadline) {
      wakeAndUnlock(); // re-lock no meio da janela mandaria os toques p/ a tela de bloqueio (no-op se já destravado)
      adb(`emu finger touch ${fingerId}`);
      if (await waitForAppAsync(CHROME_PACKAGE, 2500)) return true;
    }
    return false;
  },

  // Cancela a biometria: espera o app MP abrir (async), pressiona BACK e devolve o Chrome ao
  // primeiro plano — senão um retry seguinte não consegue reinvocar o app MP (Payment Request
  // só abre o app a partir de uma aba em foreground). Falha rápido se o app não abrir em timeoutMs.
  async cancelBiometrics(timeoutMs = 15000) {
    if (!await waitForAppAsync(MP_PACKAGE, timeoutMs)) return false;
    adb("shell input keyevent KEYCODE_BACK");
    await bringChromeToFront();
    return true;
  },

  // Abre o app MP e devolve o Chrome ao primeiro plano (reseta o estado de autorização entre
  // testes). Reabrir o Chrome é essencial: sem isso a aba fica em background e o page.goto
  // seguinte trava, porque o Chrome estrangula abas em segundo plano.
  async resetMpApp() {
    adb("shell monkey -p com.mercadopago.wallet -c android.intent.category.LAUNCHER 1");
    await new Promise((r) => setTimeout(r, 3000));
    await bringChromeToFront();
  },

  // Acorda a tela + dispensa o keyguard. Exposto p/ o fixture chamar antes do 1º goto de cada teste.
  wakeAndUnlock,
};
