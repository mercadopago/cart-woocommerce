import { mpe } from "../../../data/meli_sites";
import { registerManualRenewalSinglePaymentScenario } from "../../../flows/manual_renewal_multicountry";

// Accept Manual Renewals — multi-country coverage (MPE); @serial-store scenario (tag on the describe in flows/manual_renewal_multicountry.js).
registerManualRenewalSinglePaymentScenario('MPE', mpe, mpe.guestUser);
