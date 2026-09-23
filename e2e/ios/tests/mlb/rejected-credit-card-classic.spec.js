import { test } from '../../fixtures';
import { rejectedCreditCard } from '../../flows/custom-checkout';

test(
  'Given MLB iOS Safari Classic, When paying with a rejected credit card, Then a checkout error is shown',
  async ({ mobile, evidence }) => {
    await rejectedCreditCard(mobile, evidence, 'classic');
  }
);
