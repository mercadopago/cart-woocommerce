import { mla } from "../../../data/meli_sites";
import { registerLuhnBlockScenario } from "../../../flows/luhn_multicountry";

// Luhn validation — multi-country coverage (MLA). See flows/luhn_multicountry.js.
registerLuhnBlockScenario('MLA', mla, mla.guestUserMLA);
