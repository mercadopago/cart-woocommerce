#!/usr/bin/env node
// Read-only diagnosis of the shared lanes, run from a developer machine over SSH.
//
// It exists because the failure that blocked 8.9.4 was invisible until someone replayed the
// remote publish script by hand: the lanes were healthy, the plugin was the right version, and
// the only symptom was a generic "publication failed". This reports the state that actually
// decides whether a publish can work -- mount layout, lane pointer, permissions and leases --
// and ends with the next command to run.
const { spawnSync } = require('node:child_process');
const { getLaneContainers, loadEnvironmentConfig } = require('./helpers/environment-config');
const { getPublishLockName } = require('./run-dual-report');
const { getSshOptions } = require('./helpers/ssh-options');

const LANES = Object.freeze(['staging', 'homol']);

// Emits tab-separated records instead of JSON so it stays readable in `sh` and cannot break on a
// quoting edge case. Every probe is read-only.
const REMOTE_PREFLIGHT_SCRIPT = `
set -u
locks_root="$HOME/.woo-e2e-locks"
now=$(date +%s)

for container in "$@"; do
  state=$(docker inspect -f '{{.State.Status}}' "$container" 2>/dev/null || printf 'absent')
  if [ "$state" = "absent" ]; then
    printf 'container\\t%s\\tabsent\\t\\t\\t\\n' "$container"
    continue
  fi
  lane_mount=$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/e2e-artifacts"}}{{.Source}}{{end}}{{end}}' "$container" 2>/dev/null || printf '')
  legacy_mount=$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/woocommerce-mercadopago"}}{{.Source}}{{end}}{{end}}' "$container" 2>/dev/null || printf '')
  version=''
  if [ "$state" = "running" ]; then
    version=$(docker exec --user www-data "$container" wp --path=/var/www/html plugin get woocommerce-mercadopago --field=version 2>/dev/null || printf '')
  fi
  printf 'container\\t%s\\t%s\\t%s\\t%s\\t%s\\n' "$container" "$state" "$lane_mount" "$legacy_mount" "$version"
done

for lane in staging homol; do
  root=''
  for container in "$@"; do
    case "$container" in
      wp-"$lane"-*)
        candidate=$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/e2e-artifacts"}}{{.Source}}{{end}}{{end}}' "$container" 2>/dev/null || printf '')
        [ -n "$candidate" ] && [ -z "$root" ] && root="$candidate"
        ;;
    esac
  done
  [ -n "$root" ] || { printf 'lane\\t%s\\tunknown\\t\\t\\t\\n' "$lane"; continue; }
  modes=''
  for directory in "$root" "$root/releases" "$root/current"; do
    mode=$(stat -c '%a' "$directory" 2>/dev/null || printf '---')
    modes="$modes$mode,"
  done
  pointer=$(readlink "$root/current/woocommerce-mercadopago" 2>/dev/null || printf 'not-a-symlink')
  release_count=$(ls -1 "$root/releases" 2>/dev/null | wc -l | tr -d ' ')
  printf 'lane\\t%s\\t%s\\t%s\\t%s\\t%s\\n' "$lane" "$root" "$modes" "$pointer" "$release_count"
done

if [ -d "$locks_root" ]; then
  for lock_dir in "$locks_root"/*/; do
    [ -d "$lock_dir" ] || continue
    name=$(basename "$lock_dir")
    acquired=$(cat "$lock_dir/acquired_at" 2>/dev/null || printf '0')
    case "$acquired" in *[!0-9]*|'') acquired=0 ;; esac
    owner=$(cat "$lock_dir/owner" 2>/dev/null || printf 'unknown')
    printf 'lease\\t%s\\t%s\\t%s\\t\\t\\n' "$name" "$owner" "$((now - acquired))"
  done
fi
`;

function collectRemoteState(config, containers, dependencies = {}) {
  const spawnSyncFn = dependencies.spawnSync || spawnSync;
  const sshOptions = dependencies.sshOptions || getSshOptions(process.env, config.lock.sshHost);
  const result = spawnSyncFn(
    'ssh',
    [...sshOptions, `${config.lock.sshUser}@${config.lock.sshHost}`, 'sh', '-s', '--', ...containers],
    { input: REMOTE_PREFLIGHT_SCRIPT, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'], timeout: 180000 }
  );
  if (result.status !== 0) {
    throw new Error('[E2E] Nao foi possivel inspecionar o host compartilhado por SSH.');
  }
  return parseRemoteState(result.stdout);
}

function parseRemoteState(stdout) {
  const state = { containers: [], lanes: [], leases: [] };
  for (const line of String(stdout || '').split('\n')) {
    if (!line.trim()) continue;
    const [kind, ...fields] = line.split('\t');
    if (kind === 'container') {
      const [name, status, laneMount, legacyMount, version] = fields;
      state.containers.push({ name, status, laneMount, legacyMount, version });
    } else if (kind === 'lane') {
      const [lane, root, modes, pointer, releaseCount] = fields;
      state.lanes.push({
        lane,
        root,
        modes: String(modes || '').split(',').filter(Boolean),
        pointer,
        releaseCount: Number(releaseCount) || 0,
      });
    } else if (kind === 'lease') {
      const [name, owner, ageSeconds] = fields;
      state.leases.push({ name, owner, ageSeconds: Number(ageSeconds) || 0 });
    }
  }
  return state;
}

// Turns the raw state into the two things a release operator needs: can I publish, and if not,
// which command fixes it.
function diagnose(state, config, ttlSeconds) {
  const blockers = [];
  const warnings = [];

  const absent = state.containers.filter((container) => container.status === 'absent');
  const stopped = state.containers.filter(
    (container) => container.status !== 'absent' && container.status !== 'running'
  );
  const legacy = state.containers.filter(
    (container) => container.status === 'running' && !container.laneMount
  );

  if (absent.length) {
    blockers.push({
      what: `${absent.length} container(s) nao existem: ${absent.map((c) => c.name).join(', ')}`,
      fix: 'No host: make e2e-shared-infra-up',
    });
  }
  if (stopped.length) {
    blockers.push({
      what: `${stopped.length} container(s) parados: ${stopped.map((c) => `${c.name} (${c.status})`).join(', ')}`,
      fix: 'No host: make e2e-shared-infra-up',
    });
  }
  if (legacy.length) {
    blockers.push({
      what: `${legacy.length} container(s) ainda usam o mount antigo e ficariam presos numa release: ${legacy.map((c) => c.name).join(', ')}`,
      fix: 'No host: make e2e-shared-infra-migrate',
    });
  }

  for (const lane of state.lanes) {
    if (lane.root === 'unknown') continue;
    // uid 33 is not in the owner group, so only the "others" digit matters, and it needs r+x.
    const unreadable = lane.modes.filter((mode) => !/^[0-7][0-7][57]$/.test(mode));
    if (unreadable.length) {
      blockers.push({
        what: `lane ${lane.lane} nao e atravessavel por www-data (modos ${lane.modes.join(', ')})`,
        fix: 'No host: make e2e-shared-infra-repair-permissions',
      });
    }
    if (lane.pointer === 'not-a-symlink') {
      warnings.push(
        `lane ${lane.lane}: current/woocommerce-mercadopago nao e symlink; o publish ainda funciona, mas o rollback deixa de ser um repoint`
      );
    }
  }

  const publishLock = getPublishLockName(config);
  const activeLeases = state.leases.filter((lease) => lease.ageSeconds <= ttlSeconds);
  for (const lease of activeLeases) {
    const isPublish = lease.name === publishLock;
    blockers.push({
      what: `lease ativo ha ${Math.round(lease.ageSeconds / 60)} min: ${lease.name} (${lease.owner})`,
      fix: isPublish
        ? 'Outra publicacao esta em andamento; aguarde o fim dela'
        : 'Ha uma execucao em andamento nesse pais; aguarde ou confirme com a pessoa dona do lease',
    });
  }
  const staleLeases = state.leases.filter((lease) => lease.ageSeconds > ttlSeconds);
  for (const lease of staleLeases) {
    warnings.push(
      `lease expirado (${Math.round(lease.ageSeconds / 3600)}h) sera reciclado automaticamente: ${lease.name} (${lease.owner})`
    );
  }

  return { blockers, warnings, ready: blockers.length === 0 };
}

function formatReport(state, diagnosis) {
  const lines = [];
  lines.push('[E2E] Estado das lanes compartilhadas');
  lines.push('');

  for (const lane of state.lanes) {
    if (lane.root === 'unknown') {
      lines.push(`  ${lane.lane.padEnd(8)} | sem container migrado; nao foi possivel localizar a lane`);
      continue;
    }
    lines.push(
      `  ${lane.lane.padEnd(8)} | ${lane.pointer} | ${lane.releaseCount} release(s) | modos ${lane.modes.join('/')}`
    );
  }
  lines.push('');

  const versions = new Map();
  for (const container of state.containers) {
    const lane = container.name.startsWith('wp-staging-') ? 'staging' : 'homol';
    if (!versions.has(lane)) versions.set(lane, new Map());
    const key = container.status === 'running' ? (container.version || 'sem resposta') : container.status;
    const bucket = versions.get(lane);
    bucket.set(key, [...(bucket.get(key) || []), container.name]);
  }
  for (const [lane, buckets] of versions) {
    for (const [version, containers] of buckets) {
      lines.push(`  ${lane.padEnd(8)} | ${String(version).padEnd(14)} | ${containers.length}/7 lojas`);
      if (containers.length < 7) lines.push(`           | ${containers.join(', ')}`);
    }
  }
  lines.push('');

  if (diagnosis.warnings.length) {
    for (const warning of diagnosis.warnings) lines.push(`  [aviso] ${warning}`);
    lines.push('');
  }
  if (diagnosis.ready) {
    lines.push('[E2E] Lanes prontas para publicar. Proximo passo:');
    lines.push('  SMOOTH_USER=<user> make e2e-shared-release SITE=<SITE> PRODUCTION_VERSION=<X.Y.Z>');
  } else {
    lines.push('[E2E] A publicacao seria recusada. Pendencias:');
    for (const blocker of diagnosis.blockers) {
      lines.push(`  - ${blocker.what}`);
      lines.push(`    -> ${blocker.fix}`);
    }
  }
  return lines.join('\n');
}

function main() {
  const config = loadEnvironmentConfig();
  const containers = LANES.flatMap((lane) => getLaneContainers(config, lane));
  const state = collectRemoteState(config, containers);
  const diagnosis = diagnose(state, config, config.lock.ttlSeconds);
  process.stdout.write(`${formatReport(state, diagnosis)}\n`);
  process.exitCode = diagnosis.ready ? 0 : 1;
}

if (require.main === module) {
  try {
    main();
  } catch (error) {
    const message = error.message && error.message.startsWith('[E2E]')
      ? error.message
      : '[E2E] Falha inesperada no preflight das lanes.';
    process.stderr.write(`${message}\n`);
    process.exitCode = 2;
  }
}

module.exports = {
  LANES,
  REMOTE_PREFLIGHT_SCRIPT,
  collectRemoteState,
  diagnose,
  formatReport,
  parseRemoteState,
};
