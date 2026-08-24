const CPFValidator = require('./CPFHandler');
const CNPJValidator = require('./CNPJHandler');
const GenericValidator = require('./GenericHandler');
const MLA_DNIHandler = require('./MLA_DNIHandler');
const MPE_DNIHandler = require('./MPE_DNIHandler');
const LCHandler = require('./LCHandler');
const LEHandler = require('./LEHandler');
const MLA_CIHandler = require('./MLA_CIHandler');
const MLU_CIHandler = require('./MLU_CIHandler');
const CCHandler = require('./CCHandler');
const MCO_CEHandler = require('./MCO_CEHandler');
const MPE_CEHandler = require('./MPE_CEHandler');
const NITHandler = require('./NITHandler');
const RUTHandler = require('./RUTHandler');
const RUCHandler = require('./RUCHandler');

class DocumentHandlerFactory {
  static handlers = {
    CPF: CPFValidator,
    CNPJ: CNPJValidator,
    CC: CCHandler,
    MLA_CI: MLA_CIHandler,
    MLU_CI: MLU_CIHandler,
    MCO_CE: MCO_CEHandler,
    MPE_CE: MPE_CEHandler,
    MLA_DNI: MLA_DNIHandler,
    MPE_DNI: MPE_DNIHandler,
    NIT: NITHandler,
    LC: LCHandler,
    LE: LEHandler,
    RUT: RUTHandler,
    RUC: RUCHandler,
  };

  static getDefaultCountryHandler(siteId) {
    for (const [documentType, handler] of Object.entries(this.handlers)) {
      if (!handler.CONFIG) continue;

      if (handler.CONFIG.site_id === siteId && handler.CONFIG.default === true) {
        return documentType;
      }
    }

    return null;
  }

  static getHandler(documentType) {
    return this.handlers[documentType] || GenericValidator;
  }

  static mask(documentType, value) {
    return this.getHandler(documentType).mask(value);
  }

  static validate(documentType, value) {
    return this.getHandler(documentType).validate(value);
  }
}

module.exports = DocumentHandlerFactory;
