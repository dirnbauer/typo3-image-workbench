..  include:: /Includes.rst.txt

..  _usage:

=====
Usage
=====

..  _usage-open:

Opening the editor
==================

In the :guilabel:`Media` module, choose :guilabel:`Edit image` among the
actions of an image row, or right-click the image and choose it from the
context menu. The entry appears for ``jpg``, ``jpeg``, ``png`` and
``webp`` files the backend user may write.

The editor opens full-page. :guilabel:`Close` in the document header
returns to the folder you came from.

..  _usage-edit:

Editing and saving
==================

Work in the tabs the group is configured for — adjust, fine-tune,
filters, annotate, resize — then save from the document header.

..  list-table::
    :header-rows: 1

    *   -   Action
        -   What happens

    *   -   :guilabel:`Save as copy`
        -   Asks for a file name and writes a new file next to the
            original. The name is sanitised by the storage; a collision
            is resolved by adding a number, never by overwriting. The
            confirmation offers to open the copy in the workbench.

    *   -   :guilabel:`Overwrite original`
        -   After a confirmation, the original file's contents are
            replaced and every processed file derived from it is
            deleted, so thumbnails and crops are regenerated. This needs
            write permission on the file itself.

Both actions stay disabled until the image has loaded. Whatever the
canvas produced is re-encoded to match the extension it is saved under,
so a PNG canvas saved over a :file:`.jpg` really becomes JPEG. Data that
is not a supported image is rejected before anything is written.

Unsaved edits are protected: :guilabel:`Close`, the module menu, the
page tree and a reload of the module ask before they discard them.

..  _usage-generate:

Generating an alternative
=========================

The panel beside the editor takes a description between 10 and 8,000
characters and a size from the list the configured model supports. The
nr-llm configuration's system prompt is prepended, the image is
generated, and the result is saved as a **new PNG** next to the source
image — generation never overwrites and never touches the original.
Every generated file is listed below the form with a thumbnail and a
link that opens it in the workbench.

What is sent: the prompt, and the prompt only. The image being edited
stays on the server.

What is recorded: the configuration identifier and the backend user
travel with the request, so nr-llm enforces the user's budget and books
the provider usage and the cost against both. The footer of the panel
names the configuration and the model.

..  _usage-errors:

When something fails
====================

..  list-table::
    :header-rows: 1

    *   -   What you see
        -   What it means

    *   -   :guilabel:`Edit image` is missing
        -   The extension is not one of the four supported, the file is
            not writable for this user, or ``enable`` is off for the
            group.

    *   -   *This image cannot be edited*
        -   The editor was opened for a file that fails the same checks,
            or that no longer exists.

    *   -   *The editor sent no usable image data*
        -   The browser sent something that is not an image data URL.
            Nothing was written.

    *   -   *Saving the image failed*
        -   The storage refused the write; the message names the reason.

    *   -   *Your AI budget is used up*
        -   nr-llm's budget rules stopped the request. Manual editing is
            unaffected.

    *   -   *The description was rejected by the content policy*
        -   nr-llm's prompt guardrails refused the description.

    *   -   *The image could not be generated*
        -   The provider failed. Administrators see the provider's
            message; it is also written to the TYPO3 log.
