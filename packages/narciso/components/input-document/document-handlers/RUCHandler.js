const DocumentHandlerCommons = require('./DocumentHandlerCommons');

/**
 * Registro Único de Contribuyentes
 *
 * @countries MPE
 * @example 20.538.995.364
 */
class RUCHandler extends DocumentHandlerCommons {
  static CONFIG = {
    site_id: 'MPE',
    default: false,
    max_length: 11,
    max_length_with_mask: 14,
    min_length: 11,
    placeholder: '99.999.999.999',
  };

  static mask(ruc) {
    const value = ruc.replace(/\D+/g, '');

    if (value.length <= 3) return value;
    if (value.length <= 6) return value.slice(0, -3) + '.' + value.slice(-3);
    if (value.length <= 9) return value.slice(0, -6) + '.' + value.slice(-6, -3) + '.' + value.slice(-3);
    return value.slice(0, -9) + '.' + value.slice(-9, -6) + '.' + value.slice(-6, -3) + '.' + value.slice(-3);
  }

  // Permissive interim validation: reject only empty so the required-field error
  // surfaces in real time, mirroring the submit gate. Length/DV validation is
  // deferred to the SDK — see traps.md.
  static validate(value) {
    if (typeof value !== 'string' || value.length === 0) {
      return { result: false, type: this.ERROR_TYPES.EMPTY };
    }

    return { result: true, type: this.ERROR_TYPES.VALID };
  }
}

module.exports = RUCHandler;
