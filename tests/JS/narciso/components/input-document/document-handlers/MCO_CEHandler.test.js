const MCO_CEHandler = require('packages/narciso/components/input-document/document-handlers/MCO_CEHandler');

describe('MCO_CEHandler', () => {
  describe('mask()', () => {
    describe('given the Figma size (7 digits, no separators)', () => {
      test('when a full CE is typed, then it renders the digits unchanged', () => {
        expect(MCO_CEHandler.mask('1234567')).toBe('1234567');
      });
    });

    describe('given a value longer than the Figma size', () => {
      test('when 20 digits are typed, then every digit is kept (the mask never truncates)', () => {
        expect(MCO_CEHandler.mask('9'.repeat(20)).replace(/\D/g, '')).toHaveLength(20);
      });
    });
  });

  describe('validate()', () => {
    describe('given an empty value', () => {
      test.each([
        { input: '', description: 'an empty string' },
        { input: null, description: 'a null value' },
      ])('when validating $description, then it is rejected as empty', ({ input }) => {
        expect(MCO_CEHandler.validate(input)).toEqual({ result: false, type: 'empty' });
      });
    });

    describe('given any non-empty value (permissive interim behavior, matching develop)', () => {
      test.each([
        { input: 'E226932', description: 'a CE with the E prefix' },
        { input: '1234567', description: 'digits only' },
        { input: '123', description: 'a very short value' },
      ])('when validating $description, then it is valid', ({ input }) => {
        expect(MCO_CEHandler.validate(input)).toEqual({ result: true, type: 'valid' });
      });
    });
  });

  describe('CONFIG', () => {
    test('given the handler, then max_length is 7', () => {
      expect(MCO_CEHandler.CONFIG.max_length).toBe(7);
    });

    test('given the handler, then the placeholder matches the Figma mask', () => {
      expect(MCO_CEHandler.CONFIG.placeholder).toBe('9999999');
    });
  });
});
