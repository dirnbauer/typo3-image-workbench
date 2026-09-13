# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[0.2.0]: https://github.com/dirnbauer/typo3-image-workbench/releases/tag/v0.2.0
