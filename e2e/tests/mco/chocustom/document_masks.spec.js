import { mco } from "../../../data/meli_sites";
import { registerDocumentFieldTests } from "../../../flows/document_field_suite";

// MCO (Colombia) document field: CC (default), CE (no separator), NIT. None have a
// DV gate here (permissive), so only mask conformance and empty.
registerDocumentFieldTests({
  siteId: 'MCO',
  site: mco,
  guestUser: mco.guestUserMCO,
  maskCases: [
    { type: 'CC', raw: '1234567890', masked: '1.234.567.890', rawHidden: '1234567890' },
    { type: 'CE', raw: '1234567', masked: '1234567', rawHidden: '1234567', label: 'CE (no separator)' },
    { type: 'NIT', raw: '800000001', masked: '80.000.000-1', rawHidden: '800000001' },
  ],
  emptyTypes: ['CC', 'CE', 'NIT'],
  invalidCases: [],
});
