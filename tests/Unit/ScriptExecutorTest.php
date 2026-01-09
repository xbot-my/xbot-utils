<?php

use Xbot\Utils\ScriptExecutor;

beforeEach(function () {
    // 创建临时测试目录
    $this->tempDir = sys_get_temp_dir() . '/xbot_test_' . uniqid();
    mkdir($this->tempDir . '/scripts', 0755, true);

    // 创建测试脚本
    $this->testScript = $this->tempDir . '/scripts/test.sh';
    file_put_contents(
        $this->testScript,
        "#!/bin/bash\necho 'Hello from test script'\necho 'Error output' >&2\nexit 0"
    );
    chmod($this->testScript, 0755);
});

afterEach(function () {
    // 清理临时文件
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file) : unlink($file);
        }
        rmdir($this->tempDir);
    }
});

test('script executor executes valid script successfully', function () {
    $executor = new ScriptExecutor($this->tempDir, ['scripts']);
    $result = $executor->execute('scripts/test.sh');

    expect($result['exitCode'])->toBe(0);
    expect($result['stdout'])->toContain('Hello from test script');
    expect($result['stderr'])->toContain('Error output');
});

test('script executor passes arguments correctly', function () {
    $script = $this->tempDir . '/scripts/args.sh';
    file_put_contents(
        $script,
        "#!/bin/bash\nfor arg in \"\$@\"; do echo \"Argument: \$arg\"; done\nexit 0"
    );
    chmod($script, 0755);

    $executor = new ScriptExecutor($this->tempDir, ['scripts']);
    $result = $executor->execute('scripts/args.sh', ['hello', 'world']);

    expect($result['exitCode'])->toBe(0);
    expect($result['stdout'])->toContain('Argument: hello');
    expect($result['stdout'])->toContain('Argument: world');
});

test('script executor rejects path traversal attacks', function () {
    $executor = new ScriptExecutor($this->tempDir, ['scripts']);

    // 尝试路径遍历攻击
    expect(fn() => $executor->execute('../../../etc/passwd'))
        ->toThrow(RuntimeException::class)
        ->and(fn() => $executor->execute('scripts/../../../../etc/passwd'))
        ->toThrow(RuntimeException::class);
});

test('script executor rejects command injection in arguments', function () {
    $executor = new ScriptExecutor($this->tempDir, ['scripts']);

    // 尝试命令注入
    expect(fn() => $executor->execute('scripts/test.sh', ['; rm -rf /']))
        ->toThrow(RuntimeException::class, 'dangerous characters');

    expect(fn() => $executor->execute('scripts/test.sh', ['| cat /etc/passwd']))
        ->toThrow(RuntimeException::class, 'dangerous characters');

    expect(fn() => $executor->execute('scripts/test.sh', ['&& echo hacked']))
        ->toThrow(RuntimeException::class, 'dangerous characters');

    expect(fn() => $executor->execute('scripts/test.sh', ['`whoami`']))
        ->toThrow(RuntimeException::class, 'dangerous characters');

    expect(fn() => $executor->execute('scripts/test.sh', ['$(ls)']))
        ->toThrow(RuntimeException::class, 'dangerous characters');
});

test('script executor validates path is within allowed directories', function () {
    $executor = new ScriptExecutor($this->tempDir, ['scripts']);

    // 创建一个在允许目录外的脚本
    $outsideScript = $this->tempDir . '/outside.sh';
    file_put_contents($outsideScript, "#!/bin/bash\necho 'outside'\nexit 0");
    chmod($outsideScript, 0755);

    expect(fn() => $executor->execute('outside.sh'))
        ->toThrow(RuntimeException::class, 'not in allowed directories');
});

test('script executor rejects non-executable scripts', function () {
    $nonExecutable = $this->tempDir . '/scripts/noexec.sh';
    file_put_contents($nonExecutable, "#!/bin/bash\necho 'no exec'");
    // 不设置可执行权限

    $executor = new ScriptExecutor($this->tempDir, ['scripts']);

    expect(fn() => $executor->execute('scripts/noexec.sh'))
        ->toThrow(RuntimeException::class, 'not executable');
});

test('script executor rejects non-existent scripts', function () {
    $executor = new ScriptExecutor($this->tempDir, ['scripts']);

    expect(fn() => $executor->execute('scripts/nonexistent.sh'))
        ->toThrow(\RuntimeException::class, 'resolution failed');
});

test('script executor handles script with non-zero exit code', function () {
    $errorScript = $this->tempDir . '/scripts/error.sh';
    file_put_contents($errorScript, "#!/bin/bash\necho 'Error occurred' >&2\nexit 42");
    chmod($errorScript, 0755);

    $executor = new ScriptExecutor($this->tempDir, ['scripts']);
    $result = $executor->execute('scripts/error.sh');

    expect($result['exitCode'])->toBe(42);
    expect($result['stderr'])->toContain('Error occurred');
});

test('script executor enforces timeout', function () {
    $hangScript = $this->tempDir . '/scripts/hang.sh';
    file_put_contents($hangScript, "#!/bin/bash\nsleep 100\necho 'done'");
    chmod($hangScript, 0755);

    $executor = new ScriptExecutor($this->tempDir, ['scripts']);
    $executor->setTimeout(2); // 2 秒超时

    expect(fn() => $executor->execute('scripts/hang.sh'))
        ->toThrow(RuntimeException::class, 'timeout');
});

test('script executor scriptExists checks correctly', function () {
    $executor = new ScriptExecutor($this->tempDir, ['scripts']);

    expect($executor->scriptExists('scripts/test.sh'))->toBeTrue();
    expect($executor->scriptExists('scripts/nonexistent.sh'))->toBeFalse();
});

test('script executor allows safe arguments', function () {
    $executor = new ScriptExecutor($this->tempDir, ['scripts']);

    // 这些应该是安全的
    expect(fn() => $executor->execute('scripts/test.sh', ['normal-arg']))
        ->not->toThrow(RuntimeException::class);

    expect(fn() => $executor->execute('scripts/test.sh', ['arg_with_underscore']))
        ->not->toThrow(RuntimeException::class);

    expect(fn() => $executor->execute('scripts/test.sh', ['arg.with.dots']))
        ->not->toThrow(RuntimeException::class);

    expect(fn() => $executor->execute('scripts/test.sh', ['/path/to/file']))
        ->not->toThrow(RuntimeException::class);
});
