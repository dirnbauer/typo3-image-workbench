..  include:: /Includes.rst.txt

..  _start:

===============
Image Workbench
===============

:Extension key:
    image_workbench

:Package name:
    webconsulting/image-workbench

:Version:
    |release|

:Language:
    en

:Author:
    webconsulting business services gmbh

:License:
    This document is published under the
    `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
    license.

:Rendered:
    |today|

----

A full-page, non-destructive image editor in the TYPO3 v14 file list —
crop, resize, adjust, filter and annotate — plus generating a new visual
from a prompt through :composer:`netresearch/nr-llm`, with the provider,
budget and cost tracking staying where they belong: in the central
nr-llm configuration.

It looks and behaves like the rest of the backend: saving lives in the
document header, the editor follows the backend language and the light
or dark colour scheme, and unsaved edits are protected the way FormEngine
protects them. Editors never enter provider credentials here, and the
original image is never sent to an LLM.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: Introduction

        What it does, what it deliberately does not do, and the prior art
        it credits.

        ..  card-footer:: :ref:`Read the introduction <introduction>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: Installation

        Composer, the nr-llm image configuration, and the asset bundle.

        ..  card-footer:: :ref:`Install the extension <installation>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: Configuration

        Every user TSconfig option, per backend group.

        ..  card-footer:: :ref:`Configure the workbench <configuration>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: Developer reference

        The routes, the services, labels and theming, privacy and
        billing, the editor bundle and the test suites.

        ..  card-footer:: :ref:`Read the reference <developer>`
            :button-style: btn btn-secondary stretched-link

..  toctree::
    :maxdepth: 2
    :titlesonly:

    Introduction/Index
    Installation/Index
    Configuration/Index
    Usage/Index
    Developer/Index

..  toctree::
    :hidden:

    Sitemap
