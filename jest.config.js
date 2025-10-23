module.exports = {
  // Ambiente de teste (jsdom simula o navegador)
  testEnvironment: 'jsdom',

  // Padrão de arquivos de teste
  testMatch: [
    '**/tests/JS/**/*.test.js',
  ],

  // Arquivos a serem ignorados
  testPathIgnorePatterns: [
    '/node_modules/',
    '/vendor/',
    '/build/',
    '/e2e/',
  ],

  // Configuração de cobertura
  collectCoverageFrom: [
    'assets/js/**/*.js',
    '!assets/js/**/*.min.js',
    '!assets/js/**/__tests__/**',
    '!assets/js/blocks/**',
  ],

  // Configuração de mapeamento de nomes de arquivos
  moduleNameMapper: {
    '^assets/js/(.*)$': '<rootDir>/assets/js/$1',
  },

  coverageDirectory: 'coverage',

  coverageReporters: ['text', 'lcov', 'html'],

  // Setup de arquivos antes dos testes
  setupFilesAfterEnv: ['<rootDir>/jest.setup.js'],

  // Transformações (caso necessite de Babel no futuro)
  transform: {},

  // Variáveis globais disponíveis nos testes
  globals: {
    window: {},
  },

  // Tempo limite para testes (em ms)
  testTimeout: 10000,

  // Exibir resultados individuais dos testes
  verbose: true,
};


