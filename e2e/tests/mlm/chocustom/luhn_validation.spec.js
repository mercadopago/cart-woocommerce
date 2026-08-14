import { mlm } from "../../../data/meli_sites";
import { registerLuhnBlockScenario } from "../../../flows/luhn_multicountry";

// Luhn validation — multi-country coverage (MLM). See flows/luhn_multicountry.js.
registerLuhnBlockScenario('MLM', mlm, mlm.guestUserMLM);
