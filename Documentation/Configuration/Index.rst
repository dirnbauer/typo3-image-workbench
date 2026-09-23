..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

Everything is user TSconfig, so every option can differ per backend
group. The shipped defaults live in :file:`Configuration/user.tsconfig`
and are active without any configuration of your own.

The server reads these options on every request. The editor page cannot
switch itself to another nr-llm configuration or re-enable a panel the
group has switched off.

..  _configuration-options:

Options
=======

..  list-table::
    :header-rows: 1

    *   -   Option
        -   Default
        -   Purpose

    *   -   ``options.imageWorkbench.enable``
        -   ``1``
        -   The whole workbench: the file-list action, the context-menu
            entry, the editor and saving.

    *   -   ``options.imageWorkbench.tabs``
        -   ``adjust,finetune,filters,annotate,resize``
        -   Which editor tabs to offer, shown in the editor's own order.
            ``watermark`` is available too; unknown names are ignored.

    *   -   ``options.imageWorkbench.cropPresets``
        -   *(empty)*
        -   Named aspect ratios in the crop tool, as ``Name=W:H`` pairs.
            Malformed entries are skipped.

    *   -   ``options.imageWorkbench.ai.enable``
        -   ``1``
        -   The generation panel and the generation endpoint.

    *   -   ``options.imageWorkbench.ai.configuration``
        -   ``image-workbench``
        -   The nr-llm configuration identifier generation runs against —
            and therefore the one usage and cost are booked to. An
            identifier nr-llm would not accept switches generation off.

    *   -   ``options.imageWorkbench.ai.defaultSize``
        -   ``1024x1024``
        -   The preselected size. Editors pick from the sizes the
            configured model supports; a default the model does not
            support falls back to its first size.

To show :guilabel:`Edit image` among the always-visible actions of a file
row instead of the dropdown of further actions, list it with the Core
actions:

..  code-block:: typoscript

    options.file_list.primaryActions = view,metadata,translations,delete,imageWorkbench

..  _configuration-example:

A worked example
================

..  code-block:: typoscript
    :caption: User TSconfig of an editor group

    options.imageWorkbench.enable = 1
    options.imageWorkbench.tabs = adjust,filters,resize
    options.imageWorkbench.cropPresets = Story=9:16, Social=1:1, Wide=16:9

    # This group generates against a cheaper configuration …
    options.imageWorkbench.ai.enable = 1
    options.imageWorkbench.ai.configuration = image-workbench-draft

… and a group that may edit but not spend:

..  code-block:: typoscript

    options.imageWorkbench.ai.enable = 0

..  _configuration-permissions:

What TSconfig cannot grant
==========================

None of these options widen access. The file-list action, the
context-menu entry, the editor route and every write are gated by FAL:
the file must be writable (or, for a copy, its folder), it must live in a
real storage, and its extension must be one of ``jpg``, ``jpeg``,
``png`` or ``webp``. Setting ``enable = 1`` for a group that cannot write
the file changes nothing.
