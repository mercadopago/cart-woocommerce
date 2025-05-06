import generateCardScenarios from "./generate_card_scenarios";

const MLC = generateCardScenarios({
  master: {
    number: process.env.DC_MASTER_MLC,
  },
  form: {
    docType: process.env.DOC_TYPE_MLC,
    docNumber: process.env.DOC_NUMBER_MLC
  }
});

const MPE = generateCardScenarios({
  master: {
    number: process.env.DC_MASTER_MPE,
  },
  form: {
    docType: process.env.DOC_TYPE_MPE,
    docNumber: process.env.DOC_NUMBER_MPE
  }
});

const MLA = generateCardScenarios({
  master: {
    number: process.env.DC_MASTER,
  },
  amex: {
    number: process.env.DC_AMEX,
  },
  visa: {
    number: process.env.DC_VISA,
  },
  form: {
    docType: process.env.DOC_TYPE_MLA,
    docNumber: process.env.DOC_NUMBER_MLA
  }
});

const MCO = generateCardScenarios({
  master: {
    number: process.env.DC_MASTER_MCO,
  },
  amex: {
    number: process.env.DC_AMEX_MCO,
  },
  visa: {
    number: process.env.DC_VISA,
  },
  form: {
    docType: process.env.DOC_TYPE_MCO,
    docNumber: process.env.DOC_NUMBER_MCO
  }
});

const MLB = generateCardScenarios({
  master: {
    number: process.env.DC_MASTER,
  },
  amex: {
    number: process.env.DC_AMEX,
  },
  visa: {
    number: process.env.DC_VISA,
  },
  elo: {
    number: process.env.DC_ELO,
  },
  form: {
    docType: process.env.DOC_TYPE_MLB,
    docNumber: process.env.DOC_NUMBER_MLB
  }
});

const MLU = generateCardScenarios({
  master: {
    number: process.env.DC_MASTER,
  },
  form: {
    docType: process.env.DOC_TYPE_MLU,
    docNumber: process.env.DOC_NUMBER_MLU
  }
});

const OUTRO = generateCardScenarios({
  master: {
    number: process.env.DC_MASTER,
  },
  amex: {
    number: process.env.DC_AMEX,
  },
  visa: {
    number: process.env.DC_VISA,
  },
  form: {
    docType: process.env.DOC_TYPE_OUTRO,
    docNumber: process.env.DOC_NUMBER_OUTRO
  }
});

export default { MLC, MPE, MLA, MCO, MLB, MLU, OUTRO };
