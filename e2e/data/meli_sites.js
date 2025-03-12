import credit_card_scenarios from './credit_card_scenarios';
import debit_card_scenarios from './debit_card_scenarios';
import {
  guestUserDefault,
  guestUserMLB,
  choCreditsUserMLB,
  pseUserMCO,
  guestUserMCO,
  guestUserMPE,
  choCreditsUserMLA,
  guestUserMLA,
  guestUserMLM,
  loggedUserMLM,
  choCreditsUserMLM
} from './buyer_data';

export const mla = {
  url: process.env.STORE_URL,
  credit_card_scenarios: credit_card_scenarios,
  debit_card_scenarios: debit_card_scenarios,
  guestUserDefault,
  guestUserMLA,
  choCreditsUserMLA
}

export const mlb = {
  url: process.env.STORE_URL_MLB,
  credit_card_scenarios: credit_card_scenarios,
  debit_card_scenarios: debit_card_scenarios,
  guestUserMLB,
  choCreditsUserMLB
}

export const mco = {
  url: process.env.STORE_URL,
  credit_card_scenarios: credit_card_scenarios,
  debit_card_scenarios: debit_card_scenarios,
  pseUserMCO,
  guestUserMCO,
  guestUserDefault
}

export const mpe = {
  ...guestUserMPE,
  url: process.env.STORE_URL,
  countryId: "PE",
}

export const mlm = {
  url: process.env.STORE_URL,
  guestUserMLM,
  loggedUserMLM,
  credit_card_scenarios: credit_card_scenarios,
  debit_card_scenarios: debit_card_scenarios,
  choCreditsUserMLM,
  guestUserDefault
}