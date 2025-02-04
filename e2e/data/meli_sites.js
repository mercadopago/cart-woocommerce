import credit_card_scenarios from './credit_card_scenarios';
import debit_card_scenarios from './debit_card_scenarios';
import { guestUserMLB } from './buyer_data';

export const mlb = {
    url: process.env.STORE_URL,
    credit_card_scenarios: credit_card_scenarios,
    debit_card_scenarios: debit_card_scenarios,
    guestUserMLB,
}
