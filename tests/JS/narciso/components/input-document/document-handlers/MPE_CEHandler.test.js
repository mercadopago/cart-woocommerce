const MPE_CEHandler = require('packages/narciso/components/input-document/document-handlers/MPE_CEHandler');

describe('MPE_CEHandler', () => {
  describe('mask()', () => {
    describe('given a Carné de Extranjería typed progressively (right-to-left)', () => {
      test.each([
        { input: '1', expected: '1' },
        { input: '1234', expected: '1.234' },
        { input: '1234567', expected: '1.234.567' },
        { input: '123456789012', expected: '123.456.789.012' },
      ])('when the value is "$input", then it renders "$expected"', ({ input, expected }) => {
        expect(MPE_CEHandler.mask(input)).toBe(expected);
      });
    });

    describe('given the Figma size (12 digits)', () => {
      test('when a full CE is typed, then it matches the Figma pattern XXX.XXX.XXX.XXX', () => {
        expect(MPE_CEHandler.mask('123456789012')).toBe('123.456.789.012');
      });
    });

    describe('given a value longer than the Figma size', () => {
      test('when 20 digits are typed, then every digit is kept (the mask never truncates)', () => {
        expect(MPE_CEHandler.mask('9'.repeat(20)).replace(/\D/g, '')).toHaveLength(20);
      });
    });

    describe('given an alphanumeric value', () => {
      test('when letters are typed, then they are upper-cased and grouped', () => {
        expect(MPE_CEHandler.mask('abc123')).toBe('ABC.123');
      });
    });
  });

  describe('validate()', () => {
    describe('given an empty value', () => {
      test.each([
        { input: '', description: 'an empty string' },
        { input: null, description: 'a null value' },
      ])('when validating $description, then it is rejected as empty', ({ input }) => {
        expect(MPE_CEHandler.validate(input)).toEqual({ result: false, type: 'empty' });
      });
    });

    describe('given any non-empty value (permissive interim behavior, matching develop)', () => {
      test.each([
        { input: '123456789012', description: 'a well-formed CE' },
        { input: 'ABC123', description: 'an alphanumeric CE' },
        { input: '123', description: 'a very short value' },
      ])('when validating $description, then it is valid', ({ input }) => {
        expect(MPE_CEHandler.validate(input)).toEqual({ result: true, type: 'valid' });
      });
    });
  });

  describe('CONFIG', () => {
    test('given the handler, then max_length is 12', () => {
      expect(MPE_CEHandler.CONFIG.max_length).toBe(12);
    });

    test('given the handler, then the placeholder matches the Figma mask', () => {
      expect(MPE_CEHandler.CONFIG.placeholder).toBe('999.999.999.999');
    });
  });
});
