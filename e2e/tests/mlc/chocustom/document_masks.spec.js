import { mlc } from "../../../data/meli_sites";
import { registerDocumentFieldTests } from "../../../flows/document_field_suite";

// MLC (Chile) document field: RUT (default). No DV gate here (permissive), so no
// invalid case — only mask conformance (incl. the K check digit) and empty.
registerDocumentFieldTests({
  siteId: 'MLC',
  site: mlc,
  guestUser: mlc.guestUserMLC,
  maskCases: [
    { type: 'RUT', raw: '12000006', masked: '1.200.000-6', rawHidden: '12000006' },
    { type: 'RUT', raw: '9000008K', masked: '9.000.008-K', rawHidden: '9000008K', label: 'RUT with K' },
  ],
  emptyTypes: ['RUT'],
  invalidCases: [],
});
