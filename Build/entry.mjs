/**
 * The vendor bundle: Filerobot's React image editor behind a small class,
 * published as an ES module in the backend importmap. React, Konva and the
 * editor's UI kit stay inside this file; nothing leaks onto `window`.
 */
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import AssemblyPoint, { TABS, TOOLS } from 'react-filerobot-image-editor';
import defaultTranslations from 'react-filerobot-image-editor/lib/context/defaultTranslations';

export { TABS, TOOLS };

/** Every label the editor renders; the page resolves each one from the backend labels. */
export const translationKeys = Object.freeze(Object.keys(defaultTranslations));

export default class ImageEditor {
  #root;
  #config;
  #imageData = {};

  constructor(container, config) {
    this.#root = createRoot(container);
    this.#config = config;
  }

  /** Renders the editor; later calls update the configuration, e.g. the theme. */
  render(changes = {}) {
    this.#config = { ...this.#config, ...changes };
    this.#root.render(createElement(AssemblyPoint, { ...this.#config, getCurrentImgDataFnRef: this.#imageData }));
  }

  /**
   * The current canvas with every operation applied, or null while the
   * editor has not rendered yet.
   */
  getCurrentImgData(fileInfo = {}, pixelRatio = false, keepLoadingSpinnerShown = false) {
    return this.#imageData.current?.(fileInfo, pixelRatio, keepLoadingSpinnerShown) ?? null;
  }

  terminate() {
    this.#root.unmount();
  }
}
