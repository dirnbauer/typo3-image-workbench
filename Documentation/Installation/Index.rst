..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

..  _installation-requirements:

Requirements
============

*   TYPO3 14.3 LTS — the extension is v14-only on purpose
*   PHP 8.4 or newer, with the GD extension: the format conversion
    decodes and re-encodes through GD
*   :composer:`netresearch/nr-llm` 0.34 or newer, for the AI generation
    and its usage and cost tracking

..  _installation-composer:

Install with Composer
=====================

..  code-block:: bash

    composer require webconsulting/image-workbench
    vendor/bin/typo3 extension:setup

The editor bundle is committed, so there is nothing to build for a normal
installation. The extension adds no database tables and no site set.

..  _installation-llm:

The nr-llm image configuration
==============================

AI generation needs an nr-llm provider with an API key and an active
image configuration. By default the extension looks for the identifier
``image-workbench``; a different one can be selected per backend group,
see :ref:`Configuration <configuration>`.

Until nr-llm has an API key, editors do not see the generation panel at
all, and administrators see a short hint in its place. Manual editing
works either way.

..  _installation-verify:

Verify
======

Open the :guilabel:`Media` module, open the actions of a JPEG, PNG or
WebP file in a folder you may write to (or right-click it), and pick
:guilabel:`Edit image`. The editor opens full-page with the file loaded.

If the entry does not appear, check in this order: the file extension is
one of the four supported ones, the backend user may write the file, and
``options.imageWorkbench.enable`` is not switched off for the group.
