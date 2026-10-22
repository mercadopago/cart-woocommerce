const { createAdminRuntime: createRuntime } = require('../helpers/admin-runtime');
const { element, fragment } = require('../fixtures/settings-markup');

const params = {
  computerBlueIcon: '/desktop-blue.svg',
  computerGrayIcon: '/desktop-gray.svg',
  cellphoneBlueIcon: '/mobile-blue.svg',
  cellphoneGrayIcon: '/mobile-gray.svg',
  viewDesktop: '/desktop-example.gif',
  viewMobile: '/mobile-example.gif',
  footerDesktop: 'Desktop example',
  footerCellphone: 'Mobile example',
};
const banner = (selected) =>
  fragment(
    '\n  ',
    element(
      'div',
      { class: `credits-info-example-buttons-child ${selected === 'desktop' ? 'selected' : ''}` },
      '\n    ',
      element('div', { class: 'credits-info-example-badge' }),
      element('img', { class: 'icon-image', alt: 'computer' }),
      '\n  ',
    ),
    '\n  ',
    element(
      'div',
      { class: `credits-info-example-buttons-child ${selected === 'mobile' ? 'selected' : ''}` },
      '\n    ',
      element('div', { class: 'credits-info-example-badge' }),
      element('img', { class: 'icon-image', alt: 'cellphone' }),
      '\n  ',
    ),
    '\n  ',
    element('img', { id: 'gif-image' }),
    element('p', { id: 'credits-info-example-gif-footer' }),
  );

describe.each(['source', 'minified'])('Credits admin preview — real IIFE (%s)', assetVariant => {
  const createAdminRuntime = (markup, globals) => createRuntime(markup, globals, assetVariant);
  let runtime;
  afterEach(() => runtime.cleanup());

  function start(selected) {
    jest.useFakeTimers();
    runtime = createAdminRuntime(banner(selected), { mp_info_admin_credits_script_params: params });
    runtime.load('credits/example-info.js');
  }

  it('does nothing when the banner is not present', () => {
    jest.useFakeTimers();
    runtime = createAdminRuntime(fragment(), { mp_info_admin_credits_script_params: params });
    expect(() => runtime.load('credits/example-info.js')).not.toThrow();
    expect(runtime.requests).toHaveLength(0);
  });

  it('renders the localized desktop assets initially', () => {
    start('desktop');
    expect(document.querySelector('#gif-image').getAttribute('src')).toBe('/desktop-example.gif');
    expect(document.querySelectorAll('.icon-image')[0].getAttribute('src')).toBe('/desktop-blue.svg');
    expect(document.querySelectorAll('.icon-image')[1].getAttribute('src')).toBe('/mobile-gray.svg');
    expect(document.querySelector('#credits-info-example-gif-footer').textContent).toBe('Desktop example');
  });

  it('switches to mobile assets through a real click and keeps only mobile selected', () => {
    start('desktop');
    const buttons = document.querySelectorAll('.credits-info-example-buttons-child');
    buttons[1].click();
    expect(buttons[1]).toHaveClass('selected');
    expect(buttons[0]).not.toHaveClass('selected');
    expect(document.querySelector('#gif-image').getAttribute('src')).toBe('/mobile-example.gif');
    expect(document.querySelectorAll('.icon-image')[1].getAttribute('src')).toBe('/mobile-blue.svg');
    expect(document.querySelectorAll('.icon-image')[0].getAttribute('src')).toBe('/desktop-gray.svg');
    expect(document.querySelector('#credits-info-example-gif-footer').innerText).toBe('Mobile example');
  });

  it('starts on mobile and switches back to desktop, including clicks on nested content', () => {
    start('mobile');
    expect(document.querySelector('#gif-image').getAttribute('src')).toBe('/mobile-example.gif');
    document.querySelector('.credits-info-example-buttons-child img').click();
    expect(document.querySelector('#gif-image').getAttribute('src')).toBe('/desktop-example.gif');
    expect(document.querySelectorAll('.credits-info-example-buttons-child.selected')).toHaveLength(1);
  });

  it.each(['desktop', 'mobile'])('does not rerender when the already selected %s preview is clicked', selected => {
    start(selected);
    const image = document.getElementById('gif-image');
    const writes = jest.spyOn(image, 'setAttribute');
    document.querySelector('.credits-info-example-buttons-child.selected').click();
    expect(writes).not.toHaveBeenCalled();
  });
});
