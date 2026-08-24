const LCHandler = require('packages/narciso/components/input-document/document-handlers/LCHandler');

describe('LCHandler', () => {
  describe('mask()', () => {
    describe('given a Libreta Cívica typed progressively (right-to-left)', () => {
      test.each([
        { input: '1', expected: '1' },
        { input: '123', expected: '123' },
        { input: '1234', expected: '1.234' },
        { input: '123456', expected: '123.456' },
        { input: '1234567', expected: '1.234.567' },
      ])('when the value is "$input", then it renders "$expected"', ({ input, expected }) => {
        expect(LCHandler.mask(input)).toBe(expected);
      });
    });

    describe('given the Figma size (7 digits)', () => {
      test('when a full LC is typed, then it matches the Figma pattern X.XXX.XXX', () => {
        expect(LCHandler.mask('1234567')).toBe('1.234.567');
      });
    });

    describe('given a value longer than the Figma size', () => {
      test('when 20 digits are typed, then every digit is kept (the mask never truncates)', () => {
        expect(LCHandler.mask('9'.repeat(20)).replace(/\D/g, '')).toHaveLength(20);
      });
    });

    describe('given a value with separators already typed', () => {
      test('when the value has dots, then it is cleaned to "1.234.567"', () => {
        expect(LCHandler.mask('1.234.567')).toBe('1.234.567');
      });
    });
  });

  describe('validate()', () => {
    describe('given a non-empty value (permissive interim: only empty is rejected)', () => {
      test.each([
        { input: '1234567', description: 'a well-formed LC' },
        { input: '123', description: 'a very short value' },
        { input: 'abc', description: 'letters' },
      ])('when validating $description, then it is valid', ({ input }) => {
        expect(LCHandler.validate(input)).toEqual({ result: true, type: 'valid' });
      });
    });

    describe('given an empty value', () => {
      test.each([
        { input: '', description: 'an empty string' },
        { input: null, description: 'a null value' },
      ])('when validating $description, then it is rejected as empty', ({ input }) => {
        expect(LCHandler.validate(input)).toEqual({ result: false, type: 'empty' });
      });
    });
  });

  describe('CONFIG', () => {
    test('given the handler, then min_length is 6', () => {
      expect(LCHandler.CONFIG.min_length).toBe(6);
    });

    test('given the handler, then max_length is 7', () => {
      expect(LCHandler.CONFIG.max_length).toBe(7);
    });

    test('given the handler, then the placeholder matches the Figma mask', () => {
      expect(LCHandler.CONFIG.placeholder).toBe('9.999.999');
    });
  });
});
