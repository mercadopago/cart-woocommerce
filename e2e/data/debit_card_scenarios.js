const APPROVED = {
  elo: {
    number: process.env.CC_MASTER,
    code: '123',
    date: "11/25"
  },
  form: {
    name: "APRO",
    docType: process.env.DOC_TYPE,
    docNumber: process.env.DOC_NUMBER
  }
}

const REJECTED = {
  ...APPROVED,
  form: {
    ...APPROVED.form,
    name: "OTHE"
  }
}

const PENDING = {
  ...APPROVED,
  form: {
    ...APPROVED.form,
    name: "CONT"
  }
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
    docType: process.env.DOC_TYPE,
    docNumber: ""
  }
}

export default {APPROVED, REJECTED, PENDING, EMPTY_FIELDS};
