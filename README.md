# TYPO3 Image Workbench

[![CI](https://github.com/dirnbauer/typo3-image-workbench/actions/workflows/ci.yml/badge.svg)](https://github.com/dirnbauer/typo3-image-workbench/actions/workflows/ci.yml)
[![TYPO3 14.3](https://img.shields.io/badge/TYPO3-14.3-orange.svg)](https://get.typo3.org/version/14)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4%2B-777bb3.svg)](https://www.php.net/supported-versions.php)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

## What it is

A full-page, non-destructive image editor in the TYPO3 v14 file list. Editors crop, resize, adjust, filter and annotate JPEG, PNG and WebP files without leaving the backend — and can generate a new visual from a prompt through [`netresearch/nr-llm`](https://packagist.org/packages/netresearch/nr-llm), with the model, budget and cost tracking staying in the central nr-llm configuration.

It is built like a Core module: **Save as copy** and **Overwrite original** sit in the document header, the editor follows the backend language (English and German ship with it) and the light or dark colour scheme, and unsaved edits are protected against Close, the module menu and the page tree the way FormEngine protects a record.

Manual editing uses the open-source [Filerobot Image Editor](https://github.com/scaleflex/filerobot-image-editor), bundled as one ES module and committed, running entirely in the browser. Saving defaults to a new file next to the original; overwriting takes a confirmation and purges the processed-file cache. Whatever the canvas produced is re-encoded to match the extension it is written under, so a file's contents and its name never disagree.

Deliberately not done: the image itself is never sent to an LLM (only the prompt is), editors never enter provider credentials, the page never chooses which nr-llm configuration pays, nothing bypasses FAL, the editor never calls a third-party server, and there are no v12/v13 compatibility layers.

Georg Ringer's [image-editor](https://github.com/georgringer/image-editor) established the FAL and context-menu approach this builds its interaction ideas on. This is a separate implementation that narrows support to v14 and adds centrally governed AI generation.

## Requirements

- TYPO3 14.3 LTS
- PHP 8.4+ with `ext-gd`
- `netresearch/nr-llm` 0.34+ for the generation panel

## Install

```bash
composer require webconsulting/image-workbench
vendor/bin/typo3 extension:setup
```

The editor bundle is committed, so a normal installation needs no Node toolchain. No database tables, no site set. For AI generation, store the provider API key in nr-llm and create an active nr-llm image configuration with the identifier `image-workbench` — or point a backend group at a different one.

## Configure

Everything is user TSconfig, read on the server for every request, so every option can differ per backend group:

```typoscript
options.imageWorkbench.enable = 1
options.imageWorkbench.tabs = adjust,finetune,filters,annotate,resize
options.imageWorkbench.cropPresets = Story=9:16, Social=1:1, Wide=16:9

options.imageWorkbench.ai.enable = 1
options.imageWorkbench.ai.configuration = image-workbench
options.imageWorkbench.ai.defaultSize = 1024x1024

# Show "Edit image" among the always-visible row actions of the file list
options.file_list.primaryActions = view,metadata,translations,delete,imageWorkbench
```

None of it widens access. The file-list action, the context-menu entry, the editor route and every write are gated by FAL: the file must live in a real storage, be writable (or, for a copy, its folder), and have one of the four supported extensions.

## Use

In the **Media** module, pick **Edit image** among the actions of an image row or from its context menu. Edit, then **Save as copy** (asks for a name) or **Overwrite original** (asks for confirmation) in the document header. The panel beside the editor takes a description of 10–8,000 characters and a size the configured model supports, and saves the generated image as a **new PNG** next to the source — generation never overwrites. Every generated file is listed below the form with a link that opens it in the workbench.

nr-llm books provider usage and cost against the configuration and the backend user, and enforces that user's budget.

## Develop

```bash
composer install
composer ci                   # cgl, phpstan, unit, functional
composer ci:tests:unit
composer ci:tests:functional  # SQLite, no database server needed
composer ci:phpstan           # level 8, no baseline
composer ci:cgl -- --dry-run
composer assets:install       # npm ci in Build/
composer assets:build         # rebuild the editor bundle
docker run --rm -v $PWD:/project ghcr.io/typo3-documentation/render-guides:latest --config=Documentation
```

The asset build fails when the editor's label list and `Resources/Private/Language/locallang_editor.xlf` drift apart. CI rebuilds the bundle from the lock file and requires it to match the committed one.

## Docs

Full manual in [`Documentation/`](Documentation/Index.rst): what it does and does not do, installation and the nr-llm configuration, every TSconfig option, the editor and generation workflow with its failure messages, and a developer reference covering the routes, services, labels and theming, privacy and billing, and the test suites.

## License

GPL-2.0-or-later.
