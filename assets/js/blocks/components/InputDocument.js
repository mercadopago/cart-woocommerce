const InputDocument = ({
  labelMessage,
  helperMessage,
  inputName,
  hiddenId,
  inputDataCheckout,
  selectId,
  selectName,
  selectDataCheckout,
  flagError,
  documents,
  validate
}) => (
  <div className="mp-checkout-ticket-input-document">
    <input-document
      label-message={labelMessage}
      helper-message={helperMessage}
      input-name={inputName}
      hidden-id={hiddenId}
      input-data-checkout={inputDataCheckout}
      select-id={selectId}
      select-name={selectName}
      select-data-checkout={selectDataCheckout}
      flag-error={flagError}
      documents={documents}
      validate={validate}
    ></input-document>
  </div>

);

export default InputDocument;
