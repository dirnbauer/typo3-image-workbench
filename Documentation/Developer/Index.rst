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
        -   :php:`EditorController::edit` — the full-page editor inside
            the backend ``Module`` layout, with the save actions in the
            document header. A missing or non-editable file gets a
            404/403 page with a notice instead of an exception.

    *   -   ``image_workbench_source``
        -   :path:`/image-workbench/source`
        -   :php:`ImageController::source` — streams the original with
            its real image type, ``X-Content-Type-Options: nosniff`` and
            ``Cache-Control: private, no-store``. It serves the four
            editable formats only, never an HTML or SVG file.

    *   -   ``ajax_image_workbench_save``
        -   :path:`/ajax/image-workbench/save`
        -   :php:`SaveController::save` — POST, copy or overwrite

    *   -   ``ajax_image_workbench_generate``
        -   :path:`/ajax/image-workbench/generate`
        -   :php:`AiImageController::generate` — POST, nr-llm generation

All four inherit their access from the ``media_management`` module, and
all four re-check FAL permissions and the user TSconfig themselves rather
than trusting that. The AJAX routes answer JSON with ``success``, a
translated ``message`` and, where it helps, a ``detail`` and the written
``file`` (name, thumbnail, edit link).

The file list gets its row action from
:php:`EventListener\AddEditImageFileListAction`, a listener for the Core's
:php:`ProcessFileListActionsEvent`; the context menu gets its entry from
:php:`ContextMenu\ImageWorkbenchItemProvider`.

..  _developer-services:

Services
========

:php:`Configuration\WorkbenchSettings`
    The user TSconfig below ``options.imageWorkbench``, parsed once and
    typed: switches, tabs, crop presets, the nr-llm configuration and the
    default size.

:php:`Service\EditableImageFinder`
    Resolves a combined identifier to a file the workbench may open: it
    exists, lives in a real storage (not the fallback storage) and has
    one of the four editable extensions. Permissions stay with the
    caller, because reading, overwriting and writing a copy each need a
    different check.

:php:`Service\AiImageGenerator`
    The one place that talks to nr-llm: availability (is there an API
    key?), the configured model, its supported sizes, and the generation
    itself with the backend user attached for budget enforcement.

:php:`Service\ImagePersistenceService`
    The only place that writes. :php:`saveCopy()` refuses a folder it may
    not write, converts the binary to the target extension, sanitises the
    desired name through the storage and adds the file with
    :php:`DuplicationBehavior::RENAME` — so a collision produces a second
    file, never a lost one. :php:`overwrite()` refuses a file it may not
    write, replaces the contents and deletes every processed file derived
    from the original.

:php:`Service\ImageFormatConverter`
    The re-encoding, and nothing else — binary in, binary out, no FAL, no
    TYPO3:

    ..  code-block:: php

        $converter->toExtension($binary, 'jpg');

    It sniffs the data, returns it byte for byte when it already matches
    the extension, and otherwise decodes it through GD and writes it out
    again as PNG (alpha preserved), WebP or JPEG at quality 90. Four
    failures are distinguished by exception code: 1752910103 for data
    that is not a supported image, 1752910104 for data that will not
    decode, 1752910105 for a re-encode that fails, and 1752910106 for a
    target extension outside the four.

..  _developer-ui:

Labels and theming
==================

All labels are XLIFF 2.0 files in two translation domains:

*   ``image_workbench.messages`` (:file:`locallang.xlf`) — the page, the
    dialogs, the notifications and the JSON messages.
*   ``image_workbench.editor`` (:file:`locallang_editor.xlf`) — the 128
    labels of the Filerobot editor itself.

The page script imports both as ``~labels/…`` modules; translations are
``de.*.xlf`` files next to them. Only approved units count: a German
target must sit in a ``<segment state="final">`` (or carry no state) —
``state="translated"`` is ignored while
``$GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations']``
is on, which is the default.

:file:`Resources/Public/JavaScript/theme.js` maps the backend colour
tokens onto the editor's palette. The tokens resolve through
``light-dark()`` and ``color-mix()``, and the editor paints part of its
interface on a canvas, so every token is resolved to a plain ``rgba()``
value first — and again whenever the backend switches colour scheme.

..  _developer-privacy:

Privacy and billing
===================

*   FAL permissions are checked on every read and every write.
*   The original image is never sent to an LLM. Only the prompt is.
*   The nr-llm configuration comes from the user TSconfig on the server,
    and the backend user is passed to nr-llm, so budgets apply per user
    and usage is booked against the configuration and the user.
*   Provider error messages are shown to administrators only; everyone
    else gets a translated message, and the details go to the TYPO3 log.
*   API secrets stay inside nr-llm and nr-vault. The extension never
    sees, stores or logs one.
*   The editor makes no request outside the backend: its own
    translation service (``useBackendTranslations``) is off.

..  _developer-assets:

The editor bundle
=================

The Filerobot editor is bundled with esbuild into one ES module,
:file:`Resources/Public/JavaScript/Vendor/filerobot-image-editor.js`,
which the backend import map serves like any other module. React, Konva
and the editor's UI kit stay inside it; nothing is put on ``window``.
The result is committed, so an installation needs no Node toolchain:

..  code-block:: bash

    composer assets:install   # npm ci in Build/
    composer assets:build     # node Build/esbuild.mjs

The build fails when :file:`locallang_editor.xlf` and the editor's own
label list drift apart, so a Filerobot update cannot ship half
translated. CI rebuilds the bundle from the lock file and requires it to
be identical to the committed one.

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

The unit suite covers the format conversion on real GD binaries (the
conversion matrix, the byte-for-byte pass-through, alpha preservation,
every rejection path), the TSconfig parsing and the save modes.

The functional suite boots the backend routes against a real FAL
storage: route registration and container resolution, the rendered
editor in English and German, the TSconfig switches, the not-found and
access-denied pages, copy and overwrite, the refusals that must write
nothing, the source route's type restriction, the generation endpoint's
validation, and the file-list action.
