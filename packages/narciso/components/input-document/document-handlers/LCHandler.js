const DocumentHandlerCommons = require('./DocumentHandlerCommons');

/**
 * Libreta Cívica
 *
 * @countries MLA
 * @example 1.234.567
 */
class LCHandler extends DocumentHandlerCommons {
  static CONFIG = {
    site_id: 'MLA',
    default: false,
    max_length: 7,
    max_length_with_mask: 9,
    min_length: 6,
    placeholder: '9.999.999',
  };

  static mask(lc) {
    const value = lc.replace(/\D+/g, '');

    if (value.length <= 3) return value;
    if (value.length <= 6) return value.slice(0, -3) + '.' + value.slice(-3);
    return value.slice(0, -6) + '.' + value.slice(-6, -3) + '.' + value.slice(-3);
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

module.exports = LCHandler;
