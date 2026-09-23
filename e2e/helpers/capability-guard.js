const fs = require('node:fs');
const path = require('node:path');

const CAPABILITIES_PATH = path.resolve(__dirname, '..', 'config', 'shared-capabilities.json');
const VALID_SITES = new Set(['MLA', 'MLB', 'MLC', 'MLM', 'MCO', 'MLU', 'MPE']);
const SAFE_CAPABILITY = /^[a-z][a-z0-9_-]{1,63}$/;

function loadSharedCapabilities() {
  const stats = fs.statSync(CAPABILITIES_PATH);
  if (!stats.isFile() || stats.size === 0 || stats.size > 16384) {
    throw new Error('[E2E] Manifesto de capabilities compartilhadas invalido.');
  }

  let config;
  try {
    config = JSON.parse(fs.readFileSync(CAPABILITIES_PATH, 'utf8'));
  } catch {
    throw new Error('[E2E] Manifesto de capabilities compartilhadas corrompido.');
  }
  if (!config || config.version !== 1 || !config.sites || typeof config.sites !== 'object') {
    throw new Error('[E2E] Schema de capabilities compartilhadas invalido.');
  }
  if (
    Object.keys(config.sites).length !== VALID_SITES.size
    || [...VALID_SITES].some((site) => !config.sites[site])
  ) {
    throw new Error('[E2E] Capabilities devem declarar os sete sites.');
  }
  for (const [site, capabilities] of Object.entries(config.sites)) {
    if (!VALID_SITES.has(site) || !capabilities || typeof capabilities !== 'object' || Array.isArray(capabilities)) {
      throw new Error('[E2E] Site invalido no manifesto de capabilities.');
    }
    for (const [capability, enabled] of Object.entries(capabilities)) {
      if (!SAFE_CAPABILITY.test(capability) || typeof enabled !== 'boolean') {
        throw new Error('[E2E] Capability compartilhada invalida.');
      }
    }
  }
  return config;
}

function skipIfUnsupportedCapability(test, site, capability) {
  if (process.env.WP_EXTERNAL_STORE !== '1') return;
  const normalizedSite = String(site || '').toUpperCase();
  if (!VALID_SITES.has(normalizedSite) || !SAFE_CAPABILITY.test(capability || '')) {
    throw new Error('[E2E] Site ou capability invalida.');
  }
  const enabled = loadSharedCapabilities().sites[normalizedSite][capability];
  test.skip(enabled === false, `Shared ${normalizedSite} seller does not support ${capability}`);
}

module.exports = { loadSharedCapabilities, skipIfUnsupportedCapability };
