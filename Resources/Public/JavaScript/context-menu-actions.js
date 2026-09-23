/**
 * Context-menu callback for "Edit image": opens the workbench in the content
 * frame and returns to the listing the editor came from, like the Core's
 * "Edit" action for text files.
 */
class ImageWorkbenchContextMenuActions {
  static open(table, uid, dataset) {
    const url = new URL(dataset.actionUrl, window.location.origin);
    url.searchParams.set('target', uid);
    const listFrame = top.list_frame;
    if (listFrame) {
      url.searchParams.set('returnUrl', listFrame.document.location.pathname + listFrame.document.location.search);
    }
    top.TYPO3.Backend.ContentContainer.setUrl(url.toString());
  }
}

export default ImageWorkbenchContextMenuActions;
