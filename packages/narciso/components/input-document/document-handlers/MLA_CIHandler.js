const DocumentHandlerCommons = require('./DocumentHandlerCommons');

/**
 * Cédula de Identidad
 *
 * @countries MLA
 * @example 12.345.672
*/
class MLA_CIHandler extends DocumentHandlerCommons {
  static CONFIG = {
    site_id: 'MLA',
    default: false,
    max_length: 8,
    max_length_with_mask: 10,
    min_length: 7,
    placeholder: '99.999.999',
  };

  static mask(ci) {
    const value = ci.replace(/\D+/g, '');

    if (value.length <= 3) return value;
    if (value.length <= 6) return value.slice(0, -3) + '.' + value.slice(-3);
    return value.slice(0, -6) + '.' + value.slice(-6, -3) + '.' + value.slice(-3);
  }

  static validate(ci) {
    if (typeof ci !== 'string' || ci.length === 0) {
      return { result: false, type: this.ERROR_TYPES.EMPTY };
    }

    ci = ci.replace(/[\s.-]/g, '');

    if (ci.length > this.CONFIG.max_length) {
      return { result: false, type: this.ERROR_TYPES.WRONG };
    }

    let x = 0;
    let y = 0;
    let docCI = 0;
    const dig = ci[ci.length - 1];

    if (ci.length < this.CONFIG.min_length) {
      for (y = ci.length; y < this.CONFIG.min_length; y += 1) {
        ci = `0${ci}`;
      }
    }

    for (y = 0; y < this.CONFIG.min_length; y += 1) {
      x += (parseInt('2987634'[y], 10) * parseInt(ci[y], 10)) % 10;
    }

    if (x % 10 === 0) {
      docCI = 0;
    } else {
      docCI = 10 - (x % 10);
    }

    if (dig !== docCI.toString()) {
      return { result: false, type: this.ERROR_TYPES.WRONG };
    } else {
      return { result: true, type: this.ERROR_TYPES.VALID };
    }
  }
}

module.exports = MLA_CIHandler;
