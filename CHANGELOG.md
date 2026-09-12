* raised the `google/protobuf` constraint from `~3.15.8` to `^3.22` (ydb-platform/ydb-php-sdk#135) - the old version emits a stream of PHP 8.1/8.2 deprecation warnings (dynamic properties, internal method return types) on every request; the new range resolves to a version with none of them, verified with the full test suite
* fixed `temp_dir` being created with `0600` permissions instead of `0700` (ydb-platform/ydb-php-sdk#128) - a directory needs the executable bit to be entered/traversed, not just read/write, so every `file_put_contents()` into a freshly auto-created `temp_dir` failed with "Permission denied"
* fixed `ResourceExhausted` from an oversized outgoing message being retried forever (ydb-platform/ydb-php-sdk#258) - grpc-core rejects a message over the local send-size limit with the same `RESOURCE_EXHAUSTED` (8) code as a retryable server-side quota error, so `Table::retryTransaction()`/`Ydb::retryTransaction()` kept retrying a payload that could never shrink. `handleGrpcStatus()` now recognizes the "message larger than max" detail text and throws the existing (but previously unused) `ClientResourceExhaustedException`, which is non-retryable, instead
* regenerated `Ydb.Scheme.Entry.Type` (`protos/Ydb/Scheme/Entry/Type.php`) from current `ydb-api-protos`, adding `EXTERNAL_TABLE`, `EXTERNAL_DATA_SOURCE`, `VIEW`, `RESOURCE_POOL`, `TRANSFER`, `SYS_VIEW`, `SECRET`, and the `Entry::interrupt_permission_inheritance` field; fixes `Scheme::listDirectory()`/`Session::describeTable()` silently returning the raw type int instead of a name (e.g. `20` instead of `"VIEW"`) for any entry kind added since the previous codegen
* fixed `Session::query()` leaving `tx_id` stuck after a failed `ExecuteDataQuery` - the server had already aborted that transaction, but nothing cleared the SDK-side id, so it kept getting reused on every later call until an explicit `commit()`/`rollBack()`; matches how the transaction state is cleared on query failure in the official Python and Java SDKs
* fixed `Session::commitTransaction()`/`rollbackTransaction()` leaving `tx_id` stuck on a dead transaction when their own `CommitTransaction`/`RollbackTransaction` RPC call fails (which happens precisely when the transaction is already aborted server-side - exactly the case a caller is recovering from by calling `commit()`/`rollBack()`) - previously this left the session permanently reusing that dead transaction id, so every later query on it failed with "Transaction not found" regardless of whether it had anything to do with the original error
* fixed `DECLARE $x AS T?;` (the short YQL form of `Optional<T>`) throwing "Unknown type" client-side instead of being accepted like `Optional<T>`
* regenerated `Ydb\Table\ColumnMeta` and other `Ydb.Table.*` messages from an up-to-date `ydb-api-protos` checkout; `DescribeTable` can now report a column's `not_null` flag and default value (including `Serial`/`BigSerial` sequence info via `from_sequence`), which the stale generated code silently dropped before
* fixed `Uuid` values being written/read incorrectly - writing went through `StringType` (wrong wire type entirely, `STRING` instead of `UUID`, raw dashed text as `bytes_value`) and reading only looked at `low_128` via `dechex()`, discarding `high_128` and mangling byte order. Added `Types\UuidType`, matching the `low_128`/`high_128` split ("bytes_le") convention the official SDKs use, verified against a real server response.
* added `YdbQuery::beginTx($mode, bool $commit = true)` - passing `$commit = false` leaves the transaction open instead of always committing right after that one query; `Session::executeQuery()` now picks up the resulting transaction id from the response, so a following `Session::query()`/`commitTransaction()`/`rollbackTransaction()` call continues and closes it, the same way `Session::beginTransaction()` already does. Default behavior (`beginTx($mode)`, no second argument) is unchanged.
* fixed `Serialization of 'Closure' is not allowed` when a logger is set - `Iam::token_temp_file()` serializes the whole config to build a cache filename, and `Ydb::__construct()` attaches the logger onto the credentials object, so any real logger holding a `Closure` internally (common - processors, handlers, formatters) broke every request. `Auth::__sleep()` now drops any object-valued property before serializing, which also covers `StaticAuthentication`'s own nested `Ydb` instance.
* fixed `Decimal` values being written/read incorrectly - `Types\DecimalType` wrote as `STRING`/`bytes_value` (not a real `Decimal` value at all) and `QueryResult` never recognized a `decimalType` column, so it silently fell through to the raw, unscaled `low128` half. Also fixed `normalizeValue()` casting to `(float)`, which lost precision for values with more significant digits than a double can represent exactly. Added string-based `DECLARE $x AS Decimal(p,s);` support to `valueOfType()` alongside the existing compositional `DecimalType` API. Also fixed `toParts()` silently truncating (instead of rounding) input with more fractional digits than the declared scale, and depending on PHP's ambient `bcscale()` setting to do it - it now rounds half away from zero at an explicit scale, independent of process-wide bcmath state. Note: this local YDB build only accepts `Decimal(22,9)` for table columns - other precision/scale pairs may be rejected server-side regardless of this fix.
* fixed `Sessions\FileSessionPool` not being safe for the multiprocess use it exists for (ydb-platform/ydb-php-sdk#53) - `load()`/`save()` had no locking, so two PHP-FPM workers sharing a pool file could both see the same session as idle and take it concurrently, and a reader could crash on a torn/empty write (`foreach() on null`). Every pool operation now runs under a reentrant `flock()` held for its whole duration, `getIdleSession()` persists the taken mark itself before returning (instead of relying on a separate, unlocked follow-up call), and `save()` writes via a temp file + `rename()` so a concurrent reader never observes a half-written file.
* fixed `Uint64` values above `PHP_INT_MAX` losing precision on write - `IntType::normalizeValue()` unconditionally `(int)`-cast every value, which PHP silently clamps to `PHP_INT_MAX` for any numeric string past it, so every such value was written to the wire as `9223372036854775807` regardless of what was actually passed. Also fixed `QueryResult::fillRows()`'s `UINT64` overflow detection (ydb-platform/ydb-php-sdk#148) - it checked whether the `(int)`-cast result landed exactly on `PHP_INT_MAX`, which can't tell overflow apart from a value that legitimately equals `PHP_INT_MAX`, corrupting that one specific value to `PHP_INT_MIN`; it now checks the original string via `bccomp()` instead, matching `UuidType`/`DecimalType`'s own convention. Added `Uint64Type::toUnsigned()` to recover the true value from a `QueryResult` row for the still-existing case where it comes back as the signed 64-bit wrap (any `Uint64` above `PHP_INT_MAX` - PHP has no native type that can hold the full range).
* added `Session::isInTransaction()` - there was no public way to check whether a session currently has an active transaction, only the protected `tx_id` field.

## 1.16.3
* improve log

## 1.16.2
* logged failed discovery refresh in `checkDiscovery` instead of silently swallowing the exception
* fixed discovery retry rate after a failure: a separate `lastDiscoveryAttempt` timer gates retries by `discoveryInterval()`, so a broken discovery no longer triggers `discover()` on every API request

## 1.16.1
* added resilient internal endpoint discovery (`YdbPlatform\Ydb\Internal\Discovery`) that always targets the original bootstrap endpoint and recreates the gRPC channel with `force_new` on retries to bust the c-core DNS cache and survive bootstrap IP changes
* added discovery tuning config keys: `discoveryTimeoutMs` (default 1000), `discoveryAttemptTimeoutMs` (default 300), `discoveryInitialTimeoutMs` (default 5000; set to `PHP_INT_MAX` to wait indefinitely on startup)
* set `grpc.lb_policy_name = round_robin` as the default for every gRPC channel built via `Ydb::grpcOpts()`; user overrides via `grpc.opts.grpc.lb_policy_name` are respected
* fixed `array_search` cluster-endpoint check in `Ydb::discover()` (returned key 0 was treated as "not found"); replaced with strict `in_array`

## 1.16.0
* added support operation timeout option

## 1.15.1
* fixed Error parsing JSON @1:9: No such field

## 1.15.0
* added `$grpc_config` array for customize gRPC behavior

## 1.14.0
* added `ScanQueryMode` for `Table::scanQuery`

## 1.13.2
### Bugs

* fixed case with unexisting rows in query result

## 1.13.1
* changed default mode for ExecuteScanQueryRequest from MODE_UNSPECIFIED to MODE_EXEC

## 1.13.0
* added transaction mode for retryTransaction
* fix keepInCache param in YdbQuery
* added Yson type
* add logger as Ydb config
* added snapshot mode in noninteractive transaction

## 1.12.0
* added StaticAuthentication
* added query timeout and canceled params

## 1.11.0
* added query stats
* added ReadTokenFromFile
* added lambda on exception in retryTransaction

## 1.10.0
* changed level of update token log record from info to debug
* created refresh token ratio parameter

## 1.9.0

* added microseconds in Timestamp type

## 1.8.2

* fixed discovery on exception
* fixed logger in EnvironCredentials

## 1.8.1

* fixed bug, when function Retry::backoffType always return SlowBackoff

## 1.8.0

* update destructor in MemorySessionPool
* fixed exception on re-create server nodes
* fixed key name in createTable function
* added simple std looger

## 1.7.0

* added environment credentials

## 1.6.0

* added retry function
* fixed result with empty list
* added optional type in prepare statment

## 1.5.6

* added support of php 7.2

## 1.5.5

* fixed iam auth for PHP < 8.0
* added examples
* updated saveToken function in Iam.php

## 1.5.4

* fixed jwt authentication

## 1.5.3

* removed query id in prepare statement

## 1.5.2

* fixed refresh token when it expired
* fixed retry at BAD_SESSION
* added credentials authentication
* added CI test

## 1.5.1

* added access token authentication

## 1.5.0 (2023-02-22)

### Features

- make protobuf
- updated protos
- JWT replacement


## 1.4.5 (2023-02-15)

### Bugs

- improved issue tree


## 1.4.4 (2023-02-15)

### Bugs

- uint64 type casting


## 1.4.3 (2023-02-15)

### Features

- anonymous authentication method
- insecure grpc connection


## 1.4.2 (2023-01-13)

### Bugs

- fixed lcobucci/jwt to 4.1.5 version


## 1.4.1 (2022-11-08)

### Features

- updated namespace


## 1.4.0 (2022-05-16)

### Features

- updated namespace


## 1.3.1 (2022-02-25)

### Features

- improved display of issue messages


## 1.3.0 (2022-02-25)

### Features

- introduced the query builder to customize the query settings


## 1.2.0 (2022-02-09)

### Features

- added PHP 8 support
- updated lcobucci/jwt to 4 version


## 1.1.1 (2021-11-01)

### Features

- implemented readTable option: key_range

### Bugs

- YdbType conversion fix for integers


## 1.1.0 (2021-10-26)

### Features

- implemented auth with metadata url

### Bugs

- implemented converting int8 & int16 to typed value


## 1.0.14 (2021-10-22)

### Features

- readTable options: limit, ordered

### Bugs

- createTable composite PK annotation
