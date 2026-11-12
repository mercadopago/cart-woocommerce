const fs = require('fs');
const path = require('path');
const Ajv = require('ajv');

const root = path.resolve(__dirname, '../../SmartPix');
const base = JSON.parse(fs.readFileSync(path.join(root, 'fixtures/contract.schema.json'), 'utf8'));
const schema = JSON.parse(fs.readFileSync(path.join(root, 'mutations.schema.json'), 'utf8'));
const ajv = new Ajv({ strict: true, allErrors: true });
const core = ajv.compile(base);
const validate = ajv.compile(schema);
const names = fs.readdirSync(path.join(root, 'mutations')).filter((name) => name.endsWith('.json'));

function fixture(name) {
  return JSON.parse(fs.readFileSync(path.join(root, 'mutations', name), 'utf8'));
}

describe('Smart Pix mutation contract (synthetic evidence)', () => {
  test.each(names)('%s extends the validated core contract without secrets', (name) => {
    const value = fixture(name);
    expect(validate(value)).toBe(true);
    const normalized = JSON.parse(JSON.stringify(value));
    normalized.contract_version = 'smart-pix-internal/v1';
    delete normalized.request.operation_context;
    delete normalized.response.order_context;
    expect(core(normalized)).toBe(true);
    expect(JSON.stringify(value)).not.toMatch(/APP_USR-|TEST-|Bearer |access_token|open_finance_account_id|payer|account_holder_name/);
  });

  test.each(['seller_reference', 'amount', 'attempt_reference', 'refunded_amount'])('rejects missing %s evidence', (field) => {
    const value = fixture('orders-refund-terminal-success.json');
    delete value.response.order_context[field];
    expect(validate(value)).toBe(false);
  });

  test('requires a separate mutation key and rejects arbitrary snapshot fields', () => {
    const value = fixture('orders-refund-terminal-success.json');
    delete value.request.operation_context;
    expect(validate(value)).toBe(false);
    value.request.operation_context = fixture('orders-refund-terminal-success.json').request.operation_context;
    value.response.order_context.token = 'SYNTHETIC_SENTINEL';
    expect(validate(value)).toBe(false);
  });
});
