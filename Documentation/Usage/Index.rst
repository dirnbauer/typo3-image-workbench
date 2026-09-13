..  include:: /Includes.rst.txt

..  _usage:

=====
Usage
=====

..  _usage-open:

Opening the editor
==================

In :guilabel:`File > Filelist`, right-click an image and choose
:guilabel:`Image Workbench`. The entry appears for ``jpg``, ``jpeg``,
``png`` and ``webp`` files the backend user may write.

The editor opens full-page, with a *Back* link to where you came from.

..  _usage-edit:

Editing and saving
==================

Work in the tabs the group is configured for — adjust, fine-tune,
filters, annotate, resize — then save.

..  list-table::
    :header-rows: 1

    *   -   Save mode
        -   What happens

    *   -   Copy *(default)*
        -   A new file is written next to the original. The name comes
            from the filename field, sanitised by the storage; a
            collision is resolved by renaming, never by overwriting.

    *   -   Overwrite
        -   The original file's contents are replaced and every processed
            file derived from it is deleted, so thumbnails and crops are
            regenerated. This needs an explicit confirmation, and write
            permission on the file itself.

Whatever the canvas produced is re-encoded to match the extension it is
saved under, so a PNG canvas saved over a :file:`.jpg` really becomes
JPEG. Data that is not a supported image is rejected before anything is
written.

..  _usage-generate:

Generating an alternative
=========================

The panel next to the editor takes a prompt between 10 and 8,000
characters. The nr-llm configuration's system prompt is prepended, the
image is generated at the configured size, and the result is saved as a
**new PNG** next to the source image — generation never overwrites and
never touches the original.

What is sent: the prompt, and the prompt only. The image being edited
stays on the server.

What is recorded: the configuration identifier travels with the request,
so nr-llm books the provider usage and the cost against it. The response
names the model that ran and the configuration it was booked to.

..  _usage-errors:

When something fails
====================

..  list-table::
    :header-rows: 1

    *   -   What you see
        -   What it means

    *   -   The context-menu entry is missing
        -   The extension is not one of the four supported, the folder is
            not writable for this user, or ``enable`` is off for the
            group.

    *   -   *This image cannot be edited*
        -   The editor route was reached for a file that fails the same
            three checks.

    *   -   *Invalid image data*
        -   The browser sent something that is not a data URL, or not an
            image. Nothing was written.

    *   -   *Unsupported image data*
        -   The payload decoded, but is not JPEG, PNG or WebP. Nothing
            was written.

    *   -   A provider message on generation
        -   The nr-llm configuration is missing, inactive, out of budget
            or the provider failed. Manual editing is unaffected.
