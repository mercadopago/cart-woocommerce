/**
 * v2.1 saved-methods rendering: two grouped blocks (saved cards vs. other MP methods) with a
 * per-block header that carries the buyer's e-mail and a live e-mail listener. Ported from the
 * legacy v2.1 `organizePaymentMethodsElements`, `groupPaymentMethods`, `buildBlockHeader`,
 * `renderSavedCardsBlock`, `renderOtherMpMethodsBlock` and `setupEmailHeaderListener`.
 */
import type { SavedMethodsRenderContext } from '@super-token/ports';
import type { PaymentMethod } from '@super-token/types/external-globals';
import {
  isCreditCard,
  isDebitCard,
  isPrepaidCard,
} from '@super-token/core/checkoutSession/PaymentMethodClassifier';
import { MAX_CREDIT_CARDS } from '@super-token/core/constants';
import type { VariantViewDeps } from '../VariantViewDeps';
import { el } from '../shared/dom';
import { buildTypedRow } from '../shared/typedRow';
import { mpCardThumbnailPath } from '../shared/paymentMethodPresentation';
import type { RowPresentation } from '../shared/paymentMethodPresentation';
import { V21_STYLES } from './styles';

interface BlockHeaderData {
  email: string;
  icon: string;
}

export class V21SavedCardsView {
  private readonly rowPresentation: RowPresentation;
  private emailHeaderListenerRegistered = false;
  // The e-mail listener is registered once but must always target the current checkout
  // container: WooCommerce can rebuild the DOM (updated_checkout, multi-step) and re-render
  // into a replacement node, so the callback reads this field instead of closing over the
  // container from the first render (matches the legacy dynamic lookup).
  private currentContainer: HTMLElement | null = null;

  constructor(private readonly deps: VariantViewDeps) {
    this.rowPresentation = {
      mercadoPagoCreditCard: (paymentMethod) => ({
        name: this.deps.copy.mercadoPagoCreditCardName || paymentMethod.name,
        thumbnail: mpCardThumbnailPath(this.deps) || paymentMethod.thumbnail,
      }),
      accountMoneyRowClasses: () => [V21_STYLES.ACCOUNT_MONEY_ROW],
    };
  }

  render(context: SavedMethodsRenderContext): void {
    const { container } = context;
    this.currentContainer = container;
    try {
      this.renderBlocks(context);
    } catch (error) {
      // Leave a clean container for the legacy fallback: drop any block inserted before the
      // failure so the inline path (organizePaymentMethodsElements) never duplicates it.
      this.reset(container);
      throw error;
    }
  }

  private renderBlocks(context: SavedMethodsRenderContext): void {
    const { container, paymentMethods } = context;
    const buildRow = this.rowFactory(context);
    const { cardPaymentMethods, otherPaymentMethods } = this.groupPaymentMethods(paymentMethods);

    const blockHeader: BlockHeaderData = {
      email: this.deps.emailListener?.getEmail() || this.deps.currentUserEmail,
      icon: this.deps.thumbnails.newMpLogoPath,
    };

    if (!cardPaymentMethods.length) {
      const title =
        otherPaymentMethods.length === 1
          ? this.deps.copy.savedPaymentMethodTitle
          : this.deps.copy.paymentMethodsListText;
      this.renderBlock(container, otherPaymentMethods, V21_STYLES.BLOCK_OTHER_MP_METHODS, title, blockHeader, buildRow);
      this.setupEmailHeaderListener();
      return;
    }

    const savedCardsTitle =
      cardPaymentMethods.length === 1 ? this.deps.copy.savedCardTitle : this.deps.copy.savedCardsTitle;

    // Block 2 renders first so Block 1 ends up on top via insertBefore(firstChild).
    this.renderBlock(
      container,
      otherPaymentMethods,
      V21_STYLES.BLOCK_OTHER_MP_METHODS,
      this.deps.copy.mpMethodsTitle,
      null,
      buildRow,
    );
    this.renderBlock(container, cardPaymentMethods, V21_STYLES.BLOCK_SAVED_CARDS, savedCardsTitle, blockHeader, buildRow);
    this.setupEmailHeaderListener();
  }

  reset(container: HTMLElement): void {
    container.querySelectorAll(`.${V21_STYLES.BLOCK}`).forEach((block) => block.remove());
  }

  // Per-type row factory: delegates to the shared dispatch so v2.1 and v2 build the identical
  // interactive row for a given method type. Only the v2.1 chrome (grouped blocks, e-mail header)
  // lives here; the row itself (presentation + selection wiring + card/credits details) is shared.
  private rowFactory(context: SavedMethodsRenderContext): (paymentMethod: PaymentMethod) => HTMLElement {
    return (paymentMethod: PaymentMethod): HTMLElement =>
      buildTypedRow(paymentMethod, this.deps, this.rowPresentation, context);
  }

  private groupPaymentMethods(paymentMethods: PaymentMethod[]): {
    cardPaymentMethods: PaymentMethod[];
    otherPaymentMethods: PaymentMethod[];
  } {
    const isCard = (paymentMethod: PaymentMethod): boolean =>
      isCreditCard(paymentMethod) || isDebitCard(paymentMethod) || isPrepaidCard(paymentMethod);
    const cardPaymentMethods = paymentMethods.filter(isCard).slice(0, MAX_CREDIT_CARDS);
    const otherPaymentMethods = paymentMethods.filter((paymentMethod) => !isCard(paymentMethod));
    return { cardPaymentMethods, otherPaymentMethods };
  }

  private renderBlock(
    container: HTMLElement,
    paymentMethods: PaymentMethod[],
    blockModifierClass: string,
    title: string,
    blockHeader: BlockHeaderData | null,
    buildRow: (paymentMethod: PaymentMethod) => HTMLElement,
  ): void {
    if (!paymentMethods.length) {
      return;
    }
    const section = el('section', {
      classes: [V21_STYLES.BLOCK, blockModifierClass],
      attrs: { role: 'group', 'aria-label': title, tabindex: '0' },
      children: [this.buildBlockHeader(title, blockHeader), ...paymentMethods.map(buildRow)],
    });
    container.insertBefore(section, container.firstChild);
  }

  private buildBlockHeader(title: string, blockHeader: BlockHeaderData | null): HTMLElement {
    if (!blockHeader) {
      // Intentionally no BLOCK_HEADER_INFO wrapper — setupEmailHeaderListener relies on its absence.
      return el('header', {
        classes: [V21_STYLES.BLOCK_HEADER],
        children: [this.buildTitleSpan(title)],
      });
    }

    const showEmail = this.deps.emailListener?.isValid(blockHeader.email);
    return el('header', {
      classes: [V21_STYLES.BLOCK_HEADER],
      children: [
        el('div', {
          classes: [V21_STYLES.BLOCK_HEADER_INFO],
          children: [
            this.buildTitleSpan(title),
            showEmail ? this.buildEmailSpan(blockHeader.email) : null,
          ],
        }),
        el('img', {
          classes: [V21_STYLES.BLOCK_HEADER_LOGO],
          attrs: { alt: '', 'aria-hidden': 'true', src: blockHeader.icon ?? '' },
        }),
      ],
    });
  }

  private buildTitleSpan(title: string): HTMLElement {
    return el('span', { classes: [V21_STYLES.BLOCK_TITLE], text: title });
  }

  private buildEmailSpan(email: string): HTMLElement {
    return el('span', { classes: [V21_STYLES.BLOCK_EMAIL], text: email });
  }

  private setupEmailHeaderListener(): void {
    if (this.emailHeaderListenerRegistered || !this.deps.emailListener) {
      return;
    }
    this.emailHeaderListenerRegistered = true;
    this.deps.emailListener.onEmailChange((email, isValid) => {
      try {
        const headerInfo = this.currentContainer?.querySelector(`.${V21_STYLES.BLOCK_HEADER_INFO}`);
        if (!headerInfo) {
          return;
        }
        const existingSpan = headerInfo.querySelector(`.${V21_STYLES.BLOCK_EMAIL}`);
        if (!isValid) {
          existingSpan?.remove();
          return;
        }
        if (existingSpan) {
          existingSpan.textContent = email;
        } else {
          headerInfo.appendChild(this.buildEmailSpan(email));
        }
      } catch (error) {
        window.console?.warn?.('ST: email header update failed', error);
      }
    });
  }
}
