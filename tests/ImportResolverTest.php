<?php

namespace nedarta\CssMin\Tests;

use nedarta\CssMin\ImportResolver;
use PHPUnit\Framework\TestCase;

class ImportResolverTest extends TestCase
{
    protected static $dir;

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cssmin-import-resolver';

        @mkdir(self::$dir, 0777, true);
        @mkdir(self::$dir . DIRECTORY_SEPARATOR . 'nested', 0777, true);

        file_put_contents(self::$dir .'/base.css', "@import url(./other.css);\n@import 'nested/inner.css';\nbody{color:white;}");
        file_put_contents(self::$dir .'/other.css', ".other{margin:0}");
        file_put_contents(self::$dir .'/nested/inner.css', ".inner{color:#abc}");
        file_put_contents(self::$dir .'/cycle-a.css', "@import url(cycle-b.css);.a{color:red}");
        file_put_contents(self::$dir .'/cycle-b.css', "@import url(cycle-a.css);.b{color:red}");
    }

    public static function tearDownAfterClass(): void
    {
        foreach (array('/base.css', '/other.css', '/nested/inner.css', '/cycle-a.css', '/cycle-b.css') as $file) {
            @unlink(self::$dir . $file);
        }
        @rmdir(self::$dir .'/nested');
        @rmdir(self::$dir);
    }

    public function testReturnsInputUnchangedWithoutImports()
    {
        $resolver = new ImportResolver;
        $css = 'a{color:red;} /* @import mention in a comment */';

        $this->assertSame($css, $resolver->resolve($css));
    }

    public function testInlinesLocalImportsInOrderAndRecursively()
    {
        $resolver = new ImportResolver;
        $css = file_get_contents(self::$dir .'/base.css');

        $output = $resolver->resolve($css, self::$dir);

        $this->assertStringContainsString('.other{margin:0}', $output);
        $this->assertStringContainsString('.inner{color:#abc}', $output);
        $this->assertStringContainsString('body{color:white;}', $output);
        $this->assertStringNotContainsString('@import', $output);
        $this->assertStringStartsWith('.other{margin:0}', $output);
        $this->assertStringEndsWith('body{color:white;}', rtrim($output));
    }

    public function testMissingImportsAreLeftUntouched()
    {
        $resolver = new ImportResolver;
        $css = "@import url(idontexist.css);\n.a{color:red}";

        $output = $resolver->resolve($css, self::$dir);

        $this->assertStringContainsString('@import url(idontexist.css);', $output);
        $this->assertStringContainsString('.a{color:red}', $output);
    }

    public function testImportCyclesAreBroken()
    {
        $resolver = new ImportResolver;
        $css = file_get_contents(self::$dir .'/cycle-a.css');

        $output = $resolver->resolve($css, self::$dir);

        $this->assertStringContainsString('.a{color:red}', $output);
        $this->assertStringContainsString('.b{color:red}', $output);
        $this->assertStringNotContainsString('@import', $output);
    }

    public function testMediaQualifiedImportsAreWrappedInAtMedia()
    {
        $resolver = new ImportResolver;
        $css = "@import url(./nested/inner.css) screen and (max-width: 500px);\n.a{color:red}";

        $output = $resolver->resolve($css, self::$dir);

        $this->assertStringContainsString('@media screen and (max-width: 500px){.inner{color:#abc}}', $output);
        $this->assertStringNotContainsString('@import', $output);
    }

    public function testDataImportsAndProtocolRelativeImportsAreIgnored()
    {
        $resolver = new ImportResolver;
        $css = "@import url(data:image/svg+xml,%3Csvg%3E);\n@import //cdn.example.com/x.css;\n.a{color:red}";

        $output = $resolver->resolve($css, self::$dir);

        $this->assertStringContainsString('@import url(data:image/svg+xml,%3Csvg%3E);', $output);
        $this->assertStringContainsString('@import //cdn.example.com/x.css;', $output);
        $this->assertStringContainsString('.a{color:red}', $output);
    }
}
