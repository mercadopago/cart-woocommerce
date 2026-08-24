const RUTHandler = require('packages/narciso/components/input-document/document-handlers/RUTHandler');

describe('RUTHandler', () => {
  describe('mask()', () => {
    describe('given a RUT typed progressively (right-to-left, DV separated)', () => {
      test.each([
        { input: '1', expected: '1' },
        { input: '12', expected: '12' },
        { input: '123', expected: '123' },
        { input: '1234', expected: '1.234' },
        { input: '12345', expected: '12.345' },
        { input: '123456', expected: '123.456' },
        { input: '1234567', expected: '1.234.567' },
        { input: '12345678', expected: '1.234.567-8' },
        { input: '123456785', expected: '12.345.678-5' },
        { input: '15871597K', expected: '15.871.597-K' },
      ])('when the value is "$input", then it renders "$expected"', ({ input, expected }) => {
        expect(RUTHandler.mask(input)).toBe(expected);
      });
    });

    describe('given the Figma size (9 characters: 8-digit body + DV)', () => {
      test('when a full RUT is typed, then it matches the Figma pattern XX.XXX.XXX-D', () => {
        expect(RUTHandler.mask('123456785')).toBe('12.345.678-5');
      });
    });

    describe('given a value longer than the Figma size', () => {
      test('when 20 digits are typed, then every digit is kept (the mask never truncates)', () => {
        expect(RUTHandler.mask('9'.repeat(20)).replace(/\D/g, '')).toHaveLength(20);
      });
    });

    describe('given a "k" or "K" check digit', () => {
      test.each([
        { input: '15871597k', expected: '15.871.597-K' },
        { input: '15871597K', expected: '15.871.597-K' },
      ])('when the value is "$input", then the DV is upper-cased to "$expected"', ({ input, expected }) => {
        expect(RUTHandler.mask(input)).toBe(expected);
      });
    });

    describe('given a value with separators already typed', () => {
      test.each([
        { input: '15.871.597-K', description: 'dots and dash' },
        { input: '15 871 597 K', description: 'spaces' },
        { input: '15-871-597-K', description: 'dashes' },
      ])('when the value has $description, then it is cleaned to "15.871.597-K"', ({ input }) => {
        expect(RUTHandler.mask(input)).toBe('15.871.597-K');
      });
    });

    describe('given a short value (7 characters or fewer)', () => {
      test.each([
        { input: '1234567', expected: '1.234.567' },
        { input: '123456', expected: '123.456' },
        { input: '12345', expected: '12.345' },
      ])('when the value is "$input", then no DV dash is added ("$expected")', ({ input, expected }) => {
        expect(RUTHandler.mask(input)).toBe(expected);
      });
    });
  });

  describe('validate()', () => {
    describe('given a non-empty value (permissive interim: only empty is rejected)', () => {
      test.each([
        { input: '15871597K', description: 'a well-formed RUT' },
        { input: '158715971', description: 'a RUT with a wrong check digit' },
        { input: '111111111', description: 'a body with repeated digits' },
        { input: 'abcdefgh5', description: 'letters' },
        { input: '1', description: 'a very short value' },
      ])('when validating $description, then it is valid', ({ input }) => {
        expect(RUTHandler.validate(input)).toEqual({ result: true, type: 'valid' });
      });
    });

    describe('given an empty value', () => {
      test.each([
        { input: '', description: 'an empty string' },
        { input: null, description: 'a null value' },
      ])('when validating $description, then it is rejected as empty', ({ input }) => {
        expect(RUTHandler.validate(input)).toEqual({ result: false, type: 'empty' });
      });
    });
  });

  describe('CONFIG', () => {
    test('given the handler, then min_length is 8', () => {
      expect(RUTHandler.CONFIG.min_length).toBe(8);
    });

    test('given the handler, then max_length is 9', () => {
      expect(RUTHandler.CONFIG.max_length).toBe(9);
    });

    test('given the handler, then the placeholder matches the Figma mask', () => {
      expect(RUTHandler.CONFIG.placeholder).toBe('99.999.999-9');
    });
  });
});
