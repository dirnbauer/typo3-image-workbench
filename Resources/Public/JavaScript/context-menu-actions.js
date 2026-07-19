class ImageWorkbenchContextMenuActions {
  static open(table, uid, dataset) {
    const listFrame = top.list_frame;
    const returnUrl = listFrame
      ? encodeURIComponent(listFrame.document.location.pathname + listFrame.document.location.search)
      : '';
    const url = `${dataset.actionUrl}&target=${encodeURIComponent(uid)}&returnUrl=${returnUrl}`;
    top.TYPO3.Backend.ContentContainer.setUrl(url);
  }
}

export default ImageWorkbenchContextMenuActions;
