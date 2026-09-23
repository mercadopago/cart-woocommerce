# E2E — Limitações de ambiente (não são bugs de produto)

Alguns cenários não passam no E2E local por **limitações de ambiente, credenciais ou
plataforma** — não por bug do plugin. Estão documentados aqui para que uma falha vermelha
signifique sempre **regressão ou instabilidade real**.

## 1. Débito no MLB — não suportado no checkout online
O seller/checkout online do MLB **não oferece `debit_card`** (confirmado pelo suporte MeLi:
o débito MLB existe apenas em InStore/Tap-to-Pay, não no checkout web). A API retorna
`not_result_by_params`.
- **Spec:** `tests/mlb/chocustom/debit_card_payment_binary_off.spec.js` está com `test.describe.skip`.
- **Reabrir:** só se o débito for ativado no checkout online do MLB.

## 2. PSE (MCO) — executável localmente via `run-pse.sh` (resolvido em PSW-4206)
`PseTransaction` define `callback_url` = URL de retorno do WC (`WC_Order::get_checkout_order_received_url()`).
Em `localhost` o MP rejeita (`"callback_url attribute must be url valid"`). Diferente do
`notification_url`, o `callback_url` **não** aplica `_mp_custom_domain`.

- **Solução (PSW-4206):** `run-pse.sh` instala um mu-plugin temporário que intercepta o
  filtro `woocommerce_get_checkout_order_received_url` (o filtro exato do WooCommerce, **não**
  `home_url()` global) e substitui `localhost` por `https://e2e-test.example.com`. O MP
  valida apenas o **formato** da URL — o domínio não precisa ser acessível. O mu-plugin é
  removido automaticamente via `trap cleanup EXIT`.
- **Como rodar:** `bash e2e/run-pse.sh` (standalone) ou `--with-pse` / `--release` no runner.
- **Manutenção:** o `run-pse.sh` usa uma allowlist explícita de diretórios MCO na fase 1
  (`chocustom/`, `chopro/`, `ticket/`). Novos diretórios de teste MCO precisam ser adicionados
  manualmente — caso contrário ficam fora da cobertura do `--with-pse` / `--release` sem
  nenhum sinal de erro no relatório.
- O PSE também depende do serviço **BankTransfers** do MP, que pode dar
  `"BankTransfers Timeout"` transitório — absorvido por `retries`.

## 3. Ticket/boleto (MCO efecty, MPE pagoefectivo) — depende do serviço beta de payment-methods
O gateway de ticket busca os métodos em `/ppcore/{beta|prod}/payment-methods`. Se o **beta**
estiver fora do ar (já ocorreu: 503/424), o plugin **zera os caches** e o gateway não
renderiza. Com o beta saudável (200), funciona normalmente em sandbox.
- Em **prod**, o pagamento dá `"Unauthorized use of live credentials"` (credenciais de app de
  teste não são autorizadas para live) — esperado.
- Observação de produto (não bloqueia): `Seller::updatePaymentMethods()` zera todos os caches
  de meios de pagamento em qualquer resposta não-200 — candidato a melhoria de resiliência (PSW).

## 4. Cartões de teste são por país
Os cartões de teste do MP são **específicos por site**. Usar um cartão de outro país faz a
tokenização/pagamento falhar (ex.: cartão BR numa loja AR não solicita o documento → recusa).
- Variáveis por país no `.env`: `CC_*_MLA`, `CC_*_MCO`, `DC_*_MCO`, etc.

## 5. Modo de execução
- Sandbox (`MP_ENV=test`, padrão): cartões de teste funcionam.
- Prod (`MP_ENV=prod`): cartões de teste são recusados (cartão de teste em prod) — use só para
  validar o caminho/branch de prod, não para aprovar pagamentos.
- Config: `workers: 2` e `retries: 2` locais (reduz contenção na store única e absorve
  instabilidade do sandbox externo do MP).
