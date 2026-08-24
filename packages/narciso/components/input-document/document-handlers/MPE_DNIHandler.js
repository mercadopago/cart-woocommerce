const DocumentHandlerCommons = require('./DocumentHandlerCommons');

/**
 * Documento Nacional de Identidad
 *
 * @countries MPE
 * @example 17.801.146
 */
class MPE_DNIHandler extends DocumentHandlerCommons {
  static CONFIG = {
    site_id: 'MPE',
    default: true,
    max_length: 9,
    max_length_with_mask: 12,
    min_length: 8,
    placeholder: '99.999.999',
  };

  // Visual-only mask: group digits without truncating (LTR: XX.XXX.XXX.XXX…).
  // Unlike the other handlers this one used to cap the body at 8 + a DV, which
  // blocked what develop accepts — see traps.md.
  static mask(dni) {
    const digits = dni.replace(/\D+/g, '');
    if (digits.length <= 2) return digits;
    const head = digits.slice(0, 2);
    const rest = digits.slice(2).replace(/(\d{3})(?=\d)/g, '$1.');
    return `${head}.${rest}`;
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

module.exports = MPE_DNIHandler;
