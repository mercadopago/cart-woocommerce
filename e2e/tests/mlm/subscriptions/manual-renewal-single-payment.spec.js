import { mlm } from "../../../data/meli_sites";
import { registerManualRenewalSinglePaymentScenario } from "../../../flows/manual_renewal_multicountry";

// Accept Manual Renewals — multi-country coverage (MLM); @serial-store scenario (tag on the describe in flows/manual_renewal_multicountry.js).
registerManualRenewalSinglePaymentScenario('MLM', mlm, mlm.guestUserMLM);
