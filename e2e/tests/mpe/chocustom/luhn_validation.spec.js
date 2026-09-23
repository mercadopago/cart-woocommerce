import { mpe } from "../../../data/meli_sites";
import { registerLuhnBlockScenario } from "../../../flows/luhn_multicountry";

// Luhn validation — multi-country coverage (MPE). See flows/luhn_multicountry.js.
registerLuhnBlockScenario('MPE', mpe, mpe.guestUser);
