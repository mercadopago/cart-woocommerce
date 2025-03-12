const APPROVED = {
  elo: {
    number: process.env.CC_MASTER,
    code: '123',
    date: "11/25"
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
  formMLA: {
    name: "OTHE",
    docType: process.env.DOC_TYPE_MLA,
    docNumber: process.env.DOC_NUMBER_MLA
  },
  form: {
    ...APPROVED.formMCO,
    name: "OTHE"
  },
}

const PENDING = {
  ...APPROVED,
  form: {
    ...APPROVED.form,
    name: "CONT"
  },
  formMLA: {
    name: "CONT",
    docType: process.env.DOC_TYPE_MLA,
    docNumber: process.env.DOC_NUMBER_MLA
  },
  form: {
    ...APPROVED.formMCO,
    name: "CONT"
  },
}

// form fields doctType and docNumber only appear when card number is filled
const EMPTY_FIELDS ={
  elo: {
    ...APPROVED.elo,
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
  form: {
    name: "",
    docType: process.env.DOC_TYPE_MCO,
    docNumber: ""
  },
}

export default {APPROVED, REJECTED, PENDING, EMPTY_FIELDS};
