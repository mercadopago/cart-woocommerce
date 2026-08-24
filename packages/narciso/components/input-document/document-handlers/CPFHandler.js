const DocumentHandlerCommons = require('./DocumentHandlerCommons');

/**
 * Cadastro de Pessoa Física
 *
 * @countries MLB
 * @example 123.456.789-09
*/
class CPFHandler extends DocumentHandlerCommons {
  static CONFIG = {
    site_id: 'MLB',
    default: true,
    max_length: 11,
    max_length_with_mask: 14,
    placeholder: '999.999.999-99',
  };

  static mask(cpf) {
    return cpf
      .replace(/\D+/g, '')
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d{1,2})/, '$1-$2')
      .replace(/(-\d{2})\d+?$/, '$1');
  }

  static validate(cpf) {
    if (typeof cpf !== 'string') {
      return { result: false, type: this.ERROR_TYPES.INVALID };
    }

    cpf = cpf.replace(/[\s.-]*/gim, '');

    if (!cpf || cpf.length === 0) {
      return { result: false, type: this.ERROR_TYPES.EMPTY };
    }

    if (
      cpf === '00000000000' ||
      cpf === '11111111111' ||
      cpf === '22222222222' ||
      cpf === '33333333333' ||
      cpf === '44444444444' ||
      cpf === '55555555555' ||
      cpf === '66666666666' ||
      cpf === '77777777777' ||
      cpf === '88888888888' ||
      cpf === '99999999999'
    ) {
      return { result: false, type: this.ERROR_TYPES.WRONG };
    }

    if (parseInt(cpf, 10) === 0) {
      return { result: false, type: this.ERROR_TYPES.WRONG };
    }

    if (!cpf || cpf.length !== 11) {
      return { result: false, type: this.ERROR_TYPES.INVALID };
    }

    let soma = 0;
    let resto;

    for (let i = 1; i <= 9; i += 1) {
      soma += parseInt(cpf.substring(i - 1, i)) * (11 - i);
    }

    resto = (soma * 10) % 11;

    if (resto === 10 || resto === 11) {
      resto = 0;
    }

    if (resto !== parseInt(cpf.substring(9, 10))) {
      return { result: false, type: this.ERROR_TYPES.WRONG };
    }

    soma = 0;

    for (let i = 1; i <= 10; i += 1) {
      soma += parseInt(cpf.substring(i - 1, i)) * (12 - i);
    }

    resto = (soma * 10) % 11;

    if (resto === 10 || resto === 11) {
      resto = 0;
    }

    if (resto !== parseInt(cpf.substring(10, 11))) {
      return { result: false, type: this.ERROR_TYPES.WRONG };
    }

    return { result: true, type: this.ERROR_TYPES.VALID };
  }
}

module.exports = CPFHandler;
