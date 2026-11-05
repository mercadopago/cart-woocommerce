// Importar matchers do @testing-library/jest-dom
require('@testing-library/jest-dom');

// Mock do console para evitar logs desnecessários nos testes
global.console = {
  ...console,
  error: jest.fn(),
  warn: jest.fn(),
  log: jest.fn(),
};
