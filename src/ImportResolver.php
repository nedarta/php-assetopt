<?php

namespace nedarta\CssMin;

/**
 * Resolves @import URLs by inlining the referenced stylesheets.
 *
 * Supports local files (relative to a base directory) and remote
 * (http/https) stylesheets, recursively and cycle-safe. Media qualified
 * imports are wrapped in an equivalent @media block. Failed imports
 * are left untouched as valid fallback for browsers.
 */
class ImportResolver
{
    const MAX_DEPTH = 10;
    const MAX_IMPORT_SIZE = 2097152; // 2 MB per imported file
    const DEFAULT_SOCKET_TIMEOUT = 5;

    /**
     * Resolves the @import at-rules found in the given CSS code
     * @param string $css the CSS code containing @import at-rules
     * @param string|null $baseDir optional base directory for relative @import urls
     * @return string
     */
    public function resolve($css, $baseDir = null)
    {
        if (stripos($css, '@import') === false) {
            return $css;
        }

        $visited = array();

        return $this->resolveImports($css, $baseDir, 0, $visited);
    }

    /**
     * @param string $css
     * @param string|null $baseDir
     * @param int $depth
     * @param array $visited
     * @return string
     */
    protected function resolveImports($css, $baseDir, $depth, &$visited)
    {
        if ($depth > self::MAX_DEPTH) {
            return $css;
        }

        $imports = array();
        $names = array('url' => 'url', 'quote' => 'quote', 'import' => 'import', 'media' => 'media');

        preg_match_all(
            '/@import\s+(?:url\(\s*["\']?(?<url>[^)"\']*?)["\']?\s*\)|(?<quote>["\'])(?<qurl>[^"\']*?)(?P=quote))'
            . '(?<media>[^;]*?)\s*;/iS',
            $css,
            $matches,
            PREG_UNMATCHED_AS_NULL
        );

        if (empty($matches[0])) {
            return $css;
        }

        for ($i = 0, $l = count($matches[0]); $i < $l; $i++) {
            $target = $matches['url'][$i] !== null ? $matches['url'][$i] : $matches['qurl'][$i];

            if ($this->isIgnorableImport($target)) {
                continue;
            }

            $imports[] = array(
                'statement' => $matches[0][$i],
                'target' => trim($target),
                'media' => trim($matches['media'][$i]),
            );
        }

        if (empty($imports)) {
            return $css;
        }

        $css = preg_replace('/@import[^;]*;/iS', '', $css);

        $resolved = array();
        foreach ($imports as $import) {
            $resolved[] = $this->resolveImport($import, $baseDir, $depth, $visited);
        }

        return implode("\n", $resolved) ."\n". $css;
    }

    /**
     * Resolves a single import statement
     * @param array $import
     * @param string|null $baseDir
     * @param int $depth
     * @param array $visited
     * @return string
     */
    protected function resolveImport($import, $baseDir, $depth, &$visited)
    {
        $target = $import['target'];
        $url = $this->toAbsoluteUrl($target, $baseDir);

        $key = $this->normalizePath($target, $baseDir);

        if (isset($visited[$key])) {
            return '';
        }
        $visited[$key] = true;

        $importedCss = $this->fetchImport($url);

        if ($importedCss === false) {
            // Leave the original at-rule as a valid fallback for the browser.
            return $import['statement'];
        }

        $importedCss = $this->resolveImports($importedCss, dirname($url), $depth + 1, $visited);

        if ($import['media'] !== '') {
            $importedCss = '@media '. $import['media'] .'{'. $importedCss .'}';
        }

        return $importedCss;
    }

    /**
     * Fetches the CSS code an import refers to
     * @param string $url
     * @return string|false
     */
    protected function fetchImport($url)
    {
        if (preg_match('/^https?:\/\//i', $url)) {
            if (!ini_get('allow_url_fopen')) {
                return false;
            }

            $context = stream_context_create(array(
                'http' => array(
                    'timeout' => self::DEFAULT_SOCKET_TIMEOUT,
                    'follow_location' => 1,
                    'max_redirects' => 3
                ),
                'https' => array(
                    'timeout' => self::DEFAULT_SOCKET_TIMEOUT,
                    'follow_location' => 1,
                    'max_redirects' => 3
                )
            ));

            return @file_get_contents($url, false, $context, 0, self::MAX_IMPORT_SIZE);
        }

        if (!is_readable($url)) {
            return false;
        }

        return @file_get_contents($url, false, null, 0, self::MAX_IMPORT_SIZE);
    }

    /**
     * Converts an import target into an absolute url based on the given base dir
     * @param string $target
     * @param string|null $baseDir
     * @return string
     */
    protected function toAbsoluteUrl($target, $baseDir)
    {
        if (preg_match('/^[a-z]+:\/\//i', $target) || '\\' === DIRECTORY_SEPARATOR && preg_match('/^[a-z]:\\\\/i', $target)) {
            return $target;
        }

        if ($baseDir === null || $baseDir === '') {
            return $target;
        }

        // Remote urls always join with forward slashes.
        if (preg_match('#^[a-z]+://#i', $baseDir)) {
            return rtrim($baseDir, '/') . '/' . preg_replace('#^(\./)+#i', '', $target);
        }

        return rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . preg_replace('#^(\./)+#i', '', $target);
    }

    /**
     * Cached target normalization key for the visited set
     * @param string $target
     * @param string|null $baseDir
     * @return string
     */
    protected function normalizePath($target, $baseDir)
    {
        $absolute = $this->toAbsoluteUrl($target, $baseDir);

        if (preg_match('/^https?:\/\//i', $absolute)) {
            return preg_replace('/#.*$/S', '', $absolute);
        }

        $real = realpath($absolute);

        return $real !== false ? $real : $absolute;
    }

    /**
     * Tells whether an import should be left alone
     * @param string $target
     * @return bool
     */
    protected function isIgnorableImport($target)
    {
        $target = trim($target);

        return $target === '' || preg_match('#^(?:data:|//)#i', $target);
    }
}
