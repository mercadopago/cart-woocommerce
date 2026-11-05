'use strict';

const fs = require('fs');
const path = require('path');
const { createHash } = require('crypto');

// Optional local-only PHP runtime. Sources keep the snapshot's absolute paths,
// but execute on the container filesystem rather than a slow host bind mount.
class DockerPhp {
  constructor(image, root, directory, run) {
    this.image = image;
    this.root = root;
    this.directory = directory;
    this.run = run;
    this.staged = new Map();
  }

  async stage(files) {
    // Explicit tooling/output files only; never broaden the source archive.
    for (const file of files) {
      const fingerprint = createHash('sha256').update(fs.readFileSync(file)).digest('hex');
      const identity = `${this.cid}:${file}`;
      if (this.staged.get(identity) === fingerprint) continue;
      await this.run('docker', ['exec', this.cid, 'mkdir', '-p', path.dirname(file)], this.root);
      await this.run('docker', ['cp', file, `${this.cid}:${file}`], this.root);
      this.staged.set(identity, fingerprint);
    }
  }

  async retrieve(file) {
    await this.run('docker', ['cp', `${this.cid}:${file}`, file], this.root);
  }

  async start() {
    const archive = path.join(this.directory, 'php-snapshot.tar');
    const vendorArchive = path.join(this.directory, 'php-vendor.tar');
    fs.mkdirSync(this.directory, { recursive: true });
    const files = [];
    const excluded = name => name.startsWith('._') || name.startsWith('.env') || /^(?:\.env(?:\..*)?|\.git|\.aws|\.npmrc|node_modules|coverage|reports|test-results|playwright-report|logs)$/.test(name) || /\.(?:pem|key|p12)$/.test(name);
    const visit = relative => {
      const filename = path.join(this.root, relative);
      const stat = fs.lstatSync(filename);
      if (stat.isSymbolicLink()) {
        const target = path.relative(this.root, fs.realpathSync(filename));
        if (target.startsWith('..') || path.isAbsolute(target)) throw new Error(`PHP snapshot link escapes root: ${relative}`);
        throw new Error(`PHP snapshot source links must be materialized: ${relative}`);
      }
      if (stat.isDirectory()) for (const name of fs.readdirSync(filename)) { if (!excluded(name)) visit(path.join(relative, name)); }
      else files.push(relative);
    };
    for (const relative of ['src', 'tests', 'assets', 'templates', 'i18n', 'build', 'integrity-manifest.json', 'woocommerce-mercadopago.php', 'uninstall.php', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'phpunit.xml', 'readme.txt']) {
      if (fs.existsSync(path.join(this.root, relative))) visit(relative);
    }
    const list = path.join(this.directory, 'snapshot.files');
    fs.writeFileSync(list, files.length ? files.sort().join('\0') + '\0' : '');
    await this.run('tar', ['-cf', archive, '--null', '-T', list], this.root, { COPYFILE_DISABLE: '1' });
    const vendor = fs.realpathSync(path.join(this.root, 'vendor'));
    const checkVendor = directory => {
      for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        const filename = path.join(directory, entry.name);
        if (entry.isSymbolicLink()) {
          const target = path.relative(vendor, fs.realpathSync(filename));
          if (target.startsWith('..') || path.isAbsolute(target)) throw new Error('PHP dependency link escapes vendor');
        } else if (entry.isDirectory()) checkVendor(filename);
      }
    };
    checkVendor(vendor);
    // Only the explicitly selected vendor root may be a dependency-cache link.
    await this.run('tar', ['-cf', vendorArchive, '-C', vendor, '.'], this.root, { COPYFILE_DISABLE: '1' });
    this.cid = await this.run('docker', ['create', '--network', 'none', '--entrypoint', 'sleep', this.image, 'infinity'], this.root);
    await this.run('docker', ['start', this.cid], this.root);
    this.imageId = await this.run('docker', ['inspect', '--format', '{{.Image}}', this.cid], this.root);
    fs.writeFileSync(path.join(this.directory, 'php-runtime.json'), JSON.stringify({ cid: this.cid, image: this.imageId, root: this.root }));
    await this.run('docker', ['exec', this.cid, 'mkdir', '-p', this.root], this.root);
    await this.run('docker', ['cp', archive, `${this.cid}:/snapshot.tar`], this.root);
    await this.run('docker', ['exec', this.cid, 'tar', '-xf', '/snapshot.tar', '-C', this.root], this.root);
    await this.run('docker', ['exec', this.cid, 'mkdir', '-p', path.join(this.root, 'vendor')], this.root);
    await this.run('docker', ['cp', vendorArchive, `${this.cid}:/vendor.tar`], this.root);
    await this.run('docker', ['exec', this.cid, 'tar', '-xf', '/vendor.tar', '-C', path.join(this.root, 'vendor')], this.root);
    fs.rmSync(archive);
    fs.rmSync(vendorArchive);
  }

  async execute(args, env, log) {
    const helper = args.find(arg => arg.endsWith('/scripts/coverage/php-report.php'));
    if (helper) {
      await this.stage([helper]);
    }
    const config = args.indexOf('--configuration');
    if (config !== -1) {
      await this.stage([args[config + 1]]);
    }
    const environment = { XDEBUG_MODE: 'coverage', ...env };
    if (process.env.COVERAGE_EXPECTED) environment.COVERAGE_EXPECTED = process.env.COVERAGE_EXPECTED;
    const envArgs = Object.entries(environment).flatMap(([key, value]) => ['-e', `${key}=${value}`]);
    const result = await this.run('docker', ['exec', ...envArgs, '-w', this.root, this.cid, 'php', ...args], this.root, {}, log);
    const outputs = ['--coverage-php', '--coverage-clover', '--list-tests-xml', '--log-junit'].flatMap(flag => {
      const index = args.indexOf(flag);
      return index === -1 ? [] : [args[index + 1]];
    });
    if (args.some(arg => arg.endsWith('/scripts/coverage/php-report.php')) && !args.includes('--inventory')) {
      // Export script arguments: script, root, raw, JSON, additional raw partitions.
      const script = args.findIndex(arg => arg.endsWith('/scripts/coverage/php-report.php'));
      const json = args[script + 3];
      outputs.push(json, path.join(path.dirname(json), 'clover.xml'), path.join(path.dirname(json), 'executed-tests.json'));
      outputs.push(path.join(path.dirname(json), 'php-bootstrap-summary.json'));
    }
    for (const file of outputs) {
      fs.mkdirSync(path.dirname(file), { recursive: true });
      await this.run('docker', ['cp', `${this.cid}:${file}`, file], this.root);
    }
    return result;
  }

  async close() {
    if (this.cid) await this.run('docker', ['rm', '--force', this.cid], this.root, {}, undefined, true);
  }
}
module.exports = { DockerPhp };
