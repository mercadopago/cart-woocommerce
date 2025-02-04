const RowImageSelect = ({ text, imgSrc, id }) => {
  return (
    <div id={id} className="row-image-select">
      <img src={imgSrc} />
      <p>{text}</p>
    </div>
  );
}

export default RowImageSelect;
