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
  }

  return scenarios;
}
