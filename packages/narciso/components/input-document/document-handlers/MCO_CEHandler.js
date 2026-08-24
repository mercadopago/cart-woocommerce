const DocumentHandlerCommons = require('./DocumentHandlerCommons');

/**
 * Cédula de Extranjería
 *
 * @countries MCO
 * @example E226932
 */
class MCO_CEHandler extends DocumentHandlerCommons {
  static CONFIG = {
    site_id: 'MCO',
    default: false,
    max_length: 7,
    min_length: 6,
    max_length_with_mask: 7,
    placeholder: '9999999',
  };

  // Permissive interim validation (matches develop): only empty is rejected.
  // Real document validation is deferred to the SDK — see traps.md.
  static validate(ce) {
    if (typeof ce !== 'string' || ce.length === 0) {
      return { result: false, type: this.ERROR_TYPES.EMPTY };
    }

    return { result: true, type: this.ERROR_TYPES.VALID };
  }
}

module.exports = MCO_CEHandler;
