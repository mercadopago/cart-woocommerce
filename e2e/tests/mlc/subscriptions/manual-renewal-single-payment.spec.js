import { mlc } from "../../../data/meli_sites";
import { registerManualRenewalSinglePaymentScenario } from "../../../flows/manual_renewal_multicountry";

// Accept Manual Renewals — multi-country coverage (MLC); @serial-store scenario (tag on the describe in flows/manual_renewal_multicountry.js).
registerManualRenewalSinglePaymentScenario('MLC', mlc, mlc.guestUserMLC);
