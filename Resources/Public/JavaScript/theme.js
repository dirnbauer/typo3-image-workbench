/**
 * Dresses the image editor in the backend's colour tokens.
 *
 * The tokens resolve through light-dark() and color-mix(), and the editor
 * paints part of its interface on a canvas, where var() means nothing. So
 * every token is resolved on a probe element and read back through a 1×1
 * canvas as a plain rgba() value, and the palette is rebuilt whenever the
 * backend switches between the light and the dark scheme.
 */

const canvas = document.createElement('canvas');
canvas.width = 1;
canvas.height = 1;
const context = canvas.getContext('2d', { willReadFrequently: true });

/**
 * @param {HTMLElement} probe
 * @param {string} value a CSS colour, usually var(--typo3-…)
 * @returns {{r: number, g: number, b: number, a: number}}
 */
function resolve(probe, value) {
  probe.style.color = value;
  const computed = getComputedStyle(probe).color;
  if (!context) {
    return { r: 0, g: 0, b: 0, a: 1 };
  }
  context.clearRect(0, 0, 1, 1);
  context.fillStyle = computed;
  context.fillRect(0, 0, 1, 1);
  const [r, g, b, a] = context.getImageData(0, 0, 1, 1).data;
  return { r, g, b, a: a / 255 };
}

const rgba = ({ r, g, b, a }, alpha = a) => `rgba(${r}, ${g}, ${b}, ${Math.round(alpha * 1000) / 1000})`;

/**
 * The editor theme for the colour scheme currently active around `element`.
 *
 * @param {HTMLElement} element
 */
export function resolveTheme(element) {
  const probe = document.createElement('span');
  probe.hidden = true;
  element.append(probe);

  try {
    const token = (name) => resolve(probe, `var(${name})`);
    const text = token('--typo3-text-color-base');
    const variant = token('--typo3-text-color-variant');
    const surface = token('--typo3-surface-container-lowest');
    const surfaceLow = token('--typo3-surface-container-low');
    const surfaceBase = token('--typo3-surface-container-base');
    const surfaceHigh = token('--typo3-surface-container-high');
    const border = token('--typo3-component-border-color');
    const hover = token('--typo3-component-hover-bg');
    const primary = token('--typo3-state-primary-bg');
    const primaryText = token('--typo3-state-primary-color');

    const palette = {
      'txt-primary': rgba(text),
      'txt-secondary': rgba(variant),
      'txt-secondary-invert': rgba(surface),
      'txt-placeholder': rgba(text, 0.5),
      'txt-warning': rgba(token('--typo3-text-color-warning')),
      'txt-error': rgba(token('--typo3-text-color-danger')),
      'txt-info': rgba(token('--typo3-text-color-info')),

      'accent-primary': rgba(primary),
      'accent-primary-hover': rgba(token('--typo3-state-primary-hover-bg')),
      // Also the text colour of selected tabs and tools (on bg-primary-active)
      // and the pressed fill of primary buttons: the primary text colour
      // stays readable in both roles and both schemes.
      'accent-primary-active': rgba(token('--typo3-text-color-primary')),
      'accent-primary-disabled': rgba(token('--typo3-state-primary-disabled-bg')),
      'accent-secondary-disabled': rgba(surfaceLow),
      'accent-stateless': rgba(primary),
      'accent-stateless_0_4_opacity': rgba(primary, 0.4),
      'accent_0_5_opacity': rgba(primary, 0.05),
      'accent_0_5_5_opacity': rgba(primary, 0.55),
      'accent_0_7_opacity': rgba(primary, 0.7),
      'accent_1_2_opacity': rgba(primary, 0.12),
      'accent_1_8_opacity': rgba(primary, 0.18),
      'accent_2_8_opacity': rgba(primary, 0.28),
      'accent_4_0_opacity': rgba(primary, 0.4),

      'bg-grey': rgba(surfaceHigh),
      'bg-stateless': rgba(surface),
      'bg-active': rgba(surfaceBase),
      'bg-base-light': rgba(primary, 0.06),
      'bg-base-medium': rgba(primary, 0.12),
      'bg-primary': rgba(surfaceLow),
      'bg-primary-light': rgba(surfaceLow),
      'bg-primary-hover': rgba(hover),
      'bg-primary-active': rgba(token('--typo3-surface-container-primary')),
      'bg-primary-stateless': rgba(border),
      'bg-secondary': rgba(surface),
      'bg-hover': rgba(hover),
      'bg-tooltip': rgba(text),

      'icon-primary': rgba(variant),
      'icons-primary-opacity-0-6': rgba(text, 0.6),
      'icons-secondary': rgba(variant),
      'icons-placeholder': rgba(text, 0.25),
      'icons-invert': rgba(surface),
      'icons-muted': rgba(text, 0.45),
      'icons-primary-hover': rgba(text),
      'icons-secondary-hover': rgba(text),

      'btn-primary-text': rgba(primaryText),
      'btn-primary-text-0-6': rgba(primaryText, 0.6),
      'btn-primary-text-0-4': rgba(primaryText, 0.4),
      'btn-disabled-text': rgba(variant),
      'btn-secondary-text': rgba(text),

      'link-primary': rgba(variant),
      'link-stateless': rgba(variant),
      'link-hover': rgba(text),
      'link-active': rgba(text),
      'link-pressed': rgba(primary),
      'link-muted': rgba(text, 0.45),

      'borders-primary': rgba(border),
      'borders-primary-hover': rgba(variant),
      'borders-secondary': rgba(border, 0.6),
      'borders-strong': rgba(border),
      'borders-invert': rgba(variant),
      'border-hover-bottom': rgba(primary, 0.18),
      'border-active-bottom': rgba(primary),
      'border-primary-stateless': rgba(border),
      'borders-disabled': rgba(primary, 0.4),
      'borders-button': rgba(token('--typo3-input-border-color')),
      'borders-item': rgba(border),
      'borders-base-light': rgba(border),
      'borders-base-medium': rgba(border),

      error: rgba(token('--typo3-state-danger-bg')),
      'error-hover': rgba(token('--typo3-state-danger-hover-bg')),
      'error-active': rgba(token('--typo3-state-danger-focus-bg')),
      success: rgba(token('--typo3-state-success-bg')),
      'success-hover': rgba(token('--typo3-state-success-hover-bg')),
      'success-Active': rgba(token('--typo3-state-success-focus-bg')),
      warning: rgba(token('--typo3-state-warning-bg')),
      'warning-hover': rgba(token('--typo3-state-warning-hover-bg')),
      'warning-active': rgba(token('--typo3-state-warning-focus-bg')),
      info: rgba(token('--typo3-state-info-bg')),
    };

    return {
      palette,
      typography: { fontFamily: getComputedStyle(element).fontFamily },
    };
  } finally {
    probe.remove();
  }
}

/**
 * Calls `onChange` whenever the backend switches colour scheme or theme:
 * through the user settings (an attribute on this frame's root element) or
 * through the operating system while the scheme is "auto".
 *
 * @param {() => void} onChange
 * @returns {() => void} stops watching
 */
export function watchColorScheme(onChange) {
  let scheduled = false;
  const notify = () => {
    if (scheduled) {
      return;
    }
    scheduled = true;
    // Let the new tokens apply before they are read.
    requestAnimationFrame(() => {
      scheduled = false;
      onChange();
    });
  };

  const observer = new MutationObserver(notify);
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-color-scheme', 'data-theme'] });
  const media = window.matchMedia('(prefers-color-scheme: dark)');
  media.addEventListener('change', notify);

  return () => {
    observer.disconnect();
    media.removeEventListener('change', notify);
  };
}
