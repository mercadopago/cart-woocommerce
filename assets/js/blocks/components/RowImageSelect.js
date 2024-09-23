const RowImageSelect = ({text, imgSrc}) => {
    return (
        <div style={
          {
            height: "18px",
            display: "flex",
            justifyContent: "space-between",
            alignContent: "center",
            alignItems: "center",
            flexDirection: "row"
          }
        }>
          <img 
            style={
              {
                marginRight: "8px", 
                height: "25px",
                width: "30px",
                padding: 0
              }
            }
            src={imgSrc} 
          />
          <p style={
            {
              fontFamily: '"Proxima Nova", -apple-system, "Helvetica Neue", Helvetica, "Roboto", Arial, sans-serif', 
              fontSize: "18px",
              padding: 0
            }
          }>{text}</p>
        </div>
      );
}

export default RowImageSelect;