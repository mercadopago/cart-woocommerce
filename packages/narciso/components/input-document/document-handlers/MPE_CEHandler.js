const DocumentHandlerCommons = require('./DocumentHandlerCommons');
/**
 * Carné de Extranjería
 *
 * @countries MPE
 * @example 123.456.789
 */
class MPE_CEHandler extends DocumentHandlerCommons {
  static CONFIG = {
    site_id: 'MPE',
    default: false,
    max_length: 12,
    max_length_with_mask: 15,
    min_length: 6,
    placeholder: '999.999.999.999',
  };

  static mask(ce) {
    const value = ce.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();

    if (value.length <= 3) return value;
    if (value.length <= 6) return value.slice(0, -3) + '.' + value.slice(-3);
    if (value.length <= 9) return value.slice(0, -6) + '.' + value.slice(-6, -3) + '.' + value.slice(-3);
    return value.slice(0, -9) + '.' + value.slice(-9, -6) + '.' + value.slice(-6, -3) + '.' + value.slice(-3);
  }

  // Permissive interim validation (matches develop): only empty is rejected.
  // Real document validation is deferred to the SDK — see traps.md.
  static validate(ce) {
    if (typeof ce !== 'string' || ce.length === 0) {
      return { result: false, type: this.ERROR_TYPES.EMPTY };
    }

    return { result: true, type: this.ERROR_TYPES.VALID };
  }
}

module.exports = MPE_CEHandler;
