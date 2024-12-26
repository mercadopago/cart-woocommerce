const InputDocument = ({
  labelMessage,
  helperInvalid,
  helperEmpty,
  helperWrong,
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
      helper-invalid={helperInvalid}
      helper-empty={helperEmpty}
      helper-wrong={helperWrong}
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
