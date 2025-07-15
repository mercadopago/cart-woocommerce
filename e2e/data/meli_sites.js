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
  choCreditsUserMLM,
  loggedUserMLC,
  loggedUserMLU,
  guestUserMLU
} from './buyer_data';

export const mla = {
  shop_url: process.env.SHOP_URL,
  credit_card_scenarios: credit_card_scenarios.MLA,
  debit_card_scenarios: debit_card_scenarios.MLA,
  guestUserDefault,
  guestUserMLA,
  choCreditsUserMLA,
};

export const mlb = {
  shop_url: process.env.SHOP_URL,
  credit_card_scenarios: credit_card_scenarios.MLB,
  debit_card_scenarios: debit_card_scenarios.MLB,
  guestUserMLB,
  choCreditsUserMLB,
};

export const mco = {
  shop_url: process.env.SHOP_URL,
  credit_card_scenarios: credit_card_scenarios.MCO,
  debit_card_scenarios: debit_card_scenarios.MCO,
  pseUserMCO,
  guestUserMCO,
  guestUserDefault,
};

export const mpe = {
  shop_url: process.env.SHOP_URL,
  countryId: "PE",
  credit_card_scenarios: credit_card_scenarios.MPE,
  debit_card_scenarios: debit_card_scenarios.MPE,
  guestUser: guestUserMPE,
};

export const mlm = {
  shop_url: process.env.SHOP_URL,
  credit_card_scenarios: credit_card_scenarios.MLM,
  debit_card_scenarios: debit_card_scenarios.MLM,
  guestUserMLM,
  loggedUserMLM,
  choCreditsUserMLM,
  guestUserDefault,
};

export const mlc = {
  shop_url: process.env.SHOP_URL,
  credit_card_scenarios: credit_card_scenarios.MLC,
  debit_card_scenarios: debit_card_scenarios.MLC,
  guestUserDefault,
  loggedUserMLC,
};

export const mlu = {
  shop_url: process.env.SHOP_URL,
  guestUser: guestUserMLU,
  loggedUserMLU,
  credit_card_scenarios: credit_card_scenarios.MLU,
  debit_card_scenarios: debit_card_scenarios.MLU,
};
