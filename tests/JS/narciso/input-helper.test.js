const path = require('path');
const { loadFile } = require('../helpers/load-file');

const inputHelperPath = path.resolve(
  __dirname,
  '../../../packages/narciso/components/input-helper/InputHelper.js'
);

const InputHelper = loadFile(inputHelperPath, 'InputHelper', {
  HTMLElement: global.HTMLElement,
  customElements: { define: () => {} },
});

const buildHost = (attributes = {}) => {
  const host = document.createElement('input-helper');

  Object.entries(attributes).forEach(([name, value]) => {
    host.setAttribute(name, value);
  });

  Object.assign(host, {
    createHelperMessage: InputHelper.prototype.createHelperMessage,
    createIcon: InputHelper.prototype.createIcon,
    validateVisibility: InputHelper.prototype.validateVisibility,
  });

  return host;
};

describe('InputHelper', () => {
  describe('createHelperMessage()', () => {
    describe('given an error message', () => {
      test('when rendered, then it stays in the accessibility tree so the alert has something to announce', () => {
        const message = InputHelper.prototype.createHelperMessage.call(
          buildHost(),
          'Código de segurança inválido',
          'error'
        );

        expect(message.hasAttribute('aria-hidden')).toBe(false);
        expect(message.hasAttribute('tabindex')).toBe(false);
      });

      test('when rendered, then it keeps the message text, type class and test hook', () => {
        const message = InputHelper.prototype.createHelperMessage.call(
          buildHost(),
          'Código de segurança inválido',
          'error'
        );

        expect(message.textContent).toBe('Código de segurança inválido');
        expect(message.classList.contains('mp-helper-message')).toBe(true);
        expect(message.classList.contains('error')).toBe(true);
        expect(message.getAttribute('data-cy')).toBe('helper-message');
      });
    });
  });

  describe('createIcon()', () => {
    describe('given the decorative error icon', () => {
      test('when rendered, then it remains hidden from screen readers', () => {
        const icon = InputHelper.prototype.createIcon.call(buildHost());

        expect(icon.getAttribute('aria-hidden')).toBe('true');
        expect(icon.getAttribute('tabindex')).toBe('-1');
      });
    });
  });

  describe('createHelper()', () => {
    describe('given an error helper', () => {
      test('when rendered, then the container is a live region and the message is announceable', () => {
        const helper = InputHelper.prototype.createHelper.call(
          buildHost({ 'input-id': 'mp-security-code-helper', type: 'error', message: 'CVV inválido' })
        );

        const message = helper.querySelector('.mp-helper-message');

        expect(helper.getAttribute('role')).toBe('alert');
        expect(message.textContent).toBe('CVV inválido');
        expect(message.hasAttribute('aria-hidden')).toBe(false);
      });

      test('when rendered, then only the icon is hidden from screen readers', () => {
        const helper = InputHelper.prototype.createHelper.call(
          buildHost({ 'input-id': 'mp-security-code-helper', type: 'error', message: 'CVV inválido' })
        );

        expect(helper.querySelectorAll('[aria-hidden="true"]')).toHaveLength(1);
        expect(helper.querySelector('[aria-hidden="true"]').classList.contains('mp-helper-icon')).toBe(true);
      });

      test('when no type is given, then it still behaves as an error', () => {
        const helper = InputHelper.prototype.createHelper.call(
          buildHost({ 'input-id': 'mp-security-code-helper', message: 'CVV inválido' })
        );

        expect(helper.getAttribute('role')).toBe('alert');
      });
    });

    describe('given a helper carrying supporting text instead of an error', () => {
      test('when rendered, then it announces politely instead of interrupting', () => {
        const helper = InputHelper.prototype.createHelper.call(
          buildHost({
            'input-id': 'mp-card-holder-name-helper-info',
            type: 'info',
            message: 'Preencha o nome como está no cartão.',
          })
        );

        const message = helper.querySelector('.mp-helper-message');

        expect(helper.getAttribute('role')).toBe('status');
        expect(message.textContent).toBe('Preencha o nome como está no cartão.');
        expect(message.hasAttribute('aria-hidden')).toBe(false);
      });

      test('when rendered, then it carries no error icon', () => {
        const helper = InputHelper.prototype.createHelper.call(
          buildHost({ 'input-id': 'mp-card-holder-name-helper-info', type: 'info', message: 'Ajuda' })
        );

        expect(helper.querySelector('.mp-helper-icon')).toBeNull();
      });
    });
  });

  describe('updateMessage()', () => {
    describe('given a dynamic message containing markup', () => {
      test('when the message is updated, then it renders as text instead of HTML', () => {
        const helper = document.createElement('div');
        const helperMessage = document.createElement('div');
        const message = '<img src=x onerror=alert(1)>';
        helperMessage.classList.add('mp-helper-message');
        helper.appendChild(helperMessage);

        InputHelper.prototype.updateMessage.call(helper, message);

        expect(helper.getAttribute('message')).toBe(message);
        expect(helperMessage.textContent).toBe(message);
        expect(helperMessage.querySelector('img')).toBeNull();
      });
    });
  });
});
