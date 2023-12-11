const TermsAndConditions = ({ description, linkText, linkSrc, checkoutClass='pro' }) => (
  <div className={`mp-checkout-${checkoutClass}-terms-and-conditions`}>
    <terms-and-conditions description={description} link-text={linkText} link-src={linkSrc} />
  </div>
);

export default TermsAndConditions;
