/**
 * Escapes a string for safe interpolation into an HTML string sink. Ported verbatim from the
 * legacy `escapeHtml` (payment-methods.js:174-178): it relies on the DOM to escape `<`, `>` and
 * `&`, then additionally escapes quotes. Used by the consumer-credits hint, which is assigned via
 * `innerHTML` and must keep escaping the SDK-provided condition values.
 */
export function escapeHtml(value: string): string {
  const div = document.createElement('div');
  div.textContent = String(value);
  return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
