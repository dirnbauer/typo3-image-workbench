import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';

class ImageWorkbench {
  constructor() {
    this.root = document.getElementById('image-workbench');
    this.editorElement = document.getElementById('image-workbench-editor');
    if (!this.root || !this.editorElement || typeof window.FilerobotImageEditor !== 'function') return;

    this.config = this.parseConfig();
    this.target = this.root.dataset.target;
    this.fileName = this.root.dataset.filename || 'image';
    this.extension = (this.root.dataset.extension || 'png').toLowerCase();
    this.returnUrl = this.root.dataset.returnUrl ? decodeURIComponent(this.root.dataset.returnUrl) : '';
    this.renderEditor();
    this.bindAi();
  }

  parseConfig() {
    try { return JSON.parse(this.root.dataset.config || '{}'); } catch { return {}; }
  }

  renderEditor() {
    const FIE = window.FilerobotImageEditor;
    const tabMap = {
      adjust: FIE.TABS.ADJUST,
      finetune: FIE.TABS.FINETUNE,
      filters: FIE.TABS.FILTERS,
      annotate: FIE.TABS.ANNOTATE,
      resize: FIE.TABS.RESIZE,
      watermark: FIE.TABS.WATERMARK,
    };
    const tabs = (this.config.tabs || []).map((tab) => tabMap[tab]).filter(Boolean);
    const editor = new FIE(this.editorElement, {
      source: this.root.dataset.source,
      defaultSavedImageName: this.fileName.replace(/\.[^.]+$/, ''),
      defaultSavedImageType: { jpg: 'jpeg', jpeg: 'jpeg', png: 'png', webp: 'webp' }[this.extension],
      tabsIds: tabs.length ? tabs : undefined,
      onSave: (imageData) => this.save(imageData.imageBase64),
      onClose: () => this.close(),
    });
    this.editor = editor;
    editor.render({ onClose: () => this.close() });
  }

  async save(image) {
    const proposed = window.prompt('Dateiname der neuen Kopie', this.fileName.replace(/\.[^.]+$/, '') + '-bearbeitet');
    if (!proposed) return;
    const overwrite = proposed.replace(/\.[^.]+$/, '') === this.fileName.replace(/\.[^.]+$/, '')
      && window.confirm('Original wirklich überschreiben? Abbrechen speichert eine Kopie.');
    try {
      const response = await new AjaxRequest(TYPO3.settings.ajaxUrls.image_workbench_save).post({
        target: this.target,
        mode: overwrite ? 'overwrite' : 'copy',
        filename: proposed,
        image,
      });
      const data = await response.resolve();
      if (!data.success) throw new Error(data.message || 'Speichern fehlgeschlagen.');
      Notification.success('Bild gespeichert', data.file.name, 4);
      if (this.returnUrl) window.location.href = this.returnUrl;
    } catch (error) {
      Notification.error('Bild konnte nicht gespeichert werden', error.message || '', 8);
    }
  }

  bindAi() {
    const button = document.getElementById('image-workbench-generate');
    const prompt = document.getElementById('image-workbench-prompt');
    const status = document.getElementById('image-workbench-status');
    if (!button || !prompt || !status) return;
    button.addEventListener('click', async () => {
      const value = prompt.value.trim();
      if (value.length < 10) {
        status.textContent = 'Bitte die Bildidee genauer beschreiben.';
        return;
      }
      button.disabled = true;
      status.textContent = 'Bild wird erzeugt und der KI-Verbrauch wird über nr-llm erfasst …';
      try {
        const response = await new AjaxRequest(TYPO3.settings.ajaxUrls.image_workbench_generate).post({
          target: this.target,
          prompt: value,
          configuration: this.config.ai?.configuration || 'image-workbench',
          size: this.config.ai?.size || '1024x1024',
        });
        const data = await response.resolve();
        if (!data.success) throw new Error(data.message || 'Generierung fehlgeschlagen.');
        status.textContent = `${data.file.name} wurde mit ${data.model} gespeichert.`;
        Notification.success('KI-Bild gespeichert', data.file.name, 5);
      } catch (error) {
        status.textContent = error.message || 'Generierung fehlgeschlagen.';
        Notification.error('KI-Bild konnte nicht erzeugt werden', status.textContent, 8);
      } finally {
        button.disabled = false;
      }
    });
  }

  close() {
    this.editor?.terminate();
    if (this.returnUrl) window.location.href = this.returnUrl;
  }
}

export default new ImageWorkbench();
