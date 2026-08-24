const DocumentHandlerCommons = require('./DocumentHandlerCommons');

/**
 * Cadastro Nacional de Pessoa Jurídica
 *
 * @countries MLB
 * @example 12.345.678/0001-99 (legado), 12.ABC.345/01DE-35 (2026+)
 */
class CNPJHandler extends DocumentHandlerCommons {
  static CONFIG = {
    site_id: 'MLB',
    default: false,
    max_length: 14,
    max_length_with_mask: 18,
    placeholder: '99.999.999/9999-99',
  };

  static WEIGHTS_DV1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
  static WEIGHTS_DV2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

  static mask(cnpj) {
    const alphanumeric = cnpj.replace(/[^A-Za-z0-9]/g, '');

    const body = alphanumeric.slice(0, 12);
    const dvInput = alphanumeric.slice(12);
    const dv = dvInput.replace(/[^0-9]/g, '');

    const clean = (body + dv).slice(0, 14);

    const part1 = clean.slice(0, 2);
    const part2 = clean.slice(2, 5);
    const part3 = clean.slice(5, 8);
    const part4 = clean.slice(8, 12);
    const part5 = clean.slice(12, 14);

    let masked = part1;
    if (part2) masked += `.${part2}`;
    if (part3) masked += `.${part3}`;
    if (part4) masked += `/${part4}`;
    if (part5) masked += `-${part5}`;

    return masked;
  }

  static charToValue(char) {
    return char.charCodeAt(0) - 48;
  }

  static calculateCheckDigit(payload, weights) {
    let sum = 0;

    for (let i = 0; i < payload.length; i++) {
      const value = this.charToValue(payload[i]);
      sum += value * weights[i];
    }

    const remainder = sum % 11;
    return remainder < 2 ? 0 : 11 - remainder;
  }

  static validate(cnpj) {
    if (typeof cnpj !== 'string') {
      return { result: false, type: this.ERROR_TYPES.EMPTY };
    }

    const cleanCnpj = cnpj.replace(/[^A-Za-z0-9]/g, '').toUpperCase();

    if (cleanCnpj === '') {
      return { result: false, type: this.ERROR_TYPES.EMPTY };
    }

    if (cleanCnpj.length !== 14) {
      return { result: false, type: this.ERROR_TYPES.INVALID };
    }

    if (!/^[A-Z0-9]{12}[0-9]{2}$/.test(cleanCnpj)) {
      return { result: false, type: this.ERROR_TYPES.INVALID };
    }

    if (/^(.)\1+$/.test(cleanCnpj)) {
      return { result: false, type: this.ERROR_TYPES.WRONG };
    }

    const payload = cleanCnpj.substring(0, 12);
    const providedDV1 = parseInt(cleanCnpj[12], 10);
    const providedDV2 = parseInt(cleanCnpj[13], 10);

    const calculatedDV1 = this.calculateCheckDigit(payload, this.WEIGHTS_DV1);

    if (calculatedDV1 !== providedDV1) {
      return { result: false, type: this.ERROR_TYPES.WRONG };
    }

    const payloadWithDV1 = payload + calculatedDV1.toString();
    const calculatedDV2 = this.calculateCheckDigit(payloadWithDV1, this.WEIGHTS_DV2);

    if (calculatedDV2 !== providedDV2) {
      return { result: false, type: this.ERROR_TYPES.WRONG };
    }

    return { result: true, type: this.ERROR_TYPES.VALID };
  }
}

module.exports = CNPJHandler;
