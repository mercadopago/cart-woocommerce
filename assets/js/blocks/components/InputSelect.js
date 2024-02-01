const InputSelect = ({ name, label, optional, options, helperMessage, hiddenId, defaultOption }) => (
  <input-select
    name={name}
    label={label}
    options={options}
    optional={optional}
    helper-message={helperMessage}
    hidden-id={hiddenId}
    default-option={defaultOption}
  >
  </input-select>
);

export default InputSelect;
