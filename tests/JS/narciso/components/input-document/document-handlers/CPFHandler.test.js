const CPFHandler = require('packages/narciso/components/input-document/document-handlers/CPFHandler');

describe('CPFHandler', () => {
  describe('mask()', () => {
    describe('given a CPF typed progressively (left-to-right)', () => {
      test.each([
        { input: '1', expected: '1' },
        { input: '12', expected: '12' },
        { input: '123', expected: '123' },
        { input: '1234', expected: '123.4' },
        { input: '12345', expected: '123.45' },
        { input: '123456', expected: '123.456' },
        { input: '1234567', expected: '123.456.7' },
        { input: '12345678', expected: '123.456.78' },
        { input: '123456789', expected: '123.456.789' },
        { input: '1234567890', expected: '123.456.789-0' },
        { input: '12345678909', expected: '123.456.789-09' },
      ])('when the value is "$input", then it renders "$expected"', ({ input, expected }) => {
        expect(CPFHandler.mask(input)).toBe(expected);
      });
    });

    describe('given the Figma size (11 digits)', () => {
      test('when a full CPF is typed, then it matches the Figma pattern 999.999.999-99', () => {
        expect(CPFHandler.mask('12345678909')).toBe('123.456.789-09');
      });
    });

    describe('given a value longer than the Figma size', () => {
      test('when more than 11 digits are typed, then it truncates to the fixed CPF length (FIJO)', () => {
        expect(CPFHandler.mask('1234567890999')).toBe('123.456.789-09');
        expect(CPFHandler.mask('123456789091234567890').length).toBeLessThanOrEqual(CPFHandler.CONFIG.max_length_with_mask);
      });
    });

    describe('given a value with separators or letters', () => {
      test.each([
        { input: '123.456.789-09', description: 'already formatted' },
        { input: '123 456 789 09', description: 'spaces' },
        { input: 'abc123456789def09', description: 'letters' },
      ])('when the value has $description, then it is cleaned to "123.456.789-09"', ({ input }) => {
        expect(CPFHandler.mask(input)).toBe('123.456.789-09');
      });
    });
  });

  describe('validate()', () => {
    describe('given a well-formed CPF with a correct check digit', () => {
      test.each([
        { cpf: '12345678909', description: 'without formatting' },
        { cpf: '123.456.789-09', description: 'with dots and dash' },
        { cpf: '123 456 789 09', description: 'with spaces' },
      ])('when validating a CPF $description, then it is valid', ({ cpf }) => {
        expect(CPFHandler.validate(cpf)).toEqual({ result: true, type: 'valid' });
      });
    });

    describe('given a CPF with all repeated digits', () => {
      test.each([
        '00000000000', '11111111111', '22222222222', '33333333333', '44444444444',
        '55555555555', '66666666666', '77777777777', '88888888888', '99999999999',
      ])('when validating "%s", then it is rejected as wrong', (cpf) => {
        expect(CPFHandler.validate(cpf)).toEqual({ result: false, type: 'wrong' });
      });
    });

    describe('given a CPF with a wrong check digit', () => {
      test.each([
        { cpf: '52998224726', description: 'wrong second check digit' },
        { cpf: '52998224735', description: 'wrong first check digit' },
        { cpf: '12345678900', description: 'valid format but wrong check digits' },
        { cpf: '11144477736', description: 'wrong check digit' },
      ])('when validating a CPF with $description, then it is rejected as wrong', ({ cpf }) => {
        expect(CPFHandler.validate(cpf)).toEqual({ result: false, type: 'wrong' });
      });
    });

    describe('given a value with the wrong length', () => {
      test.each([
        { cpf: '123', description: 'too short' },
        { cpf: '123456789012', description: 'too long (12 digits)' },
        { cpf: '1234567890', description: 'only 10 digits' },
      ])('when validating a value $description, then it is rejected as invalid', ({ cpf }) => {
        expect(CPFHandler.validate(cpf)).toEqual({ result: false, type: 'invalid' });
      });
    });

    describe('given an empty value', () => {
      test('when validating an empty string, then it is rejected as empty', () => {
        expect(CPFHandler.validate('')).toEqual({ result: false, type: 'empty' });
      });
    });

    describe('given a non-string value', () => {
      test.each([
        { cpf: null, description: 'null' },
        { cpf: undefined, description: 'undefined' },
        { cpf: 12345678909, description: 'a number' },
        { cpf: {}, description: 'an object' },
        { cpf: [], description: 'an array' },
      ])('when validating $description, then it is rejected as invalid', ({ cpf }) => {
        expect(CPFHandler.validate(cpf)).toEqual({ result: false, type: 'invalid' });
      });
    });
  });

  describe('CONFIG', () => {
    test('given the handler, then max_length is 11', () => {
      expect(CPFHandler.CONFIG.max_length).toBe(11);
    });

    test('given the handler, then the placeholder matches the Figma mask', () => {
      expect(CPFHandler.CONFIG.placeholder).toBe('999.999.999-99');
    });
  });
});
