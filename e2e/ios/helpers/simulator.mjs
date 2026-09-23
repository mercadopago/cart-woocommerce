import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const DEFAULT_DEVICE_NAME = 'mp-ios-e2e';
const DEFAULT_DEVICE_TYPE_NAME = 'iPhone 16';
const SAFE_NAME = /^[A-Za-z0-9._ -]{1,80}$/;
const SAFE_IDENTIFIER = /^com\.apple\.CoreSimulator\.[A-Za-z0-9.-]+$/;
const SAFE_UDID = /^[A-F0-9-]{36}$/i;

function fail(message) {
  process.stderr.write(`[ios-simulator] ${message}\n`);
  process.exit(1);
}

function runSimctl(args, options = {}) {
  return execFileSync('xcrun', ['simctl', ...args], {
    encoding: 'utf8',
    stdio: options.stdio || ['ignore', 'pipe', 'pipe'],
  });
}

function listJson(kind) {
  return JSON.parse(runSimctl(['list', kind, '--json']));
}

function versionParts(version) {
  return String(version)
    .split('.')
    .map((part) => Number.parseInt(part, 10) || 0);
}

function compareVersions(left, right) {
  const a = versionParts(left);
  const b = versionParts(right);
  const length = Math.max(a.length, b.length);
  for (let index = 0; index < length; index += 1) {
    const difference = (a[index] || 0) - (b[index] || 0);
    if (difference !== 0) return difference;
  }
  return 0;
}

function selectRuntime(runtimes) {
  const requested = process.env.IOS_RUNTIME_ID;
  const available = runtimes.filter(
    (runtime) => runtime.isAvailable && runtime.identifier.startsWith('com.apple.CoreSimulator.SimRuntime.iOS-'),
  );

  if (requested) {
    if (!SAFE_IDENTIFIER.test(requested)) fail('IOS_RUNTIME_ID inválido.');
    const runtime = available.find((candidate) => candidate.identifier === requested);
    if (!runtime) fail('Runtime solicitado não está disponível.');
    return runtime;
  }

  if (available.length === 0) fail('Nenhum runtime iOS disponível. Instale um runtime pelo Xcode.');
  return available.sort((a, b) => compareVersions(b.version, a.version))[0];
}

function selectDeviceType(deviceTypes) {
  const requested = process.env.IOS_DEVICE_TYPE_ID;
  if (requested) {
    if (!SAFE_IDENTIFIER.test(requested)) fail('IOS_DEVICE_TYPE_ID inválido.');
    const deviceType = deviceTypes.find((candidate) => candidate.identifier === requested);
    if (!deviceType) fail('Device type solicitado não está disponível.');
    return deviceType;
  }

  return (
    deviceTypes.find((candidate) => candidate.name === DEFAULT_DEVICE_TYPE_NAME) ||
    deviceTypes.find((candidate) => /^iPhone \d+$/.test(candidate.name)) ||
    deviceTypes.find((candidate) => candidate.name.startsWith('iPhone'))
  );
}

function writeMetadata(metadata) {
  const destination = process.env.IOS_SIMULATOR_METADATA;
  if (!destination) return;
  const resolved = path.resolve(destination);
  fs.mkdirSync(path.dirname(resolved), { recursive: true, mode: 0o700 });
  fs.writeFileSync(resolved, `${JSON.stringify(metadata, null, 2)}\n`, { mode: 0o600 });
}

function waitForDeviceState(udid, expectedState, timeoutMs = 30000) {
  const startedAt = Date.now();
  while (Date.now() - startedAt < timeoutMs) {
    const device = Object.values(listJson('devices').devices)
      .flat()
      .find((candidate) => candidate.udid === udid);
    if (device?.state === expectedState) return;
    Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 250);
  }
  fail('Simulator não alcançou o estado esperado no prazo.');
}

function ensureSimulator() {
  const deviceName = process.env.IOS_DEVICE_NAME || DEFAULT_DEVICE_NAME;
  if (!SAFE_NAME.test(deviceName)) fail('IOS_DEVICE_NAME inválido.');

  const runtime = selectRuntime(listJson('runtimes').runtimes);
  const deviceType = selectDeviceType(listJson('devicetypes').devicetypes);
  if (!deviceType) fail('Nenhum device type de iPhone disponível.');

  const devices = listJson('devices').devices[runtime.identifier] || [];
  let device = devices.find((candidate) => candidate.name === deviceName && candidate.isAvailable);

  if (!device) {
    const udid = runSimctl(['create', deviceName, deviceType.identifier, runtime.identifier]).trim();
    device = { name: deviceName, udid, state: 'Shutdown', isAvailable: true };
    process.stderr.write('[ios-simulator] Simulator dedicado criado.\n');
  }

  if (!SAFE_UDID.test(device.udid)) fail('UDID retornado pelo simctl é inválido.');

  if (device.state === 'Shutting Down') {
    waitForDeviceState(device.udid, 'Shutdown');
    device.state = 'Shutdown';
  }

  if (process.env.IOS_ERASE !== '0') {
    if (device.state === 'Booted') {
      runSimctl(['shutdown', device.udid]);
      waitForDeviceState(device.udid, 'Shutdown');
    }
    runSimctl(['erase', device.udid]);
  }

  try {
    runSimctl(['boot', device.udid]);
  } catch (error) {
    const stderr = String(error.stderr || '');
    if (!stderr.includes('Unable to boot device in current state: Booted')) throw error;
  }
  runSimctl(['bootstatus', device.udid, '-b'], { stdio: ['ignore', 'ignore', 'pipe'] });

  writeMetadata({
    udid: device.udid,
    deviceName,
    deviceType: deviceType.name,
    runtimeId: runtime.identifier,
    platformVersion: runtime.version,
  });
  process.stdout.write(`${device.udid}\n`);
}

function trustCertificate(udid, certificatePath) {
  if (!SAFE_UDID.test(udid)) fail('UDID inválido para instalação do certificado.');
  const resolved = path.resolve(certificatePath || '');
  if (!fs.existsSync(resolved) || !fs.statSync(resolved).isFile()) {
    fail('CA pública não encontrada.');
  }
  runSimctl(['keychain', udid, 'add-root-cert', resolved]);
}

function shutdown(udid) {
  if (!SAFE_UDID.test(udid)) fail('UDID inválido para shutdown.');
  try {
    runSimctl(['shutdown', udid]);
  } catch (error) {
    const stderr = String(error.stderr || '');
    if (!stderr.includes('Unable to shutdown device in current state: Shutdown')) throw error;
  }
}

const [command, ...args] = process.argv.slice(2);
if (command === 'ensure') ensureSimulator();
else if (command === 'trust-ca') trustCertificate(args[0], args[1]);
else if (command === 'shutdown') shutdown(args[0]);
else fail('Uso: simulator.mjs <ensure|trust-ca|shutdown> [argumentos]');
