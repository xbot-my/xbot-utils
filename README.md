# XBot Utils

> A powerful Symfony Console wrapper for managing development tasks with extensible plugin system.

[![Latest Version](https://img.shields.io/github/v/release/xbot-my/xbot-utils)](https://github.com/xbot-my/xbot-utils/releases)
[![License](https://img.shields.io/github/license/xbot-my/xbot-utils)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue.svg)](https://php.net)
[![Symfony](https://img.shields.io/badge/symfony-console-green.svg)](https://symfony.com/doc/current/components/console.html)

XBot Utils provides a unified PHP-based command-line interface for common development workflows, built on top of [Symfony Console](https://symfony.com/doc/current/components/console.html).

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

## Available Commands

### Laravel Commands

| Command | Description |
|---------|-------------|
| `clean` | Clean Laravel project temporary and sensitive data |
| `setup` | Initialize Laravel project setup |
| `service` | Manage Laravel services (start/stop/restart/status for web, queue, schedule, horizon, echo) |
| `db` | Database management (migrate, backup, restore, test) |

### System Commands

| Command | Description |
|---------|-------------|
| `sysinfo` | Display system information and environment details |

### Core Commands

| Command | Description |
|---------|-------------|
| `config` | Manage xbot configuration (set, get, list, edit) |
| `schedule` | Manage scheduled tasks/cron jobs (list, add, remove, run, enable, disable, sync) |
| `plugin` | Manage xbot plugins (list, enable, disable, install, uninstall, info) |
| `logs` | View and manage xbot logs |

### Utility Commands

| Command | Description |
|---------|-------------|
| `info` | Display detailed information about a command |
| `search` | Search for commands by keyword |

## Examples

### Laravel Development

```bash
# Initialize Laravel project
./bin/xbot setup

# Clean temporary files
./bin/xbot clean

# Manage services
./bin/xbot service start
./bin/xbot service status
./bin/xbot service restart

# Database operations
./bin/xbot db migrate
./bin/xbot db backup
./bin/xbot db restore --file=backup.sql
./bin/xbot db test
```

### Configuration Management

```bash
# List all configuration
./bin/xbot config list

# Set configuration value
./bin/xbot config set output.color true

# Get specific configuration
./bin/xbot config get output.color

# Edit configuration file
./bin/xbot config edit

# Use global configuration
./bin/xbot config set script.timeout 300 --global

# Output as JSON
./bin/xbot config list --json
```

### Scheduled Tasks

```bash
# List all scheduled tasks
./bin/xbot schedule list

# Add a new scheduled task
./bin/xbot schedule add --id daily-backup --cron "0 0 * * *" --command "./bin/xbot db backup"

# Run a task manually
./bin/xbot schedule run daily-backup

# Enable/disable a task
./bin/xbot schedule disable daily-backup
./bin/xbot schedule enable daily-backup

# Synchronize with system crontab
./bin/xbot schedule sync

# Remove a task
./bin/xbot schedule remove daily-backup
```

### Plugin Management

```bash
# List installed plugins
./bin/xbot plugin list

# Install a plugin from Git
./bin/xbot plugin install https://github.com/user/xbot-plugin.git

# Install from local path
./bin/xbot plugin install /path/to/plugin

# Enable/disable plugins
./bin/xbot plugin enable my-plugin
./bin/xbot plugin disable my-plugin

# View plugin information
./bin/xbot plugin info my-plugin

# Uninstall a plugin
./bin/xbot plugin uninstall my-plugin
```

### Log Management

```bash
# View recent logs
./bin/xbot logs

# Follow logs in real-time
./bin/xbot logs --tail

# Show last 100 lines
./bin/xbot logs --lines=100

# Filter by log level
./bin/xbot logs --level=ERROR

# List all log files
./bin/xbot logs --all

# Clear log file
./bin/xbot logs --clear
```

### System Information

```bash
# Display system info
./bin/xbot sysinfo
```

### Command Discovery

```bash
# Search for commands
./bin/xbot search database

# Get detailed info about a command
./bin/xbot info clean

# List all commands
./bin/xbot list

# Get help for specific command
./bin/xbot clean --help
```

## Project Structure

```
.
├── bin/
│   └── xbot                          # Main executable
├── src/
│   ├── Command/
│   │   ├── BaseScriptCommand.php     # Base command class
│   │   ├── CleanCommand.php          # clean command
│   │   ├── SetupCommand.php          # setup command
│   │   ├── SysinfoCommand.php        # sysinfo command
│   │   ├── ServiceCommand.php        # service command
│   │   ├── DatabaseCommand.php       # db command
│   │   ├── ConfigCommand.php         # config command
│   │   ├── ScheduleCommand.php       # schedule command
│   │   ├── PluginCommand.php         # plugin command
│   │   ├── LogCommand.php            # logs command
│   │   ├── InfoCommand.php           # info command
│   │   └── SearchCommand.php         # search command
│   ├── Config/
│   │   └── ConfigManager.php         # Configuration management
│   ├── Scheduler/
│   │   ├── Scheduler.php             # Cron scheduler
│   │   ├── Task.php                  # Task model
│   │   ├── TaskRepository.php        # Task storage
│   │   └── CronExpression.php        # Cron parser
│   ├── Plugin/
│   │   ├── PluginManager.php         # Plugin system
│   │   ├── PluginInterface.php       # Plugin contract
│   │   └── AbstractPlugin.php        # Plugin base class
│   ├── Logging/
│   │   └── Logger.php                # Logging utilities
│   ├── Output/
│   │   └── ProgressHelper.php        # Progress bar helper
│   ├── ScriptExecutor.php            # Script execution with security
│   └── func.php                      # Helper functions
├── scripts/
│   ├── sysinfo.sh                    # System info script
│   └── laravel/
│       ├── setup.sh                  # Laravel setup script
│       ├── clean.sh                  # Laravel clean script
│       └── service.sh                # Service management script
├── tests/
│   ├── Unit/
│   │   └── Commands/                 # Command tests
│   ├── Feature/
│   ├── Pest.php                      # Pest configuration
│   └── TestCase.php                  # Base test case
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

3. Register the command in `bin/xbot`
4. Run `composer dump-autoload` to regenerate the autoloader

### Creating Plugins

Create a plugin in `plugins/your-plugin/`:

```php
<?php

declare(strict_types=1);

namespace Xbot\Plugins\YourPlugin;

use Xbot\Utils\Plugin\AbstractPlugin;

class YourPlugin extends AbstractPlugin
{
    public function getName(): string
    {
        return 'your-plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Your plugin description';
    }

    public function register(): void
    {
        // Register commands, services, etc.
    }
}
```

## Configuration

XBot Utils supports both project-level and global configuration:

- **Project config**: `.xbot.json` in project root
- **Global config**: `~/.xbot.json`

Example configuration:

```json
{
    "output": {
        "color": true,
        "format": "default"
    },
    "script": {
        "timeout": 300,
        "allowedPaths": ["scripts/"]
    },
    "schedule": {
        "storagePath": "storage/schedule/"
    },
    "plugins": {
        "enabled": ["my-plugin"],
        "paths": ["plugins/"]
    }
}
```

## Testing

The project uses [Pest](https://pestphp.com/) as the testing framework.

```bash
# Run all tests
./vendor/bin/pest

# Run specific test suite
./vendor/bin/pest tests/Unit/Commands

# Run with detailed output
./vendor/bin/pest --compact
```

## Shell Completion

XBot Utils supports shell autocompletion for Bash, Zsh, and Fish.

### Bash

Add to `~/.bashrc`:
```bash
eval "$(/path/to/bin/xbot completion bash)"
```

Or install globally:
```bash
/path/to/bin/xbot completion bash | sudo tee /etc/bash_completion.d/xbot
source /etc/bash_completion.d/xbot
```

### Zsh

Add to `~/.zshrc`:
```bash
eval "$(/path/to/bin/xbot completion zsh)"
```

### Fish

Add to `~/.config/fish/config.fish`:
```bash
eval ("/path/to/bin/xbot completion fish" | source)
```

## License

MIT
