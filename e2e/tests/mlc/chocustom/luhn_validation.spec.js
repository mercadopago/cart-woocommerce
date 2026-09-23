import { mlc } from "../../../data/meli_sites";
import { registerLuhnBlockScenario } from "../../../flows/luhn_multicountry";

// Luhn validation — multi-country coverage (MLC). See flows/luhn_multicountry.js.
registerLuhnBlockScenario('MLC', mlc, mlc.guestUserMLC);
