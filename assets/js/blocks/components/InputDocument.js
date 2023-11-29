const InputDocument = ({
  labelMessage,
  helperMessage,
  inputName,
  selectName,
  flagError,
  documents,
  validate,
}) => (
  <div className="mp-checkout-ticket-input-document">
    <input-document
      label-message={labelMessage}
      helper-message={helperMessage}
      input-name={inputName}
      select-name={selectName}
      flag-error={flagError}
      documents={documents}
      validate={validate}
    ></input-document>
  </div>

);

export default InputDocument;
