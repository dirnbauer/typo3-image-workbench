..  include:: /Includes.rst.txt

..  _developer:

===================
Developer reference
===================

..  _developer-routes:

The four routes
===============

..  list-table::
    :header-rows: 1

    *   -   Route
        -   Path
        -   Target

    *   -   ``image_workbench_edit``
        -   :path:`/image-workbench/edit`
        -   :php:`EditorController::edit` — renders the full-page editor

    *   -   ``image_workbench_source``
        -   :path:`/image-workbench/source`
        -   :php:`ImageController::source` — streams the original, with
            ``Cache-Control: private, no-store``

    *   -   ``ajax_image_workbench_save``
        -   :path:`/ajax/image-workbench/save`
        -   :php:`SaveController::save` — POST, copy or overwrite

    *   -   ``ajax_image_workbench_generate``
        -   :path:`/ajax/image-workbench/generate`
        -   :php:`AiImageController::generate` — POST, nr-llm generation

All four inherit their access from the ``media_management`` module, and
all four re-check FAL permissions themselves rather than trusting that.

..  _developer-persistence:

Persistence
===========

:php:`Service\ImagePersistenceService` is the only place that writes.

:php:`saveCopy()` refuses a folder it may not write, converts the binary
to the target extension, sanitises the desired name through the storage
and adds the file with :php:`DuplicationBehavior::RENAME` — so a
collision produces a second file, never a lost one. The temporary file is
removed in a ``finally`` block.

:php:`overwrite()` refuses a file it may not write, replaces the contents
and then deletes every processed file derived from the original, so
thumbnails and crops are regenerated instead of served stale.

:php:`Service\ImageFormatConverter` does the re-encoding, and does
nothing else — binary in, binary out, no FAL, no TYPO3:

..  code-block:: php

    $converter->toExtension($binary, 'jpg');

It sniffs the data, returns it byte for byte when it already matches the
extension, and otherwise decodes it through GD and writes it out again as
PNG (alpha preserved), WebP or JPEG at quality 90. Three failures are
distinguished by exception code: 1752910103 for data that is not a
supported image, 1752910104 for data that will not decode, 1752910105 for
a re-encode that fails.

Being pure is the point: the whole conversion matrix is unit-tested
against real GD binaries, without booting TYPO3.

..  _developer-privacy:

Privacy and billing
===================

*   FAL permissions are checked on every read and every write.
*   The original image is never sent to an LLM. Only the prompt is.
*   The ``configuration`` identifier is attached to
    :php:`ImageGenerationOptions`, so nr-llm records provider usage and
    cost against that configuration.
*   API secrets stay inside nr-llm and nr-vault. The extension never
    sees, stores or logs one.

..  _developer-assets:

The editor bundle
=================

The Filerobot editor is bundled with esbuild and the result is committed,
so an installation needs no Node toolchain:

..  code-block:: bash

    composer assets:install   # npm ci in Build/
    composer assets:build     # node Build/esbuild.mjs

The build also rewrites ``border-radius:4px`` to ``border-radius:0`` in
the vendor CSS — the squared corners the rest of the house style uses.
CI rebuilds the bundle with the pinned toolchain and checks that patch
survived.

..  _developer-tests:

Tests
=====

..  code-block:: bash

    composer install
    composer ci                   # cgl, phpstan, unit, functional
    composer ci:tests:unit
    composer ci:tests:functional  # SQLite by default, no database server
    composer ci:phpstan           # level 8, no baseline
    composer ci:cgl -- --dry-run

The unit suite covers the format conversion end to end on real GD
binaries: the conversion matrix, the byte-for-byte pass-through, upper
case and unknown extensions, alpha preservation across a round trip, and
the three rejection paths.

The functional suite boots all four backend routes against a real FAL
storage: the route definitions and their targets, container resolution,
the rendered editor, the two refusal paths, a save that writes a second
file next to the original, and an invalid payload that writes nothing.
