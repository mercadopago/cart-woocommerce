const {
  validatePolicy, resolveTolerance, meetsFloor, withinBaseTolerance, effectiveFloorPercent, jestThresholds,
} = require('../../../../scripts/coverage/policy');
const { enforce } = require('../../../../scripts/coverage/report');

function policy(floor = 88.56) {
  return {
    tolerance: { floorBasisPoints: 10, baseComparisonBasisPoints: 10 },
    js: { global: { path: './', floors: { lines: floor, branches: floor } } },
  };
}

const measured = (covered, total = 10000) => ({ covered, total, pct: Math.floor(covered * 10000 / total) / 100 });

test('decimal floors subtract basis points without a binary floating-point boundary', () => {
  expect(effectiveFloorPercent(88.56, 10)).toBe(88.46);
  expect(meetsFloor(measured(8846), 88.56, 10)).toBe(true);
  expect(meetsFloor(measured(88459, 100000), 88.56, 10)).toBe(false);
  expect(meetsFloor(measured(8856), 88.56)).toBe(true);
  expect(meetsFloor(measured(8855), 88.56)).toBe(false);
  expect(effectiveFloorPercent(0.05, 10)).toBe(0);
  expect(meetsFloor(measured(0), 0.05, 10)).toBe(true);
});

test('ratio floors retain their exact approved reference', () => {
  const floor = { covered: 2, total: 3 };
  expect(meetsFloor(measured(2, 3), floor)).toBe(true);
  expect(meetsFloor(measured(6666), floor)).toBe(false);
  expect(meetsFloor(measured(1997, 3000), floor, 10)).toBe(true);
  expect(meetsFloor(measured(19969, 30000), floor, 10)).toBe(false);
  expect(floor).toEqual({ covered: 2, total: 3 });
});

test('relative tolerance compares raw counts at and immediately below its boundary', () => {
  expect(withinBaseTolerance(measured(8860), measured(8850), 10)).toBe(true);
  expect(withinBaseTolerance(measured(8860), measured(88499, 100000), 10)).toBe(false);
  expect(withinBaseTolerance(measured(8860), measured(8859))).toBe(false);
  expect(withinBaseTolerance(measured(8860), measured(8860))).toBe(true);
  expect(withinBaseTolerance(measured(8860), measured(8861))).toBe(true);
});

test('products beyond safe Number precision still detect a one-count regression', () => {
  const total = Number.MAX_SAFE_INTEGER;
  const before = measured(total - 1, total);
  const after = measured(total - 2, total);
  expect(withinBaseTolerance(before, after)).toBe(false);
  expect(meetsFloor(after, { covered: total - 1, total })).toBe(false);
  expect(withinBaseTolerance(before, after, 1)).toBe(true);
});

test('empty metrics are explicit and cannot make a measured module disappear', () => {
  expect(meetsFloor(measured(0, 0), { covered: 0, total: 0 })).toBe(true);
  expect(meetsFloor(measured(0, 1), { covered: 0, total: 0 })).toBe(true);
  expect(meetsFloor(measured(1, 1), { covered: 0, total: 0 })).toBe(true);
  expect(meetsFloor(measured(0, 0), 0)).toBe(false);
  expect(withinBaseTolerance(measured(1, 1), measured(0, 0), 10000)).toBe(false);
  expect(withinBaseTolerance(measured(0, 0), measured(1, 1))).toBe(true);
});

test('historical floors remain fixed across successive individually tolerated PRs', () => {
  const approved = policy(90);
  const original = JSON.stringify(approved);
  const { floorBasisPoints, baseComparisonBasisPoints } = resolveTolerance(approved, 'js', 'global', 'lines');
  const revisions = [9000, 8995, 8990, 8985].map(value => measured(value));
  for (let index = 1; index < revisions.length; index += 1) {
    expect(withinBaseTolerance(revisions[index - 1], revisions[index], baseComparisonBasisPoints)).toBe(true);
    expect(meetsFloor(revisions[index], approved.js.global.floors.lines, floorBasisPoints)).toBe(index < 3);
  }
  expect(JSON.stringify(approved)).toBe(original);
});

test('an indicator override changes only the selected module and metric', () => {
  const approved = policy();
  approved.tolerance.overrides = { js: { global: { branches: { floorBasisPoints: 0 } } } };
  expect(resolveTolerance(approved, 'js', 'global', 'branches')).toEqual({ floorBasisPoints: 0, baseComparisonBasisPoints: 10 });
  expect(resolveTolerance(approved, 'js', 'global', 'lines')).toEqual({ floorBasisPoints: 10, baseComparisonBasisPoints: 10 });
  expect(jestThresholds(approved)).toEqual({ './': { lines: 88.46, branches: 88.56 } });
});

test('older policies keep zero tolerance and root PATH semantics', () => {
  const approved = policy();
  delete approved.tolerance;
  expect(resolveTolerance(approved, 'js', 'global', 'lines')).toEqual({ floorBasisPoints: 0, baseComparisonBasisPoints: 0 });
  expect(jestThresholds(approved)).toEqual({ './': { lines: 88.56, branches: 88.56 } });
});

test('collector enforcement uses raw counters instead of displayed or forged percentages', () => {
  const approved = policy();
  const actual = measured(8846);
  const result = { language: 'js', modules: { global: { lines: actual, branches: actual } } };
  expect(() => enforce(result, approved.js, approved)).not.toThrow();
  result.modules.global.lines = { ...measured(88459, 100000), pct: 88.46 };
  expect(() => enforce(result, approved.js, approved)).toThrow('floor margin 0.1 pp');
  expect(() => enforce({ language: 'js', modules: { global: { lines: actual, branches: actual } } }, approved.js)).toThrow('Coverage thresholds failed');
});

test.each([-1, 0.1, NaN, Infinity, '10', null, 10001])('invalid tolerance %p fails closed', value => {
  const approved = policy();
  approved.tolerance.floorBasisPoints = value;
  expect(() => validatePolicy(approved)).toThrow('Invalid coverage policy');
});

test.each([
  { floorsBasisPoints: 10 },
  { floorBasisPoints: 10, overrides: null },
  { overrides: { python: {} } },
  { overrides: { php: {} } },
  { overrides: { js: { missing: {} } } },
  { overrides: { js: { global: { line: { floorBasisPoints: 10 } } } } },
  { overrides: { js: { global: { lines: { floorBasisPoint: 10 } } } } },
  { overrides: { js: { global: { lines: { floorBasisPoints: -1 } } } } },
])('invalid tolerance settings %p fail closed', tolerance => {
  const approved = policy();
  approved.tolerance = tolerance;
  expect(() => validatePolicy(approved)).toThrow('Invalid coverage policy');
});

test.each([-1, 100.01, 88.561, NaN, Infinity, '88.56', {}, { covered: 2, total: 1 }, { covered: 1, total: 2, typo: 1 }])('invalid floor %p fails closed', floor => {
  expect(() => validatePolicy(policy(floor))).toThrow();
});

test.each([{ covered: -1, total: 1 }, { covered: 2, total: 1 }, { covered: 0.5, total: 1 }, { covered: 1, total: Number.MAX_SAFE_INTEGER + 1 }])('invalid measured counters %p fail closed', actual => {
  expect(() => meetsFloor(actual, 0, 10)).toThrow('Invalid coverage counts');
});

test('comparison-only modules can resolve known indicators without declaring a floor', () => {
  const approved = policy();
  approved.js.global.floors = {};
  expect(resolveTolerance(approved, 'js', 'global', 'functions')).toEqual({ floorBasisPoints: 10, baseComparisonBasisPoints: 10 });
  expect(() => resolveTolerance(approved, 'js', 'global', 'function')).toThrow('Invalid coverage policy');
});

test('repository Jest configuration applies the effective floors without changing their anchors', () => {
  const approved = require('../../../../coverage-policy.json');
  const config = require('../../../../jest.config');
  expect(approved.js.global.floors.lines).toBe(73.82);
  expect(config.coverageThreshold['./'].lines).toBe(73.72);
  expect(config.coverageThreshold['assets/js/checkouts/super-token/'].branches).toBe(88.46);
  expect(config.coverageThreshold.global).toBeUndefined();
});

test('Jest threshold generation rejects missing and duplicate module paths', () => {
  const approved = policy();
  delete approved.js.global.path;
  expect(() => jestThresholds(approved)).toThrow('Jest module paths');
  approved.js.global.path = './';
  approved.js.duplicate = { path: './', floors: { lines: 90 } };
  expect(() => jestThresholds(approved)).toThrow('Jest module paths');
});
