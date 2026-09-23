import { test } from '../../fixtures';
import { approvedCreditCard } from '../../flows/custom-checkout';

test(
  'Given MLB iOS Safari Classic, When paying with an approved credit card, Then the order is completed',
  async ({ mobile, evidence }) => {
    await approvedCreditCard(mobile, evidence, 'classic');
  }
);
