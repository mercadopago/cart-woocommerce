const GenericHandler = require('packages/narciso/components/input-document/document-handlers/GenericHandler');

describe('GenericHandler', () => {
  describe('validate()', () => {
    describe('given a non-empty value (permissive interim: only empty is rejected)', () => {
      test.each([
        { input: '1234567890', description: 'a typical document number' },
        { input: '123', description: 'a very short value' },
        { input: 'abc-123', description: 'letters and separators' },
      ])('when validating $description, then it is valid', ({ input }) => {
        expect(GenericHandler.validate(input)).toEqual({ result: true, type: 'valid' });
      });
    });

    describe('given an empty value', () => {
      test.each([
        { input: '', description: 'an empty string' },
        { input: null, description: 'a null value' },
      ])('when validating $description, then it is rejected as empty', ({ input }) => {
        expect(GenericHandler.validate(input)).toEqual({ result: false, type: 'empty' });
      });
    });
  });

  describe('CONFIG', () => {
    test('given the handler, then it is not scoped to a site', () => {
      expect(GenericHandler.CONFIG.site_id).toBeNull();
    });

    test('given the handler, then max_length is 20', () => {
      expect(GenericHandler.CONFIG.max_length).toBe(20);
    });
  });
});
