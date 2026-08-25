const DocumentHandlerCommons = require('./DocumentHandlerCommons');

/**
 * Rol Único Tributario / Rol Único Nacional
 *
 * @countries MLC
 * @example 15.871.597-k
 */
class RUTHandler extends DocumentHandlerCommons {
  static CONFIG = {
    site_id: 'MLC',
    default: true,
    max_length: 9,
    max_length_with_mask: 12,
    min_length: 8,
    placeholder: '99.999.999-9',
  };

  static mask(rut) {
    const value = rut.replace(/[^0-9kK]/g, '').toUpperCase();

    if (value.length <= 3) return value;

    if (value.length > 7) {
      const body = value.slice(0, -1);
      const dv = value.slice(-1);

      let maskedBody;
      if (body.length <= 3) {
        maskedBody = body;
      } else if (body.length <= 6) {
        maskedBody = body.slice(0, -3) + '.' + body.slice(-3);
      } else {
        maskedBody = body.slice(0, -6) + '.' + body.slice(-6, -3) + '.' + body.slice(-3);
      }

      return maskedBody + '-' + dv;
    }

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

module.exports = RUTHandler;
