<?php

namespace nedarta\AssetOpt;

use Peast\Formatter\Compact;
use Peast\Peast;

/**
 * Minify JavaScript using a syntax-aware parser and compact renderer.
 *
 * Unlike the previous whitespace stripper, this implementation parses the
 * source into an AST before rendering it. This makes whitespace and comment
 * removal syntax-aware and allows the renderer to remove optional syntax
 * such as unnecessary block braces where JavaScript permits it.
 */
class JsStrip
{
    /**
     * Compress the given JavaScript source.
     *
     * @param string $source JavaScript source code.
     * @return string
     * @throws JsStripException if the source cannot be parsed.
     */
    public function compress($source)
    {
        if (trim($source) === '') {
            return '';
        }

        try {
            $ast = Peast::latest($source)->parse();

            return trim($ast->render(new Compact()));
        } catch (\Throwable $e) {
            throw new JsStripException(
                sprintf('Unable to parse JavaScript: %s', $e->getMessage()),
                (int) $e->getCode(),
                $e
            );
        }
    }
}
