# Contributing

Thank you for helping improve Open Demat.

## Development setup

1. Copy `.env.example` to `.env` and adjust local values.
2. Copy `composer.open_demat.json.template` to `composer.open_demat.json`.
3. Run `bash bin/composer-build`.
4. Run `composer install`.
5. Run `composer test` before submitting changes.

## Composer overlay

`composer.json` is generated from `composer.core.json` and `composer.open_demat.json`. Do not edit it directly. Put shared dependencies in `composer.core.json`; put deployment-specific bundles and repositories in `composer.open_demat.json`.

## Pull requests

Please keep changes focused, include tests for behavior changes, and use conventional commit messages when possible.
