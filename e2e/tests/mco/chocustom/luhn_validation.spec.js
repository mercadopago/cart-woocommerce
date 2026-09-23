import { mco } from "../../../data/meli_sites";
import { registerLuhnBlockScenario } from "../../../flows/luhn_multicountry";

// Luhn validation — multi-country coverage (MCO). See flows/luhn_multicountry.js.
registerLuhnBlockScenario('MCO', mco, mco.guestUserMCO);
