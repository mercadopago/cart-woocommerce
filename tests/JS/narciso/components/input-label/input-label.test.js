const path = require('path');
const { loadFile } = require('../../../helpers/load-file');

const inputLabelPath = path.resolve(
  __dirname,
  '../../../../../packages/narciso/components/input-label/InputLabel.js'
);

const InputLabel = loadFile(inputLabelPath, 'InputLabel', {
  HTMLElement: global.HTMLElement,
  customElements: { define: () => {} },
});

const buildHost = (attributes = {}) => {
  const host = document.createElement('input-label');

  Object.entries(attributes).forEach(([name, value]) => {
    host.setAttribute(name, value);
  });

  return host;
};

const createLabel = (attributes) => InputLabel.prototype.createLabel.call(buildHost(attributes));

describe('InputLabel', () => {
  describe('createLabel()', () => {
    describe('given any label', () => {
      test('when rendered, then it produces a real <label> element', () => {
        const label = createLabel({ message: 'Nome do titular' });

        expect(label.tagName).toBe('LABEL');
      });

      test('when rendered, then it keeps the shared class and the test hook', () => {
        const label = createLabel({ message: 'Nome do titular' });

        expect(label.classList.contains('mp-input-label')).toBe(true);
        expect(label.getAttribute('data-cy')).toBe('input-label');
      });
    });

    describe('given a label pointing at a control id', () => {
      test('when rendered, then it is associated with that control', () => {
        const label = createLabel({
          message: 'Nome do titular',
          for: 'form-checkout__cardholderName',
        });

        expect(label.htmlFor).toBe('form-checkout__cardholderName');
      });
    });

    describe('given a label whose control lives in the SDK iframe', () => {
      test('when rendered without a for attribute, then no association is set', () => {
        const label = createLabel({ message: 'Número do cartão' });

        expect(label.hasAttribute('for')).toBe(false);
      });
    });

    describe('given a required label', () => {
      test('when rendered, then the asterisk is appended after the message', () => {
        const label = createLabel({ message: 'Nome do titular', isOptional: 'false' });

        expect(label.lastElementChild.tagName).toBe('B');
        expect(label.textContent).toBe('Nome do titular*');
      });
    });

    describe('given an optional label', () => {
      test('when rendered, then no asterisk is added', () => {
        const label = createLabel({ message: 'Complemento', isOptional: 'true' });

        expect(label.querySelector('b')).toBeNull();
        expect(label.textContent).toBe('Complemento');
      });
    });

    describe('given a message containing markup', () => {
      test('when rendered, then it is shown as text instead of being parsed as HTML', () => {
        const label = createLabel({ message: '<img src=x onerror=alert(1)>' });

        expect(label.querySelector('img')).toBeNull();
        expect(label.textContent).toContain('<img src=x onerror=alert(1)>');
      });
    });
  });
});
