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
  master: {
    ...APPROVED.master,
    code: "",
    date: "",
  },
  form: {
    name: "",
    docType: process.env.DOC_TYPE,
    docNumber: ""
  }
}

const scenarios = {APPROVED, REJECTED, PENDING, EMPTY_FIELDS};
export default scenarios;
