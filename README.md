# TYPO3 Image Workbench

Image Workbench adds a full-page, non-destructive image editor to the TYPO3 v14 file list. Editors can crop, resize, adjust, filter and annotate raster images. They can also create a new visual from a prompt through `netresearch/nr-llm`.

The extension is intentionally TYPO3 v14-only. Manual editing uses the open-source [Filerobot Image Editor](https://github.com/scaleflex/filerobot-image-editor). AI generation uses the configured `nr-llm` image provider, model, system prompt, usage tracker, budget rules and cost analytics. Customers never enter provider credentials in this extension.

## Why another image editor?

Georg Ringer's excellent [image-editor](https://github.com/georgringer/image-editor) established a clean FAL/context-menu approach. Image Workbench builds on those interaction ideas, narrows support to TYPO3 v14 and adds centrally governed AI image generation. It is a separate implementation and credits that prior art.

## Install

```bash
composer require webconsulting/image-workbench
```

Create an active `nr-llm` image configuration with identifier `image-workbench`, or change it per backend group:

```typoscript
options.imageWorkbench.enable = 1
options.imageWorkbench.ai.enable = 1
options.imageWorkbench.ai.configuration = image-workbench
options.imageWorkbench.ai.defaultSize = 1024x1024
options.imageWorkbench.tabs = adjust,finetune,filters,annotate,resize
```

The AI operation always saves a new PNG next to the selected source image. Manual changes default to a new copy; overwrite requires an explicit confirmation.

## Privacy and billing

- FAL permissions are checked for every read and write.
- The original image is not sent to an LLM. Only the prompt is sent for AI generation.
- The `configuration` identifier is attached to `ImageGenerationOptions`, so `nr-llm` records provider usage and costs against that configuration.
- API secrets remain inside `nr-llm`/`nr-vault`.

## Development

```bash
composer install
composer assets:install
composer assets:build
composer check
```

License: GPL-2.0-or-later.
