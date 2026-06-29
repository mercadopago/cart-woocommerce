# Como o Super Token funciona (e por que o ambiente precisa ser fiel)

Engenharia reversa do fluxo, feita a partir do SDK v2 do MP (`https://sdk.mercadopago.com/js/v2`)
durante a POC do PSW-3906. Documenta **por que** cada requisito de ambiente existe.

## Cadeia do fluxo (SDK v2)

O passo a passo emitido pelo SDK (enum interno `je`):

```
ParamsValidate → UserFlowsFetch → FlowProcess → ApplicationsDetect → EnrollmentCheck → ...
```

### 1. ApplicationsDetect — "o app está no device?"

Roda o fluxo **PRAPI** numa iframe hospedada no MP
(`https://sdk.mercadopago.com/op-pay/prapi/index.html`). Se nenhum app suportado
(Mercado Pago / Mercado Libre) é detectado:

```
[MERCADO PAGO]: No applications were detected on device. Supported options: Mercado Pago and Mercado Libre.
```

**Implicação:** o app precisa estar instalado **e reconhecível**. O build **sideloaded**
(APK do Meli Store) **falha** aqui: seus `asset_statements` declaram apenas
`delegate_permission/common.share_location` para `www.mercadopago.com` — insuficiente para
a associação web↔app. O app **genuíno da Play Store** tem as associações corretas e é
detectado. (Forçar `pm set-app-links` NÃO resolve — é outro subsistema.)

### 2. EnrollmentCheck — "o comprador tem instrumento elegível?"

Define o `authLevel` via a **W3C Payment Request API**:

```js
const enrolled = await new PaymentRequest([{
    supportedMethods: "https://sdk.mercadopago.com/op-pay/prapi/index.html?app=MP",
    data: { version:"v2", action:"verify", email, publicKey, productId, sessionId, amount, sdkInstanceId, deviceProfileId }
}]).hasEnrolledInstrument();

authLevel = enrolled ? "enrolled" : "installed";   // Le.Enrolled : Le.Installed
```

`hasEnrolledInstrument()` consulta o **app MP como payment handler nativo**
(serviço `IS_READY_TO_PAY` do Android) perguntando: *"o usuário do `email` tem um
instrumento (cartão) elegível para fast-payment?"*

- `true`  → `authLevel = "enrolled"`
- `false` → `authLevel = "installed"`  → erro de fluxo **`is_not_simplified_auth`**
- exceção → `user_enrolled_exception` (problema no handler/manifest)

### 3. getSimplifiedAuth / getFastPaymentToken

```js
getSimplifiedAuth()    { return authLevel === "enrolled"; }      // Dc(e){ return e===Le.Enrolled }
getFastPaymentToken()  { return getSimplifiedAuth() ? token : null; }
```

Só com `authLevel === "enrolled"` o ST renderiza os cartões salvos. Caso contrário, o
checkout cai no Custom Checkout padrão (sem "Pague com um toque").

### 4. authorizePayment (finalização)

Com o token, `authorizePayment(pseudotoken)` dispara a **biometria** no contexto do device
→ grava `authorized_pseudotoken` → backend processa. (No emulador, a biometria é simulável
via `adb emu finger touch 1`.)

## Requisitos de ambiente derivados

| Requisito | Por causa de |
|---|---|
| Imagem `google_apis_playstore` (Play-certified) | PRAPI/Payment Request dependem de Play Services completo (antes do certificado, falhava em `buildAuthenticator`/init) |
| App MP **genuíno (Play Store)** | `ApplicationsDetect` — associações web↔app corretas (sideload falha) |
| Chrome atualizado | `hasEnrolledInstrument()` / Payment Request API |
| Comprador **logado + enrolled** (cartão salvo) | `EnrollmentCheck` — senão `is_not_simplified_auth` |
| Email do checkout = conta logada no app | `hasEnrolledInstrument()` consulta por `email` |
| Seller no allow-list (`account-data-api`) | renderização dos cartões salvos (`getAccountPaymentMethods`) |

## Endpoints observados

- SDK: `https://sdk.mercadopago.com/js/v2`
- PRAPI iframe / payment method: `https://sdk.mercadopago.com/op-pay/prapi/index.html`
- Bundle ST (CDN): `https://http2.mlstatic.com/storage/v1/mercadopago/woocommerce/scripts/v1/super-token.bundle.min.js`
- Métricas frontend: `https://api.mercadopago.com/op-frontend-metrics/v1`
- User-flows / tracking: `https://api.mercadolibre.com/tracks`
