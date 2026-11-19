<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Tests;

use s00d\OnlineSimApi\OnlineSim;
use s00d\OnlineSimApi\Transport\CurlTransport;

/**
 * Checks the PHP examples of README.md and docs/content/*.md against src/, so the docs cannot drift from the API.
 * A ```php block counts as an example when it uses `$client` or calls into the SDK; signature blocks (method
 * declarations without a body) are skipped. Each example must parse, and every SDK call in it must name an existing
 * method with existing named arguments.
 */
final class DocsExamplesTest extends TestCase
{
    /** Callables an example may reach: `new X(` and functions, mapped to reflection targets. */
    private const ENTRY_POINTS = [
        'new OnlineSim' => [OnlineSim::class, '__construct'],
        'new CurlTransport' => [CurlTransport::class, '__construct'],
        'parseWebhook' => 's00d\OnlineSimApi\parseWebhook',
    ];

    private const SECTIONS = [
        'numbers' => \s00d\OnlineSimApi\Numbers::class,
        'rent' => \s00d\OnlineSimApi\Rent::class,
        'user' => \s00d\OnlineSimApi\User::class,
        'free' => \s00d\OnlineSimApi\Free::class,
    ];

    /** @return array<string, string> source:index → code */
    private static function examples(): array
    {
        $root = dirname(__DIR__);
        $sources = ['README.md', ...array_map(static fn (string $f) => "docs/content/{$f}", scandir("{$root}/docs/content") ?: [])];
        $examples = [];
        foreach ($sources as $source) {
            if (!str_ends_with($source, '.md')) {
                continue;
            }
            preg_match_all('/```php[^\n]*\n(.*?)```/s', (string) file_get_contents("{$root}/{$source}"), $blocks);
            foreach ($blocks[1] as $i => $code) {
                if (preg_match('/^\s*(public )?function \w+\(.*\)(: [^{]+)?;?\s*$/m', $code) === 1 && !str_contains($code, '$client')) {
                    continue; // a signature
                }
                $examples["{$source}:{$i}"] = $code;
            }
        }

        return $examples;
    }

    public function testDocsExamplesParseAndCallExistingMethodsWithExistingNamedArguments(): void
    {
        $examples = self::examples();
        self::assertGreaterThan(20, count($examples));

        $errors = [];
        foreach ($examples as $where => $code) {
            $source = str_starts_with(ltrim($code), '<?php') ? $code : "<?php\n{$code}";
            try {
                $tokens = \PhpToken::tokenize($source, TOKEN_PARSE);
            } catch (\ParseError $e) {
                $errors[] = "{$where}: {$e->getMessage()}\n{$code}";
                continue;
            }
            foreach (self::calls($tokens) as [$label, $target, $named]) {
                try {
                    $function = is_array($target) ? new \ReflectionMethod($target[0], $target[1]) : new \ReflectionFunction($target);
                } catch (\ReflectionException) {
                    $errors[] = "{$where}: unknown {$label}";
                    continue;
                }
                $params = array_map(static fn (\ReflectionParameter $p) => $p->getName(), $function->getParameters());
                foreach (array_diff($named, $params) as $unknown) {
                    $errors[] = "{$where}: {$label} has no parameter \${$unknown}";
                }
            }
        }
        self::assertSame([], $errors);
    }

    /**
     * SDK calls in a token stream: `$client->section->method(`, `new OnlineSim(`, `parseWebhook(`, …
     *
     * @param array<\PhpToken> $tokens
     *
     * @return list<array{string, array{string, string}|string, list<string>}> label, reflection target, named args
     */
    private static function calls(array $tokens): array
    {
        $tokens = array_values(array_filter($tokens, static fn (\PhpToken $t) => !$t->isIgnorable()));
        $calls = [];
        foreach ($tokens as $i => $token) {
            $target = null;
            $label = '';
            $open = 0;
            if ($token->is(T_VARIABLE) && $token->text === '$client' && self::text($tokens, $i + 1, 5) === ['->', '?', '->', '?', '(']) {
                $section = $tokens[$i + 2]->text;
                $method = $tokens[$i + 4]->text;
                $label = "\$client->{$section}->{$method}()";
                $target = [self::SECTIONS[$section] ?? 'unknown section', $method];
                $open = $i + 5;
            } elseif ($token->is(T_NEW) && isset($tokens[$i + 2]) && $tokens[$i + 2]->text === '(') {
                $label = "new {$tokens[$i + 1]->text}";
                $target = self::ENTRY_POINTS[$label] ?? null;
                $open = $i + 2;
            } elseif ($token->is(T_STRING) && $token->text === 'parseWebhook' && ($tokens[$i + 1]->text ?? '') === '(') {
                $label = 'parseWebhook';
                $target = self::ENTRY_POINTS[$label];
                $open = $i + 1;
            }
            if ($target === null) {
                continue;
            }
            // Named arguments at depth 1: `name` followed by `:` (not `::`).
            $named = [];
            $depth = 0;
            for ($j = $open, $n = count($tokens); $j < $n; ++$j) {
                $text = $tokens[$j]->text;
                if (in_array($text, ['(', '[', '{'], true)) {
                    ++$depth;
                } elseif (in_array($text, [')', ']', '}'], true) && --$depth === 0) {
                    break;
                } elseif ($depth === 1 && $tokens[$j]->is(T_STRING) && ($tokens[$j + 1]->text ?? '') === ':' && in_array($tokens[$j - 1]->text, ['(', ','], true)) {
                    $named[] = $text;
                }
            }
            $calls[] = [$label, $target, $named];
        }

        return $calls;
    }

    /**
     * Texts of `$count` tokens from `$from`, with identifiers replaced by `?`.
     *
     * @param list<\PhpToken> $tokens
     *
     * @return list<string>
     */
    private static function text(array $tokens, int $from, int $count): array
    {
        $out = [];
        for ($k = $from; $k < $from + $count; ++$k) {
            $t = $tokens[$k] ?? null;
            $out[] = $t === null ? '' : ($t->is(T_STRING) ? '?' : $t->text);
        }

        return $out;
    }
}
