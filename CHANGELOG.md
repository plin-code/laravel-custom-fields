# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Added

- The test suite runs against MySQL 8.4 and PostgreSQL 17 on every build, alongside the
  SQLite matrix. `DB_DRIVER` points it at a server locally.

### Fixed

- `getCustomFields()` and the generated filters and sorts come back ordered by
  `sort_order` and then by slug. The order was whatever the database returned, so the same
  code gave a different order on SQLite and on a server, and the `sort_order` column was
  never read.
- A `decimal` value reads back the same on every driver. MySQL and PostgreSQL return the
  full scale of the column, so a stored `12.5` came back as `12.500000` while SQLite gave
  `12.5`.

## 0.1.0 - 2026-09-07

First public release.

### Added

- Typed custom field definitions bound to a registered entity, with a stable slug
  generated once from the name and unique per entity.
- Twelve built in field types: `text`, `textarea`, `email`, `url`, `phone`, `number`,
  `decimal`, `boolean`, `date`, `datetime`, `select`, `multiselect`. Each type owns its
  storage column, validation rules, serialization, default, labels and the query
  operations it declares.
- The `FieldType` contract and `CustomFields::registerType()`, so a product can add its
  own types.
- The `HasCustomFields` trait with `getCustomField()`, `getCustomFields()`,
  `setCustomField()`, `setCustomFields()`, `clearCustomField()` and the
  `whereCustomField()` query scope.
- Partial and complete validation through `ValueValidator`, reachable as
  `CustomFields::validate()` and `CustomFields::validator()->rules()`.
- Structured options with stable keys, plus `updateOptions()`, `optionsForInput()`,
  `optionKeys()` and `activeOptionKeys()`. An inactive option stays readable on the
  records that already hold it and is never offered to a new one.
- Request filters and sorts for `spatie/laravel-query-builder` through
  `CustomFields::filtersFor()`, `sortsFor()` and `queryOptionsFor()`, with names built
  from the configurable `key_prefix` by `filterName()` and `sortName()`.
- Ten filter operations: `equals`, `in`, `contains`, `greater_than`, `less_than`,
  `between`, `is_null`, `is_not_null`, `contains_any`, `contains_all`. Each one is
  registered only for the types that declare it, so nothing arbitrary can be built from
  a request.
- Sorting on a custom field through a correlated subquery, which leaves the caller's
  select, aggregates and existing order untouched and places the records without a value
  last in both directions.
- Five events dispatched after the surrounding transaction commits: `CustomFieldCreated`,
  `CustomFieldUpdated`, `CustomFieldDeleted`, `CustomFieldValueSaved`,
  `CustomFieldValueDeleted`.
- `UnknownCustomFieldException` and `ModelNotPersistedException`.
- Configurable tables, key types (`id`, `uuid`, `ulid`), models and filter prefix,
  validated while the service provider registers so a typo fails at boot.
- English and Italian validation messages under the `laravel-custom-fields` translation
  namespace.
- Model factories for both package models.
- A README covering installation, configuration, the field type table, the query
  operations, the events, the exceptions, the form metadata and the test commands.

### Changed

- `morph_key_type` now defaults to `id` instead of `uuid`. A fresh installation matches a
  default Laravel application and gets a `bigint` `valuable_id` column. `uuid` and `ulid`
  stay fully supported and still have to be chosen before the migrations run.
- The service provider is built on `spatie/laravel-package-tools`. The publish tags are
  unchanged (`laravel-custom-fields`, `laravel-custom-fields-config`,
  `laravel-custom-fields-lang`, `laravel-custom-fields-migrations`), and a published
  migration now receives a fresh timestamp instead of keeping the packaged one.
- The migrations are named `create_custom_fields_table` and
  `create_custom_field_values_table`.
- `getCustomField()` takes a second argument, `includeInactive`, mirroring
  `getCustomFields()`. Reading a deactivated definition without it throws.
- `clearCustomField()` works on a deactivated definition with no flag, otherwise retired
  data could never be removed.
- An unknown or inactive slug submitted to `setCustomField()` or `setCustomFields()` is
  reported as a `ValidationException` keyed by slug, before the transaction opens, rather
  than as an `InvalidArgumentException` in the middle of a write. The direct accessors
  keep throwing `UnknownCustomFieldException`, which extends `InvalidArgumentException`.
- Validation messages resolve through the translation namespace and use the field name as
  the attribute, so an error reads "The date of birth field is required." instead of
  naming the raw slug.
- `filtersFor()` returns one filter per declared operation instead of a single equality
  filter per field, and the filter and sort names come from `key_prefix` instead of a
  hardcoded `cf_`.
- `BooleanType::serialize()` reads textual booleans through `filter_var`, so
  `filter[cf_flag]=true` matches instead of returning nothing.
- `CustomFieldSorter` refuses a field type that does not declare `sort` at construction,
  and `CustomFieldFilter` refuses an operation the type does not declare.

### Fixed

- Complete validation no longer fails on a required field that is already stored and
  absent from the payload. It still fails when that field is explicitly submitted as
  `null`, and when it was never stored at all.
- Passing `null` now deletes the stored row inside the same transaction as the rest of the
  batch and dispatches `CustomFieldValueDeleted`, instead of writing a row with every
  value column set to null. An empty array on a `multiselect` keeps the row, so a cleared
  selection stays distinct from a field that was never answered.
- A `multiselect` no longer receives an equality filter its JSON storage cannot serve,
  which silently returned no rows. It exposes `contains_any` and `contains_all`, built on
  `whereJsonContains`.
- The `contains` filter escapes `%`, `_`, `!` and the backslash with an explicit escape
  clause, so those characters are matched literally on MySQL, PostgreSQL and SQLite.
- `setCustomFields()` rejects a model that has not been saved with
  `ModelNotPersistedException` before touching the database, instead of failing on a NOT
  NULL constraint.
- Reads and writes no longer issue one query per field. The definitions and the stored
  rows are loaded once per call and shared between validation and writing.
- `setCustomFields()` releases the `customFieldValues` relation after a write, so an eager
  loaded relation is not left stale.
- A generated slug stays within 100 characters once a collision suffix is appended, and
  no longer ends up with a doubled dash.
- The sort no longer depends on where each database places a null.
- Complete validation decides whether a required field is satisfied without replaying the
  input rules of its type over the value already stored. A type whose read shape differs
  from its write shape, which the custom type extension point explicitly allows, no longer
  makes every complete write fail on a field the caller never touched.
- The values migration falls back to `id` rather than `uuid` when `morph_key_type` is
  absent, so a missing config key no longer builds a values table that matches neither
  documented default.
- The test suite pins an in memory database, so `composer build` followed by
  `composer test` no longer fails on the migrations that `workbench:build` publishes into
  the Testbench skeleton.
