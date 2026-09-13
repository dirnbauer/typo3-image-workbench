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

*Image Workbench* appears in the file list's context menu for JPEG, PNG
and WebP files the editor may write. It opens a full-page editor —
`Filerobot Image Editor <https://github.com/scaleflex/filerobot-image-editor>`__,
open source, running entirely in the browser — with tabs for adjusting,
fine-tuning, filtering, annotating and resizing.

Editing is non-destructive by default: the result is saved as a new file
next to the original. Overwriting the original is possible, but it takes
an explicit confirmation, and it purges the processed-file cache so the
new version actually shows up everywhere.

..  _introduction-ai:

Generating an alternative
=========================

The editor can also generate a new visual from a prompt. That goes
through the nr-llm image configuration, so the model, the system prompt,
the usage tracker, the budget rules and the cost analytics are the ones
the installation already governs centrally. The result is always saved as
a **new PNG next to the source image** — generation never overwrites.

..  _introduction-boundaries:

What it does not do
===================

*   It never sends the image itself to an LLM. Only the prompt is sent.
*   It never asks an editor for provider credentials. Those live in
    nr-llm and nr-vault.
*   It never bypasses FAL. Every read and every write goes through the
    permission checks of the storage, and an editor without write
    permission on the folder cannot reach the editor at all.
*   It never silently mislabels a file. The editor's canvas hands back
    PNG whatever the source was; the binary is re-encoded to match the
    extension it will be written under.
*   It is TYPO3 v14 only, deliberately. No compatibility layers.

..  _introduction-prior-art:

Prior art
=========

Georg Ringer's `image-editor <https://github.com/georgringer/image-editor>`__
established the clean FAL and context-menu approach this extension takes
its interaction ideas from. Image Workbench is a separate implementation
that narrows support to v14 and adds centrally governed AI generation —
and it credits that prior art.
