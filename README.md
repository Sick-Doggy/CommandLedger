# CommandLedger

[![GitHub](https://img.shields.io/badge/GitHub-arca--codicum%2FCommandLedger-181717?logo=github)](https://github.com/arca-codicum/CommandLedger)

Versioned files with Symfony console commands, executed once and tracked in
a Doctrine DBAL table — similar to Doctrine migrations, but they run console
commands and shell scripts instead of SQL.

Repository: <https://github.com/arca-codicum/CommandLedger>

## Requirements

- PHP 8.2+
- Symfony 5.4
- Doctrine DBAL 2.13 or 3.x (ORM is not required)

## Installation

If the package is available on Packagist:

```bash
composer require arca-codicum/command-ledger
```

Install directly from GitHub (before Packagist publication or when you need
the `main` branch):

```bash
composer config repositories.command-ledger vcs https://github.com/arca-codicum/CommandLedger
composer require arca-codicum/command-ledger:dev-main
```

Once a tag is available you can use a version constraint instead, for example
`composer require arca-codicum/command-ledger:^1.0`.

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    ArcaCodicum\CommandLedger\CommandLedgerBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/command_ledger.yaml
command_ledger:
    directory: '%kernel.project_dir%/command-files'
    table_name: 'command_ledger_executions'
    connection: 'default'
    console_path: '%kernel.project_dir%/bin/console'
    php_binary: null       # defaults to PHP_BINARY
    timeout: 300           # seconds per command; 0 = no limit
    output_limit: 65536    # bytes per stream
    environment: null      # defaults to the kernel environment
```

Create the ledger table:

```bash
bin/console command-ledger:setup
```

## File format

File name: `YYYYMMDDHHMMSS_description.php`. The class must be named
`Version{timestamp}{StudlyCase(description)}` and extend `AbstractCommandFile`.

```php
<?php

declare(strict_types=1);

use ArcaCodicum\CommandLedger\AbstractCommandFile;

final class Version20260101120000ClearCache extends AbstractCommandFile
{
    public function up(): void
    {
        $this->execute('cache:clear');
    }

    public function down(): void
    {
        // optional; leave empty when the operation is irreversible
    }
}
```

### Symfony console commands

`execute()` accepts positional arguments and options:

```php
$this->execute('app:import', ['products.csv'], ['--limit' => '100', '--dry-run' => true]);
```

- Positional arguments are appended after the command name.
- Option keys may omit leading `--`; `true` produces `--flag`, `false` and
  `null` skip the option, scalars produce `--key=value`, and arrays repeat the
  option for every value.

### Bash scripts and shell commands

```php
$this->executeScript('scripts/import.sh', ['products.csv', '--dry-run']);
$this->executeBash('ls -la | grep .csv');
```

- `executeScript(string $scriptPath, array $arguments = [])` runs
  `bash <script> <arguments...>`. The path is resolved relative to the process
  working directory; arguments are passed as separate argv elements, so they
  are not interpreted by the shell. A missing or unreadable script throws
  `CommandLedgerException` before any process is started.
- `executeBash(string $command)` runs `bash -c <command>` and intentionally
  interprets shell metacharacters. Use it only in trusted command files.
- Both return `CommandResult`, throw `CommandFailedException` on a non-zero
  exit code or timeout, and are recorded in `results()` exactly like
  `execute()`.

### Failure behaviour

A non-zero exit code or timeout aborts `up()` with an exception and marks the
file as `failed`.

## Commands

| Command | Description |
|---|---|
| `command-ledger:setup` | Creates the ledger table if it does not exist. |
| `command-ledger:migrate` | Runs new, failed and abandoned files; stops on the first failure. |
| `command-ledger:status` | Shows file statuses; `--verbose` adds captured output for failures. |
| `command-ledger:rollback` | Runs `down()` and removes records; `--steps=N` or `--to=VERSION`. |
| `command-ledger:generate` | Creates a new command file with the current timestamp. |

## Statuses

- `pending` — new file,
- `success` — completed successfully,
- `failed` — failed and will be retried by the next `migrate`,
- `running` — currently executing,
- `stale running` — probably an interrupted process; will be retried,
- `missing` — a database record without a matching file.

## Rollback

`rollback` reverts the latest successful file by default. `--steps=2` reverts
two files, and `--to=VERSION` reverts everything newer than the given version
(the version itself stays applied). A record is removed only after a successful
`down()`; a `down()` failure leaves the record untouched and stops the
rollback.

## Output and logs

The ledger stores the exit code, timestamps, duration, and the tail of stdout
and stderr (truncated to `output_limit`). Full output is written to the PSR-3
log at the `debug` level.

## Security

The command directory is trusted application code — `require` executes
arbitrary PHP, just like Doctrine migrations. Do not point it at a directory
writable by untrusted users. `executeBash()` deliberately passes its command to
`bash -c`, so shell metacharacters are interpreted; use it only in trusted
command files.

## Examples

Ready-to-copy files:

- `examples/command-files/20260101120000_clear_cache.php` — `execute()`,
- `examples/command-files/20260101120500_import_products.php` — positional
  arguments and options,
- `examples/command-files/20260101121000_generate_reports.php` —
  `executeScript()` and `executeBash()`,
- `examples/scripts/build-report.sh` — example script used by the report
  command file.

## Source and issues

- Repository: <https://github.com/arca-codicum/CommandLedger>
- Bug reports and feature requests: <https://github.com/arca-codicum/CommandLedger/issues>

## Development

The project targets PHP 8.2 and Symfony 5.4. Composer scripts are available:

```bash
composer test       # PHPUnit
composer stan       # PHPStan (level 8)
composer cs         # PHP-CS-Fixer (applies fixes)
composer cs:check   # PHP-CS-Fixer (check only)
```

If the host has no PHP or Composer, use Docker:

```bash
docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer \
  -v "$PWD":/app -w /app composer:2 install

docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp \
  -v "$PWD":/app -w /app php:8.2-cli php vendor/bin/phpunit
```

CI (`.github/workflows/ci.yml`) runs PHPUnit, PHPStan and PHP-CS-Fixer on
PHP 8.2 with a DBAL 2.13/3.x matrix.

## License

MIT — see the [LICENSE](LICENSE) file.
