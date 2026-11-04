<?php

declare(strict_types = 1);

namespace Kirby\CLI;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversFunction('bootstrap')]
#[CoversFunction('index')]
#[CoversFunction('path')]
class BootstrapTest extends TestCase
{
	protected string|null $cwd = null;

	protected function setUp(): void
	{
		$this->cwd = getcwd();
	}

	protected function tearDown(): void
	{
		if ($this->cwd !== null) {
			chdir($this->cwd);
			$this->cwd = null;
		}

		putenv('KIRBY_PATH');

		parent::tearDown();
	}

	public function testIndexInPublic(): void
	{
		chdir($root = __DIR__ . '/fixtures/bootstrap/c');
		$this->assertSame($root . '/public/index.php', index());
		$this->assertSame($root . '/public/index.php', bootstrap());
	}

	public function testIndexInPublicHtml(): void
	{
		chdir($root = __DIR__ . '/fixtures/bootstrap/d');
		$this->assertSame($root . '/public_html/index.php', index());
		$this->assertSame($root . '/public_html/index.php', bootstrap());
	}

	public function testIndexInRoot(): void
	{
		chdir($root = __DIR__ . '/fixtures/bootstrap/a');
		$this->assertSame($root . '/index.php', index());
		$this->assertSame($root . '/index.php', bootstrap());
	}

	public function testIndexInWww(): void
	{
		chdir($root = __DIR__ . '/fixtures/bootstrap/b');
		$this->assertSame($root . '/www/index.php', index());
		$this->assertSame($root . '/www/index.php', bootstrap());
	}

	public static function pathArgvProvider(): array
	{
		$root = __DIR__ . '/fixtures/bootstrap/a';

		return [
			// long option with equals sign
			[['kirby', 'version', '--path=' . $root]],
			// long option with space
			[['kirby', 'version', '--path', $root]],
			// short option with space
			[['kirby', 'version', '-p', $root]],
			// short option with equals sign
			[['kirby', 'version', '-p=' . $root]],
			// after positional arguments
			[['kirby', 'clear:cache', 'pages', '--path', $root]],
			// next to other options
			[['kirby', 'version', '--debug', '--path', $root, '--quiet']],
		];
	}

	#[DataProvider('pathArgvProvider')]
	public function testPath(array $argv): void
	{
		$this->assertSame(__DIR__ . '/fixtures/bootstrap/a', path($argv));
	}

	public function testPathAfterTrailingDelimiter(): void
	{
		$this->assertNull(path(['kirby', 'version', '--', '--path', 'bogus']));
		$this->assertNull(path(['kirby', 'version', '--', '--path']));
	}

	public static function emptyPathProvider(): array
	{
		return [
			// empty value with equals sign
			[['kirby', 'version', '--path=']],
			[['kirby', 'version', '-p=']],
			// bare option followed by another global option, which the
			// early parser knows and therefore does not take as the value
			[['kirby', 'version', '--path', '--debug']],
		];
	}

	#[DataProvider('emptyPathProvider')]
	public function testPathEmpty(array $argv): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The --path parameter cannot be empty when provided.');

		path($argv);
	}

	public function testPathFromEmptyEnv(): void
	{
		putenv('KIRBY_PATH=');
		$this->assertNull(path(['kirby', 'version']));
	}

	public function testPathFromEnv(): void
	{
		putenv('KIRBY_PATH=' . __DIR__ . '/fixtures/bootstrap/b');
		$this->assertSame(__DIR__ . '/fixtures/bootstrap/b', path(['kirby', 'version']));
	}

	public function testPathMissingDirectory(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The path "/does/not/exist" does not exist.');

		path(['kirby', 'version', '--path', '/does/not/exist']);
	}

	public function testPathMissingDirectoryFromEnv(): void
	{
		putenv('KIRBY_PATH=/does/not/exist');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The path "/does/not/exist" does not exist.');

		path(['kirby', 'version']);
	}

	public function testPathOptionBeatsEnv(): void
	{
		putenv('KIRBY_PATH=' . __DIR__ . '/fixtures/bootstrap/b');
		$this->assertSame(
			__DIR__ . '/fixtures/bootstrap/a',
			path(['kirby', 'version', '--path', __DIR__ . '/fixtures/bootstrap/a'])
		);
	}

	public function testPathRelative(): void
	{
		chdir(__DIR__ . '/fixtures');
		$this->assertSame(__DIR__ . '/fixtures/bootstrap/a', path(['kirby', '--path', 'bootstrap/a']));
		$this->assertSame(__DIR__ . '/fixtures/bootstrap/a', path(['kirby', '--path', './bootstrap/../bootstrap/a']));
		$this->assertSame(__DIR__ . '/fixtures', path(['kirby', '--path', '.']));
	}

	public function testPathTilde(): void
	{
		$this->assertSame(realpath(getenv('HOME')), path(['kirby', '--path', '~']));
		$this->assertSame(realpath(getenv('HOME')), path(['kirby', '--path=~/']));
	}

	public function testPathWithFile(): void
	{
		$file = __DIR__ . '/fixtures/bootstrap/a/index.php';

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The path "' . $file . '" does not exist.');

		path(['kirby', 'version', '--path', $file]);
	}

	public function testPathWithoutOption(): void
	{
		$this->assertNull(path(['kirby', 'version']));
		$this->assertNull(path(['kirby']));
	}
}
