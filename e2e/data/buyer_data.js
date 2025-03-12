export const guestUserDefault = {
  email: process.env.GUEST_EMAIL_DEFAULT,
  documentType: process.env.DOC_TYPE_OUTRO,
  document: process.env.DOC_NUMBER_OUTRO,
  firstName: "John",
  lastName: "Doe",
  phone: "",
  siteId: "",
  address: {
    street: "Rua das Flores",
    number: "122",
    countryId: "BR",
    state: "SP",
    city: "Assis",
    neighborhood: "Jardim California",
    zip: "19800000",
    complement: "sem"
  }
}

export const guestUserMLB = {
  ...guestUserDefault,
  documentType: process.env.DOC_TYPE_MLB,
  document: process.env.DOC_NUMBER_MLB,
  siteId: "MLB",
}

export const guestUserMLA = {
  ...guestUserDefault,
  documentType: process.env.DOC_TYPE_MLA,
  document: process.env.DOC_NUMBER_MLA,
  siteId: "MLA",
}

export const choCreditsUserMLB = {
  ...guestUserMLB,
  email: process.env.USER_CREDITS_MLB,
  password: process.env.USER_CREDITS_PASSWORD_MLB,
  twoFactor: process.env.TWO_FACTOR_MLB,
}

export const choCreditsUserMLA = {
  ...guestUserMLA,
  mpUserAccount: process.env.USER_CREDITS_MLA,
  mpPasswordAccount: process.env.USER_CREDITS_PASSWORD_MLA,
}

export const choCreditsUserMLM = {
  ...guestUserMLB,
  mpUserAccount: process.env.USER_CREDITS_MLM,
  mpPasswordAccount: process.env.USER_CREDITS_PASSWORD_MLM,
  twoFactor: process.env.TWO_FACTOR_MLM,
}

export const guestUserMCO = {
  ...guestUserDefault,
  email: process.env.USER_MCO,
}

export const pseUserMCO = {
  ...guestUserDefault,
  email: process.env.USER_MCO,
  password: process.env.USER_MCO_PASSWORD,
}

export const guestUserMPE = {
  ...guestUserDefault,
  documentType: process.env.DOC_TYPE_MPE,
  document: process.env.DOC_NUMBER_MPE,
}

export const guestUserMLM = {
  ...guestUserDefault,
}

export const loggedUserMLM = {
  ...guestUserDefault,
  email: process.env.USER_LOGGED_MLM,
  password: process.env.USER_LOGGED_PASSWORD_MLM,
}
