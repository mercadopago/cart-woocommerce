const { wpEval } = require('./wp-env');

// Sentinel distinguishing "option absent" from "option present but empty string".
// get_option returns this default only when the option row does not exist.
const ABSENT = '__mp_e2e_absent__';

/**
 * Snapshots the given WP options (base64 JSON) so they can be restored to their
 * EXACT prior state — including "did not exist" (restored via delete_option), so a
 * test never leaves behind an option the store did not have. Values may be scalars
 * or arrays (e.g. a gateway settings option).
 *
 * Take the snapshot BEFORE any setup that seeds these options, so the restore returns
 * the store to its genuine pre-test configuration.
 *
 * @param {string[]} keys option names
 * @returns {string} opaque base64 snapshot for restoreOptions()
 */
function snapshotOptions(keys) {
  const entries = keys.map((k) => `"${k}" => get_option("${k}", "${ABSENT}")`).join(',');
  return String(wpEval(`echo base64_encode(json_encode(array(${entries})));`) || '').trim();
}

/**
 * Restores options captured by snapshotOptions(): options that were absent are
 * deleted; the rest are written back with their original value.
 *
 * @param {string} snapshot value returned by snapshotOptions()
 */
function restoreOptions(snapshot) {
  if (!snapshot) return;
  wpEval(
    `$c = json_decode(base64_decode("${snapshot}"), true);` +
    `if (is_array($c)) { foreach ($c as $k => $v) {` +
    `if ($v === "${ABSENT}") { delete_option($k); } else { update_option($k, $v); }` +
    `} }`
  );
}

module.exports = { snapshotOptions, restoreOptions };
