import { mco } from "../../../data/meli_sites";
import { registerManualRenewalSinglePaymentScenario } from "../../../flows/manual_renewal_multicountry";

// Accept Manual Renewals — multi-country coverage (MCO); @serial-store scenario (tag on the describe in flows/manual_renewal_multicountry.js).
registerManualRenewalSinglePaymentScenario('MCO', mco, mco.guestUserMCO);
