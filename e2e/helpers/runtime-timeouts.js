const TIMEOUT_PROFILES = Object.freeze({
  standard: 1,
  fast: 0.5,
});

const MINIMUM_TIMEOUT_MS = 500;

function getTimeoutProfile(environment = process.env) {
  const profile = environment.E2E_TIMEOUT_PROFILE || 'standard';
  if (!Object.hasOwn(TIMEOUT_PROFILES, profile)) {
    throw new Error(`[E2E] Perfil de timeout invalido: ${profile}. Use standard ou fast.`);
  }
  return profile;
}

function e2eTimeout(milliseconds, environment = process.env) {
  if (!Number.isFinite(milliseconds) || milliseconds <= 0) {
    throw new Error('[E2E] Timeout deve ser um numero positivo.');
  }

  const profile = getTimeoutProfile(environment);
  return Math.max(MINIMUM_TIMEOUT_MS, Math.round(milliseconds * TIMEOUT_PROFILES[profile]));
}

module.exports = {
  TIMEOUT_PROFILES,
  e2eTimeout,
  getTimeoutProfile,
};
