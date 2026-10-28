# Contributing

Thanks for helping improve Laravel Gemini! Bug reports, fixes and new features are all welcome.

## Reporting bugs

[Open an issue](https://github.com/anmolmishra23-dev/laravel-gemini/issues/new/choose) and include:

- your PHP, Laravel and package versions
- the Gemini model you used
- a short code sample that reproduces the problem
- the full error message

Never paste your API key into an issue.

## Suggesting features

Open a feature request first if the change is large, so we can agree on the approach before you spend time on it.

## Pull requests

1. Fork the repo and create a branch from `main`.
2. Install dependencies with `composer install`.
3. Make your change and add tests for it.
4. Run the tests with `composer test`.
5. Fix the code style with `composer format`.
6. If the change affects users, add it to the README and to CHANGELOG.md under "Unreleased".
7. Open the pull request with a clear description of what changed and why.

Tests use `Gemini::fake()`, so you don't need an API key to run them. If your change depends on how the real API behaves, mention in the pull request which model you tested it against.

## Versioning

The package follows [semantic versioning](https://semver.org). Breaking changes only happen in major versions.
