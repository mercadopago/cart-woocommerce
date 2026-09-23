import { mpe } from "../../../data/meli_sites";
import { registerDocumentFieldTests } from "../../../flows/document_field_suite";

// MPE (Peru) document field: DNI (default), C.E, RUC. None have a DV gate here
// (permissive), so only mask conformance and empty.
registerDocumentFieldTests({
  siteId: 'MPE',
  site: mpe,
  guestUser: mpe.guestUser,
  maskCases: [
    { type: 'DNI', raw: '10000000', masked: '10.000.000', rawHidden: '10000000' },
    { type: 'C.E', raw: '123456789012', masked: '123.456.789.012', rawHidden: '123456789012' },
    { type: 'RUC', raw: '20100000009', masked: '20.100.000.009', rawHidden: '20100000009' },
  ],
  emptyTypes: ['DNI', 'C.E', 'RUC'],
  invalidCases: [],
});
