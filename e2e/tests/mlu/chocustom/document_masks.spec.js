import { mlu } from "../../../data/meli_sites";
import { registerDocumentFieldTests } from "../../../flows/document_field_suite";

// MLU (Uruguay) document field: CI (default, DV).
registerDocumentFieldTests({
  siteId: 'MLU',
  site: mlu,
  guestUser: mlu.guestUser,
  maskCases: [
    { type: 'CI', raw: '40000002', masked: '4.000.000-2', rawHidden: '40000002' },
  ],
  emptyTypes: ['CI'],
  invalidCases: [
    { type: 'CI', invalid: '40000003', label: 'CI' },
  ],
});
