..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

Everything is user TSconfig, so every option can differ per backend
group. The shipped defaults live in :file:`Configuration/user.tsconfig`
and are active without any configuration of your own.

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
        -   Show the context-menu entry at all.

    *   -   ``options.imageWorkbench.tabs``
        -   ``adjust,finetune,filters,annotate,resize``
        -   Which editor tabs to offer. ``watermark`` is available too.

    *   -   ``options.imageWorkbench.cropPresets``
        -   *(empty)*
        -   Named aspect ratios in the crop tab, as
            ``Name=W:H`` pairs.

    *   -   ``options.imageWorkbench.ai.enable``
        -   ``1``
        -   Show the generation panel next to the editor.

    *   -   ``options.imageWorkbench.ai.configuration``
        -   ``image-workbench``
        -   The nr-llm configuration identifier the generation runs
            against — and therefore the one usage and cost are booked to.

    *   -   ``options.imageWorkbench.ai.defaultSize``
        -   ``1024x1024``
        -   The requested image size. Square is the default because it is
            the one size every supported model accepts.

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

None of these options widen access. The context-menu entry, the editor
route and every write are gated by FAL: the file must be readable, its
folder writable, and the extension one of ``jpg``, ``jpeg``, ``png`` or
``webp``. Setting ``enable = 1`` for a group that cannot write the folder
changes nothing.
