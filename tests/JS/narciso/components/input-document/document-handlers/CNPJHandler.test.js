const CNPJHandler = require('packages/narciso/components/input-document/document-handlers/CNPJHandler');

describe('CNPJHandler', () => {
  describe('mask()', () => {
    describe('given a CNPJ typed progressively (left-to-right)', () => {
      test.each([
        { input: '1', expected: '1' },
        { input: '11', expected: '11' },
        { input: '112', expected: '11.2' },
        { input: '11222', expected: '11.222' },
        { input: '112223', expected: '11.222.3' },
        { input: '11222333', expected: '11.222.333' },
        { input: '112223330', expected: '11.222.333/0' },
        { input: '112223330001', expected: '11.222.333/0001' },
        { input: '1122233300018', expected: '11.222.333/0001-8' },
        { input: '11222333000181', expected: '11.222.333/0001-81' },
      ])('when the value is "$input", then it renders "$expected"', ({ input, expected }) => {
        expect(CNPJHandler.mask(input)).toBe(expected);
      });
    });

    describe('given the Figma size (14 characters)', () => {
      test('when a full CNPJ is typed, then it matches the Figma pattern 99.999.999/9999-99', () => {
        expect(CNPJHandler.mask('11222333000181')).toBe('11.222.333/0001-81');
      });
    });

    describe('given an alphanumeric CNPJ (2026+)', () => {
      test.each([
        { input: 'L3BZQJKT000189', expected: 'L3.BZQ.JKT/0001-89', description: 'alphanumeric body with numeric DV' },
        { input: 'ABCDEFGH000199', expected: 'AB.CDE.FGH/0001-99', description: 'all letters in body' },
        { input: '12ABC345000199', expected: '12.ABC.345/0001-99', description: 'mixed alphanumeric' },
      ])('when the value is "$input" ($description), then it renders "$expected"', ({ input, expected }) => {
        expect(CNPJHandler.mask(input)).toBe(expected);
      });

      test('when letters are typed in the DV positions, then only digits are kept there', () => {
        expect(CNPJHandler.mask('L3BZQJKT0001AB')).toBe('L3.BZQ.JKT/0001');
      });
    });

    describe('given a value longer than the Figma size', () => {
      test('when more than 14 characters are typed, then it truncates to the fixed CNPJ length (FIJO)', () => {
        expect(CNPJHandler.mask('112223330001819999')).toBe('11.222.333/0001-81');
        expect(CNPJHandler.mask('112223330001811234567890').length).toBeLessThanOrEqual(CNPJHandler.CONFIG.max_length_with_mask);
      });
    });

    describe('given a value with separators', () => {
      test.each([
        { input: '11 222 333 0001 81', description: 'spaces' },
        { input: '11-222-333-0001-81', description: 'dashes' },
      ])('when the value has $description, then it is cleaned to "11.222.333/0001-81"', ({ input }) => {
        expect(CNPJHandler.mask(input)).toBe('11.222.333/0001-81');
      });
    });
  });

  describe('validate()', () => {
    describe('given a well-formed CNPJ with a correct check digit', () => {
      test.each([
        { cnpj: '11222333000181', description: 'numeric without formatting' },
        { cnpj: '11.222.333/0001-81', description: 'numeric with formatting' },
        { cnpj: '33014556000196', description: 'another numeric CNPJ' },
        { cnpj: '45997418000153', description: 'numeric starting with 4' },
        { cnpj: 'L3.BZQ.JKT/0001-89', description: 'alphanumeric with formatting' },
      ])('when validating a CNPJ $description, then it is valid', ({ cnpj }) => {
        expect(CNPJHandler.validate(cnpj)).toEqual({ result: true, type: 'valid' });
      });
    });

    describe('given a CNPJ with all repeated digits', () => {
      test.each([
        '00000000000000', '11111111111111', '22222222222222', '33333333333333', '44444444444444',
        '55555555555555', '66666666666666', '77777777777777', '88888888888888', '99999999999999',
      ])('when validating "%s", then it is rejected as wrong', (cnpj) => {
        expect(CNPJHandler.validate(cnpj)).toEqual({ result: false, type: 'wrong' });
      });
    });

    describe('given a CNPJ with a wrong check digit', () => {
      test.each([
        { cnpj: '11222333000182', description: 'wrong second check digit' },
        { cnpj: '11222333000191', description: 'wrong first check digit' },
        { cnpj: '12345678000100', description: 'valid format but wrong check digits' },
      ])('when validating a CNPJ with $description, then it is rejected as wrong', ({ cnpj }) => {
        expect(CNPJHandler.validate(cnpj)).toEqual({ result: false, type: 'wrong' });
      });
    });

    describe('given a value with the wrong length', () => {
      test.each([
        { cnpj: '123', description: 'too short' },
        { cnpj: '112223330001811', description: 'too long (15 digits)' },
        { cnpj: '1122233300018', description: 'only 13 digits' },
      ])('when validating a value $description, then it is rejected as invalid', ({ cnpj }) => {
        expect(CNPJHandler.validate(cnpj)).toEqual({ result: false, type: 'invalid' });
      });

      test('when validating an empty string, then it is rejected as empty', () => {
        expect(CNPJHandler.validate('')).toEqual({ result: false, type: 'empty' });
      });
    });
  });

  describe('CONFIG', () => {
    test('given the handler, then max_length is 14', () => {
      expect(CNPJHandler.CONFIG.max_length).toBe(14);
    });

    test('given the handler, then the placeholder matches the Figma mask', () => {
      expect(CNPJHandler.CONFIG.placeholder).toBe('99.999.999/9999-99');
    });
  });
});
