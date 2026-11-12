const fs = require('fs');
const os = require('os');
const path = require('path');
const Ajv = require('ajv');

const FIXTURES_DIRECTORY = path.resolve(__dirname, '../../SmartPix/fixtures');
const schema = JSON.parse(fs.readFileSync(path.join(FIXTURES_DIRECTORY, 'contract.schema.json'), 'utf8'));
// Raw Core notification shapes (posted bodies and fetched envelopes), not the internal contract.
const RAW_NOTIFICATION_DIRECTORIES = ['notifications/posted', 'notifications/fetched'];
const fixtureNames = discoverFixtureNames(FIXTURES_DIRECTORY);

function discoverFixtureNames(fixturesDirectory, relativeDirectory = '') {
  const directory = path.join(fixturesDirectory, relativeDirectory);

  return fs
    .readdirSync(directory, { withFileTypes: true })
    .flatMap((entry) => {
      const relativePath = relativeDirectory ? `${relativeDirectory}/${entry.name}` : entry.name;

      if (entry.isDirectory()) {
        if (RAW_NOTIFICATION_DIRECTORIES.includes(relativePath)) {
          return [];
        }

        return discoverFixtureNames(fixturesDirectory, relativePath);
      }

      if (!entry.isFile() || !entry.name.endsWith('.json') || relativePath === 'contract.schema.json') {
        return [];
      }

      return [relativePath];
    })
    .sort();
}

function loadFixture(fileName, fixturesDirectory = FIXTURES_DIRECTORY) {
  return JSON.parse(fs.readFileSync(path.join(fixturesDirectory, fileName), 'utf8'));
}

function loadFixtureForOperation(operation) {
  if (operation === 'eligibility') {
    const fixture = loadFixture('sdk/sdk-authorize-success.json');

    fixture.operation = 'eligibility';
    fixture.response.state = 'eligible';
    fixture.response.eligibility = {
      eligible: true,
      available_options: 1,
      selection_required: false,
    };
    fixture.response.authorization = null;

    return fixture;
  }

  const fixtureNamesByOperation = {
    authorize: 'sdk/sdk-authorize-success.json',
    create: 'orders/orders-create-processing-without-payment.json',
    query: 'orders/orders-query-terminal-success.json',
    cancel: 'orders/orders-cancel-terminal-success.json',
    refund: 'orders/orders-refund-terminal-success.json',
  };

  return loadFixture(fixtureNamesByOperation[operation]);
}

const NORMALIZED_RESPONSE_STATES = [
  'empty',
  'unavailable',
  'eligible',
  'authorizing',
  'authorized',
  'processing',
  'terminal_success',
  'error',
  'terminal_failure',
];

const VALID_OUTCOME_STATES_BY_OPERATION = {
  eligibility: {
    success: ['empty', 'unavailable', 'eligible'],
    failure: ['error'],
  },
  authorize: {
    success: ['authorizing', 'authorized'],
    failure: ['unavailable', 'error', 'terminal_failure'],
  },
  create: {
    success: ['processing'],
    failure: ['processing', 'error', 'terminal_failure'],
  },
  query: {
    success: ['processing', 'terminal_success'],
    failure: ['processing', 'error', 'terminal_failure'],
  },
  cancel: {
    success: ['processing', 'terminal_failure'],
    failure: ['processing', 'error'],
  },
  refund: {
    success: ['processing', 'terminal_success'],
    failure: ['processing', 'error', 'terminal_failure'],
  },
};

const OUTCOME_STATE_CASES = Object.entries(VALID_OUTCOME_STATES_BY_OPERATION).flatMap(
  ([operation, statesByOutcome]) =>
    Object.entries(statesByOutcome).flatMap(([outcome, validStates]) =>
      NORMALIZED_RESPONSE_STATES.map((state) => ({
        operation,
        outcome,
        state,
        expectedValidity: validStates.includes(state) ? 'accepted' : 'rejected',
      })),
    ),
);

function findForbiddenFixtureData(value, currentPath = '$') {
  const forbiddenKeyPattern =
    /^(?:access_token|account_id|authorization_material|authorization_proof|authorization_token|bank_account|buyer|client_secret|cnpj|cpf|customer|document|email|open_finance_account_id|payer|private_key|pseudotoken|public_key|seller)$/i;
  const forbiddenValuePatterns = [
    /\bAPP_USR-[A-Za-z0-9_-]+\b/i,
    /\bTEST-[A-Za-z0-9_-]+\b/i,
    /\bBearer\s+\S+/i,
    /\b[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/,
    /\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i,
    /\b\d{11,16}\b/,
  ];
  const findings = [];

  if (Array.isArray(value)) {
    value.forEach((item, index) => {
      findings.push(...findForbiddenFixtureData(item, `${currentPath}[${index}]`));
    });

    return findings;
  }

  if (value !== null && typeof value === 'object') {
    Object.entries(value).forEach(([key, item]) => {
      const itemPath = `${currentPath}.${key}`;

      if (forbiddenKeyPattern.test(key)) {
        findings.push(itemPath);
      }

      findings.push(...findForbiddenFixtureData(item, itemPath));
    });

    return findings;
  }

  if (typeof value === 'string' && forbiddenValuePatterns.some((pattern) => pattern.test(value))) {
    findings.push(currentPath);
  }

  return findings;
}

describe('Smart Pix normalized contract schema', () => {
  const ajv = new Ajv({ allErrors: true, strict: true });
  const validate = ajv.compile(schema);

  test('has at least one versioned fixture', () => {
    expect(fixtureNames.length).toBeGreaterThan(0);
  });

  test('discovers nested Orders fixtures', () => {
    expect(fixtureNames).toContain('orders/orders-create-processing-without-payment.json');
  });

  test.each(fixtureNames)('validates %s', (fixtureName) => {
    const fixture = loadFixture(fixtureName);
    const isValid = validate(fixture);

    if (!isValid) {
      throw new Error(ajv.errorsText(validate.errors, { dataVar: fixtureName, separator: '\n' }));
    }

    expect(fixture.contract_version).toBe('smart-pix-internal/v1');
  });

  test.each(fixtureNames)('keeps %s scenario_id tied to its file name', (fixtureName) => {
    const fixture = loadFixture(fixtureName);

    expect(fixture.scenario_id).toBe(path.basename(fixtureName, '.json'));
  });

  test.each(fixtureNames)('keeps %s free of sensitive or production-like data', (fixtureName) => {
    expect(findForbiddenFixtureData(loadFixture(fixtureName))).toEqual([]);
  });

  test('accepts a successful cancellation with a terminal financial failure', () => {
    const fixture = loadFixture('orders/orders-cancel-terminal-success.json');

    expect(validate(fixture)).toBe(true);
    expect(validate.errors).toBeNull();
  });

  test.each(OUTCOME_STATE_CASES)(
    '$operation with outcome $outcome and state $state is $expectedValidity',
    ({ operation, outcome, state, expectedValidity }) => {
      const fixture = loadFixtureForOperation(operation);

      fixture.response.outcome = outcome;
      fixture.response.state = state;
      fixture.response.error =
        outcome === 'failure'
          ? {
              category: 'unknown',
              code: 'SMART_PIX_CONTRADICTORY_OUTCOME_STATE',
              retryable: false,
            }
          : null;

      if (operation === 'authorize') {
        fixture.response.authorization.completed = state === 'authorized';
      }

      const isValid = validate(fixture);

      expect(isValid).toBe(expectedValidity === 'accepted');

      if (isValid) {
        expect(validate.errors).toBeNull();
        return;
      }

      expect(validate.errors).toEqual(
        expect.arrayContaining([expect.objectContaining({ instancePath: '/response/state' })]),
      );
    },
  );

  test.each([
    ['authorized', false],
    ['authorizing', true],
  ])('rejects authorize state %s with authorization.completed=%s', (state, completed) => {
    const fixture = loadFixture('sdk/sdk-authorize-success.json');

    fixture.response.state = state;
    fixture.response.authorization.completed = completed;

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          instancePath: '/response/authorization/completed',
          keyword: 'const',
        }),
      ]),
    );
  });

  test('rejects a schema-invalid nested fixture', () => {
    const temporaryDirectory = fs.mkdtempSync(path.join(os.tmpdir(), 'smart-pix-fixtures-'));
    const nestedDirectory = path.join(temporaryDirectory, 'orders');
    const fixture = loadFixture('orders/orders-query-terminal-success.json');

    fixture.response.order_reference = null;
    fs.mkdirSync(nestedDirectory);
    fs.writeFileSync(path.join(nestedDirectory, 'invalid-nested.json'), JSON.stringify(fixture));

    try {
      const discoveredFixtureNames = discoverFixtureNames(temporaryDirectory);

      expect(discoveredFixtureNames).toEqual(['orders/invalid-nested.json']);
      expect(validate(loadFixture(discoveredFixtureNames[0], temporaryDirectory))).toBe(false);
    } finally {
      fs.rmSync(temporaryDirectory, { recursive: true, force: true });
    }
  });

  test.each([
    ['production', 'APP_USR-1234567890-20260911-secret-9876543210'],
    ['test', 'TEST-1234567890-20260911-secret-9876543210'],
  ])('rejects a %s Mercado Pago credential-like value', (_credentialType, credential) => {
    const fixture = loadFixture('orders/orders-create-processing-without-payment.json');

    fixture.request.attempt_reference = credential;

    expect(findForbiddenFixtureData(fixture)).toContain('$.request.attempt_reference');
  });

  test('rejects terminal success with a failed outcome', () => {
    const fixture = loadFixture('orders/orders-query-terminal-success.json');

    fixture.response.outcome = 'failure';
    fixture.response.error = {
      category: 'unknown',
      code: 'SMART_PIX_CONTRADICTORY_TERMINAL_SUCCESS',
      retryable: false,
    };

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([expect.objectContaining({ instancePath: '/response/outcome', keyword: 'const' })]),
    );
  });

  test('rejects an Orders-shaped SDK eligibility response', () => {
    const fixture = loadFixture('orders/orders-create-processing-without-payment.json');

    fixture.port = 'sdk';
    fixture.operation = 'eligibility';
    fixture.request.attempt_reference = null;
    fixture.request.amount = null;
    fixture.response.state = 'processing';
    fixture.response.eligibility = null;
    fixture.expected_effects.persist_attempt = false;
    fixture.expected_effects.link_order_reference = false;
    fixture.expected_effects.orders_create_calls = 0;
    fixture.expected_effects.state_transition = 'none';
    fixture.expected_effects.reconciliation = 'none';

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ instancePath: '/response/state', keyword: 'enum' }),
        expect.objectContaining({ instancePath: '/response/order_reference', keyword: 'type' }),
        expect.objectContaining({ instancePath: '/response/eligibility', keyword: 'type' }),
      ]),
    );
  });

  test('rejects eligibility response members on SDK authorization', () => {
    const fixture = loadFixture('sdk/sdk-authorize-success.json');

    fixture.response.state = 'eligible';
    fixture.response.eligibility = {
      eligible: true,
      available_options: 1,
      selection_required: false,
    };
    fixture.response.authorization = null;

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ instancePath: '/response/state', keyword: 'enum' }),
        expect.objectContaining({ instancePath: '/response/eligibility', keyword: 'type' }),
        expect.objectContaining({ instancePath: '/response/authorization', keyword: 'type' }),
      ]),
    );
  });

  test('rejects financial request members on SDK authorization', () => {
    const fixture = loadFixture('sdk/sdk-authorize-success.json');

    fixture.request.attempt_reference = 'attempt_fixture_sdk_001';
    fixture.request.amount = {
      value: '125.50',
      currency: 'BRL',
    };

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ instancePath: '/request/attempt_reference', keyword: 'type' }),
        expect.objectContaining({ instancePath: '/request/amount', keyword: 'type' }),
      ]),
    );
  });

  test('rejects SDK response members on an Orders operation', () => {
    const fixture = loadFixture('orders/orders-query-terminal-success.json');

    fixture.response.eligibility = {
      eligible: true,
      available_options: 1,
      selection_required: true,
    };

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([expect.objectContaining({ instancePath: '/response/eligibility', keyword: 'type' })]),
    );
  });

  test('rejects an Orders creation effect on a non-create operation', () => {
    const fixture = loadFixture('orders/orders-query-terminal-success.json');

    fixture.expected_effects.orders_create_calls = 1;

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ instancePath: '/expected_effects/orders_create_calls', keyword: 'const' }),
      ]),
    );
  });

  test('rejects a successful Orders create without an order reference', () => {
    const fixture = loadFixture('orders/orders-create-processing-without-payment.json');

    fixture.response.order_reference = null;

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([expect.objectContaining({ instancePath: '/response/order_reference', keyword: 'type' })]),
    );
  });

  test.each([
    ['persist_attempt', false],
    ['link_order_reference', false],
    ['orders_create_calls', 0],
    ['state_transition', 'none'],
    ['reconciliation', 'none'],
  ])('rejects a successful processing create with expected_effects.%s=%p', (field, value) => {
    const fixture = loadFixture('orders/orders-create-processing-without-payment.json');

    fixture.expected_effects[field] = value;

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ instancePath: `/expected_effects/${field}`, keyword: 'const' }),
      ]),
    );
  });

  test('rejects a fixture whose scenario_id does not match its file name', () => {
    const temporaryDirectory = fs.mkdtempSync(path.join(os.tmpdir(), 'smart-pix-fixtures-'));
    const nestedDirectory = path.join(temporaryDirectory, 'orders');
    const fixture = loadFixture('orders/orders-query-terminal-success.json');

    fixture.scenario_id = 'orders-query-success';
    fs.mkdirSync(nestedDirectory);
    fs.writeFileSync(path.join(nestedDirectory, 'orders-query-mismatched-scenario.json'), JSON.stringify(fixture));

    try {
      const discoveredFixtureNames = discoverFixtureNames(temporaryDirectory);
      const mismatchedFixtureName = discoveredFixtureNames[0];
      const mismatchedFixture = loadFixture(mismatchedFixtureName, temporaryDirectory);

      expect(validate(mismatchedFixture)).toBe(true);
      expect(mismatchedFixture.scenario_id).not.toBe(path.basename(mismatchedFixtureName, '.json'));
    } finally {
      fs.rmSync(temporaryDirectory, { recursive: true, force: true });
    }
  });

  test.each([
    ['orders-cancel-terminal-success.json', 'terminal_success'],
    ['orders-refund-terminal-success.json', 'eligible'],
  ])('rejects an incompatible state for orders/%s', (fixtureName, state) => {
    const fixture = loadFixture('orders/' + fixtureName);

    fixture.response.state = state;

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([expect.objectContaining({ instancePath: '/response/state', keyword: 'enum' })]),
    );
  });

  test('rejects provider-specific fields outside the normalized contract', () => {
    const fixture = loadFixture(fixtureNames[0]);

    fixture.response.provider_status = 'action_required';

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([expect.objectContaining({ instancePath: '/response', keyword: 'additionalProperties' })]),
    );
  });

  test('rejects an operation assigned to the wrong port', () => {
    const fixture = loadFixture(fixtureNames[0]);

    fixture.port = 'sdk';

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([expect.objectContaining({ instancePath: '/operation', keyword: 'enum' })]),
    );
  });

  test.each([
    ['failure', 'processing'],
    ['failure', 'error'],
  ])('rejects a rejected error with outcome %s and non-terminal state %s', (outcome, state) => {
    const fixture = loadFixture('orders/orders-query-terminal-failure.json');

    fixture.response.outcome = outcome;
    fixture.response.state = state;

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([expect.objectContaining({ instancePath: '/response/state', keyword: 'const' })]),
    );
  });

  test('rejects a rejected error on a successful outcome', () => {
    const fixture = loadFixture('orders/orders-query-terminal-failure.json');

    fixture.response.outcome = 'success';

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([expect.objectContaining({ instancePath: '/response/outcome', keyword: 'const' })]),
    );
  });

  test('rejects a rejected error on an SDK response', () => {
    const fixture = loadFixture('sdk/sdk-authorize-success.json');

    fixture.response.outcome = 'failure';
    fixture.response.state = 'terminal_failure';
    fixture.response.authorization.completed = false;
    fixture.response.error = {
      category: 'rejected',
      code: 'SMART_PIX_REJECTED_HIGH_RISK',
      retryable: false,
    };

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([expect.objectContaining({ instancePath: '/port', keyword: 'const' })]),
    );
  });

  test.each([
    ['SMART_PIX_HIGH_RISK'],
    ['SMART_PIX_REJECTED_'],
    ['SMART_PIX_REJECTED_high_risk'],
    ['SMART_PIX_REJECTED_HIGH-RISK'],
    [`SMART_PIX_REJECTED_${'A'.repeat(65)}`],
  ])('rejects a rejected error with code %s', (code) => {
    const fixture = loadFixture('orders/orders-query-terminal-failure.json');

    fixture.response.error.code = code;

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([expect.objectContaining({ instancePath: '/response/error/code' })]),
    );
  });

  test('rejects a retryable rejected error', () => {
    const fixture = loadFixture('orders/orders-query-terminal-failure.json');

    fixture.response.error.retryable = true;

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([expect.objectContaining({ instancePath: '/response/error/retryable', keyword: 'const' })]),
    );
  });

  test('rejects observed status facts on an SDK response', () => {
    const fixture = loadFixture('sdk/sdk-authorize-success.json');

    fixture.response.observed = loadFixture('orders/orders-query-terminal-failure.json').response.observed;

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([expect.objectContaining({ instancePath: '/response/observed', keyword: 'false schema' })]),
    );
  });

  test('rejects observed status facts with extra keys', () => {
    const fixture = loadFixture('orders/orders-query-terminal-failure.json');

    fixture.response.observed.raw_status = 'failed';

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ instancePath: '/response/observed', keyword: 'additionalProperties' }),
      ]),
    );
  });

  test.each(['order_status', 'order_status_detail', 'payment_status', 'payment_status_detail', 'total_failed'])(
    'rejects observed status facts without %s',
    (field) => {
      const fixture = loadFixture('orders/orders-query-terminal-failure.json');

      delete fixture.response.observed[field];

      expect(validate(fixture)).toBe(false);
      expect(validate.errors).toEqual(
        expect.arrayContaining([expect.objectContaining({ instancePath: '/response/observed', keyword: 'required' })]),
      );
    },
  );

  test.each([
    ['order_status', 'approved'],
    ['payment_status', 'FAILED'],
    ['order_status_detail', 'High_Risk'],
    ['payment_status_detail', 'high-risk'],
    ['order_status_detail', ''],
    ['payment_status_detail', 'a'.repeat(65)],
  ])('rejects observed %s=%p', (field, value) => {
    const fixture = loadFixture('orders/orders-query-terminal-failure.json');

    fixture.response.observed[field] = value;

    expect(validate(fixture)).toBe(false);
    expect(validate.errors).toEqual(
      expect.arrayContaining([expect.objectContaining({ instancePath: `/response/observed/${field}` })]),
    );
  });

  test.each([['125.5'], ['125.500'], ['0125.50'], ['-125.50'], [125.5], ['']])(
    'rejects malformed observed total_failed %p',
    (totalFailed) => {
      const fixture = loadFixture('orders/orders-query-terminal-failure.json');

      fixture.response.observed.total_failed = totalFailed;

      expect(validate(fixture)).toBe(false);
      expect(validate.errors).toEqual(
        expect.arrayContaining([expect.objectContaining({ instancePath: '/response/observed/total_failed' })]),
      );
    },
  );

  test('accepts observed status facts without a failed total', () => {
    const fixture = loadFixture('orders/orders-query-terminal-failure.json');

    fixture.response.observed.total_failed = null;

    expect(validate(fixture)).toBe(true);
  });
});
