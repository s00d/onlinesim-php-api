<?php

declare(strict_types=1);

/*
 * Smoke test of the PUBLISHED artifact. Packagist/Composer ship the `git archive` of a tag, so: archive the
 * working tree (tracked + untracked, .gitignore and .gitattributes export-ignore applied, the real index untouched),
 * check it holds only src/ + README/LICENSE/CHANGELOG/composer.json, install it as a path repository into an empty
 * Composer project (packagist disabled: fully offline) and run the README "Testing" example against MockOnlineSim.
 *
 *     php tests/smoke-artifact.php
 */

$root = dirname(__DIR__);

/**
 * Runs `$cmd` in `$cwd`; returns its output (stdout + stderr), throws on a non-zero exit.
 *
 * @param list<string>          $cmd
 * @param array<string, string> $env extra environment variables
 */
function run(array $cmd, string $cwd, array $env = []): string
{
    $line = implode(' ', array_map(escapeshellarg(...), $cmd));
    $process = proc_open("{$line} 2>&1", [1 => ['pipe', 'w']], $pipes, $cwd, $env === [] ? null : $env + getenv());
    if (!is_resource($process)) {
        throw new RuntimeException('cannot start ' . implode(' ', $cmd));
    }
    $out = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $code = proc_close($process);
    if ($code !== 0) {
        throw new RuntimeException("{$line} exited with {$code}\n{$out}");
    }

    return $out;
}

/** @phpstan-assert true $ok */
function check(bool $ok, string $what): void
{
    if (!$ok) {
        throw new RuntimeException("smoke: {$what}");
    }
    echo "ok: {$what}\n";
}

function remove(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                remove("{$path}/{$entry}");
            }
        }
        rmdir($path);
    } elseif (file_exists($path) || is_link($path)) {
        unlink($path);
    }
}

/** @return list<string> paths relative to `$dir`, sorted */
function files(string $dir): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file instanceof SplFileInfo && $file->isFile()) {
            $out[] = substr($file->getPathname(), strlen($dir) + 1);
        }
    }
    sort($out);

    return $out;
}

$tmp = sys_get_temp_dir() . '/onlinesim-php-smoke-' . getmypid();
remove($tmp);
mkdir("{$tmp}/package", 0o777, true);
mkdir("{$tmp}/app");

try {
    // 1. The archive Composer would download for this tree.
    $index = ['GIT_INDEX_FILE' => "{$tmp}/index"];
    run(['git', 'read-tree', 'HEAD'], $root, $index);
    run(['git', 'add', '-A'], $root, $index);
    $tree = trim(run(['git', 'write-tree'], $root, $index));
    run(['git', 'archive', '--format=tar', '-o', "{$tmp}/package.tar", $tree], $root);
    run(['tar', '-xf', "{$tmp}/package.tar", '-C', "{$tmp}/package"], $root);

    $files = files("{$tmp}/package");
    $stray = array_values(array_filter($files, static fn (string $f) => preg_match('#^(src/.+\.php|README\.md|LICENSE|CHANGELOG\.md|composer\.json)$#', $f) !== 1));
    check($stray === [], 'archive holds only src/, README.md, LICENSE, CHANGELOG.md, composer.json' . ($stray === [] ? '' : ': stray ' . implode(', ', $stray)));
    foreach (['composer.json', 'README.md', 'LICENSE', 'CHANGELOG.md', 'src/OnlineSim.php', 'src/Mock/MockOnlineSim.php'] as $required) {
        check(in_array($required, $files, true), "archive contains {$required}");
    }
    echo 'archived ', count($files), " files\n";

    // 2. Install it into an empty project, offline.
    $changelog = (string) file_get_contents("{$tmp}/package/CHANGELOG.md");
    check(preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $m) === 1, 'CHANGELOG.md starts with a version entry');
    $version = $m[1];
    $app = [
        'name' => 'smoke/app',
        'type' => 'project',
        'require' => ['s00d/onlinesim-api' => $version],
        'repositories' => [
            ['type' => 'path', 'url' => "{$tmp}/package", 'options' => ['symlink' => false, 'versions' => ['s00d/onlinesim-api' => $version]]],
            ['packagist.org' => false],
        ],
        'config' => ['lock' => false],
    ];
    file_put_contents("{$tmp}/app/composer.json", json_encode($app, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    echo run(['composer', 'install', '--no-interaction', '--no-progress', '--no-dev'], "{$tmp}/app", ['COMPOSER' => 'composer.json']);
    check(!is_dir("{$tmp}/app/vendor/s00d/onlinesim-api/tests"), "installed s00d/onlinesim-api {$version} without tests/");

    // 3. The README "Testing" example, from the installed copy, with assertions on.
    $readme = (string) file_get_contents("{$tmp}/app/vendor/s00d/onlinesim-api/README.md");
    check(preg_match('/^## Testing\n.*?```php\n(.*?)```/ms', $readme, $m) === 1, 'README.md has a "## Testing" section with a php example');
    // Any warning, notice or deprecation raised by the package fails the run (PHP start-up noise of the host does not).
    $prelude = <<<'PHP'
        <?php

        declare(strict_types=1);

        error_reporting(E_ALL);
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        require __DIR__ . '/vendor/autoload.php';

        PHP;
    file_put_contents("{$tmp}/app/readme-testing.php", "{$prelude}\n{$m[1]}\necho \"done\\n\";\n");
    $out = run([PHP_BINARY, '-d', 'zend.assertions=1', '-d', 'assert.exception=1', 'readme-testing.php'], "{$tmp}/app");
    echo $out;
    $lines = explode("\n", trim($out));
    check(end($lines) === 'done', 'README "Testing" example runs against the installed package (PHP ' . PHP_VERSION . ')');
} finally {
    remove($tmp);
}
