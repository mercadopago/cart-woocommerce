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

const MLA = generateCardScenarios({
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
    number: process.env.CC_VISA,
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

export default { MLC, MPE, MLA, MCO, MLB, MLU, OUTRO };
