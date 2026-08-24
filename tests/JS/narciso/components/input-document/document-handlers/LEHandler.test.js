const LEHandler = require('packages/narciso/components/input-document/document-handlers/LEHandler');

describe('LEHandler', () => {
  describe('mask()', () => {
    describe('given a Libreta de Enrolamiento typed progressively (right-to-left)', () => {
      test.each([
        { input: '1', expected: '1' },
        { input: '123', expected: '123' },
        { input: '1234', expected: '1.234' },
        { input: '450000', expected: '450.000' },
        { input: '4500000', expected: '4.500.000' },
      ])('when the value is "$input", then it renders "$expected"', ({ input, expected }) => {
        expect(LEHandler.mask(input)).toBe(expected);
      });
    });

    describe('given the Figma size (7 digits)', () => {
      test('when a full LE is typed, then it matches the Figma pattern X.XXX.XXX', () => {
        expect(LEHandler.mask('4500000')).toBe('4.500.000');
      });
    });

    describe('given a value longer than the Figma size', () => {
      test('when 20 digits are typed, then every digit is kept (the mask never truncates)', () => {
        expect(LEHandler.mask('9'.repeat(20)).replace(/\D/g, '')).toHaveLength(20);
      });
    });

    describe('given a value with separators already typed', () => {
      test('when the value has dots, then it is cleaned to "4.500.000"', () => {
        expect(LEHandler.mask('4.500.000')).toBe('4.500.000');
      });
    });
  });

  describe('validate()', () => {
    describe('given a non-empty value (permissive interim: only empty is rejected)', () => {
      test.each([
        { input: '4500000', description: 'a well-formed LE' },
        { input: '123', description: 'a very short value' },
        { input: 'abc', description: 'letters' },
      ])('when validating $description, then it is valid', ({ input }) => {
        expect(LEHandler.validate(input)).toEqual({ result: true, type: 'valid' });
      });
    });

    describe('given an empty value', () => {
      test.each([
        { input: '', description: 'an empty string' },
        { input: null, description: 'a null value' },
      ])('when validating $description, then it is rejected as empty', ({ input }) => {
        expect(LEHandler.validate(input)).toEqual({ result: false, type: 'empty' });
      });
    });
  });

  describe('CONFIG', () => {
    test('given the handler, then min_length is 6', () => {
      expect(LEHandler.CONFIG.min_length).toBe(6);
    });

    test('given the handler, then max_length is 7', () => {
      expect(LEHandler.CONFIG.max_length).toBe(7);
    });

    test('given the handler, then the placeholder matches the Figma mask', () => {
      expect(LEHandler.CONFIG.placeholder).toBe('9.999.999');
    });
  });
});
