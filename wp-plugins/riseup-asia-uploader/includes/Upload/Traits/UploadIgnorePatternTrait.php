<?php
/**
 * UploadIgnorePatternTrait — pattern compilation and matching for gitignore-style rules.
 *
 * @package RiseupAsia\Upload\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait UploadIgnorePatternTrait {

    /**
     * Compile a gitignore-style pattern to regex.
     *
     * @param string $pattern The pattern to compile.
     * @return array Compiled pattern info.
     */
    private function compilePattern($pattern) {
        $info = array(
            'original'   => $pattern,
            'anchored'   => false,
            'directory'  => false,
            'regex'      => '',
        );

        if (strpos($pattern, '/') === 0) {
            $info['anchored'] = true;
            $pattern = substr($pattern, 1);
        }

        if (substr($pattern, -1) === '/') {
            $info['directory'] = true;
            $pattern = rtrim($pattern, '/');
        }

        $regex = preg_quote($pattern, '/');
        $regex = str_replace('\\*\\*', '.*', $regex);
        $regex = str_replace('\\*', '[^/]*', $regex);
        $regex = str_replace('\\?', '[^/]', $regex);

        if ($info['anchored']) {
            $regex = '^' . $regex;
        } else {
            $regex = '(^|/)' . $regex;
        }

        $regex = $regex . '(/|$)';

        $info['regex'] = '/' . $regex . '/i';

        return $info;
    }

    /**
     * Match a compiled pattern against a path.
     *
     * @param array  $pattern The compiled pattern.
     * @param string $path    The path to match.
     * @return bool True if matches.
     */
    private function matchPattern($pattern, $path) {
        return preg_match($pattern['regex'], $path) === 1;
    }
}
