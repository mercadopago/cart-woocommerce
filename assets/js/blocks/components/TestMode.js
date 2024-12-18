const TestMode = ({ title, description, linkText, linkSrc }) => (
  <div className="mp-test-mode-container">
    <test-mode title={title} description={description} link-text={linkText} link-src={linkSrc} />
  </div>
);

export default TestMode;
