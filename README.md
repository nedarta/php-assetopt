# php-assetopt

Small, dependency-free PHP utility library that optimizes frontend assets (CSS and JavaScript) without external binaries such as the Java-based YUI Compressor or closure-compiler.

* **CSS**: whitespace-only minifier, a clean port of the YUI CSS compressor.
  Optionally resolves `@import` at-rules (full inlining, also remote stylesheets) before minifying.
* **JavaScript**: whitespace/comment stripper that keeps the code fully functional without renaming anything.
* **CLI**: a drop-in replacement for the compressor commands used in build tools and frameworks such as Yii2's AssetManager.

[![Latest Stable Version](https://poser.pugx.org/nedarta/php-assetopt/v/stable)](https://packagist.org/packages/nedarta/php-assetopt)
[![Total Downloads](https://poser.pugx.org/nedarta/php-assetopt/downloads)](https://packagist.org/packages/nedarta/php-assetopt)
[![Daily Downloads](https://poser.pugx.org/nedarta/php-assetopt/d/daily)](https://packagist.org/packages/nedarta/php-assetopt)
[![License](https://poser.pugx.org/nedarta/php-assetopt/license)](https://packagist.org/packages/nedarta/php-assetopt)

## Requirements

* PHP 8.2 or higher
* PCRE PHP extension

## Installation

```sh
composer require nedarta/php-assetopt
```

**Note:** `composer.phar install` is needed to run the tests, GUI or CLI utilities within the repository downloaded from GitHub.

## Usage

<a name="php"></a>

### PHP

```php
use nedarta\AssetOpt\Minifier;

$compressedCss = (new Minifier)->run($css);
```

```php
use nedarta\AssetOpt\JsStrip;

$compressedJs = (new JsStrip)->compress($js);
```

Resolving `@import` at-rules (local relative paths and remote `http/https` urls) before minifying:
```php
use nedarta\AssetOpt\ImportResolver;
use nedarta\AssetOpt\Minifier;

// css with @import at-rules
$css = file_get_contents('path/to/styles.css');

// resolve @import at-rules: $baseDir for relative @import urls
$css = (new ImportResolver)->resolve($css, 'path/to');

// minify resolved css
$compressedCss = (new Minifier)->run($css);
```

```php
use nedarta\AssetOpt\Minifier as CSSmin;

$cssmin = new Minifier([
    /* Raise PHP limits */
    'raise_php_limits' => true
]);

$cssmin->setMaxExecutionTime(30); // Set maximum execution time to 30 seconds by default
$cssmin->setPcreBacktrackLimit(2000000); // Set PCRE backtrack limit to 2 x 2 by default
$cssmin->setPcreRecursionLimit(500000); // Set PCRE recursion limit to 5 x 0.5 by default
$cssmin->keepSourceMapComment(); // Keeps sourcemap special comment in the output by default
$cssmin->removeImportantComments(); // Removes !important comments in the output by default
$cssmin->setLineBreakPosition(500); // Set line break position

$compressedCss = $cssmin->run($css);
```

<a name="cli"></a>

### CLI

The CLI allows both the option-style commands used by the original YUI Compressor port, and the positional `{from} {to}`-style commands used in e.g. Yii2's AssetManager.
Use `--type js` to strip JavaScript, or `--type css` (the default) to minify CSS:

```sh
$ cd <pkg-root-folder>
$ composer install
$ ./vendor/bin/assetopt -i ./my-css-file.css -o ./my-css-file.min.css
```

Output compression result to another file:
```sh
./vendor/bin/assetopt -i ./my-css-file.css -o ./my-css-file.min.css
```

Minify JavaScript:
```sh
./vendor/bin/assetopt --type js ./my-js-file.js -o ./my-js-file.min.js
```

Output minified CSS code to standard output:
```sh
./vendor/bin/assetopt -i ./my-css-file.css
```

YUI-compressor-style commands (the `--type` flag selects the pipeline, `--disable-optimizations` is accepted and ignored):
```sh
# the following commands are equivalent (css minification)
./vendor/bin/assetopt --type css ./my-css-file.css -o ./my-css-file.min.css
./vendor/bin/assetopt --type css --disable-optimizations -i ./my-css-file.css -o ./my-css-file.min.css

# JavaScript
./vendor/bin/assetopt --type js --disable-optimizations -i ./my-js-file.js -o ./my-js-file.min.js
```

Merge and minify several external CSS files into one file (files are processed in the given order, duplicate `@charset` rules are removed automatically):
```sh
./vendor/bin/assetopt -i ./reset.css -i ./layout.css -o ./main.min.css
```

Keep sourcemap comments in the output:
```sh
# note that this option only has an effect when minifying CSS
./vendor/bin/assetopt -i ./my-css-file.css -o ./my-css-file.min.css --keep-sourcemap
```

Remove `/*! ... */` important comments from the output:
```sh
./vendor/bin/assetopt -i ./my-css-file.css -o ./my-css-file.min.css --remove-important-comments
```

Inline stylesheets pointed to by `@import` at-rules (local relative paths and remote http/https urls) before minifying — imported stylesheets are resolved recursively, imported files declaring media queries are wrapped in the equivalent `@media` block:
```sh
./vendor/bin/assetopt -i ./my-css-file.css -o ./my-css-file.min.css --resolve-imports
```

See the binary help (`./vendor/bin/assetopt --help`) for all available options.

<a name="framework-integration"></a>

### Framework integration (Yii2 example)

Use the library's CLI as a drop-in replacement for legacy Java-based compressors. The asset bundle configuration itself stays unchanged — you only switch the compressor commands in the application config:

```php
/**
 * Simple Yii2 AssetBundle with minification of contents
 * of its $css and $js files in the production environment.
 */
class AppAsset extends AssetBundle
{
    public $sourcePath = '@app/assets';

    public $css = [
        'css/app.css',
        'https://fonts.googleapis.com/css?family=PT+Serif:400,700,400italic,700italic',
    ];

    public $js = [
        'js/app.js',
    ];

    public $depends = [
        \yii\web\JqueryAsset::class,
        \yii\bootstrap\BootstrapAsset::class,
    ];

    ...
}
```

Below the `assetManager` snippet to put in application config, e.g. `config/web.php`:

```php
'components' => [
    'assetManager' => [
        'forceCopy' => YII_DEBUG,
        'linkAssets' => true,
        // compress css and js assets of the app
        'compress' => !YII_DEBUG,
        'cssCompressor' => 'php vendor/bin/assetopt {from} -o {to}',
        'jsCompressor' => 'php vendor/bin/assetopt --type js {from} -o {to}',
    ],
],
```

This replaces the typical Java-tool configuration (taken as reference of what the above switch replaces):

```php
'components' => [
    'assetManager' => [
        // ... the settings above can replace tools such as:
        'cssCompressor' => 'java -jar yuicompressor-2.4.8.jar --disable-optimizations --type css {from} -o {to}',
        'jsCompressor' => 'java -jar closure-compiler-v20250820.jar --js {from} --js_output_file {to} --warning_level=QUIET',
    ],
],
```

Notes for Yii2:

* `cssCompressor` / `jsCompressor` receive `{from}` and `{to}` absolute paths; assetopt treats the input as a positional argument and `-o {to}` as the output option, matching both templates shown above.
* `--warning_level=QUIET` and `--disable-optimizations` style flags are accepted and ignored, so a previous configuration can keep working almost verbatim.
* closure-compiler removes dead/redundant code and renames symbols, assetopt never alters code semantics — if you rely on those optimizations consider keeping esbuild/terser around for JS and using `assetopt` for CSS only.

<a name="gui"></a>

### GUI

We've made a simple web based GUI to use the compressor, it's in the `gui` folder.

GUI features:

* Optional on-the-fly LESS compilation before compression with error reporting included.
* Absolute control of the library.

How to use the GUI:

* You need a server with PHP 8.2+ installed.
* Download the repository and upload it to a folder in your server.
* Run `composer install` in project's root to install dependencies.
* Open your favourite browser and enter the URL to the `/gui` folder.

<a name="tests"></a>

## Tests

Tests from YUI compressor have been modified to fit this port.
Run all tests:
```sh
$ cd <pkg-root-folder>
$ composer.phar install
$ ./vendor/bin/phpunit
```

<a name="api-reference"></a>

## API Reference

### __construct ([ bool *$raisePhpLimits* ])

Creates a new minifier instance.

* *$raisePhpLimits*: (_bool_). If true, PHP settings inherited from default values like execution time limit will be raised when values are not enough to process very big CSS code.
  Default: _true_

### run (string *$css*)

Takes a CSS string of code to be minified, returning the compressed CSS code if everything is OK; throwing an `Exception` if the code could not be minified.

* *$css*: (_string_). CSS code to be minified.

### keepSourceMapComment (bool *$keepSourceMap*)

Keeps the sourcemap special comment (`/*# sourceMappingURL=... */`) after the end of the minification.

* *$keepSourceMap*: (_bool_). If false, sourcemap comments will be removed by default.

### removeImportantComments (bool *$removeImportantComments*)

Removes `/*! ... */` special comments — typically license headers — from the minified output.

* *$removeImportantComments*: (_bool_). If false, important comments won't be removed.

### setLinebreakPosition (int *$position*)

Better readability of minified CSS could be achieved splitting the output after a specific position, e.g. a column value around 500 characters.

* *$position*: (_int_). Every 256 characters by default.

### setMaxExecutionTime (int *$seconds*)

Sets the max PHP execution time allowed to minify.

* *$seconds*: (_int_). PHP max execution time limit, as specified in php.ini. Read this section from provided `php.ini` by default.

### setMemoryLimit (mixed *$limit*)

Raised PHP memory limits on minification.

* *$limit*: (_mixed_). PHP memory limit, as specified in php.ini. Read this section from provided `php.ini` by default.

### setPcreBacktrackLimit (int *$limit*)

Raised PCRE backtrack limits on minification.

* *$limit*: (_int_). Backtrack limit as specified in `php.ini`, 10 times the default (2 MB) is applied by default.

### setPcreRecursionLimit (int *$limit*)

Raised PCRE recursion limits on minification.

* *$limit*: (_int_). Recursion limit as specified in `php.ini`, 5 times the default (500k) is applied by default.

<a name="credits"></a>

## Credits and provenance

This package is based on the following building blocks — big thanks to the original authors:

* **YUI CSS compressor** (C) 2009-2011 Yahoo! Inc. — part of the YUI Compressor, BSD license.
* **YUI-CSS-compressor-PHP-port** by [Tobal Martín](https://github.com/tubalmartin) (tubalmartin/MojoYUIcompressor etc.), a PHP port of the above. This package started as a modernized fork of it, and the CSS minifier is essentially the successor of that code.
* **splitbrain/php-jsstrip** by [Andreas Gohr](https://github.com/splitbrain), vendored into `src/JsStrip.php` (BSD-3-Clause) — itself a PHP port of Nick Galbreath's `jsstrip.py`.
* **FineDiff** by Stephen Clay, vendored under `tests/FineDiff` to compare minified output against the original test suite expectations.

The compressor implementation remains faithful to the original YUI compressor behavior, with the port optionally inlining `@import` rules, wiring JavaScript support, and dropping obsolete workarounds for legacy PHP versions.

<a name="changelog"></a>

## Changelog

### v1.0.0

First release of this package.

* CSS minifier ported from `tubalmartin/YUI-CSS-compressor-PHP-port` with namespace `nedarta\AssetOpt`
* PHP 8.2+ support, PHPUnit 9 test suite
* `JsStrip` JS whitespace/comment stripper (from `splitbrain/php-jsstrip`)
* `ImportResolver` + `--resolve-imports`: full recursive `@import` inlining (local paths, remote http/https)
* CLI rewritten: positional `{from} -o {to}` invocations, `--type js|css` selection, inline `--opt=value` values, `--type` and `--disable-optimizations` yuicompressor.jar flags accepted and ignored
* Fixed minification of empty custom property values like `--var: ;` (emitted by Sass for undefined variables)

### History

For project history prior to this version, see the legacy YUI-CSS-compressor-PHP-port repositories (upstream original port by Tobal Martín, and the interim fork nedarta/cssmin).
