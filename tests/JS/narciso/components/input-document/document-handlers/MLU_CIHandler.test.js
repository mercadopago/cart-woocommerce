const MLU_CIHandler = require('packages/narciso/components/input-document/document-handlers/MLU_CIHandler');

// Builds a CI whose last digit is the correct module-10 check digit, mirroring
// the handler's own algorithm (weights 2987634, left-padded to 7 digits).
function withCheckDigit(body) {
  let padded = body;
  while (padded.length < 7) padded = `0${padded}`;
  let x = 0;
  for (let y = 0; y < 7; y++) {
    x += (parseInt('2987634'[y], 10) * parseInt(padded[y], 10)) % 10;
  }
  const dv = x % 10 === 0 ? 0 : 10 - (x % 10);
  return padded + dv;
}

describe('MLU_CIHandler', () => {
  describe('mask()', () => {
    describe('given a CI typed progressively (right-to-left, DV separated)', () => {
      test.each([
        { input: '1', expected: '1' },
        { input: '1234', expected: '1.234' },
        { input: '123456', expected: '123.456' },
        { input: '1234567', expected: '123.456-7' },
        { input: '12345679', expected: '1.234.567-9' },
        { input: '58801519', expected: '5.880.151-9' },
      ])('when the value is "$input", then it renders "$expected"', ({ input, expected }) => {
        expect(MLU_CIHandler.mask(input)).toBe(expected);
      });
    });

    describe('given the Figma size (8 digits)', () => {
      test('when a full CI is typed, then it matches the Figma pattern 9.999.999-9', () => {
        expect(MLU_CIHandler.mask('58801519')).toBe('5.880.151-9');
      });
    });

    describe('given a value with separators or letters', () => {
      test.each([
        { input: '5 880 151 9', description: 'spaces' },
        { input: '5-880-151-9', description: 'dashes' },
        { input: 'abc58801519def', description: 'letters' },
      ])('when the value has $description, then it is cleaned to "5.880.151-9"', ({ input }) => {
        expect(MLU_CIHandler.mask(input)).toBe('5.880.151-9');
      });
    });

    describe('given a short value (6 digits or fewer)', () => {
      test.each([
        { input: '123456', expected: '123.456' },
        { input: '12345', expected: '12.345' },
        { input: '1234', expected: '1.234' },
      ])('when the value is "$input", then no DV dash is added ("$expected")', ({ input, expected }) => {
        expect(MLU_CIHandler.mask(input)).toBe(expected);
      });
    });
  });

  describe('validate()', () => {
    describe('given a CI with a correct check digit', () => {
      test.each([
        { body: '123456', description: 'a 7-digit CI (padded)' },
        { body: '0000000', description: 'a CI whose check digit is 0' },
      ])('when validating $description, then it is valid', ({ body }) => {
        expect(MLU_CIHandler.validate(withCheckDigit(body))).toEqual({ result: true, type: 'valid' });
      });

      test('when validating a CI shorter than 7 digits with a correct DV, then it is valid (left-padded)', () => {
        expect(MLU_CIHandler.validate(withCheckDigit('12345'))).toEqual({ result: true, type: 'valid' });
      });
    });

    describe('given a CI with a wrong check digit', () => {
      test('when validating "58801510", then it is rejected as wrong', () => {
        expect(MLU_CIHandler.validate('58801510')).toEqual({ result: false, type: 'wrong' });
      });
    });

    describe('given a CI longer than the configured maximum', () => {
      test('when the first seven digits and final DV happen to match, then it is still rejected', () => {
        expect(MLU_CIHandler.validate('58801510009')).toEqual({ result: false, type: 'wrong' });
      });
    });

    describe('given an empty or non-string value', () => {
      test.each([
        { ci: '', description: 'an empty string' },
        { ci: null, description: 'null' },
        { ci: undefined, description: 'undefined' },
        { ci: 12345678, description: 'a number' },
        { ci: {}, description: 'an object' },
      ])('when validating $description, then it is rejected as empty', ({ ci }) => {
        expect(MLU_CIHandler.validate(ci)).toEqual({ result: false, type: 'empty' });
      });
    });
  });

  describe('CONFIG', () => {
    test('given the handler, then min_length is 7', () => {
      expect(MLU_CIHandler.CONFIG.min_length).toBe(7);
    });

    test('given the handler, then max_length is 8', () => {
      expect(MLU_CIHandler.CONFIG.max_length).toBe(8);
    });

    test('given the handler, then the placeholder matches the Figma mask', () => {
      expect(MLU_CIHandler.CONFIG.placeholder).toBe('9.999.999-9');
    });
  });
});
