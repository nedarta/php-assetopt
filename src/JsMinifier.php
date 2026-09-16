<?php

namespace nedarta\AssetOpt;

use Peast\Formatter\Compact;
use Peast\Peast;

/**
 * Syntax-aware JavaScript minifier.
 *
 * The minifier deliberately limits itself to transformations performed by
 * Peast's parser and compact formatter. It does not rename identifiers or
 * perform potentially unsafe compiler optimizations.
 */
class JsMinifier
{
    /**
     * Compress the given JavaScript source.
     *
     * @param string $source JavaScript source code.
     * @param array $options Parser options. Supported options include Peast's
     * sourceType and jsx options.
     * @return string
     * @throws JsStripException if the source cannot be parsed.
     */
    public function compress($source, array $options = array())
    {
        if (trim($source) === '') {
            return '';
        }

        try {
            $options['comments'] = false;

            $ast = Peast::latest($source, $options)->parse();

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
