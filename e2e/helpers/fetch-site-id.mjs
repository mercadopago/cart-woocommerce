import { fileURLToPath } from 'node:url';

const ALLOWED_STORE_HOST = /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.ppolimpo\.io$/i;
const MAX_REDIRECTS = 5;
const MAX_HTML_BYTES = 2 * 1024 * 1024;
const REDIRECT_STATUSES = new Set([301, 302, 303, 307, 308]);

export function checkoutIdentityUrl(rawUrl) {
  let url;
  try {
    url = new URL(rawUrl);
  } catch {
    throw new Error('[E2E] SHOP_URL invalida para detectar o site_id.');
  }
  if (
    url.protocol !== 'https:'
    || url.username
    || url.password
    || url.port
    || url.search
    || url.hash
    || !ALLOWED_STORE_HOST.test(url.hostname)
  ) {
    throw new Error('[E2E] SHOP_URL fora da allowlist para detectar o site_id.');
  }
  return new URL('/checkout/', url.origin);
}

function assertSameOriginRedirect(location, currentUrl, allowedOrigin) {
  if (!location) throw new Error('[E2E] Redirect sem Location ao detectar o site_id.');
  const nextUrl = new URL(location, currentUrl);
  if (nextUrl.username || nextUrl.password) {
    throw new Error('[E2E] Redirect com credenciais bloqueado ao detectar o site_id.');
  }
  if (nextUrl.origin !== allowedOrigin) {
    throw new Error('[E2E] Redirect cross-origin bloqueado ao detectar o site_id.');
  }
  return nextUrl;
}

export async function fetchStoreSiteId(rawUrl, dependencies = {}) {
  const fetchImpl = dependencies.fetchImpl || fetch;
  const timeoutMs = dependencies.timeoutMs || 15000;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  let currentUrl = checkoutIdentityUrl(rawUrl);
  const allowedOrigin = currentUrl.origin;

  try {
    for (let redirectCount = 0; redirectCount <= MAX_REDIRECTS; redirectCount += 1) {
      const response = await fetchImpl(currentUrl, {
        redirect: 'manual',
        signal: controller.signal,
      });
      if (REDIRECT_STATUSES.has(response.status)) {
        if (redirectCount === MAX_REDIRECTS) {
          throw new Error('[E2E] Limite de redirects excedido ao detectar o site_id.');
        }
        currentUrl = assertSameOriginRedirect(
          response.headers.get('location'),
          currentUrl,
          allowedOrigin
        );
        continue;
      }
      if (!response.ok) {
        throw new Error(`[E2E] Checkout indisponivel ao detectar o site_id (HTTP ${response.status}).`);
      }
      const declaredLength = Number(response.headers.get('content-length') || 0);
      if (declaredLength > MAX_HTML_BYTES) {
        throw new Error('[E2E] Resposta grande demais ao detectar o site_id.');
      }
      const html = await response.text();
      if (Buffer.byteLength(html, 'utf8') > MAX_HTML_BYTES) {
        throw new Error('[E2E] Resposta grande demais ao detectar o site_id.');
      }
      const match = html.match(/"site_id":"([A-Za-z]{3})"/);
      if (!match) throw new Error('[E2E] site_id ausente no checkout da loja externa.');
      return match[1].toUpperCase();
    }
  } finally {
    clearTimeout(timer);
  }
  throw new Error('[E2E] Nao foi possivel detectar o site_id da loja externa.');
}

async function main() {
  const rawUrl = process.argv[2];
  if (!rawUrl) throw new Error('[E2E] SHOP_URL obrigatoria para detectar o site_id.');
  process.stdout.write(await fetchStoreSiteId(rawUrl));
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  main().catch((error) => {
    process.stderr.write(`${error.message?.startsWith('[E2E]') ? error.message : '[E2E] Falha ao detectar o site_id.'}\n`);
    process.exitCode = 1;
  });
}
