const MPE_DNIHandler = require('packages/narciso/components/input-document/document-handlers/MPE_DNIHandler');

describe('MPE_DNIHandler', () => {
  describe('mask()', () => {
    describe('given a DNI typed progressively (left-to-right)', () => {
      test.each([
        { input: '1', expected: '1' },
        { input: '12', expected: '12' },
        { input: '123', expected: '12.3' },
        { input: '1234', expected: '12.34' },
        { input: '12345', expected: '12.345' },
        { input: '123456', expected: '12.345.6' },
        { input: '1234567', expected: '12.345.67' },
        { input: '12345678', expected: '12.345.678' },
      ])('when the value is "$input", then it renders "$expected"', ({ input, expected }) => {
        expect(MPE_DNIHandler.mask(input)).toBe(expected);
      });
    });

    describe('given the Figma size (8 digits)', () => {
      test('when a full DNI is typed, then it matches the Figma pattern XX.XXX.XXX', () => {
        expect(MPE_DNIHandler.mask('17801146')).toBe('17.801.146');
      });
    });

    describe('given a value longer than the Figma size', () => {
      test('when 20 digits are typed, then every digit is kept (the mask never truncates)', () => {
        expect(MPE_DNIHandler.mask('9'.repeat(20)).replace(/\D/g, '')).toHaveLength(20);
      });

      test('when more than 8 digits are typed, then the extra digits are grouped, not dropped', () => {
        expect(MPE_DNIHandler.mask('123456789')).toBe('12.345.678.9');
      });
    });

    describe('given a value with letters or separators', () => {
      test('when letters are typed, then they are dropped (DNI is numeric only)', () => {
        expect(MPE_DNIHandler.mask('17801146K')).toBe('17.801.146');
      });

      test('when the value has dots, then it is cleaned to "17.801.146"', () => {
        expect(MPE_DNIHandler.mask('17.801.146')).toBe('17.801.146');
      });
    });
  });

  describe('validate()', () => {
    describe('given a non-empty value (permissive interim: only empty is rejected)', () => {
      test.each([
        { input: '17801146', description: 'a well-formed DNI' },
        { input: '178011465', description: 'a DNI with a check digit' },
        { input: '123', description: 'a very short value' },
        { input: 'abc', description: 'letters' },
      ])('when validating $description, then it is valid', ({ input }) => {
        expect(MPE_DNIHandler.validate(input)).toEqual({ result: true, type: 'valid' });
      });
    });

    describe('given an empty value', () => {
      test.each([
        { input: '', description: 'an empty string' },
        { input: null, description: 'a null value' },
      ])('when validating $description, then it is rejected as empty', ({ input }) => {
        expect(MPE_DNIHandler.validate(input)).toEqual({ result: false, type: 'empty' });
      });
    });
  });

  describe('CONFIG', () => {
    test('given the handler, then min_length is 8', () => {
      expect(MPE_DNIHandler.CONFIG.min_length).toBe(8);
    });

    test('given the handler, then max_length is 9', () => {
      expect(MPE_DNIHandler.CONFIG.max_length).toBe(9);
    });

    test('given the handler, then the placeholder matches the Figma mask', () => {
      expect(MPE_DNIHandler.CONFIG.placeholder).toBe('99.999.999');
    });
  });
});
