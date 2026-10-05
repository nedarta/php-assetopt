<?php

namespace nedarta\AssetOpt;

/**
 * Backwards-compatible JavaScript minifier facade.
 *
 * @deprecated Use JsMinifier directly for new code.
 */
class JsStrip
{
    /**
     * Compress the given JavaScript source.
     *
     * @param string $source JavaScript source code.
     * @param array $options Parser options.
     * @return string
     * @throws JsStripException if the source cannot be parsed.
     */
    public function compress($source, array $options = array())
    {
        return (new JsMinifier)->compress($source, $options);
    }
}
