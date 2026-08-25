const DocumentHandlerCommons = require('./DocumentHandlerCommons');

/**
 * Número de Identificación Tributaria
 *
 * @countries MCO
 * @example 800.173.557-4
 */
class NITHandler extends DocumentHandlerCommons {
  static CONFIG = {
    site_id: 'MCO',
    default: false,
    max_length: 16,
    min_length: 7,
    max_length_with_mask: 16,
    placeholder: '999.999.999-9',
  };

  static mask(nit) {
    const value = nit.replace(/\D+/g, '');

    if (value.length <= 3) return value;
    if (value.length <= 6) return value.slice(0, -3) + '.' + value.slice(-3);

    if (value.length >= 9) {
      const body = value.slice(0, -1);
      const dv = value.slice(-1);
      const maskedBody = body.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      return `${maskedBody}-${dv}`;
    }
    return value.slice(0, -6) + '.' + value.slice(-6, -3) + '.' + value.slice(-3);
  }

  // Permissive interim validation (matches develop): only empty is rejected.
  // NIT does not get the PSW-3095 module-11 check digit. Real document
  // validation is deferred to the SDK — see traps.md.
  static validate(nit) {
    if (typeof nit !== 'string' || nit.length === 0) {
      return { result: false, type: this.ERROR_TYPES.EMPTY };
    }

    return { result: true, type: this.ERROR_TYPES.VALID };
  }
}

module.exports = NITHandler;
