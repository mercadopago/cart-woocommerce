const { formatCurrency } = require('@super-token/core/shared/formatting');

// The module formats with the standard `Intl` and, for MLM only, inserts a space after the
// currency symbol. Comparisons build the raw Intl output the same way the module does, so the
// assertions stay independent of the Node ICU locale data.
const rawIntl = (value, intl, currency) =>
  new Intl.NumberFormat(intl, { currency, style: 'currency', currencyDisplay: 'narrowSymbol' }).format(value);

describe('formatCurrency', () => {
  it('Given a non-MLM site, When formatted, Then it returns the Intl currency output unchanged', () => {
    const raw = rawIntl(1234.5, 'pt-BR', 'BRL');
    expect(formatCurrency(1234.5, { intl: 'pt-BR', currency: 'BRL', siteId: 'MLB' })).toBe(raw);
  });

  it('Given MLM, When formatted, Then a space is inserted after the currency symbol', () => {
    const raw = rawIntl(1234.5, 'es-MX', 'MXN');
    const expected = raw.replace(/^(\D+)/, '$1 ');
    expect(formatCurrency(1234.5, { intl: 'es-MX', currency: 'MXN', siteId: 'MLM' })).toBe(expected);
  });

  it('Given MLM, When the symbol has no trailing space in the raw output, Then the result adds exactly one', () => {
    const raw = rawIntl(10, 'es-MX', 'MXN');
    const formatted = formatCurrency(10, { intl: 'es-MX', currency: 'MXN', siteId: 'MLM' });
    // Only MLM differs from the raw Intl output, and only by the inserted separator.
    expect(formatted).not.toBe(raw);
    expect(formatted.replace(/(\D+)\s/, '$1')).toBe(raw);
  });
});
