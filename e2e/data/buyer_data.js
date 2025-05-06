const createUser = (base, overrides) => ({ ...base, ...overrides });

const BASE_GUEST_USER = {
  email: process.env.GUEST_EMAIL,
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
    complement: "sem",
  },
};

const GUEST_USER_CONFIGS = {
  MLB: {
    documentType: process.env.DOC_TYPE_MLB,
    document: process.env.DOC_NUMBER_MLB,
    siteId: "MLB",
  },
  MLA: {
    documentType: process.env.DOC_TYPE_MLA,
    document: process.env.DOC_NUMBER_MLA,
    siteId: "MLA",
  },
  MCO: {
    email: process.env.USER_MCO,
  },
  MPE: {
    mpUserAccount: process.env.USER_MPE,
    mpPasswordAccount: process.env.USER_MPE_PASSWORD,
    documentType: process.env.DOC_TYPE_MPE,
    document: process.env.DOC_NUMBER_MPE,
  },
  MLM: {},
  MLU: {
    siteId: "MLU",
    documentType: process.env.DOC_TYPE_MLU,
    document: process.env.DOC_NUMBER_MLU,
  },
  MLC: {},
};

const CREDITS_USER_CONFIGS = {
  MLB: {
    mpUserAccount: process.env.USER_CREDITS_MLB,
    mpPasswordAccount: process.env.USER_CREDITS_PASSWORD_MLB,
    twoFactor: process.env.TWO_FACTOR_MLB,
  },
  MLA: {
    mpUserAccount: process.env.USER_CREDITS_MLA,
    mpPasswordAccount: process.env.USER_CREDITS_PASSWORD_MLA,
  },
  MLM: {
    mpUserAccount: process.env.USER_CREDITS_MLM,
    mpPasswordAccount: process.env.USER_CREDITS_PASSWORD_MLM,
    twoFactor: process.env.TWO_FACTOR_MLM,
  },
};

export const guestUserDefault = BASE_GUEST_USER;
export const guestUserMLB = createUser(BASE_GUEST_USER, GUEST_USER_CONFIGS.MLB);
export const guestUserMLA = createUser(BASE_GUEST_USER, GUEST_USER_CONFIGS.MLA);
export const guestUserMCO = createUser(BASE_GUEST_USER, GUEST_USER_CONFIGS.MCO);
export const guestUserMPE = createUser(BASE_GUEST_USER, GUEST_USER_CONFIGS.MPE);
export const guestUserMLM = createUser(BASE_GUEST_USER, GUEST_USER_CONFIGS.MLM);
export const guestUserMLU = createUser(BASE_GUEST_USER, GUEST_USER_CONFIGS.MLU);

export const loggedUserMLM = createUser(BASE_GUEST_USER, {
  email: process.env.USER_LOGGED_MLM,
  password: process.env.USER_LOGGED_PASSWORD_MLM,
});

export const loggedUserMLC = createUser(BASE_GUEST_USER, {
  email: process.env.USER_MLC,
  password: process.env.USER_MLC_PASSWORD,
});

export const loggedUserMLU = createUser(BASE_GUEST_USER, {
  email: process.env.USER_MLU_EMAIL,
  password: process.env.USER_MLU_PASSWORD,
});

export const choCreditsUserMLB = createUser(guestUserMLB, CREDITS_USER_CONFIGS.MLB);
export const choCreditsUserMLA = createUser(guestUserMLA, CREDITS_USER_CONFIGS.MLA);
export const choCreditsUserMLM = createUser(guestUserMLB, CREDITS_USER_CONFIGS.MLM);

export const pseUserMCO = createUser(guestUserMCO, {
  password: process.env.USER_MCO_PASSWORD,
});
