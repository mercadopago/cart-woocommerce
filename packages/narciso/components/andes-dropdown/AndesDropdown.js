class AndesDropdown extends HTMLElement {
    constructor() {
      super();
      this.state = {
        isOpen: false,
        selectedItem: null,
        focusedIndex: 0,
        hasFocus: false,
        isValid: true,
        hasInteracted: false,
        taxInfo: null
      };
    }
  
    static get observedAttributes() {
      return ['label', 'placeholder', 'hint', 'required-message', 'disabled', 'items', 'site-id'];
    }
  
    connectedCallback() {
      this.build();
    }

    isIOS() {
      // Modern approach using userAgentData
      if (navigator.userAgentData?.platform) {
          return ['iOS'].includes(navigator.userAgentData.platform);
      }
  
      // Fallback for older browsers
      const userAgent = navigator.userAgent;
      return /iPad|iPhone|iPod/.test(userAgent) || 
         (userAgent.includes("Mac") && "ontouchend" in document);
    }
  
    attributeChangedCallback(name, oldValue, newValue) {
      if (oldValue === newValue) return;
      
      switch (name) {
        case 'disabled':
          this.setDisabled(newValue !== null);
          break;
        case 'items':
          this.setItems(JSON.parse(newValue || '[]'));
          break;
        case 'label':
          this.updateLabel(newValue);
          break;
        case 'hint':
          this.updateHint(newValue);
          break;
        case 'required-message':
          this.setRequiredMessage(newValue);
          break;
        case 'site-id':
          this.updateTaxInfo();
          break;
      }
    }
  
    build() {
      this.classList.add('andes-dropdown', 'andes-dropdown--form');
      
      const wrapper = document.createElement('div');
      wrapper.classList.add('andes-dropdown__wrapper');
  
      const trigger = this.createTrigger();
      const menu = this.createMenu();
      const defaultHelper = this.createDefaultHelper();
      const errorHelper = this.createErrorHelper();
      const taxInfo = this.createTaxInfo();
  
      wrapper.appendChild(trigger);
      wrapper.appendChild(menu);
  
      const label = this.createLabel();
      this.appendChild(label);
      this.appendChild(wrapper);
      this.appendChild(defaultHelper);
      this.appendChild(errorHelper);
      this.appendChild(taxInfo);

      this.trigger = trigger;
      this.menu = menu;
      this.defaultHelper = defaultHelper;
      this.errorHelper = errorHelper;
      this.taxInfo = taxInfo;
      this.itemsContainer = menu.querySelector('.andes-dropdown__items');
  
      this.setupEventListeners();
      
      if (this.itemsContainer) {
        this.renderItems();
      }
    }
  
    createLabel() {
      const label = document.createElement('label');
      label.className = 'andes-dropdown__label';
      label.textContent = this.getAttribute('label') || '';
      label.setAttribute('tabindex', '0');
      label.setAttribute('for', this.getAttribute('id') || 'andes-dropdown');
      return label;
    }
  
    createTrigger() {
      const trigger = document.createElement('button');
      trigger.type = 'button';
      trigger.className = 'andes-dropdown__trigger';
      trigger.setAttribute('aria-haspopup', 'listbox');
      trigger.setAttribute('aria-expanded', 'false');
      trigger.setAttribute('aria-controls', 'andes-dropdown-menu');
      trigger.setAttribute('data-placeholder', !this.state.selectedItem);
  
      const selectedText = document.createElement('span');
      selectedText.className = 'andes-dropdown__selected-text';
      selectedText.textContent = this.getAttribute('placeholder') || '';
  
      const arrow = document.createElement('span');
      arrow.className = 'andes-dropdown__arrow';
  
      trigger.appendChild(selectedText);
      trigger.appendChild(arrow);
  
      return trigger;
    }
  
    createMenu() {
      const menu = document.createElement('div');
      menu.className = 'andes-dropdown__menu';
      menu.setAttribute('role', 'listbox');
      menu.setAttribute('tabindex', '0');
      menu.setAttribute('aria-required', this.getAttribute('required') || 'false');
      menu.hidden = true;
  
      const items = document.createElement('div');
      items.className = 'andes-dropdown__items';
  
      menu.appendChild(items);
      return menu;
    }
  
    createDefaultHelper() {
      const helper = document.createElement('input-helper');
      helper.setAttribute('input-id', `${this.getAttribute('id') || 'andes-dropdown'}-default`);
      helper.setAttribute('message', this.getAttribute('hint') || '');
      helper.setAttribute('type', 'message');
      helper.setAttribute('isVisible', this.getAttribute('hint') ? 'true' : 'false');
      return helper;
    }
  
    createErrorHelper() {
      const helper = document.createElement('input-helper');
      helper.setAttribute('input-id', `${this.getAttribute('id') || 'andes-dropdown'}-error`);
      helper.setAttribute('message', this.getAttribute('required-message') || '');
      helper.setAttribute('type', 'error');
      helper.setAttribute('isVisible', 'false');
      return helper;
    }
  
    createTaxInfo() {
      const taxInfo = document.createElement('div');
      taxInfo.className = 'andes-dropdown__tax-info';
      taxInfo.style.display = 'none';
      return taxInfo;
    }
  
    setupEventListeners() {
      this.trigger.addEventListener('click', () => this.toggleMenu());
      this.trigger.addEventListener('keydown', (e) => this.handleKeyDown(e));
      this.trigger.addEventListener('keyup', (e) => this.handleKeyUp(e));
      this.trigger.addEventListener('focus', () => this.handleFocus());
      this.trigger.addEventListener('blur', () => this.handleBlur());
      
      // Add mousedown listener to the menu to prevent closing when clicking inside
      this.menu.addEventListener('mousedown', (e) => {
        e.preventDefault();
        e.stopPropagation();
      });
      
      // Use mousedown on document to handle outside clicks
      document.addEventListener('mousedown', (e) => this.handleOutsideClick(e));
    }
  
    renderItems() {
      if (!this.itemsContainer) return;
      
      this.itemsContainer.innerHTML = '';
      const items = JSON.parse(this.getAttribute('items') || '[]');
      
      items.forEach((item, idx) => {
        const itemElement = document.createElement('div');
        itemElement.className = 'andes-dropdown__item';
        itemElement.setAttribute('role', 'option');
        itemElement.setAttribute('aria-selected', this.state.selectedItem && this.state.selectedItem.value === item.value);
        itemElement.setAttribute('tabindex', '0');
        itemElement.setAttribute('aria-label', item.title);
        itemElement.textContent = item.title;
  
        if (item.disabled) {
          itemElement.classList.add('andes-dropdown__item--disabled');
        }
  
        if (this.state.selectedItem && this.state.selectedItem.value === item.value) {
          itemElement.classList.add('andes-dropdown__item--selected');
        }
  
        if (this.state.focusedIndex === idx && this.state.isOpen) {
          itemElement.classList.add('andes-dropdown__item--focused');
          itemElement.setAttribute('aria-selected', 'true');
          this.trigger.setAttribute('aria-activedescendant', `andes-dropdown-item-${idx}`);
          itemElement.id = `andes-dropdown-item-${idx}`;
        } else {
          itemElement.setAttribute('aria-selected', 'false');
          itemElement.removeAttribute('id');
        }
  
        if (!item.disabled) {
          itemElement.addEventListener('mousedown', (e) => {
            e.preventDefault();
            this.selectItem(item, idx);
          });
  
          itemElement.addEventListener('mouseenter', (e) => {
            this.state.focusedIndex = idx;

            if (this.isIOS()) {
              // Timeout to avoid the item being selected when the user is scrolling
              setTimeout(() => this.selectItem(item, idx), 300);
            }

            this.renderItems();
          });
        }
  
        this.itemsContainer.appendChild(itemElement);
      });
    }
  
    toggleMenu() {
      if (this.getAttribute('disabled') !== null) return;
      this.state.isOpen = !this.state.isOpen;
      this.updateMenuState();
      
      if (this.state.isOpen) {
        this.state.hasInteracted = true;
      }
    }
  
    updateMenuState() {
      if (this.state.isOpen) {
        this.menu.hidden = false;
        this.classList.add('andes-dropdown--open');
        this.trigger.setAttribute('aria-expanded', 'true');
        this.dispatchEvent(new CustomEvent('mp-open-dropdown'));
      } else {
        this.menu.hidden = true;
        this.classList.remove('andes-dropdown--open');
        this.trigger.setAttribute('aria-expanded', 'false');
        this.dispatchEvent(new CustomEvent('mp-close-dropdown'));
      }
      this.renderItems();
    }
  
    selectItem(item, idx) {
      if (item.disabled) return;
      this.state.selectedItem = item;
      this.state.focusedIndex = idx;
      this.state.isOpen = false;
      this.updateSelectedText();
      this.updateMenuState();
      this.updateTaxInfo();
      this.validate();
      this.dispatchEvent(new CustomEvent('change', { detail: item }));
    }
  
    updateSelectedText() {
      const selectedText = this.trigger.querySelector('.andes-dropdown__selected-text');
      if (!selectedText) return;
  
      if (!this.state.selectedItem) {
        selectedText.textContent = this.getAttribute('placeholder') || '';
        this.trigger.setAttribute('data-placeholder', 'true');
      } else {
        selectedText.textContent = this.state.selectedItem.title;
        this.trigger.setAttribute('data-placeholder', 'false');
      }
    }
  
    handleOutsideClick(event) {
      // Only close if clicking outside both the trigger and menu
      if (!this.trigger.contains(event.target) && !this.menu.contains(event.target)) {
        this.state.isOpen = false;
        this.updateMenuState();
      }
    }
  
    handleKeyDown(event) {
      if (!this.state.isOpen && (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ')) {
        event.preventDefault();
        this.state.isOpen = true;
        this.updateMenuState();
        return;
      }
  
      if (!this.state.isOpen) return;
  
      const items = JSON.parse(this.getAttribute('items') || '[]');

      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        const item = items[this.state.focusedIndex];
        if (item && !item.disabled) {
          this.selectItem(item, this.state.focusedIndex);
        }
      } else if (event.key === 'Tab') {
        event.preventDefault();
      } else if (event.key === 'Escape' || event.key === 'Backspace') {
          event.preventDefault();
          this.state.isOpen = false;
          this.state.hasInteracted = true;
          this.updateMenuState();
          this.validate();
        }
    }

    handleKeyUp(event) {
      if (!this.state.isOpen) return;

      const items = JSON.parse(this.getAttribute('items') || '[]');

      if (event.key === 'Tab' && event.shiftKey) {
        let prev = this.state.focusedIndex - 1;
        while (prev >= 0 && items[prev].disabled) prev--;
        if (prev >= 0) this.state.focusedIndex = prev;
        this.renderItems();
      } else if (event.key === 'Tab' && !event.shiftKey) {
        let next = this.state.focusedIndex + 1;
        while (next < items.length && items[next].disabled) next++;
        if (next < items.length) this.state.focusedIndex = next;
        this.renderItems();
      }
    }
  
    handleFocus() {
      this.state.hasFocus = true;
      if (this.defaultHelper) {
        const helperElement = this.defaultHelper.querySelector('.mp-helper');
        if (helperElement) {
          helperElement.style.display = 'none';
        }
      }
    }
  
    handleBlur() {
      this.state.hasFocus = false;
      this.state.isOpen = false;
      this.updateMenuState();
      if (this.state.hasInteracted) {
        this.validate();
      }
    }
  
    setItems(items) {
      this.setAttribute('items', JSON.stringify(items));
      this.renderItems();
    }
  
    setDisabled(disabled) {
      if (disabled) {
        this.setAttribute('disabled', '');
      } else {
        this.removeAttribute('disabled');
      }
      this.classList.toggle('andes-dropdown--disabled', disabled);
    }
  
    updateLabel(label) {
      const labelElement = this.querySelector('.andes-dropdown__label');
      if (labelElement) {
        labelElement.textContent = label || '';
      }
    }
  
    updateHint(hint) {
      if (this.defaultHelper) {
        this.defaultHelper.setAttribute('message', hint || '');
        const hintElement = this.defaultHelper.querySelector('.mp-helper');
        if (hintElement) {
          hintElement.style.display = hint ? 'flex' : 'none';
        }
      }
    }
  
    setRequiredMessage(message) {
      if (this.errorHelper) {
        this.errorHelper.setAttribute('message', message || '');
      }
    }
  
    setErrorMessage(message) {
      if (this.errorHelper) {
        this.errorHelper.setAttribute('message', message || '');
        const errorElement = this.errorHelper.querySelector('.mp-helper');
        if (errorElement) {
          errorElement.style.display = 'flex';
          errorElement.setAttribute('tabindex', '0');
          errorElement.setAttribute('aria-label', message || '');
        }
        // Hide hint when showing error
        if (this.defaultHelper) {
          const hintElement = this.defaultHelper.querySelector('.mp-helper');
          if (hintElement) {
            hintElement.style.display = 'none';
            hintElement.setAttribute('tabindex', '-1');
            hintElement.setAttribute('aria-label', '');
          }
        }
        this.classList.add('andes-dropdown--error');
      }
    }
  
    hideErrorMessage() {
      if (this.errorHelper) {
        const errorElement = this.errorHelper.querySelector('.mp-helper');
        if (errorElement) {
          errorElement.style.display = 'none';
        }
      }
      // Show hint again
      if (this.defaultHelper) {
        const hintElement = this.defaultHelper.querySelector('.mp-helper');
        if (hintElement) {
          hintElement.style.display = 'flex';
        }
      }
      this.classList.remove('andes-dropdown--error');
    }
  
    validate() {
      const hasRequiredMessage = this.getAttribute('required-message') !== null;
      const isValid = !hasRequiredMessage || (hasRequiredMessage && this.state.selectedItem !== null);
      
      this.state.isValid = isValid;
      
      if (this.state.hasInteracted && !this.state.isOpen) {
        if (!isValid) {
          this.setErrorMessage(this.getAttribute('required-message'));
        } else {
          this.hideErrorMessage();
        }
      } else {
        this.hideErrorMessage();
      }
      
      return isValid;
    }
  
    getValue() {
      return this.state.selectedItem ? this.state.selectedItem.value : null;
    }
  
    updateTaxInfo() {
      if (!this.taxInfo) return;
      
      const siteId = this.getAttribute('site-id');
      if (siteId?.toLowerCase() === 'mla' && this.state.selectedItem?.taxInfo) {
        const { cft, tna, tea } = this.state.selectedItem.taxInfo;
        const installmentNumber = parseInt(this.state.selectedItem.value, 10);

        // Only show tax info for credit cards with 2 or more installments
        if (installmentNumber >= 2) {
          const taxes = [];

          if (cft) {
            taxes.push(`<b>CFTEA: ${cft}%</b>`);
          }

          if (tna) {
            taxes.push(`TNA: ${tna}%`);
          }
          
          if (tea) {
            taxes.push(`TEA: ${tea}%`);
          }

          if (!taxes.length) {
            this.taxInfo.style.display = 'none';
            return;
          }
          
          this.taxInfo.style.display = 'block';
          this.taxInfo.innerHTML = taxes.join(' - ').concat('. Tasa fija.');
        } else {
          this.taxInfo.style.display = 'none';
        }
      } else {
        this.taxInfo.style.display = 'none';
      }
    }
}

customElements.define('andes-dropdown', AndesDropdown);
