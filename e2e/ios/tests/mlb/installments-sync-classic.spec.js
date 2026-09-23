import { test } from '../../fixtures';
import { installmentsSyncClassic } from '../../flows/custom-checkout';

test(
  'Given MLB iOS Safari Classic, When the auto-selected installment is untouched, Then it synchronizes before submit',
  async ({ mobile, evidence }) => {
    await installmentsSyncClassic(mobile, evidence);
  }
);
