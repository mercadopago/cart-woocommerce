/**
 * v2 has no account-money selection decoration — the balance line is a v2.1-only feature.
 * Both hooks are intentional no-ops so the checkout orchestrator can call them uniformly
 * without branching on the variant.
 */
export class V2AccountMoneyDecoration {
  decorate(): void {
    // no-op: v2 shows no account-money balance line
  }

  clear(): void {
    // no-op: v2 has nothing to undo
  }
}
