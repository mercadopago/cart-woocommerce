'use strict';

const { validateVmBindings } = require('./jest-vm-metadata');

function clearGitLocation(env) {
  // Git exports these inside real hooks; fixtures and snapshot collectors must
  // resolve their own repositories and must never inherit the caller's index.
  for (const name of ['GIT_DIR', 'GIT_WORK_TREE', 'GIT_COMMON_DIR', 'GIT_INDEX_FILE', 'GIT_OBJECT_DIRECTORY', 'GIT_ALTERNATE_OBJECT_DIRECTORIES', 'GIT_PREFIX', 'GIT_SHALLOW_FILE', 'GIT_GRAFT_FILE', 'GIT_NAMESPACE', 'GIT_CEILING_DIRECTORIES']) delete env[name];
}

module.exports = async function setupFixtureGit(_globalConfig, projectConfig) {
  validateVmBindings({ root: projectConfig.rootDir });
  clearGitLocation(process.env);
  // Runs in Jest's Node process, before worker/environment creation. Corporate
  // hooks do not belong to disposable repositories created by tooling tests.
  // Nothing changes the caller shell or any persistent Git configuration.
  const count = Number(process.env.GIT_CONFIG_COUNT || 0);
  if (!Number.isInteger(count) || count < 0) throw new Error('Invalid inherited Git configuration count');
  process.env[`GIT_CONFIG_KEY_${count}`] = 'core.hooksPath';
  process.env[`GIT_CONFIG_VALUE_${count}`] = '/dev/null';
  process.env.GIT_CONFIG_COUNT = String(count + 1);
};

module.exports.clearGitLocation = clearGitLocation;
