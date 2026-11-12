const coveragePolicy = require('./coverage-policy.json');
const { jestThresholds } = require('./scripts/coverage/policy');

module.exports = {
  collectCoverage: true,
  globalSetup: '<rootDir>/scripts/coverage/jest-global-setup.js',
  // Root PATH aggregates every measured source, even when module PATHs overlap.
  coverageThreshold: jestThresholds(coveragePolicy),
  // Ambiente de teste (jsdom simula o navegador)
  testEnvironment: 'jsdom',
  modulePathIgnorePatterns: ['<rootDir>/.task-evidence/'],

  // Todas as suites usam a experiencia unica do Super Token.
  testMatch: ['**/tests/JS/**/*.test.js'],

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
    'assets/js/**/*.ts',
    'packages/narciso/**/*.js',
    '!assets/js/**/*.min.js',
    '!assets/js/**/__tests__/**',
    '!assets/js/**/*.bundle.js',
    '!assets/js/**/super-token.bundle.js',
    '!assets/js/**/*.d.ts',
  ],

  // Configuração de mapeamento de nomes de arquivos
  moduleNameMapper: {
    '^@super-token/(.*)$': '<rootDir>/assets/js/checkouts/super-token/$1',
    '^assets/js/(.*)$': '<rootDir>/assets/js/$1',
    '^packages/narciso/(.*)$': '<rootDir>/packages/narciso/$1',
  },

  // Resolve os módulos ES/TS do super-token (as classes globais legadas seguem
  // sendo carregadas via vm/loadFile, que lê o source cru e ignora o resolver).
  moduleFileExtensions: ['js', 'ts', 'json', 'node'],

  coverageDirectory: 'coverage',

  // V8 native coverage: instruments executed code without a Babel/SWC transform
  // (none is configured), so source loaded via the vm-based loadFile helper is
  // still attributed to its real file (see tests/JS/helpers/load-file.js).
  coverageProvider: 'v8',

  coverageReporters: ['text', 'lcov', 'html', 'json', 'json-summary'],

  // Setup de arquivos antes dos testes
  setupFilesAfterEnv: ['<rootDir>/jest.setup.js', '<rootDir>/scripts/coverage/jest-vm-metadata.js'],

  // Só os arquivos .ts do super-token passam por Babel (apaga os tipos). Os testes
  // legados em .js continuam sem transform — o coverage v8 os atribui via vm/loadFile.
  // configFile/babelrc: false isola do Babel do wp-scripts (evita clobber no build dos blocks).
  transform: {
    '^.+/assets/js/blocks/.+\\.js$': ['babel-jest', {
      configFile: false,
      babelrc: false,
      presets: [['@babel/preset-env', { targets: { node: 'current' } }], '@babel/preset-react'],
    }],
    '^.+\\.ts$': ['babel-jest', {
      configFile: false,
      babelrc: false,
      presets: [
        ['@babel/preset-env', { targets: { node: 'current' } }],
        '@babel/preset-typescript',
      ],
    }],
  },

  // Variáveis globais disponíveis nos testes
  globals: {
    window: {},
  },

  // Tempo limite para testes (em ms)
  testTimeout: 10000,

  // Exibir resultados individuais dos testes
  verbose: true,
};


