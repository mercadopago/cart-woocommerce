'use strict';

// Local tooling only. Never interpolate Git paths or refs into a shell command.
const { spawn } = require('node:child_process');
const active = new Set();

function run(command, args, options = {}) {
  const started = Date.now();
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, {
      cwd: options.cwd, env: { ...process.env, ...options.env },
      detached: true, stdio: ['pipe', 'pipe', 'pipe'],
    });
    let stdout = '';
    let stderr = '';
    let failure;
    const stop = (reason) => {
      failure = reason;
      if (child.pid) {
        try { process.kill(-child.pid, 'SIGKILL'); } catch (error) {
          if (error.code !== 'ESRCH') failure = error.message;
        }
      }
    };
    active.add(stop);
    const timer = setTimeout(() => stop('TIMEOUT'), options.timeout || 600000);
    const capture = (stream, value) => {
      if (stream === 'stdout') stdout += value;
      else stderr += value;
      if (stdout.length + stderr.length > 16 * 1024 * 1024) stop('OUTPUT_LIMIT');
    };
    child.stdout.on('data', (value) => capture('stdout', value));
    child.stderr.on('data', (value) => capture('stderr', value));
    child.on('error', (error) => { failure = error.code; });
    child.on('close', (status, signal) => {
      clearTimeout(timer);
      active.delete(stop);
      if (failure || signal || status !== 0) {
        const error = new Error(`${command} failed (${failure || signal || status}): ${(stderr || stdout).slice(-4000)}`);
        error.stdout = stdout; error.stderr = stderr; error.failure = failure;
        reject(error);
      }
      else resolve({ stdout, stderr, durationMs: Date.now() - started });
    });
    child.stdin.on('error', () => {});
    child.stdin.end(options.input);
  });
}

function cancel() { for (const stop of active) stop('CANCELLED'); }
module.exports = { run, cancel };
