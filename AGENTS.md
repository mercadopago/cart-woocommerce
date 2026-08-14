---
type: Repository
app: woocommerce-plugins-enablers
archetype: wordpress-plugin
version: 1aa9f644
validated: 2026-07-29
update_when: when the repo identity, reading order, or maintenance rule changes
---

# AGENTS.md

## Identity

`woocommerce-plugins-enablers` is the official Mercado Pago payment gateway plugin for WooCommerce/WordPress — a wordpress-plugin that enables LATAM merchants to accept card, PIX, boleto, PSE, Yape, and Creditos payments; it runs entirely inside merchant WordPress installations with no standalone server.

## How to use this package

Read the orientation guides under `docs/agent/` in this order:

1. [overview.md](docs/agent/overview.md) — what this repo does, the capabilities it owns, and where each lives in the code.
2. [architecture.md](docs/agent/architecture.md) — layers, entrypoints, request/data flow.
3. [contracts.md](docs/agent/contracts.md) — what it exposes and what it depends on.
4. [runbook.md](docs/agent/runbook.md) — how to build/run/test and the Definition of Done.
5. [traps.md](docs/agent/traps.md) — tacit gotchas not visible in the code.

## Team hub (P&P)

This repo belongs to the **Plugins & Payments (P&P)** team. To navigate the wider ecosystem from here:

- **Domain hub — specs / SDD index, source of truth:** https://github.com/melisource/fury_mp-op-pp-sdd — see [`tech/architecture/sdd-index.md`](https://github.com/melisource/fury_mp-op-pp-sdd/blob/master/tech/architecture/sdd-index.md) for the features this plugin participates in. Details in [overview.md](docs/agent/overview.md).
- **Process hub — DoR/DoD, code review, standards:** https://github.com/melisource/fury_mp-op-pp-development-cycle (also referenced in the DoR/DoD section below).

## Maintenance rule (read this before changing anything)

**If you change code in this repo, update the matching guide in `docs/agent/` in the same PR**, and record any new non-obvious gotcha in [traps.md](docs/agent/traps.md). When updating a guide, bump its `version` to the current HEAD SHA; if you added or moved code directories it covers, update its `scope`. Before a breaking change to a public interface, check the consumer snapshot in [contracts.md](docs/agent/contracts.md) and, if a consumer is your own team's, adapt it too. **Keeping the guides in sync with the code is part of Done.**

This applies to anyone — human or agent, with or without the team's shared conventions loaded.

## Code & security rules (linked, not authored here)

See `.agentic-rules/nodejs/nodejs-security-patterns-rules_v1.md` for full security patterns. The section below is authored by the AppSec team and must be preserved verbatim.

## 1. Security & Environment

### Security Best Practices

Follow security best practices for node development.

**rule file**: `.agentic-rules/nodejs/nodejs-security-patterns-rules_v1.md`

## 2. Code Style & Standards

Follow the code style conventions for node.

## 3. Testing

Write comprehensive tests for all new functionality.

## Process: DoR/DoD

The P&P team follows a centralized Definition of Ready and Definition of Done standard.

**Hub (source of truth):** https://github.com/melisource/fury_mp-op-pp-development-cycle
- DoR: [`docs/DEFINITION_OF_READY.md`](https://github.com/melisource/fury_mp-op-pp-development-cycle/blob/master/docs/DEFINITION_OF_READY.md) — gates DoR-0 to DoR-3
- DoD: [`docs/DEFINITION_OF_DONE.md`](https://github.com/melisource/fury_mp-op-pp-development-cycle/blob/master/docs/DEFINITION_OF_DONE.md)
- Operational checklist: [`checklists/FEATURE_CHECKLIST.md`](https://github.com/melisource/fury_mp-op-pp-development-cycle/blob/master/checklists/FEATURE_CHECKLIST.md)

### DoD checklist — applied to this repo (derived from the P&P hub standard)

Verify before opening a PR, without leaving this repository (see also [runbook.md](docs/agent/runbook.md#definition-of-done)):

- [ ] Tests pass: `composer test && npm test`
- [ ] Static analysis clean: `composer phpstan` / lint: `npm run lint`
- [ ] Coverage checked: `composer test:coverage`
- [ ] Agent docs (`AGENTS.md` / `docs/agent/`) updated in the same PR if agent-relevant behavior changed
- [ ] No secrets, tokens or credentials exposed in code
- [ ] CHANGELOG updated if the repo keeps one
- [ ] Observability updated when applicable

> Product gates (feature flag, gradual rollout, formal TL/PL approvals): see
> [FEATURE_CHECKLIST.md](https://github.com/melisource/fury_mp-op-pp-development-cycle/blob/master/checklists/FEATURE_CHECKLIST.md) in the team hub.
