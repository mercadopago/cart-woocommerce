// Importar matchers do @testing-library/jest-dom
require('@testing-library/jest-dom');

// Default do pin de variante injetado por webpack DefinePlugin (PSW-4417). Vazio = bundle runtime
// (resolve a variante em runtime). Os testes do variantRuntime sobrescrevem e restauram este valor.
global.__ST_FIXED_VARIANT__ = '';

// Mock do console para evitar logs desnecessários nos testes
global.console = {
  ...console,
  error: jest.fn(),
  warn: jest.fn(),
  log: jest.fn(),
};
