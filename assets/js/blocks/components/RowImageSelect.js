const RowImageSelect = ({ text, imgSrc, id }) => {
  return (
    <div id={id} className="row-image-select">
      <img src={imgSrc} />
      <span>{text}</span>
    </div>
  );
}

export default RowImageSelect;
