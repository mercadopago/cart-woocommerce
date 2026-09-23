import { mlb } from "../../../data/meli_sites";
import { registerDocumentFieldTests } from "../../../flows/document_field_suite";

// MLB (Brazil) document field: CPF (default, DV), CNPJ (numeric + alphanumeric, DV).
registerDocumentFieldTests({
  siteId: 'MLB',
  site: mlb,
  guestUser: mlb.guestUserMLB,
  maskCases: [
    { type: 'CPF', raw: '11144478200', masked: '111.444.782-00', rawHidden: '11144478200' },
    { type: 'CNPJ', raw: '11222333001900', masked: '11.222.333/0019-00', rawHidden: '11222333001900', label: 'CNPJ (numeric)' },
    { type: 'CNPJ', raw: '1A020000005900', masked: '1A.020.000/0059-00', rawHidden: '1A020000005900', label: 'CNPJ (alphanumeric)' },
  ],
  emptyTypes: ['CPF', 'CNPJ'],
  invalidCases: [
    { type: 'CPF', invalid: '12345678901', label: 'CPF' },
    { type: 'CNPJ', invalid: '11222333001901', label: 'CNPJ' },
  ],
});
