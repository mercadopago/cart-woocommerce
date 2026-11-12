/**
 * Shared buyer-identity gate for every fast-payment consumer backed by
 * `getAccountPaymentMethods`. Keeping it outside the Super Token listener prevents
 * Smart Pix from drifting to a subtly different eligibility rule.
 */
export function normalizeBuyerEmail(value: unknown): string {
  return typeof value === 'string' ? value.trim() : '';
}

export function isValidBuyerEmail(value: unknown): boolean {
  const email = normalizeBuyerEmail(value);

  if (!email || email.length > 254) return false;

  const localPart = email.split('@')[0];
  if (localPart && localPart.length > 64) return false;

  const regex =
    /^[a-zA-Z0-9_%+-]+(\.[a-zA-Z0-9_%+-]+)*@[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/i;
  return regex.test(email);
}
