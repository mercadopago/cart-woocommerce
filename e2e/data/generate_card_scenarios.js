export default function (base) {
  for (const key in base) {
    if (!['master', 'visa', 'amex', 'elo'].includes(key)) {
      continue;
    }

    base[key] = {
      code: key === 'amex' ? '1234' : '123',
      date: '11/30',
      ...base[key]
    }
  }

  let scenarios = {
    APPROVED: {
      ...base,
      form: {
        name: "APRO",
        ...base.form
      }
    },
    PENDING: {
      ...base,
      form: {
        ...base.form,
        name: "CONT",
      },
    },
    EMPTY_FIELDS: {
      form: {
        ...base.form,
        name: "",
        docNumber: "",
      }
    },
    // WC form is filled and valid, but the card fields are empty/invalid so the
    // MP SDK refuses to tokenize. Used to assert the checkout recovers (no stuck
    // overlay) when the server-side gate fails open on a valid WC form.
    INVALID_CARD: {
      form: {
        ...base.form,
        name: "APRO",
      }
    },
    REJECTED: {
      ...base,
      form: {
        ...base.form,
        name: "OTHE"
      },
    }
  }

  for (const key in base) {
    if (!['master', 'visa', 'amex', 'elo'].includes(key)) {
      continue;
    }

    scenarios['EMPTY_FIELDS'][key] = {
      ...base[key],
      code: "",
      date: "",
    }

    // Empty card fields: the SDK cannot tokenize, exercising the fail-open +
    // recovery path while the WC form itself stays valid.
    scenarios['INVALID_CARD'][key] = {
      ...base[key],
      number: "",
      code: "",
      date: "",
    }
  }

  return scenarios;
}
