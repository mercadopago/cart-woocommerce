const MLA_DNIHandler = require('packages/narciso/components/input-document/document-handlers/MLA_DNIHandler');

describe('MLA_DNIHandler', () => {
  describe('mask()', () => {
    describe('given a DNI typed progressively (right-to-left)', () => {
      test.each([
        { input: '1', expected: '1' },
        { input: '12', expected: '12' },
        { input: '123', expected: '123' },
        { input: '1234', expected: '1.234' },
        { input: '12345', expected: '12.345' },
        { input: '123456', expected: '123.456' },
        { input: '1234567', expected: '1.234.567' },
        { input: '12345678', expected: '12.345.678' },
      ])('when the value is "$input", then it renders "$expected"', ({ input, expected }) => {
        expect(MLA_DNIHandler.mask(input)).toBe(expected);
      });
    });

    describe('given the Figma size (8 digits)', () => {
      test('when a full DNI is typed, then it matches the Figma pattern XX.XXX.XXX', () => {
        expect(MLA_DNIHandler.mask('30879547')).toBe('30.879.547');
      });
    });

    describe('given a value longer than the Figma size', () => {
      test('when 20 digits are typed, then every digit is kept (the mask never truncates)', () => {
        expect(MLA_DNIHandler.mask('9'.repeat(20)).replace(/\D/g, '')).toHaveLength(20);
      });
    });

    describe('given a value with separators already typed', () => {
      test.each([
        { input: '30.879.547', description: 'dots' },
        { input: '30 879 547', description: 'spaces' },
      ])('when the value has $description, then it is cleaned to "30.879.547"', ({ input }) => {
        expect(MLA_DNIHandler.mask(input)).toBe('30.879.547');
      });
    });
  });

  describe('validate()', () => {
    describe('given a non-empty value (permissive interim: only empty is rejected)', () => {
      test.each([
        { input: '30879547', description: 'a well-formed DNI' },
        { input: '123', description: 'a very short value' },
        { input: 'abc', description: 'letters' },
      ])('when validating $description, then it is valid', ({ input }) => {
        expect(MLA_DNIHandler.validate(input)).toEqual({ result: true, type: 'valid' });
      });
    });

    describe('given an empty value', () => {
      test.each([
        { input: '', description: 'an empty string' },
        { input: null, description: 'a null value' },
      ])('when validating $description, then it is rejected as empty', ({ input }) => {
        expect(MLA_DNIHandler.validate(input)).toEqual({ result: false, type: 'empty' });
      });
    });
  });

  describe('CONFIG', () => {
    test('given the handler, then min_length is 7', () => {
      expect(MLA_DNIHandler.CONFIG.min_length).toBe(7);
    });

    test('given the handler, then max_length is 8', () => {
      expect(MLA_DNIHandler.CONFIG.max_length).toBe(8);
    });

    test('given the handler, then the placeholder matches the Figma mask', () => {
      expect(MLA_DNIHandler.CONFIG.placeholder).toBe('99.999.999');
    });
  });
});
