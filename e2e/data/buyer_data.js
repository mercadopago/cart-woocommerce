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
    address: {
      street: "Av Corrientes",
      number: "1234",
      countryId: "AR",
      state: "C",
      city: "Buenos Aires",
      neighborhood: "San Nicolas",
      zip: "C1043AAZ",
      complement: "",
    },
  },
  MCO: {
    email: process.env.USER_MCO,
    documentType: process.env.DOC_TYPE_MCO,
    document: process.env.DOC_NUMBER_MCO,
    siteId: "MCO",
    address: {
      street: "Carrera 7 No 71-21",
      number: "71",
      countryId: "CO",
      state: "CO-DC",
      city: "Bogota",
      neighborhood: "Chapinero",
      zip: "110231",
      complement: "",
    },
  },
  MPE: {
    documentType: process.env.DOC_TYPE_MPE,
    document: process.env.DOC_NUMBER_MPE,
    siteId: "MPE",
    address: {
      street: "Av Javier Prado Este",
      number: "4600",
      countryId: "PE",
      state: "LIM",
      city: "Lima",
      neighborhood: "Surco",
      zip: "15023",
      complement: "",
    },
  },
  MLM: {
    email: process.env.GUEST_EMAIL,
    firstName: "John",
    lastName: "Doe",
    siteId: "MLM",
    address: {
      street: "Av Reforma",
      number: "222",
      countryId: "MX",
      state: "DF",
      city: "Ciudad de Mexico",
      neighborhood: "Centro",
      zip: "06000",
      complement: "",
    }
  },
  MLU: {
    siteId: "MLU",
    documentType: process.env.DOC_TYPE_MLU,
    document: process.env.DOC_NUMBER_MLU,
    address: {
      street: "Av 18 de Julio",
      number: "1234",
      countryId: "UY",
      state: "UY-MO",
      city: "Montevideo",
      neighborhood: "Centro",
      zip: "11100",
      complement: "",
    },
  },
  MLC: {
    siteId: "MLC",
    address: {
      street: "Av Providencia",
      number: "1234",
      countryId: "CL",
      state: "CL-RM",
      city: "Santiago",
      neighborhood: "Providencia",
      zip: "7500000",
      complement: "",
    },
  },
};

export const guestUserDefault = BASE_GUEST_USER;
export const guestUserMLB = createUser(BASE_GUEST_USER, GUEST_USER_CONFIGS.MLB);
export const guestUserMLA = createUser(BASE_GUEST_USER, GUEST_USER_CONFIGS.MLA);
export const guestUserMCO = createUser(BASE_GUEST_USER, GUEST_USER_CONFIGS.MCO);
export const guestUserMPE = createUser(BASE_GUEST_USER, GUEST_USER_CONFIGS.MPE);
export const guestUserMLM = createUser(BASE_GUEST_USER, GUEST_USER_CONFIGS.MLM);
export const guestUserMLU = createUser(BASE_GUEST_USER, GUEST_USER_CONFIGS.MLU);
export const guestUserMLC = createUser(BASE_GUEST_USER, GUEST_USER_CONFIGS.MLC);

export const pseUserMCO = createUser(guestUserMCO, {
  password: process.env.USER_MCO_PASSWORD,
});
