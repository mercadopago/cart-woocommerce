import { test } from '../../fixtures';
import { installmentsSyncBlocks } from '../../flows/custom-checkout';

test(
  'Given MLB iOS Safari Blocks, When the auto-selected installment is untouched, Then it synchronizes before submit',
  async ({ mobile, evidence }) => {
    await installmentsSyncBlocks(mobile, evidence);
  }
);
