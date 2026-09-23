..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

An editor who needs a differently cropped image today leaves TYPO3, opens
a desktop tool, re-uploads and hopes nobody was using the old file. Image
Workbench keeps that work inside the backend, and keeps it honest about
what it writes.

..  _introduction-editing:

Manual editing
==============

:guilabel:`Edit image` appears in the file list — in the context menu
and among the actions of each row — for JPEG, PNG and WebP files the
editor may write. It opens a full-page editor —
`Filerobot Image Editor <https://github.com/scaleflex/filerobot-image-editor>`__,
open source, running entirely in the browser — with tabs for adjusting,
fine-tuning, filtering, annotating and resizing.

The editor speaks the backend language of the editor (English and German
ship with the extension) and takes its colours from the backend tokens,
so it follows the light and the dark colour scheme.

Editing is non-destructive by default: :guilabel:`Save as copy` in the
document header writes a new file next to the original.
:guilabel:`Overwrite original` is there too, behind a confirmation, and
it purges the processed-file cache so the new version actually shows up
everywhere. Leaving with unsaved edits — through :guilabel:`Close`, the
module menu or the page tree — asks first.

..  _introduction-ai:

Generating an alternative
=========================

The panel beside the editor generates a new visual from a prompt. That
goes through the nr-llm image configuration, so the model, the system
prompt, the usage tracker, the budget rules and the cost analytics are
the ones the installation already governs centrally — and the spend is
booked against the backend user who clicked. The result is always saved
as a **new PNG next to the source image** — generation never overwrites.

..  _introduction-boundaries:

What it does not do
===================

*   It never sends the image itself to an LLM. Only the prompt is sent.
*   It never asks an editor for provider credentials. Those live in
    nr-llm and nr-vault.
*   It never lets the page choose what to bill: the nr-llm configuration
    comes from the user TSconfig of the editor's group, on the server.
*   It never bypasses FAL. Every read and every write goes through the
    permission checks of the storage, and files outside the storages
    (the fallback storage) are refused outright.
*   It never silently mislabels a file. The editor's canvas hands back
    PNG whatever the source was; the binary is re-encoded to match the
    extension it will be written under.
*   It never calls out of the backend. The editor's own translation
    service is switched off; its labels are backend labels.
*   It is TYPO3 v14 only, deliberately. No compatibility layers.

..  _introduction-prior-art:

Prior art
=========

Georg Ringer's `image-editor <https://github.com/georgringer/image-editor>`__
established the clean FAL and context-menu approach this extension takes
its interaction ideas from. Image Workbench is a separate implementation
that narrows support to v14 and adds centrally governed AI generation —
and it credits that prior art.
