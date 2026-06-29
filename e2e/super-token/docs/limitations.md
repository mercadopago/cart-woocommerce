# Limitações e o que NÃO dá para automatizar

Esta página é honesta sobre os limites da automação do E2E de Super Token e justifica o
modelo de **2 fases + golden device por país**.

## Por que não dá para automatizar 100% ponta a ponta

Três passos são **resistentes a automação por design** (proteção anti-fraude do Google e
do MP). Não é falta de ferramenta — é intencional:

| Passo | Por que não é automatizável de forma robusta |
|---|---|
| **Login da conta Google no emulador** | O Google bloqueia sign-in automatizado (detecção de bot, captcha, "couldn't sign you in"). Não há via `adb`/CLI oficial para adicionar conta. |
| **Login do comprador no app MP** | Formulário em WebView; pode ter OTP/2FA/risk/device-binding. Frágil e sujeito a bloqueio. |
| **Enrollment do comprador (fast-payment)** | Estado de conta no backend do MP (cartão salvo elegível + opt-in). Provisionado uma vez, fora do nosso controle de automação. |

Tentativas de burlar (injetar token de conta Google, automatizar captcha) são frágeis e
ficam em zona cinza de ToS — **não recomendado**.

## A solução: golden device por país

1. Faz-se a Fase A (os 3 passos acima) **uma vez, manualmente**, por país.
2. Salva-se um **snapshot do emulador** com tudo pronto (conta Google, MP genuíno logado,
   Chrome atualizado, comprador enrolled).
3. A Fase B **boota do snapshot** → device já configurado → **execução 100% automatizada**.

O snapshot converte um custo manual-por-run num **custo de manutenção periódica**.

### Trade-offs do snapshot (honestos)

- **Tokens expiram** (sessão Google/MP) → re-snapshot de tempos em tempos (tipicamente semanas).
- **Updates de Chrome/MP** podem invalidar o fluxo → re-snapshot.
- **No refresh pode bater captcha** → exige humano. Por isso é "quase 100%", com manutenção.
- **Multiplica por país**: 1 device + 1 comprador enrolled por país (ex.: 7 países = 7 setups).

## O que JÁ é 100% automatizado (Fase B)

- Criar/bootar/wipe do AVD; boot a partir do snapshot.
- Subir loja local + túnel cloudflared; allow-list do seller.
- Dirigir o Chrome do emulador via CDP: abrir checkout, preencher email, **validar ST**.
- Finalizar pedido + **biometria** via `adb emu finger touch 1`.

## Alternativas consideradas e descartadas

| Alternativa | Por que não |
|---|---|
| Sideload do APK genuíno (sem Play Store) | Associações/verificação de App Links e descoberta do payment handler ficam frágeis; a Play Store estabelece tudo de forma limpa. |
| Mockar o payment handler (service worker que sempre retorna enrolled) | Automatiza, mas deixa de testar o Super Token de verdade — teste falso. |
| Sauce Labs / device cloud | Ajuda na infra, mas o sign-in Google/MP continua manual/bloqueado. |
| LTP `@webview` (WebView do app via Developer Mode) | Evita a detecção web↔app, mas esbarrou no seletor do Developer Mode e foge da visão real do comprador. |

## Pré-condição que pode ser bloqueante

**Test users podem não ser *enrollable*** para o programa de fast-payment/Super Token.
O enrollment depende de provisionamento no lado do MP. **Antes de escalar**, confirmar com
o time dono do Super Token se há um **comprador de teste provisionado como enrolled** por
país. Sem isso, o fluxo sempre cai em `installed` / `is_not_simplified_auth`,
independentemente do device.
