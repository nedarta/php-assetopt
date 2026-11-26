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
    private const NOCOMPRESS_PATTERN = '~/\* BEGIN NOCOMPRESS \*/(.*?)/\* END NOCOMPRESS \*/~s';

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

        list($source, $protected) = $this->protectNoCompressBlocks($source);

        try {
            $options['comments'] = false;

            $ast = Peast::latest($source, $options)->parse();

            return trim($this->restoreNoCompressBlocks(trim($ast->render(new Compact())), $protected));
        } catch (\Throwable $e) {
            throw new JsStripException(
                sprintf('Unable to parse JavaScript: %s', $e->getMessage()),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Replaces every `/* BEGIN NOCOMPRESS *\/ ... /* END NOCOMPRESS *\/` region with an empty
     * statement placeholder, so the parser never touches it and it survives verbatim.
     *
     * @param string $source
     * @return array{0: string, 1: array<string, string>}
     */
    private function protectNoCompressBlocks($source)
    {
        $protected = array();
        $index = 0;

        $source = preg_replace_callback(
            self::NOCOMPRESS_PATTERN,
            static function ($matches) use (&$protected, &$index) {
                $placeholder = 'ASSETOPTNOCOMPRESS' . $index++;
                $protected[$placeholder] = '/* BEGIN NOCOMPRESS */' . $matches[1] . '/* END NOCOMPRESS */';

                return ';' . $placeholder . ';';
            },
            $source
        );

        return array($source, $protected);
    }

    /**
     * Puts the original NOCOMPRESS regions back in place of their placeholders.
     *
     * @param string $source
     * @param array<string, string> $protected
     * @return string
     */
    private function restoreNoCompressBlocks($source, array $protected)
    {
        foreach ($protected as $placeholder => $block) {
            $source = str_replace($placeholder, $block, $source);
        }

        return $source;
    }
}
