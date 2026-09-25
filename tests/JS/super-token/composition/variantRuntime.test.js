const mockResolve = jest.fn();

jest.mock('@super-token/adapters/platform', () => ({
  VariantConfigAdapter: jest.fn().mockImplementation(() => ({ resolve: mockResolve })),
}));

const {
  readVariantCookie,
  isSelfConstruct,
  resolveSuperTokenVariant,
} = require('@super-token/composition/variantRuntime');

describe('composition/variantRuntime', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    // Clear the A/B cookie between cases.
    document.cookie = 'mp_st_variant=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
    delete window.wc_mercadopago_supertoken_bundle_params;
    // Default to the runtime bundle (no build-time pin); the pin cases override this and it is
    // restored here for the next case.
    global.__ST_FIXED_VARIANT__ = '';
  });

  describe('readVariantCookie', () => {
    it('Given the mp_st_variant cookie is set, When read, Then it returns the stored variant', () => {
      document.cookie = 'mp_st_variant=v2.1';
      expect(readVariantCookie()).toBe('v2.1');
    });

    it('Given no mp_st_variant cookie, When read, Then it returns null', () => {
      expect(readVariantCookie()).toBeNull();
    });
  });

  describe('isSelfConstruct', () => {
    it('Given self_construct is true in the localized params, When checked, Then it returns true', () => {
      window.wc_mercadopago_supertoken_bundle_params = { self_construct: true };
      expect(isSelfConstruct()).toBe(true);
    });

    it('Given the params are absent or self_construct is falsy, When checked, Then it returns false', () => {
      expect(isSelfConstruct()).toBe(false);
      window.wc_mercadopago_supertoken_bundle_params = { self_construct: false };
      expect(isSelfConstruct()).toBe(false);
    });
  });

  describe('resolveSuperTokenVariant', () => {
    it('Given self-construct with a localized super_token_version, When resolved, Then it follows the constant (not the cookie)', async () => {
      document.cookie = 'mp_st_variant=v2';
      window.wc_mercadopago_supertoken_bundle_params = { self_construct: true, super_token_version: 'v2.1' };
      await expect(resolveSuperTokenVariant()).resolves.toBe('v2.1');
      expect(mockResolve).not.toHaveBeenCalled();
    });

    it('Given self-construct with no localized version, When resolved, Then it falls back to the cookie', async () => {
      document.cookie = 'mp_st_variant=v2.1';
      window.wc_mercadopago_supertoken_bundle_params = { self_construct: true };
      await expect(resolveSuperTokenVariant()).resolves.toBe('v2.1');
    });

    it('Given self-construct with neither version nor cookie, When resolved, Then it falls back to v2', async () => {
      window.wc_mercadopago_supertoken_bundle_params = { self_construct: true };
      await expect(resolveSuperTokenVariant()).resolves.toBe('v2');
    });

    it('Given bundle mode (not self-construct), When resolved, Then it delegates to VariantConfigAdapter', async () => {
      mockResolve.mockResolvedValue('v2.1');
      window.wc_mercadopago_supertoken_bundle_params = { self_construct: false };
      await expect(resolveSuperTokenVariant()).resolves.toBe('v2.1');
      expect(mockResolve).toHaveBeenCalledTimes(1);
    });

    it('Given a build-time pin of v2 (legacy v1/ retrocompat bundle), When resolved, Then it returns v2 without runtime A/B', async () => {
      global.__ST_FIXED_VARIANT__ = 'v2';
      // Even with a conflicting cookie and bundle mode set, the pin wins and the adapter is untouched.
      document.cookie = 'mp_st_variant=v2.1';
      window.wc_mercadopago_supertoken_bundle_params = { self_construct: false };
      await expect(resolveSuperTokenVariant()).resolves.toBe('v2');
      expect(mockResolve).not.toHaveBeenCalled();
    });

    it('Given a build-time pin of v2.1 (legacy v2.1/ retrocompat bundle), When resolved, Then it returns v2.1 without runtime A/B', async () => {
      global.__ST_FIXED_VARIANT__ = 'v2.1';
      document.cookie = 'mp_st_variant=v2';
      window.wc_mercadopago_supertoken_bundle_params = { self_construct: false };
      await expect(resolveSuperTokenVariant()).resolves.toBe('v2.1');
      expect(mockResolve).not.toHaveBeenCalled();
    });

    it('Given a build-time pin, When resolved in self-construct, Then the pin still wins (dev override ignored)', async () => {
      global.__ST_FIXED_VARIANT__ = 'v2';
      window.wc_mercadopago_supertoken_bundle_params = { self_construct: true, super_token_version: 'v2.1' };
      await expect(resolveSuperTokenVariant()).resolves.toBe('v2');
      expect(mockResolve).not.toHaveBeenCalled();
    });
  });
});
