const fs = require('fs');
const { resolveAlias } = require('../helpers/path-resolver');

describe('mp-behavior-tracking telemetry URL', () => {
  test('uses origin and pathname without copying query credentials or fragments', () => {
    const source = fs.readFileSync(
      resolveAlias('assets/js/scripts/mp-behavior-tracking.js'),
      'utf8'
    );

    expect(source).toContain('`${window.location.origin}${window.location.pathname}`');
    expect(source).not.toContain('url: window.location.href');
  });
});
