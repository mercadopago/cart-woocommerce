const CVV_LENGTH_THREE = '123';
const CVV_LENGTH_FOUR = '1234';

const APPROVED = {
  amex: {
    number: process.env.CC_AMEX,
    code: CVV_LENGTH_FOUR,
    date: "12/30"
  },
  master: {
    number: process.env.CC_MASTER,
    code: CVV_LENGTH_THREE,
    date: "12/30"
  },
  masterMCO: {
    number: process.env.CC_MASTER_MCO,
    code: CVV_LENGTH_THREE,
    date: "12/30"
  },
  visa: {
    number: process.env.CC_VISA,
    code: CVV_LENGTH_THREE,
    date: "12/27"
  },
  form: {
    name: "APRO",
    docType: process.env.DOC_TYPE_OUTRO,
    docNumber: process.env.DOC_NUMBER_OUTRO
  },
  formMLB: {
    name: "APRO",
    docType: process.env.DOC_TYPE_MLB,
    docNumber: process.env.DOC_NUMBER_MLB
  },
  formMLA: {
    name: "APRO",
    docType: process.env.DOC_TYPE_MLA,
    docNumber: process.env.DOC_NUMBER_MLA
  },
  formMCO: {
    name: "APRO",
    docType: process.env.DOC_TYPE_MCO,
    docNumber: process.env.DOC_NUMBER_MCO
  }
}

const REJECTED = {
  ...APPROVED,
  form: {
    ...APPROVED.form,
    name: "OTHE"
  },
  formMLB: {
    ...APPROVED.formMLB,
    name: "OTHE"
  },
  formMLA: {
    ...APPROVED.formMLA,
    name: "OTHE",
  },
  formMCO: {
    ...APPROVED.formMCO,
    name: "OTHE",
  }
}

const PENDING = {
  ...APPROVED,
  form: {
    ...APPROVED.form,
    name: "CONT"
  },
  formMLB: {
    ...APPROVED.formMLB,
    name: "CONT"
  },
  formMLA: {
    ...APPROVED.formMLA,
    name: "CONT",
  },
  formMCO: {
    ...APPROVED.formMCO,
    name: "CONT",
  }
}

// form fields doctType and docNumber only appear when card number is filled
const EMPTY_FIELDS ={
  master: {
    ...APPROVED.master,
    code: "",
    date: "",
  },
  form: {
    name: "",
    docType: process.env.DOC_TYPE_OUTRO,
    docNumber: ""
  },
  formMLB: {
    name: "",
    docType: process.env.DOC_TYPE_MLB,
    docNumber: ""
  },
  formMLA: {
    name: "",
    docType: process.env.DOC_TYPE_MLA,
    docNumber: ""
  },
  formMCO: {
    name: "",
    docType: process.env.DOC_TYPE_MCO,
    name: "",
  }
}

export default {APPROVED, REJECTED, PENDING, EMPTY_FIELDS};
