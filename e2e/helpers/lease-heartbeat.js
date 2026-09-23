function startLeaseHeartbeat({
  config,
  token,
  owner,
  correlationId,
  site,
  runRemoteLock,
  onLost = () => {},
  setIntervalFn = setInterval,
  clearIntervalFn = clearInterval,
}) {
  if (!config?.lock?.heartbeatSeconds || typeof runRemoteLock !== 'function') {
    throw new Error('[E2E] Configuracao do heartbeat do lease invalida.');
  }

  let timer = null;
  let stopped = false;
  let lostError = null;

  const stop = () => {
    if (stopped) return;
    stopped = true;
    if (timer) clearIntervalFn(timer);
    timer = null;
  };

  const renew = () => {
    if (stopped || lostError) return;
    try {
      runRemoteLock(config, 'renew', token, owner, correlationId, site);
    } catch {
      lostError = new Error(
        `[E2E] Lease remoto perdido para ${String(site).toUpperCase()}; a comparacao foi invalidada.`
      );
      stop();
      try {
        onLost(lostError);
      } catch {
        // Losing the lease is already terminal; cleanup errors must not hide that cause.
      }
    }
  };

  timer = setIntervalFn(renew, config.lock.heartbeatSeconds * 1000);
  if (timer && typeof timer.unref === 'function') timer.unref();

  return {
    assertHeld() {
      if (lostError) throw lostError;
    },
    isLost() {
      return Boolean(lostError);
    },
    renewNow: renew,
    stop,
  };
}

module.exports = { startLeaseHeartbeat };
