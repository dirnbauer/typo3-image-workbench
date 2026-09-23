# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2026-09-23

The editor page is now built like a TYPO3 v14 module instead of a branded page of its own, the vendor bundle is a proper ES module, and every endpoint takes its settings from the server.

### Added

- **Edit image** in the action column of the file list (`ProcessFileListActionsEvent`), next to the Core actions; `options.file_list.primaryActions = …,imageWorkbench` moves it into the always-visible group.
- Saving in the document header: **Save as copy** asks for the file name in a Core modal, **Overwrite original** asks for confirmation. Both stay disabled until the image has loaded. After a copy, the notification offers to open it in the workbench.
- Unsaved edits are protected: **Close**, the module menu, the page tree and a module reload ask first (Core consumer scope, like FormEngine).
- The editor speaks the backend language: all labels are XLIFF 2.0 files with English sources and German translations, including the 128 labels of the Filerobot editor (translation domains `image_workbench.messages` and `image_workbench.editor`, imported as `~labels/…` modules).
- The editor follows the backend colour tokens in the light and the dark scheme and re-themes live when the user switches; selected tools stay above 6:1 contrast in both.
- A size selector in the generation panel, limited to the sizes the configured model supports; generated files are listed with a thumbnail and a link that opens them in the workbench.
- The generation panel explains itself to administrators while nr-llm has no API key, and stays hidden for editors until then.
- Missing or non-editable files get a Core notice page (404/403) instead of an exception.
- Tests: TSconfig parsing, save modes, German labels, overwrite, the TSconfig switches, the source route's type restriction, the generation endpoint's validation and the file-list action (27 unit and 19 functional tests).

### Changed

- The nr-llm configuration comes from the user TSconfig on the server; a request can no longer name a different configuration. `ai.enable` and `enable` are enforced by the endpoints, not only by the page.
- The backend user is passed to nr-llm, so per-user budgets apply and usage is booked against the user.
- The Filerobot bundle is one ES module in the backend import map (`Vendor/filerobot-image-editor.js`) instead of a script that put `FilerobotImageEditor` on `window`; the vanilla wrapper package and its `overrides` hack are gone.
- The editor exports at pixel ratio 1 (its default of 4 multiplied the working canvas of large photos) and passes the JPEG quality it actually means (0.92).
- Layout and colours: the hard-coded palette, the dark sidebar and the squared corners are replaced by Core cards, forms, buttons and colour tokens; the CSS is layout only.
- The extension icon is redrawn in the v14 line-art style.
- JSON responses use the Core `JsonResponse`; messages are translated, provider details are shown to administrators only and logged.
- CI runs lint, unit and functional tests on PHP 8.4 and 8.5, uses MariaDB 11.4 and Node 24, validates the XLIFF files and requires the committed bundle to match a fresh build.

### Fixed

- The editor no longer calls `i18n-fastly.ultrafast.io` for its labels; the request leaked the editor's IP address and was blocked by the backend CSP anyway.
- `options.imageWorkbench.cropPresets` had no effect; the presets now appear in the crop tool.
- The source route served any readable file with its own MIME type under the backend origin; it now serves the four editable image formats only, with `nosniff`.
- Save and generation accepted any readable file as the target; both now require an editable image in a real storage (the fallback storage is refused, as in the Core file editor).
- A target extension outside the four is refused by the format converter instead of being written unconverted.
- The styled-components peer conflict (5.3 installed, 6 required by the editor's UI kit).

### Dependencies

- esbuild 0.25.12 → 0.28.2, react and react-dom 19.2.7 → 19.3.0, react-konva 19.2.5 → 19.3.0, styled-components 5.3.11 → 6.5.3; `filerobot-image-editor` removed (the React package is used directly).
- `actions/checkout` v5 → v7, `actions/setup-node` v4 → v7.

## [0.2.1] - 2026-09-18

### Changed

- Allows `netresearch/nr-llm` 0.35.

## [0.2.0] - 2026-09-13

The extension had no PHPStan configuration, no tests, no CI and no manual. This release adds all of it, without changing what the editor does.

### Added

- `Tests/Unit/Service/ImageFormatConverterTest`: the conversion matrix on real GD binaries — every supported source and target format, the byte-for-byte pass-through when the data already matches, uppercase and unknown extensions, alpha preservation across a round trip, and the three rejection paths.
- `Tests/Functional/Backend/ImageWorkbenchRoutesTest`: all four backend routes booted against a real FAL storage — the route definitions and their targets, container resolution, the rendered editor, the two refusal paths, a save that writes a second file next to the original, and an invalid payload that writes nothing.
- A CI workflow: lint, coding standards, PHPStan level 8, unit tests on PHP 8.4 and 8.5, functional tests against MariaDB 10.11, and an assets job that rebuilds the Filerobot bundle with the pinned toolchain and checks the squared-corner patch survived.
- `Documentation/` as a rendered TYPO3 manual — introduction, installation, every TSconfig option, the workflow with its failure messages, and a developer reference.
- `CHANGELOG.md`, `phpstan.neon`, `.php-cs-fixer.dist.php` on `typo3/coding-standards`, `Build/phpunit/` on the TYPO3 testing framework, and the `composer ci`, `ci:cgl`, `ci:phpstan`, `ci:tests:unit` and `ci:tests:functional` scripts.

### Changed

- Requires PHP `^8.4` (was `>=8.3 <8.5`) and TYPO3 14.3.7.
- `ext-gd` and `typo3/cms-core` are declared requirements. Both were already used; neither was declared.
- The format conversion moved out of `ImagePersistenceService` into a pure `Service\ImageFormatConverter` — binary in, binary out, no FAL — injected into the persistence service. Behaviour is unchanged; it is now testable without booting TYPO3.
- `extra.typo3/cms` carries the version and `Package.providesPackages`, so TYPO3 14.3 no longer deprecates the `ext_emconf.php` that TER uploads still need.
- README follows the shared structure and documents every TSconfig option.

### Removed

- The `imagedestroy()` calls in the conversion path: no-ops since PHP 8.0 and deprecated in 8.5, which the PHP 8.5 leg of the new matrix reported.

### Verified

- The nr-llm 0.34 integration holds: `DallEImageService`, `ImageGenerationOptions`, `resolveModelForConfiguration()` and `getConfigurationSystemPrompt()` (the last two inherited from `AbstractSpecializedService`) are all present and unchanged.

[0.3.0]: https://github.com/dirnbauer/typo3-image-workbench/releases/tag/v0.3.0
[0.2.1]: https://github.com/dirnbauer/typo3-image-workbench/releases/tag/v0.2.1
[0.2.0]: https://github.com/dirnbauer/typo3-image-workbench/releases/tag/v0.2.0
