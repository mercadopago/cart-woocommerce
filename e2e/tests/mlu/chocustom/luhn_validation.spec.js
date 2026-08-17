import { mlu } from "../../../data/meli_sites";
import { registerLuhnBlockScenario } from "../../../flows/luhn_multicountry";

// Luhn validation — multi-country coverage (MLU). See flows/luhn_multicountry.js.
registerLuhnBlockScenario('MLU', mlu, mlu.guestUser);
