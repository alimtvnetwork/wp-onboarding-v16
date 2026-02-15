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

    /** Compile a gitignore-style pattern to regex. */
    private function compilePattern(string $pattern): array {
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

    /** Match a compiled pattern against a path. */
    private function matchPattern(array $pattern, string $path): bool {
        return preg_match($pattern['regex'], $path) === 1;
    }
}
