const CCHandler = require('packages/narciso/components/input-document/document-handlers/CCHandler');

describe('CCHandler', () => {
  describe('mask()', () => {
    describe('given a Cédula de Ciudadanía typed progressively (right-to-left)', () => {
      test.each([
        { input: '1', expected: '1' },
        { input: '1234', expected: '1.234' },
        { input: '1234567', expected: '1.234.567' },
        { input: '1234567890', expected: '1.234.567.890' },
      ])('when the value is "$input", then it renders "$expected"', ({ input, expected }) => {
        expect(CCHandler.mask(input)).toBe(expected);
      });
    });

    describe('given the Figma size (10 digits)', () => {
      test('when a full CC is typed, then it matches the Figma pattern X.XXX.XXX.XXX', () => {
        expect(CCHandler.mask('1234567890')).toBe('1.234.567.890');
      });
    });

    describe('given a value longer than the Figma size', () => {
      test('when 20 digits are typed, then every digit is kept (the mask never truncates)', () => {
        expect(CCHandler.mask('9'.repeat(20)).replace(/\D/g, '')).toHaveLength(20);
      });
    });

    describe('given a value with separators already typed', () => {
      test('when the value has dots, then it is cleaned to "1.234.567.890"', () => {
        expect(CCHandler.mask('1.234.567.890')).toBe('1.234.567.890');
      });
    });
  });

  describe('validate()', () => {
    describe('given an empty value', () => {
      test.each([
        { input: '', description: 'an empty string' },
        { input: null, description: 'a null value' },
      ])('when validating $description, then it is rejected as empty', ({ input }) => {
        expect(CCHandler.validate(input)).toEqual({ result: false, type: 'empty' });
      });
    });

    describe('given any non-empty value (permissive interim behavior, matching develop)', () => {
      test.each([
        { input: '1234567890', description: 'a well-formed CC' },
        { input: '123', description: 'a very short value' },
        { input: 'abc', description: 'letters' },
      ])('when validating $description, then it is valid', ({ input }) => {
        expect(CCHandler.validate(input)).toEqual({ result: true, type: 'valid' });
      });
    });
  });

  describe('CONFIG', () => {
    test('given the handler, then max_length is 10', () => {
      expect(CCHandler.CONFIG.max_length).toBe(10);
    });

    test('given the handler, then the placeholder matches the Figma mask', () => {
      expect(CCHandler.CONFIG.placeholder).toBe('9.999.999.999');
    });
  });
});
