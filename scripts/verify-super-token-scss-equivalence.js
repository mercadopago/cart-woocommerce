#!/usr/bin/env node
/**
 * Verifies that merging the two per-variant Super Token stylesheets into a single
 * served CSS did not change either variant's rendered result (TASK-013 / PSW-4277,
 * Fatia 2a). Succeeds the legacy-monolith equivalence gate from PSW-4275: that
 * reference (`v2/`, `v2.1/` legacy `.css`) was removed with the SCSS unification in
 * PSW-4276, so this now guards the merge instead.
 *
 * Reference: `super-token-v{2,2.1}.scss` (kept only as this fixture, not shipped) —
 * each compiles to the shared rules plus that variant's rules.
 * Candidate: the shipped `super-token.scss`, sliced to one variant — the shared
 * rules (no data-variant) plus that variant's `[data-variant]`-scoped rules; the
 * other variant's rules are dropped (a root carries exactly one data-variant, so
 * v2 and v2.1 rules never co-match).
 *
 * Per variant, the reference and the sliced candidate must be equivalent:
 *   - CONTENT: the multiset of (at-context, selector, property, value) atoms must
 *     be identical (grouping- and order-insensitive) + keyframes canonical equal;
 *   - CASCADE: among declarations with equal specificity + same property whose
 *     selectors can co-match, relative order (hence the winner) must be preserved.
 *
 * Requires Node ^20 (see .nvmrc) so the pinned Sass runs. Exits non-zero on any
 * difference. Run: `node scripts/verify-super-token-scss-equivalence.js`.
 */
const path = require('path');
const postcss = require('postcss');
const selparser = require('postcss-selector-parser');
const { execFileSync } = require('child_process');

const PLUGIN = path.resolve(__dirname, '..');
const ST = path.join(PLUGIN, 'assets/css/checkouts/super-token');
const sassBin = path.join(PLUGIN, 'node_modules/sass/sass.js');

function compile(entry) {
  return execFileSync(process.execPath, [sassBin, '--no-source-map', path.join(ST, entry)], { encoding: 'utf8', maxBuffer: 1 << 26 });
}

function canonSel(selraw) {
  const out = [];
  selparser((sels) => sels.each((sel) => {
    const parts = []; let comp = [];
    const flush = () => { if (comp.length) { comp.sort(); parts.push(comp.join('')); comp = []; } };
    sel.each((n) => { if (n.type === 'combinator') { flush(); const c = n.value.trim(); parts.push(c === '' ? ' ' : ` ${c} `); } else comp.push(n.toString().trim()); });
    flush(); out.push(parts.join('').replace(/\s+/g, ' ').replace(/['"]/g, '').trim());
  })).processSync(selraw);
  return out;
}
const declStr = (d) => `${d.prop.trim().toLowerCase()}:${d.value.replace(/\s+/g, ' ').trim()}${d.important ? ' !important' : ''}`;

function specTuple(sel) {
  try {
    let a = 0, b = 0, c = 0;
    const root = selparser().astSync(sel);
    const walk = (node, sign = 1) => node.each(n => {
      if (n.type === 'id') a += sign;
      else if (n.type === 'class' || n.type === 'attribute') b += sign;
      else if (n.type === 'tag') { if (n.value !== '*') c += sign; }
      else if (n.type === 'pseudo') {
        const name = n.value.replace(/:/g, '').toLowerCase();
        if (n.value.startsWith('::')) c += sign;
        else if (name === 'where') { /* 0 */ }
        else if (['not', 'is', 'has'].includes(name)) {
          let best = [0, 0, 0];
          n.nodes.forEach(arg => { const s = specTuple(arg.toString()); if (cmp(s, best) > 0) best = s; });
          a += sign * best[0]; b += sign * best[1]; c += sign * best[2];
        } else b += sign;
      }
    });
    root.each(s => walk(s));
    return [a, b, c];
  } catch (e) { return [0, 0, 0]; }
}
const cmp = (x, y) => { for (let i = 0; i < 3; i++) if (x[i] !== y[i]) return x[i] - y[i]; return 0; };

function extract(css) {
  const root = postcss.parse(css);
  const atoms = [], decls = [], keyframes = [];
  let order = 0;
  const handleRule = (rule, ctx) => {
    const sels = canonSel(rule.selector);
    const body = []; rule.each(n => { if (n.type === 'decl') body.push({ prop: n.prop.trim().toLowerCase(), value: declStr(n).split(':').slice(1).join(':') }); });
    sels.forEach(s => {
      const sp = specTuple(s);
      body.forEach(d => { atoms.push(`${ctx}||${s}||${d.prop}||${d.value}`); decls.push({ selector: s, spec: sp, prop: d.prop, value: d.value, order: order++, ctx }); });
    });
  };
  root.each(node => {
    if (node.type === 'rule') handleRule(node, '');
    else if (node.type === 'atrule') {
      if (node.name === 'keyframes') {
        const inner = [];
        node.walkRules(r => { const b = []; r.walkDecls(d => b.push(declStr(d))); inner.push(`${r.selector}{${b.sort().join(';')}}`); });
        keyframes.push(`@keyframes ${node.params}||${inner.join('')}`);
      } else {
        const ctx = `@${node.name} ${node.params.replace(/\s+/g, ' ').trim()}`;
        node.each(child => { if (child.type === 'rule') handleRule(child, ctx); });
      }
    }
  });
  return { atoms, decls, keyframes };
}

// A root carries exactly one data-variant, so a rule scoped to the other variant never applies to
// this one — keep the shared (un-scoped) rules and this variant's, drop the other variant's.
function belongsToVariant(selector, v) {
  const scoped = (val) => selector.includes(`[data-variant=${val}]`);
  if (scoped(v)) return true;
  if (scoped('v2') || scoped('v2.1')) return false;
  return true;
}
function sliceVariant(full, v) {
  return {
    atoms: full.atoms.filter((a) => belongsToVariant(a.split('||')[1], v)),
    decls: full.decls.filter((d) => belongsToVariant(d.selector, v)),
    keyframes: full.keyframes,
  };
}

function multisetDiff(aArr, bArr) {
  const count = new Map();
  aArr.forEach(x => count.set(x, (count.get(x) || 0) + 1));
  bArr.forEach(x => count.set(x, (count.get(x) || 0) - 1));
  const onlyA = [], onlyB = [];
  for (const [k, v] of count) { if (v > 0) onlyA.push(k); else if (v < 0) onlyB.push(k); }
  return { onlyA, onlyB };
}
function tailOf(sel) { const m = 'mp-super-token-payment-methods-list'; const i = sel.indexOf(m); return i < 0 ? sel : sel.slice(i + m.length).trim(); }
function coMatch(a, b) { const ta = tailOf(a), tb = tailOf(b); return ta === tb || ta.includes(tb) || tb.includes(ta); }

function cascadeFlags(orig, comp) {
  const flags = [];
  const key = d => `${d.prop}##${d.spec.join(',')}`;
  const groups = new Map();
  orig.forEach(d => { const k = key(d); (groups.get(k) || groups.set(k, { o: [], c: [] }).get(k)).o.push(d); });
  comp.forEach(d => { const k = key(d); (groups.get(k) || groups.set(k, { o: [], c: [] }).get(k)).c.push(d); });
  for (const [k, g] of groups) {
    const oOrder = new Map(); g.o.forEach((d, i) => oOrder.set(`${d.ctx}|${d.selector}=>${d.value}`, i));
    const cOrder = new Map(); g.c.forEach((d, i) => cOrder.set(`${d.ctx}|${d.selector}=>${d.value}`, i));
    const entries = g.o.map(d => ({ ctx: d.ctx, selector: d.selector, value: d.value }));
    for (let i = 0; i < entries.length; i++) {
      for (let j = i + 1; j < entries.length; j++) {
        const A = entries[i], B = entries[j];
        if (A.value === B.value || A.ctx !== B.ctx || !coMatch(A.selector, B.selector)) continue;
        const oc = oOrder.get(`${A.ctx}|${A.selector}=>${A.value}`) - oOrder.get(`${B.ctx}|${B.selector}=>${B.value}`);
        const cc = cOrder.get(`${A.ctx}|${A.selector}=>${A.value}`) - cOrder.get(`${B.ctx}|${B.selector}=>${B.value}`);
        if (Number.isNaN(cc) || cc === undefined) continue;
        if (Math.sign(oc) !== Math.sign(cc)) flags.push({ key: k, a: `${A.selector} => ${A.value}`, b: `${B.selector} => ${B.value}` });
      }
    }
  }
  return flags;
}

let ok = true;
const single = extract(compile('super-token.scss'));
for (const v of ['v2', 'v2.1']) {
  const entry = v === 'v2' ? 'super-token-v2.scss' : 'super-token-v2.1.scss';
  const O = extract(compile(entry));
  const C = sliceVariant(single, v);
  const atomD = multisetDiff(O.atoms, C.atoms);
  const kfD = multisetDiff(O.keyframes, C.keyframes);
  const flags = cascadeFlags(O.decls, C.decls);
  const good = !atomD.onlyA.length && !atomD.onlyB.length && !kfD.onlyA.length && !kfD.onlyB.length && !flags.length;
  ok = ok && good;
  console.log(`\n=== ${v} ${good ? 'OK' : 'DIFF'} === atoms ref ${O.atoms.length} / single-slice ${C.atoms.length}`);
  console.log(`  content missing ${atomD.onlyA.length}, extra ${atomD.onlyB.length}; keyframes -${kfD.onlyA.length}/+${kfD.onlyB.length}; cascade flags ${flags.length}`);
  atomD.onlyA.slice(0, 40).forEach(x => console.log('  - ' + x));
  atomD.onlyB.slice(0, 40).forEach(x => console.log('  + ' + x));
  flags.slice(0, 40).forEach(f => console.log(`  [${f.key}] ${f.a} | ${f.b}`));
}
console.log(ok ? '\nALL EQUIVALENT' : '\nDIFFERENCES REMAIN');
process.exit(ok ? 0 : 1);
