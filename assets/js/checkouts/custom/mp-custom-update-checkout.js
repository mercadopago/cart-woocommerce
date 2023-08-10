var form = document.querySelector("form[id=checkout]");

if (form) {
  jQuery(document).on("updated_checkout", function () {
    if (cardFormMounted) {
      cardForm.unmount();
    }

    handleCardFormLoad();
  });
}
