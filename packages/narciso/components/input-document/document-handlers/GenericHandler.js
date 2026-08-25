const DocumentHandlerCommons = require('./DocumentHandlerCommons');

/**
 * Generic Document Handler
 *
 * @countries all countries
 * @example 1234567890
*/
class GenericHandler extends DocumentHandlerCommons {
  static CONFIG = {
    site_id: null,
    default: false,
    min_length: 5,
    max_length: 20,
    max_length_with_mask: 20,
    placeholder: '',
  };

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

module.exports = GenericHandler;
