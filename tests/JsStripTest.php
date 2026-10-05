<?php

namespace nedarta\AssetOpt\Tests;

use nedarta\AssetOpt\JsMinifier;
use nedarta\AssetOpt\JsStrip;
use nedarta\AssetOpt\JsStripException;
use PHPUnit\Framework\TestCase;

class JsStripTest extends TestCase
{
    public function testCommentsAndWhitespaceAreRemoved(): void
    {
        $source = <<<'JS'
            // comment
            function add(first, second) {
                /* comment */
                return first + second;
            }
            JS;

        $result = (new JsMinifier)->compress($source);

        $this->assertSame('function add(first,second){return first+second}', $result);
    }

    public function testLegacyFacadeUsesMinifier(): void
    {
        $source = 'function add(first, second) { return first + second; }';

        $this->assertSame(
            (new JsMinifier)->compress($source),
            (new JsStrip)->compress($source)
        );
    }

    public function testModernSyntaxIsParsed(): void
    {
        $source = <<<'JS'
            const getValue = (object) => object?.value ?? `fallback: ${object?.name}`;
            JS;

        $result = (new JsMinifier)->compress($source);

        $this->assertStringContainsString('object?.value??`fallback: ${object?.name}`', $result);
        $this->assertStringNotContainsString('//', $result);
    }

    public function testRegexLiteralIsPreserved(): void
    {
        $source = 'const pattern = /a[\\/]b+/gi; const value = pattern.test("a/b");';

        $result = (new JsMinifier)->compress($source);

        $this->assertStringContainsString('/a[\\/]b+/gi', $result);
    }

    public function testObjectPropertyNamesArePreserved(): void
    {
        $source = 'const object = { longProperty: true, method() { return this.longProperty; } };';

        $result = (new JsMinifier)->compress($source);

        $this->assertStringContainsString('longProperty', $result);
    }

    public function testModuleSyntaxCanBeSelected(): void
    {
        $source = 'export const value = 42;';

        $result = (new JsMinifier)->compress($source, array('sourceType' => 'module'));

        $this->assertSame('export const value=42', $result);
    }

    public function testCommentsOptionCannotReenableComments(): void
    {
        $source = '/* remove */ const value = 42;';

        $result = (new JsMinifier)->compress($source, array('comments' => true));

        $this->assertStringNotContainsString('remove', $result);
    }

    public function testInvalidJavaScriptThrowsException(): void
    {
        $this->expectException(JsStripException::class);

        (new JsMinifier)->compress('function broken( {');
    }

    public function testEmptySource(): void
    {
        $this->assertSame('', (new JsMinifier)->compress(" \n\t"));
    }
}
