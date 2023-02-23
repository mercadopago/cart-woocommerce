(() => {
    let modal = document.querySelector('#mp-credits-modal');
    let modalContent = document.querySelector('.mp-credits-modal-container-content');
    let modalCentralize = document.querySelector('#mp-credits-centralize');
    let resizeControl;
  
    const setupElements = () => {
      modal || (modal = document.querySelector('#mp-credits-modal'));
      modalContent || (modalContent = document.querySelector('.mp-credits-modal-container-content'));
      modalCentralize || (modalCentralize = document.querySelector('#mp-credits-centralize'));
    }
  
    const switchModal = () => {
      setupElements();
      const actualStyle = modal.style.visibility;
      if (actualStyle == 'visible') {
        modal.style.visibility = 'hidden';
        modalContent.classList.remove('mp-mobile');
        document.dispatchEvent(new CustomEvent('mp-modal-close'));
      } else {
        modal.style.visibility = 'visible';
        modalCentralize.classList.add('mp-credits-modal-content-centralize');
        if (modal.clientWidth < 768) {
          modalCentralize.classList.remove('mp-credits-modal-content-centralize');
          const modalHeight = modal.clientHeight;
          const modalContentHeight = modalContent.clientHeight;
          modalContent.style.top = `${modalHeight - modalContentHeight}.px`;
          modalContent.classList.add('mp-mobile');
        }
      }
    }
  
    window.onclick = function (event) {
      const modal = document.querySelector('.mp-credits-modal-container');
      const openBtn = document.querySelector('#mp-open-modal');
      const closebBtn = document.querySelector('#mp-credits-modal-close-modal');
  
      if (event.target === modal || event.target === openBtn || event.target === closebBtn) {
        switchModal();
      }
    }
  
    window.onresize = () => {
      clearTimeout(resizeControl);
      resizeControl = setTimeout(() => {
        setupElements();
  
        if (modal.clientWidth > 768) {
          modalCentralize.classList.add('mp-credits-modal-content-centralize');
          modalContent.classList.remove('mp-mobile');
        } else {
          modalCentralize.classList.remove('mp-credits-modal-content-centralize');
          modalContent.style.top = `${modal.clientHeight - modalContent.clientHeight}.px`;
          modalContent.classList.add('mp-mobile');
        }
      }, 100);
    }
  })()
  