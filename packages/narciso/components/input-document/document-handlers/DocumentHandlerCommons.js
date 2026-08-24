/**
 * Abstract base class for document handlers
 *
 * All subclasses must implement:
 * - static CONFIG { site_id, default, max_length, max_length_with_mask, placeholder }
 * - static validate(value) - returns { result: boolean, type: string }
 * - static mask(value) - returns masked string (optional)
 */
class DocumentHandlerCommons {
  static ERROR_TYPES = {
    INVALID: 'invalid',
    EMPTY: 'empty',
    WRONG: 'wrong',
    VALID: 'valid',
  };

  /**
   * Abstract method - MUST be implemented by subclasses
   * @param {string} value - Document value to validate
   * @returns {{ result: boolean, type: string }}
   */
  static validate(value) {
    throw new Error(`Method 'validate()' must be implemented by ${this.name}`);
  }

  /**
   * Abstract method - CAN be overridden by subclasses
   * @param {string} value - Document value to mask
   * @returns {string}
   */
  static mask(value) {
    return value;
  }
}

module.exports = DocumentHandlerCommons;
