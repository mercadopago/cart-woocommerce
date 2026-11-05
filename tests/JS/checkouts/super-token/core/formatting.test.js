const { formatCurrency } = require('@super-token/core/shared/formatting');

// The module formats with the standard `Intl` and, for MLM only, inserts a space after the
// currency symbol. Comparisons build the raw Intl output the same way the module does, so the
// assertions stay independent of the Node ICU locale data.
const rawIntl = (value, intl, currency) =>
  new Intl.NumberFormat(intl, { currency, style: 'currency', currencyDisplay: 'narrowSymbol' }).format(value);

describe('formatCurrency', () => {
  it('Given MLC with CLP, When formatted, Then it uses the Chilean currency output without decimal places', () => {
    const formatted = formatCurrency(123456.78, {
      intl: 'es-CL',
      currency: 'CLP',
      siteId: 'MLC',
    });

    expect(formatted).toBe(rawIntl(123456.78, 'es-CL', 'CLP'));
    expect(formatted).toMatch(/^\$123\.457$/);
  });

  it('Given MCO with COP, When formatted, Then it drops the decimal places Intl would show for COP', () => {
    const formatted = formatCurrency(123456.78, {
      intl: 'es-CO',
      currency: 'COP',
      siteId: 'MCO',
    });

    // Whether Intl shows COP cents depends on the ICU data of the runtime (two decimals in some
    // Node/browser builds, none in others), so the zero fraction digits are forced, not inherited.
    expect(formatted).toBe(
      new Intl.NumberFormat('es-CO', {
        currency: 'COP',
        style: 'currency',
        currencyDisplay: 'narrowSymbol',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(123456.78)
    );
    expect(formatted).toMatch(/^\$\s123\.457$/);
  });

  it('Given COP on a site that keeps decimals, When formatted, Then the decimal rule follows the site, not the currency', () => {
    expect(formatCurrency(10.5, { intl: 'es-CO', currency: 'COP', siteId: 'MLA' })).toBe(rawIntl(10.5, 'es-CO', 'COP'));
  });

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
