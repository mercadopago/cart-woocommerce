const { escapeHtml } = require('@super-token/core/shared/escapeHtml');

describe('escapeHtml', () => {
  it('Given angle brackets, ampersand and quotes, When escaped, Then they become HTML entities', () => {
    expect(escapeHtml('<b>&"\'')).toBe('&lt;b&gt;&amp;&quot;&#39;');
  });

  it('Given a plain value, When escaped, Then it is returned unchanged', () => {
    expect(escapeHtml('2,5')).toBe('2,5');
  });
});
