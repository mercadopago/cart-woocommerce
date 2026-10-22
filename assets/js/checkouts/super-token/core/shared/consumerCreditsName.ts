import { BRAZIL_ACCRONYM } from '@super-token/core/constants';

/** Product names follow the credential site for every consumer of the updated runtime. */
export function resolveConsumerCreditsName(siteId: string): string {
  return siteId === BRAZIL_ACCRONYM ? 'Mercado Parcelado' : 'Mercado Crédito';
}
