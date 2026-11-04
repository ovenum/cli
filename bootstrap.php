<?php

namespace Kirby\CLI;

use InvalidArgumentException;
use League\CLImate\Argument\Manager;

/**
 * Tries to load the kirby index.php
 */
function bootstrap(): string|null
{
	if ($index = index()) {
		// switch off the renderer to
		// avoid any output in the CLI
		$_ENV['KIRBY_RENDER'] = false;

		if ($host = getenv('KIRBY_HOST')) {
			$_SERVER['SERVER_NAME'] = $host;
			$_SERVER['HTTP_HOST']   = $host;
		}

		ob_start();
		require $index;
		ob_end_clean();

		// rendering could be useful in commands
		// again. That's why we switch it back on
		$_ENV['KIRBY_RENDER'] = true;
	}

	return $index;
}

/**
 * Returns the absolute path
 * to the Kirby index.php if it
 * can be found
 */
function index(): string|null
{
	$locations = [
		'./',
		'./www',
		'./public',
		'./public_html'
	];

	foreach ($locations as $location) {
		// try to find the index.php in current working directory
		$index = realpath(getcwd() . '/' . $location . '/index.php');

		if ($index !== false) {
			return $index;
		}

		// try to find the index.php from the (possible) root of the project
		// in the __ROOT__ <- /vendor/getkirby/cli directory
		$index = realpath(dirname(__DIR__, 3) . '/' . $location . '/index.php');

		if ($index !== false) {
			return $index;
		}
	}

	return null;
}

/**
 * Resolves the Kirby installation path from
 * the --path/-p option or the KIRBY_PATH env variable.
 * Returns null if neither one is given.
 *
 * @throws InvalidArgumentException if the path is empty or does not exist
 */
function path(array $argv): string|null
{
	$arguments = new Manager();
	$arguments->add([
		'path' => [
			'prefix'     => 'p',
			'longPrefix' => 'path'
		],
		'quiet' => [
			'longPrefix' => 'quiet',
			'noValue'    => true
		],
		'debug' => [
			'prefix'     => 'd',
			'longPrefix' => 'debug',
			'noValue'    => true
		],
		'help' => [
			'prefix'     => 'h',
			'longPrefix' => 'help',
			'noValue'    => true
		]
	]);

	$arguments->parse($argv);

	if ($arguments->provided('path') === true) {
		$path = $arguments->get('path');

		if ($path === '') {
			throw new InvalidArgumentException('The --path parameter cannot be empty when provided.');
		}
	} else {
		$path = getenv('KIRBY_PATH');

		if ($path === false || $path === '') {
			return null;
		}
	}

	// expand a leading tilde; the shell only does that
	// at the start of a word, so --path=~/site arrives literally
	if ($path === '~' || str_starts_with($path, '~/') === true) {
		$path = getenv('HOME') . substr($path, 1);
	}

	// Resolve relative path against the current working directory.
	// It will return false for missing dirs.
	$resolved = realpath($path);

	if ($resolved === false || is_dir($resolved) === false) {
		throw new InvalidArgumentException('The path "' . $path . '" does not exist.');
	}

	return $resolved;
}
