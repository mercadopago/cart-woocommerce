const fs = require('fs');
const net = require('net');
const path = require('path');

const E2E_DIR = path.resolve(__dirname, '..');
const CONFIG_DIR = path.join(E2E_DIR, 'config');
const DEFAULT_CONFIG_PATH = path.join(CONFIG_DIR, 'environments.json');
const VALID_ENVIRONMENTS = new Set(['staging', 'homol']);
const VALID_CHECKOUTS = new Set(['classic', 'blocks']);
const VALID_SITES = new Set(['MLA', 'MLB', 'MLC', 'MLM', 'MCO', 'MLU', 'MPE']);
const SAFE_HOSTNAME = /^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/;
const SAFE_IDENTIFIER = /^[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}$/;

function resolveConfigPath(requestedPath = process.env.E2E_STORE_CONFIG_PATH) {
  const resolved = path.resolve(requestedPath || DEFAULT_CONFIG_PATH);
  const relative = path.relative(CONFIG_DIR, resolved);

  if (relative.startsWith('..') || path.isAbsolute(relative) || path.extname(resolved) !== '.json') {
    throw new Error('[E2E] O arquivo de ambientes deve ser um JSON dentro de e2e/config.');
  }

  return resolved;
}

function assertPlainObject(value, label) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    throw new Error(`[E2E] Configuracao invalida: ${label}.`);
  }
}

function validateHostname(hostname, label) {
  const normalized = String(hostname || '').toLowerCase();
  if (!SAFE_HOSTNAME.test(normalized) || net.isIP(normalized)) {
    throw new Error(`[E2E] Host invalido em ${label}.`);
  }
  return normalized;
}

function validateUrl(rawUrl, allowedHosts, label) {
  let parsed;
  try {
    parsed = new URL(rawUrl);
  } catch {
    throw new Error(`[E2E] URL invalida em ${label}.`);
  }

  if (
    parsed.protocol !== 'https:'
    || parsed.username
    || parsed.password
    || parsed.search
    || parsed.hash
    || !allowedHosts.has(parsed.hostname.toLowerCase())
  ) {
    throw new Error(`[E2E] URL nao permitida em ${label}.`);
  }

  return parsed.toString();
}

function validateConfig(config) {
  assertPlainObject(config, 'raiz');
  if (config.version !== 1) {
    throw new Error('[E2E] Versao de configuracao de ambientes nao suportada.');
  }
  if (!Array.isArray(config.allowedHosts) || config.allowedHosts.length === 0) {
    throw new Error('[E2E] allowedHosts deve conter ao menos um host.');
  }

  const allowedHosts = new Set(config.allowedHosts.map((host, index) => (
    validateHostname(host, `allowedHosts[${index}]`)
  )));
  if (allowedHosts.size !== config.allowedHosts.length) {
    throw new Error('[E2E] allowedHosts contem entradas duplicadas.');
  }

  assertPlainObject(config.lock, 'lock');
  const lockHost = validateHostname(config.lock.sshHost, 'lock.sshHost');
  if (!allowedHosts.has(lockHost)) {
    throw new Error('[E2E] lock.sshHost deve estar em allowedHosts.');
  }
  if (!SAFE_IDENTIFIER.test(config.lock.sshUser || '') || !SAFE_IDENTIFIER.test(config.lock.name || '')) {
    throw new Error('[E2E] Usuario ou nome de lock invalido.');
  }
  if (!Number.isInteger(config.lock.ttlSeconds) || config.lock.ttlSeconds < 300 || config.lock.ttlSeconds > 21600) {
    throw new Error('[E2E] lock.ttlSeconds deve estar entre 300 e 21600.');
  }
  if (
    !Number.isInteger(config.lock.heartbeatSeconds)
    || config.lock.heartbeatSeconds < 30
    || config.lock.heartbeatSeconds > 900
    || config.lock.heartbeatSeconds * 3 >= config.lock.ttlSeconds
  ) {
    throw new Error('[E2E] lock.heartbeatSeconds deve estar entre 30 e 900 e abaixo de um terco do TTL.');
  }

  assertPlainObject(config.environments, 'environments');
  for (const environment of VALID_ENVIRONMENTS) {
    const environmentConfig = config.environments[environment];
    assertPlainObject(environmentConfig, `environments.${environment}`);
    const expectedRole = environment === 'staging' ? 'candidate' : 'baseline';
    if (environmentConfig.role !== expectedRole) {
      throw new Error(`[E2E] Papel invalido para ${environment}.`);
    }
    assertPlainObject(environmentConfig.sites, `environments.${environment}.sites`);
    const configuredSites = Object.keys(environmentConfig.sites);
    if (
      configuredSites.length !== VALID_SITES.size
      || [...VALID_SITES].some((site) => !configuredSites.includes(site))
    ) {
      throw new Error(`[E2E] ${environment} deve configurar os sete sites suportados.`);
    }

    for (const [site, siteConfig] of Object.entries(environmentConfig.sites)) {
      assertPlainObject(siteConfig, `${environment}.${site}`);
      assertPlainObject(siteConfig.checkoutUrls, `${environment}.${site}.checkoutUrls`);
      const expectedContainerName = `wp-${environment}-${site.toLowerCase()}`;
      if (
        !SAFE_IDENTIFIER.test(siteConfig.containerName || '')
        || siteConfig.containerName !== expectedContainerName
      ) {
        throw new Error(`[E2E] Container invalido em ${environment}/${site}.`);
      }

      const shopUrl = validateUrl(siteConfig.shopUrl, allowedHosts, `${environment}.${site}.shopUrl`);
      const shopOrigin = new URL(shopUrl).origin;
      const expectedOrigin = `https://e2e-${environment}-${site.toLowerCase()}.ppolimpo.io`;
      if (shopOrigin !== expectedOrigin) {
        throw new Error(`[E2E] Origem inesperada em ${environment}/${site}.`);
      }
      for (const checkout of VALID_CHECKOUTS) {
        const checkoutUrl = validateUrl(
          siteConfig.checkoutUrls[checkout],
          allowedHosts,
          `${environment}.${site}.checkoutUrls.${checkout}`
        );
        if (new URL(checkoutUrl).origin !== shopOrigin) {
          throw new Error(`[E2E] Shop e checkout devem usar a mesma origem em ${environment}/${site}.`);
        }
      }
    }
  }

  return config;
}

function loadEnvironmentConfig(requestedPath) {
  const configPath = resolveConfigPath(requestedPath);
  const stats = fs.statSync(configPath);
  if (!stats.isFile() || stats.size > 65536) {
    throw new Error('[E2E] Arquivo de ambientes invalido ou grande demais.');
  }

  let config;
  try {
    config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
  } catch {
    throw new Error('[E2E] Nao foi possivel ler o JSON de ambientes.');
  }
  return validateConfig(config);
}

function getEnvironmentTarget(config, environment, site, checkout) {
  const normalizedEnvironment = String(environment || '').toLowerCase();
  const normalizedSite = String(site || '').toUpperCase();
  const normalizedCheckout = String(checkout || '').toLowerCase();

  if (!VALID_ENVIRONMENTS.has(normalizedEnvironment)) {
    throw new Error('[E2E] Ambiente deve ser staging ou homol.');
  }
  if (!VALID_SITES.has(normalizedSite)) {
    throw new Error('[E2E] Site invalido.');
  }
  if (!VALID_CHECKOUTS.has(normalizedCheckout)) {
    throw new Error('[E2E] Checkout deve ser classic ou blocks.');
  }

  const siteConfig = config.environments[normalizedEnvironment].sites[normalizedSite];
  if (!siteConfig) {
    throw new Error(`[E2E] ${normalizedSite} ainda nao foi provisionado em ${normalizedEnvironment}.`);
  }

  return Object.freeze({
    environment: normalizedEnvironment,
    role: config.environments[normalizedEnvironment].role,
    site: normalizedSite,
    checkout: normalizedCheckout,
    containerName: siteConfig.containerName,
    shopUrl: siteConfig.shopUrl,
    checkoutUrl: siteConfig.checkoutUrls[normalizedCheckout],
    identityUrl: siteConfig.checkoutUrls.classic,
  });
}

// All seven countries of an environment share one artifacts lane, so a publish is never scoped to
// a single site: every container of the lane sees the new release the moment `current` is
// repointed. Callers need the full list to reload them and to verify the result.
function getLaneContainers(config, environment) {
  const normalizedEnvironment = String(environment || '').toLowerCase();
  if (!VALID_ENVIRONMENTS.has(normalizedEnvironment)) {
    throw new Error('[E2E] Ambiente deve ser staging ou homol.');
  }

  const { sites } = config.environments[normalizedEnvironment];
  return Object.freeze(
    [...VALID_SITES]
      .sort()
      .map((site) => sites[site].containerName)
  );
}

function getRuntimeTargetFromEnvironment() {
  const config = loadEnvironmentConfig();
  const target = getEnvironmentTarget(
    config,
    process.env.E2E_ENVIRONMENT,
    process.env.SITE,
    process.env.CHECKOUT
  );

  if (process.env.SHOP_URL !== target.shopUrl || process.env.CHECKOUT_URL !== target.checkoutUrl) {
    throw new Error('[E2E] SHOP_URL/CHECKOUT_URL divergem da configuracao versionada.');
  }

  return target;
}

async function assertHttpReady(url, label, settings = {}) {
  const attempts = settings.attempts ?? 2;
  const retryDelayMs = settings.retryDelayMs ?? 2000;
  if (!Number.isInteger(attempts) || attempts < 1 || attempts > 2 || retryDelayMs < 0 || retryDelayMs > 5000) {
    throw new Error('[E2E] Configuracao invalida de retry do preflight.');
  }

  let lastError;
  for (let attempt = 1; attempt <= attempts; attempt += 1) {
    try {
      await requestHttpReady(url, label);
      return;
    } catch (error) {
      lastError = error;
      const retryable = /indisponivel|Falha no preflight HTTP/.test(error.message || '');
      if (!retryable || attempt === attempts) throw error;
      await new Promise((resolve) => setTimeout(resolve, retryDelayMs));
    }
  }
  throw lastError;
}

async function requestHttpReady(url, label) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 15000);
  try {
    const response = await fetch(url, { redirect: 'manual', signal: controller.signal });
    if (response.status < 200 || response.status >= 400) {
      throw new Error(`[E2E] ${label} indisponivel (HTTP ${response.status}).`);
    }
    if (response.status >= 300) {
      const location = response.headers.get('location');
      if (!location || new URL(location, url).origin !== new URL(url).origin) {
        throw new Error(`[E2E] ${label} redirecionou para origem nao permitida.`);
      }
    }
  } catch (error) {
    if (error.message && error.message.startsWith('[E2E]')) throw error;
    throw new Error(`[E2E] Falha no preflight HTTP de ${label}.`);
  } finally {
    clearTimeout(timer);
  }
}

async function preflightExternalTarget(target) {
  await Promise.all([
    assertHttpReady(target.shopUrl, 'shop'),
    assertHttpReady(target.checkoutUrl, 'checkout'),
  ]);

  let lastError;
  for (let attempt = 1; attempt <= 2; attempt += 1) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 15000);
    try {
      const expectedOrigin = new URL(target.identityUrl).origin;
      let identityUrl = target.identityUrl;
      let response;
      for (let redirects = 0; redirects <= 2; redirects += 1) {
        response = await fetch(identityUrl, { redirect: 'manual', signal: controller.signal });
        if (response.status < 300 || response.status >= 400) break;

        const location = response.headers.get('location');
        const nextUrl = location ? new URL(location, identityUrl) : null;
        if (!nextUrl || nextUrl.origin !== expectedOrigin || redirects === 2) {
          throw new Error('[E2E] O site_id redirecionou para um destino nao permitido.');
        }
        identityUrl = nextUrl.toString();
      }
      if (
        !response
        || response.status < 200
        || response.status >= 300
        || new URL(response.url || identityUrl).origin !== expectedOrigin
      ) {
        throw new Error('[E2E] Nao foi possivel confirmar o site_id da loja externa.');
      }
      const html = await response.text();
      const match = html.match(/"site_id":"([A-Za-z]{3})"/);
      if (!match || match[1].toUpperCase() !== target.site) {
        // A proven mismatch is deterministic and must never be retried into a permissive result.
        throw new Error(`[E2E] Loja externa nao corresponde ao site ${target.site}.`);
      }
      return;
    } catch (error) {
      if (error.message && /nao corresponde/.test(error.message)) throw error;
      lastError = error;
      if (attempt === 2) {
        if (error.message && error.message.startsWith('[E2E]')) throw error;
        throw new Error('[E2E] Falha ao validar o site_id da loja externa.');
      }
    } finally {
      clearTimeout(timer);
    }
  }
  throw lastError;
}

module.exports = {
  assertHttpReady,
  CONFIG_DIR,
  DEFAULT_CONFIG_PATH,
  VALID_CHECKOUTS,
  VALID_ENVIRONMENTS,
  VALID_SITES,
  getEnvironmentTarget,
  getLaneContainers,
  getRuntimeTargetFromEnvironment,
  loadEnvironmentConfig,
  preflightExternalTarget,
  resolveConfigPath,
  validateConfig,
};
