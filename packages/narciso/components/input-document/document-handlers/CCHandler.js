const DocumentHandlerCommons = require('./DocumentHandlerCommons');

/**
 * Cédula de Ciudadanía
 *
 * @countries MCO
 * @example 1.929.274.789
 */
class CCHandler extends DocumentHandlerCommons {
  static CONFIG = {
    site_id: 'MCO',
    default: true,
    max_length: 10,
    min_length: 5,
    max_length_with_mask: 13,
    placeholder: '9.999.999.999',
  };

  static mask(cc) {
    const value = cc.replace(/\D+/g, '');

    if (value.length <= 3) return value;
    if (value.length <= 6) return value.slice(0, -3) + '.' + value.slice(-3);
    if (value.length <= 9) return value.slice(0, -6) + '.' + value.slice(-6, -3) + '.' + value.slice(-3);
    return value.slice(0, -9) + '.' + value.slice(-9, -6) + '.' + value.slice(-6, -3) + '.' + value.slice(-3);
  }

  // Permissive interim validation (matches develop): only empty is rejected.
  // Real document validation is deferred to the SDK — see traps.md.
  static validate(cc) {
    if (typeof cc !== 'string' || cc.length === 0) {
      return { result: false, type: this.ERROR_TYPES.EMPTY };
    }

    return { result: true, type: this.ERROR_TYPES.VALID };
  }
}

module.exports = CCHandler;
