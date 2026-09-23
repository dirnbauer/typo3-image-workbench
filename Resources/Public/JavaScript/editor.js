/**
 * Image Workbench: the editor page.
 *
 * Mounts the Filerobot editor into the module body, wires the save actions
 * in the document header, guards unsaved edits against navigation, and runs
 * the AI generation panel. Everything visible is a backend label and a core
 * component; the editor itself is themed from the backend colour tokens.
 */
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';
import Modal from '@typo3/backend/modal.js';
import ImmediateAction from '@typo3/backend/action-button/immediate-action.js';
import { SeverityEnum } from '@typo3/backend/enum/severity.js';
import '@typo3/backend/element/icon-element.js';
import { html, render } from 'lit';
import labels from '~labels/image_workbench.messages';
import editorLabels from '~labels/image_workbench.editor';
import ImageEditor, { TABS, translationKeys } from '@webconsulting/image-workbench/Vendor/filerobot-image-editor.js';
import { resolveTheme, watchColorScheme } from '@webconsulting/image-workbench/theme.js';

const TAB_IDS = {
  adjust: TABS.ADJUST,
  finetune: TABS.FINETUNE,
  filters: TABS.FILTERS,
  annotate: TABS.ANNOTATE,
  resize: TABS.RESIZE,
  watermark: TABS.WATERMARK,
};

/** Canvas export type per file extension; the server re-encodes anything else. */
const EXPORT_TYPES = { jpg: 'jpeg', jpeg: 'jpeg', png: 'png', webp: 'webp' };

/** Navigation requests of the backend viewport that would unload this page. */
const LEAVING_REQUESTS = ['typo3.setUrl', 'typo3.beforeSetUrl', 'typo3.refresh'];

/**
 * POSTs to an AJAX route and always resolves to the JSON the route sent,
 * also for 4xx/5xx answers, or to a network error in the same shape.
 *
 * @returns {Promise<{success: boolean, message: string, detail?: string, file?: object, model?: string}>}
 */
async function post(route, data) {
  try {
    const response = await new AjaxRequest(TYPO3.settings.ajaxUrls[route]).post(data);
    return await response.resolve('json');
  } catch (error) {
    if (typeof error?.resolve === 'function') {
      try {
        const body = await error.resolve('json');
        if (body && typeof body.message === 'string') {
          return { success: false, ...body };
        }
      } catch {
        // Not JSON: fall through to the generic message.
      }
    }
    return { success: false, message: labels.get('error.network') };
  }
}

/**
 * The Core's invalid state: `.has-error` on the form group plus aria-invalid.
 *
 * @param {HTMLElement|null} control
 * @param {boolean} invalid
 */
function markInvalid(control, invalid) {
  if (!control) {
    return;
  }
  control.toggleAttribute('aria-invalid', invalid);
  control.closest('.form-group')?.classList.toggle('has-error', invalid);
}

class ImageWorkbench {
  /** @param {HTMLElement} root */
  constructor(root) {
    this.root = root;
    this.container = root.querySelector('[data-image-workbench-editor]');
    this.config = this.parseConfig(root.dataset.config);
    this.target = root.dataset.target ?? '';
    this.fileName = root.dataset.filename ?? 'image';
    this.baseName = this.fileName.replace(/\.[^.]+$/, '');
    this.extension = (root.dataset.extension ?? 'png').toLowerCase();
    this.returnUrl = root.dataset.returnUrl ?? '';
    this.actions = [...document.querySelectorAll('[data-image-workbench-action]')];
    this.dirty = false;
    this.busy = false;

    this.bindDocumentHeader();
    this.guardNavigation();
    this.bindGenerator();
    this.mount();
  }

  parseConfig(json) {
    try {
      return JSON.parse(json ?? '{}');
    } catch {
      return {};
    }
  }

  async mount() {
    let source;
    try {
      // Load the pixels first: a missing or broken image becomes a clear
      // message instead of an empty canvas.
      source = new Image();
      source.src = this.root.dataset.source ?? '';
      await source.decode();
    } catch {
      this.showLoadFailure();
      return;
    }

    const tabs = (this.config.tabs ?? []).map((tab) => TAB_IDS[tab]).filter(Boolean);
    this.editor = new ImageEditor(this.container, {
      source,
      tabsIds: tabs.length ? tabs : undefined,
      defaultTabId: tabs[0],
      defaultSavedImageName: this.baseName,
      defaultSavedImageType: EXPORT_TYPES[this.extension] ?? 'png',
      removeSaveButton: true,
      showBackButton: false,
      // Labels come from the backend; never from the vendor's translation server.
      useBackendTranslations: false,
      translations: this.editorTranslations(),
      language: document.documentElement.lang || 'en',
      avoidChangesNotSavedAlertOnLeave: true,
      observePluginContainerSize: true,
      savingPixelRatio: 1,
      theme: resolveTheme(this.container),
      Crop: { presetsItems: this.cropPresets() },
      onModify: () => {
        this.dirty = true;
      },
    });
    this.editor.render();
    this.stopWatchingScheme = watchColorScheme(() => this.editor.render({ theme: resolveTheme(this.container) }));
    this.setActionsEnabled(true);
  }

  editorTranslations() {
    const translations = {};
    for (const key of translationKeys) {
      try {
        translations[key] = editorLabels.get(key);
      } catch {
        // A label the XLIFF file does not know keeps its English default.
      }
    }
    return translations;
  }

  cropPresets() {
    return (this.config.cropPresets ?? []).map(({ label, width, height }) => ({
      titleKey: label,
      descriptionKey: `${width}:${height}`,
      ratio: width / height,
    }));
  }

  showLoadFailure() {
    render(
      html`<div class="callout callout-danger" role="alert">
        <div class="callout-content">
          <div class="callout-body">${labels.get('editor.loadFailed')}</div>
        </div>
      </div>`,
      this.container,
    );
  }

  setActionsEnabled(enabled) {
    for (const button of this.actions) {
      button.disabled = !enabled;
    }
  }

  bindDocumentHeader() {
    for (const button of this.actions) {
      button.addEventListener('click', () => {
        if (button.dataset.imageWorkbenchAction === 'overwrite') {
          this.confirmOverwrite();
        } else {
          this.askForCopyName();
        }
      });
    }

    document.querySelector('[data-image-workbench-close]')?.addEventListener('click', async (event) => {
      if (!this.dirty) {
        return;
      }
      event.preventDefault();
      if (await this.confirmDiscard()) {
        this.leave(event.currentTarget.href);
      }
    });
  }

  /**
   * Leaving through the module menu, the page tree or a reload asks first
   * while there are unsaved edits, the same way FormEngine does.
   */
  guardNavigation() {
    const scope = top.TYPO3?.Backend?.consumerScope;
    if (!scope) {
      return;
    }
    const consumer = {
      consume: (request) => {
        if (!this.dirty || !request.concernsTypes(LEAVING_REQUESTS)) {
          return undefined;
        }
        const outer = request.outerMostRequest;
        if (outer.isProcessed()) {
          return outer.getProcessedData().discard ? Promise.resolve() : Promise.reject(new Error('Navigation cancelled'));
        }
        return this.confirmDiscard().then((discard) => {
          outer.setProcessedData({ discard });
          if (!discard) {
            throw new Error('Navigation cancelled');
          }
          this.dirty = false;
        });
      },
    };
    scope.attach(consumer);
    window.addEventListener('pagehide', () => {
      scope.detach(consumer);
      this.stopWatchingScheme?.();
    }, { once: true });
  }

  /** @returns {Promise<boolean>} true when the edits may be thrown away */
  confirmDiscard() {
    return new Promise((resolve) => {
      const modal = Modal.confirm(
        labels.get('close.dialog.title'),
        labels.get('close.dialog.message'),
        SeverityEnum.warning,
        [
          { text: labels.get('close.dialog.keep'), btnClass: 'btn-default', name: 'keep', active: true },
          { text: labels.get('close.dialog.discard'), btnClass: 'btn-warning', name: 'discard' },
        ],
      );
      modal.addEventListener('button.clicked', (event) => {
        resolve(event.target.getAttribute('name') === 'discard');
        modal.hideModal();
      });
      modal.addEventListener('typo3-modal-hidden', () => resolve(false));
    });
  }

  leave(url) {
    this.dirty = false;
    if (url) {
      window.location.href = url;
    }
  }

  askForCopyName() {
    if (this.busy) {
      return;
    }
    const inputId = 'image-workbench-copy-name';
    const suffix = `.${this.extension}`;
    const submit = (modal) => {
      const input = modal.querySelector(`#${inputId}`);
      const name = input?.value.trim() ?? '';
      if (name === '') {
        markInvalid(input, true);
        input?.focus();
        return;
      }
      modal.hideModal();
      this.save('copy', name);
    };

    const modal = Modal.advanced({
      title: labels.get('save.dialog.title'),
      severity: SeverityEnum.notice,
      content: html`
        <form @submit=${(event) => { event.preventDefault(); submit(modal); }}>
          <div class="form-group">
            <label class="form-label" for=${inputId}>${labels.get('save.dialog.filename')}</label>
            <div class="input-group">
              <input
                class="form-control"
                id=${inputId}
                type="text"
                required
                maxlength="200"
                autocomplete="off"
                autofocus
                aria-describedby="${inputId}-help"
                .value=${`${this.baseName}-edited`}
                @input=${(event) => markInvalid(event.target, false)}
              >
              <span class="input-group-text">${suffix}</span>
            </div>
            <div class="form-text" id="${inputId}-help">${labels.get('save.dialog.help')}</div>
          </div>
        </form>`,
      buttons: [
        {
          text: labels.get('dialog.cancel'),
          btnClass: 'btn-default',
          name: 'cancel',
          trigger: (event, currentModal) => currentModal.hideModal(),
        },
        {
          text: labels.get('save.dialog.submit'),
          btnClass: 'btn-primary',
          name: 'save',
          trigger: (event, currentModal) => submit(currentModal),
        },
      ],
    });
    modal.addEventListener('typo3-modal-shown', () => {
      const input = modal.querySelector(`#${inputId}`);
      input?.focus();
      input?.select();
    });
  }

  confirmOverwrite() {
    if (this.busy) {
      return;
    }
    const modal = Modal.confirm(
      labels.get('overwrite.dialog.title'),
      labels.get('overwrite.dialog.message', { name: this.fileName }),
      SeverityEnum.warning,
      [
        { text: labels.get('dialog.cancel'), btnClass: 'btn-default', name: 'cancel', active: true },
        { text: labels.get('overwrite.dialog.submit'), btnClass: 'btn-warning', name: 'overwrite' },
      ],
    );
    modal.addEventListener('button.clicked', (event) => {
      modal.hideModal();
      if (event.target.getAttribute('name') === 'overwrite') {
        this.save('overwrite');
      }
    });
  }

  /**
   * @param {'copy'|'overwrite'} mode
   * @param {string} filename name of the copy, ignored for an overwrite
   */
  async save(mode, filename = '') {
    const exported = this.editor?.getCurrentImgData(
      { name: this.baseName, extension: EXPORT_TYPES[this.extension] ?? 'png', quality: 0.92 },
      1,
    );
    const image = exported?.imageData?.imageBase64;
    if (!image) {
      Notification.error(labels.get('save.failure.title'), labels.get('error.invalidImage'));
      return;
    }

    this.busy = true;
    this.setActionsEnabled(false);
    const result = await post('image_workbench_save', {
      target: this.target,
      mode,
      filename,
      image,
      returnUrl: this.returnUrl,
    });
    this.busy = false;
    this.setActionsEnabled(true);

    if (!result.success) {
      Notification.error(labels.get('save.failure.title'), [result.message, result.detail].filter(Boolean).join(' '));
      return;
    }

    this.dirty = false;
    const actions = mode === 'copy' && result.file?.editUrl
      ? [{ label: labels.get('save.openCopy'), action: new ImmediateAction(() => this.leave(result.file.editUrl)) }]
      : [];
    Notification.success(labels.get('save.success.title'), result.message, 6, actions);
  }

  bindGenerator() {
    const form = this.root.querySelector('[data-image-workbench-generate]');
    if (!form) {
      return;
    }
    const prompt = form.elements.namedItem('prompt');
    const size = form.elements.namedItem('size');
    const submit = form.querySelector('button[type="submit"]');
    const status = this.root.querySelector('[data-image-workbench-status]');
    const minLength = Number(prompt.minLength) || 10;
    const maxLength = Number(prompt.maxLength) || 8000;

    prompt.addEventListener('input', () => markInvalid(prompt, false));

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const value = prompt.value.trim();
      if (value.length < minLength || value.length > maxLength) {
        markInvalid(prompt, true);
        this.showStatus(status, labels.get('error.promptLength', { min: minLength, max: maxLength }), 'danger');
        prompt.focus();
        return;
      }

      submit.disabled = true;
      form.setAttribute('aria-busy', 'true');
      this.showStatus(status, labels.get('ai.generating'), 'progress');

      const result = await post('image_workbench_generate', {
        target: this.target,
        prompt: value,
        size: size?.value ?? '',
        returnUrl: this.returnUrl,
      });

      submit.disabled = false;
      form.removeAttribute('aria-busy');

      if (!result.success) {
        const message = [result.message, result.detail].filter(Boolean).join(' ');
        this.showStatus(status, message, 'danger');
        Notification.error(labels.get('ai.failure.title'), message);
        return;
      }

      this.showStatus(status, result.message, 'success');
      this.addResult(result.file);
      Notification.success(labels.get('ai.success.title'), result.message, 6);
    });
  }

  /**
   * @param {HTMLElement|null} element
   * @param {string} message
   * @param {'progress'|'success'|'danger'} tone
   */
  showStatus(element, message, tone) {
    if (!element) {
      return;
    }
    render(
      html`<p class="image-workbench-status-message image-workbench-status-${tone}">
        ${tone === 'progress' ? html`<typo3-backend-icon identifier="spinner-circle" size="small"></typo3-backend-icon>` : ''}
        <span>${message}</span>
      </p>`,
      element,
    );
  }

  addResult(file) {
    const wrapper = this.root.querySelector('[data-image-workbench-results]');
    const list = this.root.querySelector('[data-image-workbench-result-list]');
    if (!wrapper || !list || !file) {
      return;
    }
    this.results = [file, ...(this.results ?? [])];
    render(
      html`${this.results.map((result) => html`
        <li class="list-group-item image-workbench-result">
          <img src=${result.previewUrl} alt="" width="48" height="48" loading="lazy">
          <span class="image-workbench-result-name">${result.name}</span>
          <a
            class="btn btn-sm btn-default"
            href=${result.editUrl}
            title=${labels.get('ai.openInWorkbench', { name: result.name })}
            @click=${(event) => {
              event.preventDefault();
              this.openGenerated(result.editUrl);
            }}
          >
            <typo3-backend-icon identifier="actions-brush" size="small"></typo3-backend-icon>
            <span class="visually-hidden">${labels.get('ai.openInWorkbench', { name: result.name })}</span>
          </a>
        </li>`)}`,
      list,
    );
    wrapper.hidden = false;
  }

  async openGenerated(url) {
    if (!this.dirty || await this.confirmDiscard()) {
      this.leave(url);
    }
  }
}

const root = document.querySelector('[data-image-workbench]');
if (root instanceof HTMLElement) {
  new ImageWorkbench(root);
}
