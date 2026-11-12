const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const {
  RELEASE_EXCLUDED_ASSETS,
  removeReleaseExcludedAssets,
} = require('../../../scripts/public/release-assets');
const { INTEGRITY_IGNORED_ASSETS } = require('../../../scripts/public/verify-integrity');

const repoRoot = path.resolve(__dirname, '../../..');

describe('release assets', () => {
  let root;

  beforeEach(() => {
    root = fs.mkdtempSync(path.join(os.tmpdir(), 'mp-release-assets-'));
  });

  afterEach(() => {
    fs.rmSync(root, { recursive: true, force: true });
  });

  test('removes every personal-review asset while preserving distributable files', () => {
    RELEASE_EXCLUDED_ASSETS.forEach((relativePath) => {
      const absolutePath = path.join(root, relativePath);
      fs.mkdirSync(path.dirname(absolutePath), { recursive: true });
      fs.writeFileSync(absolutePath, 'personal-review-only');
    });
    const distributable = path.join(root, 'assets/js/checkouts/pix.min.js');
    fs.mkdirSync(path.dirname(distributable), { recursive: true });
    fs.writeFileSync(distributable, 'distributable');

    removeReleaseExcludedAssets(root);

    RELEASE_EXCLUDED_ASSETS.forEach((relativePath) => {
      expect(fs.existsSync(path.join(root, relativePath))).toBe(false);
    });
    expect(fs.readFileSync(distributable, 'utf8')).toBe('distributable');
  });

  test('is idempotent when personal-review assets were not generated', () => {
    expect(() => removeReleaseExcludedAssets(root)).not.toThrow();
  });

  test('the local Smart Pix bundle stays outside the release and the integrity manifest', () => {
    expect(RELEASE_EXCLUDED_ASSETS).toContain('assets/js/checkouts/smart-pix/smart-pix.bundle.min.js');
    expect(INTEGRITY_IGNORED_ASSETS).toContain('assets/js/checkouts/smart-pix/smart-pix.bundle.min.js');
  });

  test('the integrity allowlist matches the release exclusion list exactly', () => {
    expect([...INTEGRITY_IGNORED_ASSETS]).toEqual([...RELEASE_EXCLUDED_ASSETS]);
  });

  test('the committed integrity manifest lists only tracked, distributable assets', () => {
    const manifest = JSON.parse(fs.readFileSync(path.join(repoRoot, 'integrity-manifest.json'), 'utf8'));
    const entries = Object.keys(manifest);
    const tracked = new Set(execFileSync('git', ['ls-files', '-z', '--', ...entries], { cwd: repoRoot })
      .toString('utf8')
      .split('\0')
      .filter(Boolean));

    expect(entries.filter((entry) => INTEGRITY_IGNORED_ASSETS.includes(entry))).toEqual([]);
    expect(entries.filter((entry) => !tracked.has(entry))).toEqual([]);
  });
});
