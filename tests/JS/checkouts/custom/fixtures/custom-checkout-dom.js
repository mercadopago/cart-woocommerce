const fs = require('fs');
const path = require('path');
const { createRequire } = require('module');
const { loadFile } = require('../../../helpers/load-file');

const root = path.resolve(__dirname, '../../../../..');
const templatePath = path.join(root, 'templates/public/checkouts/custom-checkout.php');

function registerComponents() {
  [
    ['input-label', 'InputLabel', 'input-label/InputLabel.js'],
    ['input-helper', 'InputHelper', 'input-helper/InputHelper.js'],
    ['input-document', 'InputDocument', 'input-document/InputDocument.js'],
  ].forEach(([tag, className, relativePath]) => {
    if (customElements.get(tag)) return;

    const sourcePath = path.join(root, 'packages/narciso/components', relativePath);
    loadFile(sourcePath, className, {
      HTMLElement,
      customElements,
      MutationObserver,
      setTimeout,
      clearTimeout,
      require: createRequire(sourcePath),
    });
  });
}

function mountCustomCheckout() {
  registerComponents();

  // Inspect static markup, without executing PHP conditions, escaping or wallet JS.
  // Reading the actual template makes selector/nesting drift visible to the tests.
  const markup = fs.readFileSync(templatePath, 'utf8')
    .replace(/<\?[\s\S]*?\?>/g, '')
    .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
  // A fixed body wrapper preserves leading whitespace in the versioned markup.
  // Parse it in a detached document; the fixture does not accept external HTML.
  const parsed = new DOMParser().parseFromString(`<body>${markup}</body>`, 'text/html');
  const nodes = [...parsed.body.childNodes].map((node) => document.importNode(node, true));
  const fragment = document.createDocumentFragment();
  fragment.append(...nodes);

  const documentComponent = fragment.querySelector('input-document');
  documentComponent.setAttribute('site-id', 'MLB');
  documentComponent.setAttribute('documents', JSON.stringify(['CPF', 'CNPJ']));

  document.body.append(fragment);
  return document.querySelector('.mp-checkout-custom-container');
}

module.exports = { mountCustomCheckout };
