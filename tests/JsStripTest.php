<?php

namespace nedarta\AssetOpt\Tests;

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

        $result = (new JsStrip)->compress($source);

        $this->assertSame('function add(first,second){return first+second}', $result);
    }

    public function testModernSyntaxIsParsed(): void
    {
        $source = <<<'JS'
            const getValue = (object) => object?.value ?? `fallback: ${object?.name}`;
            JS;

        $result = (new JsStrip)->compress($source);

        $this->assertStringContainsString('object?.value??`fallback: ${object?.name}`', $result);
        $this->assertStringNotContainsString('//', $result);
    }

    public function testRegexLiteralIsPreserved(): void
    {
        $source = 'const pattern = /a[\\/]b+/gi; const value = pattern.test("a/b");';

        $result = (new JsStrip)->compress($source);

        $this->assertStringContainsString('/a[\\/]b+/gi', $result);
    }

    public function testObjectPropertyNamesArePreserved(): void
    {
        $source = 'const object = { longProperty: true, method() { return this.longProperty; } };';

        $result = (new JsStrip)->compress($source);

        $this->assertStringContainsString('longProperty', $result);
    }

    public function testInvalidJavaScriptThrowsException(): void
    {
        $this->expectException(JsStripException::class);

        (new JsStrip)->compress('function broken( {');
    }

    public function testEmptySource(): void
    {
        $this->assertSame('', (new JsStrip)->compress(" \n\t"));
    }
}
