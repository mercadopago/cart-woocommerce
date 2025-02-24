export const guestUserMLB = {
  email: process.env.GUEST_EMAIL,
  documentType: process.env.DOC_TYPE,
  document: process.env.DOC_NUMBER,
  firstName: "John",
  lastName: "Doe",
  phone: "",
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

export const choCreditsUserMLB = {
  ...guestUserMLB,
  email: process.env.USER_CREDITS_MLB,
  password: process.env.USER_CREDITS_PASSWORD_MLB,
}

export const guestUserROLA = {
  ...guestUserMLB,
  email: process.env.GUEST_EMAIL_MLA,
  documentType: process.env.DOC_TYPE_MLA,
  document: process.env.DOC_NUMBER_MLA,
}

export const choCreditsUserMLA = {
  ...guestUserROLA,
  email: process.env.USER_CREDITS_MLA,
  password: process.env.USER_CREDITS_PASSWORD_MLA,
}
