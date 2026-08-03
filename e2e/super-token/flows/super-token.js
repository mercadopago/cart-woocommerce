import { expect } from "@playwright/test";
import { fillBillingData } from "../../flows/fill_steps_to_checkout.js";
import { guestUserMLB, guestUserMLA, guestUserMLM } from "../../data/buyer_data.js";
import { SELECTORS } from "../selectors.js";

// Reusa o place order do e2e/ (trata Classic/Blocks + espera enabled/networkidle p/ não cair
// no recalc de totais do WC Blocks em vez de criar o pedido).
export { placeOrder } from "../../flows/place_order.helper.js";

const { shopUrl } = require("../data/shop.js");

const BASE = shopUrl();
const GUEST_USERS = { mlb: guestUserMLB, mla: guestUserMLA, mlm: guestUserMLM };
const DATADOG_METRIC = "/monitor/v1/event/datadog/big/";

// Motivo de skip quando o país não tem um comprador configurado em countries.json.
export const PENDING_BUYER = "no buyer configured for this country (pending Super Token test user)";

// --- ações --------------------------------------------------------------------

// Adiciona o produto e abre o checkout. O goto de add-to-cart pode ser interrompido por um
// redirect client-side do WC ("interrupted by another navigation") — a mutação do carrinho
// acontece no servidor de qualquer forma, então toleramos a interrupção e seguimos para o checkout.
async function addToCartThenCheckout(page, productId) {
  // add-to-cart dispara um redirect no servidor. Espera a navegação (com o redirect) ASSENTAR
  // (`load`) antes de ir pro checkout — senão o redirect tardio interrompe o goto do /checkout/.
  await page.goto(`${BASE}/?add-to-cart=${productId}`, { waitUntil: "load" }).catch(() => {});
  // Rede-de-segurança p/ a corrida sob carga da suíte: se o redirect tardio do add-to-cart ainda
  // interromper o goto ("interrupted by another navigation"), reintenta uma vez — a essa altura o
  // carrinho já está populado e a navegação assenta. Só reintenta nesse erro específico.
  for (let attempt = 1; attempt <= 2; attempt++) {
    try {
      await page.goto(`${BASE}/checkout/`, { waitUntil: "load" });
      break;
    } catch (e) {
      if (attempt === 2 || !/interrupted by another navigation/.test(e?.message || "")) throw e;
      await page.waitForTimeout(1000);
    }
  }
  // O WC redireciona /checkout/ → /cart/ quando o carrinho está vazio. Se caímos no cart, o
  // add-to-cart não populou o carrinho NESTE host — quase sempre desalinhamento túnel ↔ WP siteurl
  // (o canonical-redirect jogou a sessão noutro host). Falha com a causa explícita em vez de seguir
  // e estourar mais à frente num seletor de pagamento ausente ("ST não renderizou").
  if (/\/cart\/?(?:$|[?#])/.test(page.url())) {
    throw new Error(
      `Checkout redirecionou para o carrinho (carrinho vazio em ${BASE}). ` +
        `Provável desalinhamento túnel ↔ WP siteurl — rode 'make store SITE=<país>' para realinhar.`,
    );
  }
}

// Apenas email → carrega o Super Token (cartões salvos). Para cenários de renderização.
export async function startCustomCheckout(page, buyer) {
  await addToCartThenCheckout(page, buyer.productId);
  await triggerSuperToken(page, buyer.email);
}

// Só abre o checkout, sem interagir. Para cenários onde a UI muda o layout (ex.: Fluid Checkout
// multistep esconde o radio no passo não-atual) e basta verificar presença no DOM.
export async function openCheckout(page, buyer) {
  await addToCartThenCheckout(page, buyer.productId);
}

// Preenche todos os campos (endereço + frete) ANTES de interagir com o ST — necessário para
// concluir o pagamento. Add-to-cart direto (a loja não tem product-loop na home) + reusa o
// fillBillingData do e2e/ com o endereço do guestUser do país e o email da conta MP (carrega o ST).
export async function startCheckoutReadyToPay(page, buyer) {
  const user = { ...GUEST_USERS[buyer.site], email: buyer.email };
  await addToCartThenCheckout(page, buyer.productId);
  // O contexto do Chrome é compartilhado entre testes → o endereço pode já estar preenchido
  // (e recolhido, com os campos invisíveis). Só preenche quando ainda não há endereço.
  if (await isAddressEmpty(page)) await fillBillingData(page, user);
  await ensureRequiredClassicFields(page, user); // CPF + Número não persistem entre testes
  await triggerSuperToken(page, buyer.email);
}

// Classic (extra-checkout-fields-for-brazil): CPF e Número são obrigatórios e nem sempre persistem
// na sessão do WC entre testes (o gate acima só checa o nome). Garante que estejam preenchidos —
// senão o WC bloqueia o place order e o authorizePayment nunca dispara. No Blocks os campos não
// existem (isVisible=false) → no-op.
async function ensureRequiredClassicFields(page, user) {
  const persontype = page.locator("#billing_persontype");
  if (await persontype.isVisible({ timeout: 1000 }).catch(() => false)) {
    if ((await persontype.inputValue().catch(() => "")) !== "1") {
      await persontype.selectOption("1");
      await page.waitForTimeout(500);
    }
    const cpf = page.locator("#billing_cpf");
    if ((await cpf.isVisible().catch(() => false)) && !(await cpf.inputValue().catch(() => ""))) {
      await cpf.fill(user.document || "");
    }
  }
  const number = page.locator("#billing_number");
  if ((await number.isVisible({ timeout: 1000 }).catch(() => false)) && !(await number.inputValue().catch(() => ""))) {
    await number.fill(user.address?.number || "122");
  }
  // Preencher esses campos dispara o update_order_review do WC (AJAX), que re-renderiza a seção
  // de pagamento. Sair do campo + esperar o overlay sumir deixa o AJAX assentar ANTES do gatilho
  // do email — senão o re-render apaga o ST recém-carregado (checkoutType cai p/ custom).
  await number.blur().catch(() => {});
  await page.waitForFunction(() => !document.querySelector(".blockUI.blockOverlay"), { timeout: 8000 }).catch(() => {});
  // Espera determinística: o radio do checkout reaparecer já sinaliza que o re-render do
  // update_order_review assentou. O waitForTimeout fica só como folga residual em máquinas lentas.
  await page.waitForSelector(SELECTORS.customCheckoutRadio, { state: "visible", timeout: 8000 }).catch(() => {});
  await page.waitForTimeout(500);
}

// Reabre o checkout (sem re-adicionar ao carrinho) para uma nova tentativa limpa. Após um
// cancelamento, o ST faz um reset assíncrono na MESMA página e o clique seguinte tokeniza o CVV
// mas não re-invoca a autorização (o app não abre). Uma página nova arma o fluxo do zero, igual
// à 1ª tentativa. O endereço/carrinho persistem no contexto compartilhado do Chrome.
export async function reopenCheckout(page, buyer) {
  const user = { ...GUEST_USERS[buyer.site], email: buyer.email };
  await page.goto(`${BASE}/checkout/`);
  // O reload recarrega o checkout do zero; como o pedido foi cancelado (não criado), CPF/Número
  // não persistem e o endereço pode ter recolhido. Re-garante os campos obrigatórios — senão o
  // place order da 2ª tentativa é barrado e o app de biometria nunca abre.
  if (await isAddressEmpty(page)) await fillBillingData(page, user);
  await ensureRequiredClassicFields(page, user);
  await triggerSuperToken(page, buyer.email);
}

async function isAddressEmpty(page) {
  const name = page.locator("#shipping-first_name, #billing-first_name, #billing_first_name").first();
  if (!(await name.count())) return true;
  return (await name.inputValue().catch(() => "")) === "";
}

export async function fillBuyerEmail(page, email) {
  page.__buyerEmail = email; // guardado p/ a recuperação do render do ST (recoverSuperTokenRender)
  const field = page.locator(SELECTORS.email).first();
  await field.click();
  await field.fill(email); // substitui o valor inteiro (limpa + seta) — nunca duplica
  // Re-digita o último caractere com teclas reais → aciona o listener de input que carrega o ST.
  await field.press("End");
  await field.press("Backspace");
  await field.pressSequentially(email.slice(-1));
}

// Dispara o ST: digita o email (gatilho do listener do SDK) + seleciona o checkout custom. Se não
// renderizar de primeira (corrida do listener async, comum após resetMpApp), a recuperação fica no
// expectSuperTokenVisible — só os cenários que ESPERAM o ST pagam o custo; os de fallback não.
export async function triggerSuperToken(page, email) {
  await fillBuyerEmail(page, email);
  await selectCustomCheckout(page);
}

// Recupera o render do ST quando ele não veio de primeira. O listener de email do SDK anexa async
// (após super_token_sdk_loaded); se o email foi digitado antes, o gatilho se perde e cai no custom.
// (1) re-dispara o email (re-arma o listener); (2) se ainda não vier, alterna p/ outro meio de
// pagamento e volta ao custom — re-renderiza a seção e força o ST a montar (ideia do dev: o mesmo
// mecanismo do cenário "switch and return").
async function recoverSuperTokenRender(page, rounds = 2) {
  const list = page.locator(SELECTORS.savedCardsList);
  for (let i = 0; i < rounds; i++) {
    if (page.__buyerEmail) {
      await fillBuyerEmail(page, page.__buyerEmail);
      await selectCustomCheckout(page);
      if (await list.isVisible({ timeout: 8000 }).catch(() => false)) return;
    }
    if (await selectOtherPaymentMethod(page)) {
      await page.waitForTimeout(1000);
      await selectCustomCheckout(page);
      if (await list.isVisible({ timeout: 8000 }).catch(() => false)) return;
    }
  }
}

// Seleciona um radio de meio de pagamento pelo DOM: o clique por ponteiro é interceptado pelo
// footer/#content desta loja (e o `check({force})` clica no overlay, não muda o estado). Marcar
// `checked` + disparar `change`/`click` aciona o handler do WC (re-render da seção) sem depender
// de hit-test de ponteiro. Retorna se conseguiu marcar.
function selectPaymentRadio(radio) {
  return radio
    .evaluate((el) => {
      if (!el || el.checked) {
        el?.dispatchEvent(new Event("change", { bubbles: true }));
        return !!el;
      }
      el.checked = true;
      el.dispatchEvent(new Event("change", { bubbles: true }));
      el.dispatchEvent(new MouseEvent("click", { bubbles: true }));
      return true;
    })
    .catch(() => false);
}

export async function selectCustomCheckout(page) {
  const radio = page.locator(SELECTORS.customCheckoutRadio).first();
  if (await radio.isChecked().catch(() => false)) return;
  await selectPaymentRadio(radio);
}

// Seleciona o primeiro método de pagamento que não seja o Checkout Custom. Retorna false se não houver.
export async function selectOtherPaymentMethod(page) {
  const radios = page.locator(SELECTORS.paymentMethodRadio);
  for (let i = 0; i < (await radios.count()); i++) {
    const id = (await radios.nth(i).getAttribute("id")) || "";
    if (!id.includes("woo-mercado-pago-custom")) {
      await selectPaymentRadio(radios.nth(i));
      return true;
    }
  }
  return false;
}

export async function selectFirstSavedCard(page) {
  await page.locator(SELECTORS.savedCard).first().click();
}

export async function fillSecurityCode(page, code) {
  // O CVV é um campo seguro do MP: o iframe traz todos os campos do cartão (cardNumber,
  // expiration…), mas o do ST é o #securityCode. Digita por teclas reais (campo seguro).
  // Espera o iframe (re)montar antes de digitar — ao re-selecionar o cartão (ex.: retry após o
  // erro de identidade) o campo é recriado e digitar cedo demais perde o CVV.
  const input = page.frameLocator(`${SELECTORS.securityCode} iframe`).locator("#securityCode");
  try {
    await input.waitFor({ state: "visible", timeout: 10000 });
  } catch {
    return; // cartões com ESC não pedem CVV (o campo nunca monta)
  }
  await input.click();
  await input.pressSequentially(code);
  // Commita o campo seguro: o MP só dispara a validação/tokenização do CVV no blur. Sem isso o
  // place order às vezes corre antes e o validador vê o campo "vazio" ("CVV é obrigatório") mesmo
  // tendo sido digitado. Espera o sinal de CVV preenchido assentar antes de seguir.
  await input.blur().catch(() => {});
  await page.waitForTimeout(500);
}


// Aplica um cupom via formulário UI (Classic ou Blocks). Classic pode ocultar o form atrás de
// .showcoupon — clica no toggle antes de preencher. A submissão dispara o recálculo nativo do WC,
// garantindo que a cadeia de eventos (update_checkout / cartTotal React prop) aconteça de verdade.
export async function applyCoupon(page, couponCode) {
  const toggle = page.locator('.showcoupon');
  if (await toggle.isVisible({ timeout: 2000 }).catch(() => false)) {
    await toggle.click();
    await page.waitForTimeout(500);
  }
  const input = page.locator(SELECTORS.couponInput).first();
  await input.waitFor({ state: 'visible', timeout: 8000 });
  await input.fill(couponCode);
  await page.locator(SELECTORS.couponApply).first().click();
}

// Confirma que o WC recalculou: espera o overlay aparecer e sumir (Classic).
// Usa waitForFunction (DOM-level) para evitar strict mode — o WC pode ter múltiplos
// .blockUI.blockOverlay no mesmo form (coupon, order-review, payment). No Blocks o
// overlay não existe: o .catch no visible é intencional; o hidden resolve imediatamente.
export async function expectCheckoutLoading(page, timeout = 10000) {
  const sel = SELECTORS.checkoutOverlay;
  await page.waitForFunction((s) => !!document.querySelector(s), sel, { timeout }).catch(() => {});
  await page.waitForFunction((s) => !document.querySelector(s), sel, { timeout: 15000 });
}

// Email de outra conta (local-part trocado) → hasEnrolledInstrument retorna false → sem ST.
// Derivado em runtime do domínio do comprador (nenhum email literal no código-fonte).
export const notEnrolledEmail = (email) => email.replace(/^[^@]+/, `not-enrolled-${Date.now()}`);

// --- asserções ----------------------------------------------------------------

export async function expectSuperTokenVisible(page, timeout = 25000) {
  const list = page.locator(SELECTORS.savedCardsList);
  if (await list.isVisible({ timeout }).catch(() => false)) return;
  // Não veio de primeira (corrida do render do SDK): recupera (re-dispara email + alterna meio de
  // pagamento) antes de desistir — só os cenários que esperam o ST pagam isso.
  await recoverSuperTokenRender(page);
  try {
    await expect(list).toBeVisible({ timeout: 12000 });
  } catch (error) {
    // Diagnóstico: por que o ST não renderizou? (selector errado? SDK não carregou? não elegível?)
    const dom = await page
      .evaluate(() => ({
        superTokenEls: document.querySelectorAll("[class*=super-token],[class*=one-tap]").length,
        mpIframes: document.querySelectorAll("iframe[src*=mercadopago],iframe[src*=mercadolibre]").length,
        checkoutType: document.getElementById("mp_checkout_type")?.value || "(none)",
        hasCustomRadio: !!document.querySelector(
          "#radio-control-wc-payment-method-options-woo-mercado-pago-custom, #payment_method_woo-mercado-pago-custom",
        ),
      }))
      .catch(() => null);
    const signals = (page.__superTokenSignals || []).slice(-8);
    throw new Error(`Super Token saved-cards list not visible. DOM=${JSON.stringify(dom)} SDK signals=${JSON.stringify(signals)}`);
  }
}

// Erro de identidade do ST (notice laranja exibido após cancelar a biometria no app).
// .first(): o notice e o <span> de texto interno casam juntos — pega o notice (vem antes no DOM).
export async function expectIdentityError(page, timeout = 60000) {
  await expect(
    page.locator(SELECTORS.errorNotice).or(page.getByText(/não foi possível validar sua identidade/i)).first(),
  ).toBeVisible({ timeout });
}

export async function expectCustomCheckoutWithoutSuperToken(page) {
  await expect(page.locator(SELECTORS.customCheckoutRadio).first()).toBeVisible();
  // Espera o form de cartão padrão montar (iframe ANEXADO — pode estar oculto se o painel está
  // recolhido, então não exigimos visível) antes de afirmar a ausência do ST.
  await expect(page.locator(SELECTORS.mpIframe).first()).toBeAttached({ timeout: 20000 });
  await expect(page.locator(SELECTORS.savedCardsList)).toHaveCount(0);
}

export async function expectOrderReceived(page, timeout = 40000) {
  // Mesma detecção do chocustom: navega para /order-received/ e mostra o thank-you.
  await page.waitForURL(/order-received/, { waitUntil: "domcontentloaded", timeout });
  await expect(page.locator(".woocommerce-thankyou-order-received")).toBeVisible({ timeout: 30000 });
}

// Aguarda o POST de uma métrica específica do Super Token (evento Datadog). timeout maior para
// métricas que só saem depois de um fluxo lento (ex.: a de cancelamento, após abrir o app MP).
export function expectMetric(page, name, timeout = 20000) {
  return page.waitForRequest((req) => req.url().includes(`${DATADOG_METRIC}${name}`), { timeout });
}

// Contador de métricas observadas na rede (para asserções de reuso / ausência).
export function recordMetrics(page) {
  const seen = [];
  page.on("request", (req) => {
    const match = req.url().match(/\/monitor\/v1\/event\/datadog\/big\/([^?#/]+)/);
    if (match) seen.push(match[1]);
  });
  return { count: (name) => seen.filter((n) => n === name).length };
}
