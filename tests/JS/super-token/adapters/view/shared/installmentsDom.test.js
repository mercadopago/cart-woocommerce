const {
  installmentsSelectId,
  installmentsErrorHelperId,
  taxInfoElementId,
  findInstallmentsSelect,
  setInstallmentsErrorState,
  installmentsWasSelected,
  syncCardInstallments,
} = require('@super-token/adapters/view/shared/installmentsDom');
const { SHARED_STYLES } = require('@super-token/adapters/view/shared/styles');
const { paymentMethodIdentifier } = require('@super-token/core/checkoutSession/PaymentMethodClassifier');
const { creditCard } = require('../../../core/fixtures');

describe('installmentsDom', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  describe('id builders', () => {
    const pm = creditCard({ id: 'cc1' }); // identifier = 'cc1' + '1234'
    const identifier = paymentMethodIdentifier(pm);

    it('Given a payment method, When ids are built, Then they embed the payment method identifier', () => {
      expect(installmentsSelectId(pm)).toBe(`mp-super-token-installments-select-${identifier}`);
      expect(installmentsErrorHelperId(pm)).toBe(`mp-super-token-installments-error-${identifier}`);
      expect(taxInfoElementId(pm)).toBe(`mp-super-token-installments-tax-info-${identifier}`);
    });
  });

  describe('findInstallmentsSelect', () => {
    it('Given a row containing the select, When searched, Then it returns the element', () => {
      const pm = creditCard({ id: 'cc1' });
      const row = document.createElement('div');
      const select = document.createElement('select');
      select.id = installmentsSelectId(pm);
      row.appendChild(select);

      expect(findInstallmentsSelect(row, pm)).toBe(select);
    });

    it('Given a row without the select, When searched, Then it returns null', () => {
      const pm = creditCard({ id: 'cc1' });
      expect(findInstallmentsSelect(document.createElement('div'), pm)).toBeNull();
    });
  });

  describe('setInstallmentsErrorState', () => {
    const pm = creditCard({ id: 'cc1' });

    const mountFullDom = () => {
      const selectId = installmentsSelectId(pm);
      document.body.innerHTML = `
        <label for="${selectId}"></label>
        <select id="${selectId}"></select>
        <div id="${installmentsErrorHelperId(pm)}" style="display:none"></div>
      `;
    };

    it('Given the full DOM and an error, When set, Then the helper is shown and error classes are added', () => {
      mountFullDom();
      setInstallmentsErrorState(pm, true);

      expect(document.getElementById(installmentsErrorHelperId(pm)).style.display).toBe('flex');
      expect(document.getElementById(installmentsSelectId(pm)).classList.contains(SHARED_STYLES.INSTALLMENTS_ERROR)).toBe(true);
      expect(document.querySelector(`label[for="${installmentsSelectId(pm)}"]`).classList.contains(SHARED_STYLES.INSTALLMENTS_LABEL_ERROR)).toBe(true);
    });

    it('Given the full DOM and no error, When set, Then the helper is hidden and error classes are removed', () => {
      mountFullDom();
      setInstallmentsErrorState(pm, true);
      setInstallmentsErrorState(pm, false);

      expect(document.getElementById(installmentsErrorHelperId(pm)).style.display).toBe('none');
      expect(document.getElementById(installmentsSelectId(pm)).classList.contains(SHARED_STYLES.INSTALLMENTS_ERROR)).toBe(false);
      expect(document.querySelector(`label[for="${installmentsSelectId(pm)}"]`).classList.contains(SHARED_STYLES.INSTALLMENTS_LABEL_ERROR)).toBe(false);
    });

    it('Given a missing element, When set, Then it is a safe no-op (does not throw)', () => {
      document.body.innerHTML = `<select id="${installmentsSelectId(pm)}"></select>`; // no label, no helper
      expect(() => setInstallmentsErrorState(pm, true)).not.toThrow();
    });
  });

  describe('installmentsWasSelected', () => {
    const pm = creditCard({ id: 'cc1' });

    it('Given a select with a value, When checked, Then it is true', () => {
      const select = document.createElement('select');
      select.id = installmentsSelectId(pm);
      const option = document.createElement('option');
      option.value = '3';
      select.appendChild(option);
      select.value = '3';
      document.body.appendChild(select);

      expect(installmentsWasSelected(pm)).toBe(true);
    });

    it('Given no select in the DOM, When checked, Then it is false', () => {
      expect(installmentsWasSelected(pm)).toBe(false);
    });
  });

  describe('syncCardInstallments', () => {
    it('Given the hidden #cardInstallments field, When synced, Then its value mirrors the argument', () => {
      const field = document.createElement('input');
      field.type = 'hidden';
      field.id = 'cardInstallments';
      document.body.appendChild(field);

      syncCardInstallments('6');
      expect(field.value).toBe('6');
    });

    it('Given no #cardInstallments field, When synced, Then it is a safe no-op', () => {
      expect(() => syncCardInstallments('6')).not.toThrow();
    });
  });
});
