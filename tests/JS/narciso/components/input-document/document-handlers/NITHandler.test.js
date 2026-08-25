const NITHandler = require('packages/narciso/components/input-document/document-handlers/NITHandler');

describe('NITHandler', () => {
  describe('mask()', () => {
    describe('given a NIT typed progressively (right-to-left, DV separated from 9 digits)', () => {
      test.each([
        { input: '1', expected: '1' },
        { input: '1234', expected: '1.234' },
        { input: '123456789', expected: '12.345.678-9' },
        { input: '1234567890', expected: '123.456.789-0' },
      ])('when the value is "$input", then it renders "$expected"', ({ input, expected }) => {
        expect(NITHandler.mask(input)).toBe(expected);
      });
    });

    describe('given the Figma size (10 digits: 9-digit body + DV)', () => {
      test('when a full NIT is typed, then it matches the Figma pattern XXX.XXX.XXX-D', () => {
        expect(NITHandler.mask('1234567890')).toBe('123.456.789-0');
      });
    });

    describe('given a value longer than the Figma size', () => {
      test('when 20 digits are typed, then every digit is kept (the mask never truncates)', () => {
        expect(NITHandler.mask('9'.repeat(20)).replace(/\D/g, '')).toHaveLength(20);
      });
    });

    describe('given a value with separators already typed', () => {
      test('when the value has dots and a dash, then it is cleaned to "123.456.789-0"', () => {
        expect(NITHandler.mask('123.456.789-0')).toBe('123.456.789-0');
      });
    });
  });

  describe('validate()', () => {
    describe('given an empty value', () => {
      test.each([
        { input: '', description: 'an empty string' },
        { input: null, description: 'a null value' },
      ])('when validating $description, then it is rejected as empty', ({ input }) => {
        expect(NITHandler.validate(input)).toEqual({ result: false, type: 'empty' });
      });
    });

    describe('given any non-empty value (permissive interim behavior, matching develop)', () => {
      test.each([
        { input: '8001735574', description: 'a well-formed NIT' },
        { input: '1234567890', description: 'a NIT with a wrong check digit' },
        { input: '123', description: 'a very short value' },
        { input: 'abc', description: 'letters' },
      ])('when validating $description, then it is valid', ({ input }) => {
        expect(NITHandler.validate(input)).toEqual({ result: true, type: 'valid' });
      });
    });
  });

  describe('CONFIG', () => {
    test('given the handler, then max_length is 16', () => {
      expect(NITHandler.CONFIG.max_length).toBe(16);
    });

    test('given the handler, then the placeholder matches the Figma mask', () => {
      expect(NITHandler.CONFIG.placeholder).toBe('999.999.999-9');
    });
  });
});
