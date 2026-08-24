const RUCHandler = require('packages/narciso/components/input-document/document-handlers/RUCHandler');

describe('RUCHandler', () => {
  describe('mask()', () => {
    describe('given a RUC typed progressively (right-to-left)', () => {
      test.each([
        { input: '1', expected: '1' },
        { input: '12', expected: '12' },
        { input: '123', expected: '123' },
        { input: '1234', expected: '1.234' },
        { input: '1234567', expected: '1.234.567' },
        { input: '1234567890', expected: '1.234.567.890' },
        { input: '12345678901', expected: '12.345.678.901' },
      ])('when the value is "$input", then it renders "$expected"', ({ input, expected }) => {
        expect(RUCHandler.mask(input)).toBe(expected);
      });
    });

    describe('given the Figma size (11 digits)', () => {
      test('when a full RUC is typed, then it matches the Figma pattern XX.XXX.XXX.XXX', () => {
        expect(RUCHandler.mask('20538995364')).toBe('20.538.995.364');
      });
    });

    describe('given a value longer than the Figma size', () => {
      test('when 20 digits are typed, then every digit is kept (the mask never truncates)', () => {
        expect(RUCHandler.mask('9'.repeat(20)).replace(/\D/g, '')).toHaveLength(20);
      });
    });

    describe('given a value with separators already typed', () => {
      test.each([
        { input: '20.538.995.364', description: 'dots' },
        { input: '20 538 995 364', description: 'spaces' },
      ])('when the value has $description, then it is cleaned to "20.538.995.364"', ({ input }) => {
        expect(RUCHandler.mask(input)).toBe('20.538.995.364');
      });
    });
  });

  describe('validate()', () => {
    describe('given a non-empty value (permissive interim: only empty is rejected)', () => {
      test.each([
        { input: '20538995364', description: 'a well-formed RUC' },
        { input: '12345678901', description: 'a RUC with a wrong check digit' },
        { input: '99999999999', description: 'an invalid prefix' },
        { input: '123', description: 'a very short value' },
      ])('when validating $description, then it is valid', ({ input }) => {
        expect(RUCHandler.validate(input)).toEqual({ result: true, type: 'valid' });
      });
    });

    describe('given an empty value', () => {
      test.each([
        { input: '', description: 'an empty string' },
        { input: null, description: 'a null value' },
      ])('when validating $description, then it is rejected as empty', ({ input }) => {
        expect(RUCHandler.validate(input)).toEqual({ result: false, type: 'empty' });
      });
    });
  });

  describe('CONFIG', () => {
    test('given the handler, then min_length is 11', () => {
      expect(RUCHandler.CONFIG.min_length).toBe(11);
    });

    test('given the handler, then max_length is 11', () => {
      expect(RUCHandler.CONFIG.max_length).toBe(11);
    });

    test('given the handler, then the placeholder matches the Figma mask', () => {
      expect(RUCHandler.CONFIG.placeholder).toBe('99.999.999.999');
    });
  });
});
