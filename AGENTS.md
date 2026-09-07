# Laravel Custom Fields

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `plin-code/laravel-custom-fields`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises.

## Quick Commands

- Full validation: `composer test`
- Formatting check: `composer lint:check`
- Static analysis: `composer analyse`
- Pest tests: `composer test:unit`
- Workbench build: `composer build`
- Workbench server: `composer serve`

## Local Skills

- `package-scaffold`: use when adding package capabilities or wiring them through the service provider, including commands, migrations, routes, config, views, translations, assets, middleware, publish tags, workbench files, and console-only behavior.
- `package-testing`: use when adding or changing package tests with Pest 4/5 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.

## Project Rules

- Read PLAN.md and IMPLEMENTATION.md for the agreed product contracts.
- PHP ^8.4; Laravel 12 and 13. Use Pint for formatting.
- Keep the core headless, preserve configured model scopes and connections.
- Preserve stable field slugs and option keys, including inactive options.
- Keep partial updates distinct from complete validation.
- Never add Co-authored-by trailers or AI attribution to commits.
- Do not push, publish, or change repository visibility without user instruction.
- Use type(scope): description for authorized commits, with lowercase scope and imperative description.
