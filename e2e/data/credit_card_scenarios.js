import generateCardScenarios from "./generate_card_scenarios";

const MLC = generateCardScenarios({
  master: {
    number: process.env.CC_MASTER_MLC,
  },
  amex: {
    number: process.env.CC_AMEX_MLC,
  },
  visa: {
    number: process.env.CC_VISA,
  },
  form: {
    docType: process.env.DOC_TYPE_MLC,
    docNumber: process.env.DOC_NUMBER_MLC
  }
});

const MPE = generateCardScenarios({
  master: {
    number: process.env.CC_MASTER_MPE,
  },
  amex: {
    number: process.env.CC_AMEX_MPE,
  },
  visa: {
    number: process.env.CC_VISA,
  },
  form: {
    docType: process.env.DOC_TYPE_MPE,
    docNumber: process.env.DOC_NUMBER_MPE
  }
});

// MLA must use Argentine test cards. The generic CC_* are Brazilian (CC_MASTER=5031…),
// and a BR card in an AR store makes the SDK skip the DNI requirement (document field
// stays display:none) so the payment is rejected. Prefer CC_*_MLA when provided;
// fall back to the generic to avoid breaking until the AR cards are added to .env.
const MLA = generateCardScenarios({
  master: {
    number: process.env.CC_MASTER_MLA || process.env.CC_MASTER,
  },
  amex: {
    number: process.env.CC_AMEX_MLA || process.env.CC_AMEX,
  },
  visa: {
    number: process.env.CC_VISA_MLA || process.env.CC_VISA,
  },
  form: {
    docType: process.env.DOC_TYPE_MLA,
    docNumber: process.env.DOC_NUMBER_MLA
  }
});

const MCO = generateCardScenarios({
  master: {
    number: process.env.CC_MASTER_MCO,
  },
  amex: {
    number: process.env.CC_AMEX_MCO,
  },
  visa: {
    // MCO must use a Colombian Visa; the generic CC_VISA is Brazilian.
    number: process.env.CC_VISA_MCO || process.env.CC_VISA,
  },
  form: {
    docType: process.env.DOC_TYPE_MCO,
    docNumber: process.env.DOC_NUMBER_MCO
  }
});

const MLB = generateCardScenarios({
  master: {
    number: process.env.CC_MASTER,
  },
  amex: {
    number: process.env.CC_AMEX,
  },
  visa: {
    number: process.env.CC_VISA,
  },
  form: {
    docType: process.env.DOC_TYPE_MLB,
    docNumber: process.env.DOC_NUMBER_MLB
  }
});

const MLU = generateCardScenarios({
  master: {
    number: process.env.CC_MASTER_MLU,
  },
  visa: {
    number: process.env.CC_VISA_MLU,
  },
  amex: {
    number: process.env.CC_AMEX,
  },
  form: {
    docType: process.env.DOC_TYPE_MLU,
    docNumber: process.env.DOC_NUMBER_MLU
  }
});

const MLM = generateCardScenarios({
  master: {
    number: process.env.CC_MASTER_MLM,
  }
});

const OUTRO = generateCardScenarios({
  master: {
    number: process.env.CC_MASTER,
  },
  amex: {
    number: process.env.CC_AMEX,
  },
  visa: {
    number: process.env.CC_VISA,
  },
  form: {
    docType: process.env.DOC_TYPE_OUTRO,
    docNumber: process.env.DOC_NUMBER_OUTRO
  }
});

export default { MLC, MPE, MLA, MCO, MLB, MLU, MLM, OUTRO };
