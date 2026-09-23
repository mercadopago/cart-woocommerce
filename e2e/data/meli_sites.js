import credit_card_scenarios from './credit_card_scenarios';
import debit_card_scenarios from './debit_card_scenarios';

import {
  guestUserMLB,
  pseUserMCO,
  guestUserMCO,
  guestUserMPE,
  guestUserMLA,
  guestUserMLM,
  guestUserMLC,
  guestUserMLU
} from './buyer_data';

export const mla = {
  shop_url: process.env.SHOP_URL,
  credit_card_scenarios: credit_card_scenarios.MLA,
  debit_card_scenarios: debit_card_scenarios.MLA,
  guestUserDefault: guestUserMLA,
  guestUserMLA,
};

export const mlb = {
  shop_url: process.env.SHOP_URL,
  credit_card_scenarios: credit_card_scenarios.MLB,
  debit_card_scenarios: debit_card_scenarios.MLB,
  guestUserMLB,
};

export const mco = {
  shop_url: process.env.SHOP_URL,
  credit_card_scenarios: credit_card_scenarios.MCO,
  debit_card_scenarios: debit_card_scenarios.MCO,
  pseUserMCO,
  guestUserMCO,
  guestUserDefault: guestUserMCO,
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
  guestUserDefault: guestUserMLM,
};

export const mlc = {
  shop_url: process.env.SHOP_URL,
  credit_card_scenarios: credit_card_scenarios.MLC,
  debit_card_scenarios: debit_card_scenarios.MLC,
  guestUserDefault: guestUserMLC,
  guestUser: guestUserMLC,
  guestUserMLC,
};

export const mlu = {
  shop_url: process.env.SHOP_URL,
  guestUser: guestUserMLU,
  credit_card_scenarios: credit_card_scenarios.MLU,
  debit_card_scenarios: debit_card_scenarios.MLU,
};
