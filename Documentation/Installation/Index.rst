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
installation.

..  _installation-llm:

The nr-llm image configuration
==============================

AI generation needs an active nr-llm image configuration. By default the
extension looks for the identifier ``image-workbench``; a different one
can be selected per backend group, see
:ref:`Configuration <configuration>`.

Without such a configuration, manual editing keeps working — only the
generation panel fails, with the provider's message.

..  _installation-verify:

Verify
======

Open the file list, right-click a JPEG, PNG or WebP file in a folder you
may write to, and pick :guilabel:`Image Workbench`. The editor opens
full-page with the file loaded.

If the entry does not appear, check in this order: the file extension is
one of the four supported ones, the backend user may write the folder,
and ``options.imageWorkbench.enable`` is not switched off for the group.
