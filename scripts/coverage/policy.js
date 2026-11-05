const METRICS = ['lines', 'branches', 'functions', 'statements'];
const TOLERANCE_KEYS = ['floorBasisPoints', 'baseComparisonBasisPoints'];
const SCALE = 10000n;

function record(value, label) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) throw new Error(`Invalid coverage policy: ${label}`);
}

function counts(value) {
  if (!value || !Number.isSafeInteger(value.covered) || !Number.isSafeInteger(value.total)
      || value.covered < 0 || value.total < value.covered) throw new Error('Invalid coverage counts');
  return { numerator: BigInt(value.covered), denominator: BigInt(value.total) };
}

function floorRatio(floor) {
  if (typeof floor === 'number') {
    // Stored percentages have at most two decimals. Convert their decimal text,
    // rather than subtracting binary floats at the acceptance boundary.
    const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(String(floor));
    if (!Number.isFinite(floor) || floor < 0 || floor > 100 || !match) throw new Error('Invalid coverage policy: floor');
    return { numerator: BigInt(match[1]) * 100n + BigInt((match[2] || '').padEnd(2, '0')), denominator: SCALE };
  }
  record(floor, 'floor');
  if (Object.keys(floor).some((key) => !['covered', 'total'].includes(key))) throw new Error('Invalid coverage policy: floor');
  return counts(floor);
}

function basisPoints(value) {
  if (!Number.isSafeInteger(value) || value < 0 || value > 10000) throw new Error('Invalid coverage policy: tolerance basis points');
  return BigInt(value);
}

function toleranceSettings(value, label, allowOverrides = false) {
  record(value, label);
  for (const [key, setting] of Object.entries(value)) {
    if (allowOverrides && key === 'overrides') continue;
    if (!TOLERANCE_KEYS.includes(key)) throw new Error(`Invalid coverage policy: ${label}.${key}`);
    basisPoints(setting);
  }
}

function validatePolicy(policy) {
  record(policy, 'policy');
  for (const language of ['js', 'php']) {
    if (!Object.hasOwn(policy, language)) continue;
    record(policy[language], language);
    for (const [module, group] of Object.entries(policy[language])) {
      record(group, `${language}.${module}`);
      record(group.floors, `${language}.${module}.floors`);
      for (const [name, floor] of Object.entries(group.floors)) {
        if (!METRICS.includes(name)) throw new Error(`Invalid coverage policy: ${language}.${module}.${name}`);
        floorRatio(floor);
      }
    }
  }
  if (!Object.hasOwn(policy, 'tolerance')) return policy;
  toleranceSettings(policy.tolerance, 'tolerance', true);
  if (!Object.hasOwn(policy.tolerance, 'overrides')) return policy;
  record(policy.tolerance.overrides, 'tolerance.overrides');
  for (const [language, modules] of Object.entries(policy.tolerance.overrides)) {
    if (!['js', 'php'].includes(language) || !Object.hasOwn(policy, language)) throw new Error(`Invalid coverage policy: override language ${language}`);
    record(modules, `tolerance.overrides.${language}`);
    for (const [module, metrics] of Object.entries(modules)) {
      if (!Object.hasOwn(policy[language], module)) throw new Error(`Invalid coverage policy: override module ${language}.${module}`);
      record(metrics, `tolerance.overrides.${language}.${module}`);
      for (const [name, settings] of Object.entries(metrics)) {
        if (!Object.hasOwn(policy[language][module].floors, name)) throw new Error(`Invalid coverage policy: override metric ${language}.${module}.${name}`);
        toleranceSettings(settings, `tolerance.overrides.${language}.${module}.${name}`);
      }
    }
  }
  return policy;
}

function resolveTolerance(policy, language, module, name) {
  validatePolicy(policy);
  if (!['js', 'php'].includes(language) || !Object.hasOwn(policy[language] || {}, module)
      || !METRICS.includes(name)) throw new Error(`Invalid coverage policy: missing ${language}.${module}.${name}`);
  const defaults = policy.tolerance || {};
  const override = defaults.overrides?.[language]?.[module]?.[name] || {};
  return Object.fromEntries(TOLERANCE_KEYS.map((key) => [key, override[key] ?? defaults[key] ?? 0]));
}

function effectiveFloor(floor, margin) {
  const ratio = floorRatio(floor);
  const adjustment = basisPoints(margin);
  if (!ratio.denominator) return ratio;
  const numerator = ratio.numerator * SCALE - adjustment * ratio.denominator;
  return { numerator: numerator < 0n ? 0n : numerator, denominator: ratio.denominator * SCALE };
}

function meetsFloor(actual, floor, margin = 0) {
  const measured = counts(actual);
  const minimum = effectiveFloor(floor, margin);
  // A calibrated 0/0 floor has no minimum. Disappearing measured modules are
  // rejected separately by the base comparison, rather than treating 0/0 as 100%.
  if (!minimum.denominator) return true;
  if (!measured.denominator) return false;
  return measured.numerator * minimum.denominator >= minimum.numerator * measured.denominator;
}

function withinBaseTolerance(before, after, margin = 0) {
  const previous = counts(before);
  const current = counts(after);
  const adjustment = basisPoints(margin);
  if (!previous.denominator) return true;
  if (!current.denominator) return false;
  return SCALE * current.numerator * previous.denominator + adjustment * current.denominator * previous.denominator
    >= SCALE * previous.numerator * current.denominator;
}

function effectiveFloorPercent(floor, margin = 0) {
  const minimum = effectiveFloor(floor, margin);
  if (!minimum.denominator) return null;
  return Number(minimum.numerator * 100n) / Number(minimum.denominator);
}

function jestThresholds(policy, language = 'js') {
  validatePolicy(policy);
  if (!['js', 'php'].includes(language) || !policy[language]) throw new Error(`Invalid coverage policy: missing ${language}`);
  const paths = Object.values(policy[language]).map((group) => group.path);
  if (paths.some((value) => typeof value !== 'string' || !value) || new Set(paths).size !== paths.length) throw new Error('Invalid coverage policy: Jest module paths');
  return Object.fromEntries(Object.entries(policy[language]).map(([module, group]) => [group.path,
    Object.fromEntries(Object.entries(group.floors).map(([name, floor]) => [name,
      effectiveFloorPercent(floor, resolveTolerance(policy, language, module, name).floorBasisPoints),
    ])),
  ]));
}

module.exports = {
  validatePolicy, resolveTolerance, meetsFloor, withinBaseTolerance, effectiveFloorPercent, jestThresholds,
};
