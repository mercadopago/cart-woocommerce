import { mla } from "../../../data/meli_sites";
import { registerDocumentFieldTests } from "../../../flows/document_field_suite";

// MLA (Argentina) document field: DNI (default), CI (DV), LC, LE.
registerDocumentFieldTests({
  siteId: 'MLA',
  site: mla,
  guestUser: mla.guestUserMLA,
  maskCases: [
    { type: 'DNI', raw: '12345678', masked: '12.345.678', rawHidden: '12345678' },
    { type: 'CI', raw: '30000004', masked: '30.000.004', rawHidden: '30000004' },
    { type: 'LC', raw: '1234567', masked: '1.234.567', rawHidden: '1234567' },
    { type: 'LE', raw: '1234567', masked: '1.234.567', rawHidden: '1234567' },
  ],
  emptyTypes: ['DNI', 'CI', 'LC', 'LE'],
  invalidCases: [
    { type: 'CI', invalid: '30000005', label: 'CI' },
  ],
});
