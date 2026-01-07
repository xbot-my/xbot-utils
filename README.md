# XBot Utils

> A Symfony Console wrapper for executing Bash scripts as unified commands.

XBot Utils provides a clean, PHP-based interface to your existing Bash scripts, built on top of [Symfony Console](https://symfony.com/doc/current/components/console.html). No need to rewrite your Bash logic—just wrap it.

## Requirements

- PHP >= 8.3
- Composer

## Installation

```bash
composer install
```

## Usage

```bash
./bin/xbot <command>
```

### Available Commands

| Command | Description | Script |
|---------|-------------|--------|
| `clean` | Clean Laravel project temporary and sensitive data | `scripts/laravel/clean.sh` |
| `setup` | Initialize Laravel project setup | `scripts/laravel/setup.sh` |
| `sysinfo` | Display system information and environment details | `scripts/sysinfo.sh` |

### Examples

```bash
# Show system information
./bin/xbot sysinfo

# Clean Laravel project
./bin/xbot clean

# Setup Laravel project
./bin/xbot setup

# Get help for a specific command
./bin/xbot clean --help

# List all commands
./bin/xbot list
```

## Project Structure

```
.
├── bin/
│   └── xbot                    # Main executable
├── src/
│   ├── Command/
│   │   ├── BaseScriptCommand.php    # Base command class
│   │   ├── CleanCommand.php         # clean command
│   │   ├── SetupCommand.php         # setup command
│   │   └── SysinfoCommand.php       # sysinfo command
│   └── func.php                     # Helper functions
├── scripts/
│   ├── sysinfo.sh             # System info script
│   └── laravel/
│       ├── setup.sh           # Laravel setup script
│       ├── clean.sh           # Laravel clean script
│       └── service.sh         # Laravel service management
├── composer.json
└── README.md
```

## Development

### Adding a New Command

1. Create your Bash script in `scripts/`
2. Create a command class extending `BaseScriptCommand`:

```php
<?php

declare(strict_types=1);

namespace Xbot\Utils\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'your-command',
    description: 'Your command description'
)]
class YourCommand extends BaseScriptCommand
{
    protected function getScriptPath(): string
    {
        return 'scripts/your-script.sh';
    }

    protected function getStartMessage(): string
    {
        return 'Running your command...';
    }

    protected function getSuccessMessage(): string
    {
        return 'Your command completed successfully!';
    }
}
```

3. Register the command in `bin/xbot`:

```php
$app->addCommand(new YourCommand());
```

4. Run `composer dump-autoload` to regenerate the autoloader.

## Testing

The project uses [Pest](https://pestphp.com/) as the testing framework.

### Running Tests

```bash
# Run all tests
./vendor/bin/pest

# Run specific test suite
./vendor/bin/pest tests/Unit/Commands

# Run with detailed output
./vendor/bin/pest --compact
```

### Test Structure

```
tests/
├── Unit/
│   └── Commands/
│       ├── CommandTestHelpers.php    # Test helper utilities
│       ├── CleanCommandTest.php      # Tests for clean command
│       ├── SetupCommandTest.php      # Tests for setup command
│       └── SysinfoCommandTest.php    # Tests for sysinfo command
├── Feature/
│   └── ExampleTest.php
├── Pest.php                          # Pest configuration
└── TestCase.php                      # Base test case
```

### Test Coverage

Each command test covers:
- Command name and description verification
- Successful execution scenarios
- Script failure handling
- Error handling (script not found, not executable)

## License

MIT
