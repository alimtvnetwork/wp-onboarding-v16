<?php
/**
 * Riseup Asia Uploader - Upload Ignore Parser
 *
 * Parses .uploadignore files using gitignore-style pattern matching.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Upload Ignore Parser class.
 */
class RiseupUploadIgnore {

    /**
     * Include patterns (files to ignore).
     *
     * @var array
     */
    private $patterns = array();

    /**
     * Negation patterns (files to keep).
     *
     * @var array
     */
    private $negations = array();

    /**
     * Whether the ignore file was loaded.
     *
     * @var bool
     */
    private $loaded = false;

    /**
     * File logger instance.
     *
     * @var RiseupFileLogger
     */
    private $file_logger;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->file_logger = RiseupFileLogger::get_instance();
    }

    /**
     * Load patterns from .uploadignore file.
     *
     * @param string $plugin_dir The plugin directory path.
     *
     * @return bool True if file was loaded, false otherwise.
     */
    public function load($plugin_dir) {
        $ignore_file = rtrim($plugin_dir, '/\\') . '/' . IGNORE_FILENAME;

        $this->file_logger->debug('Loading uploadignore', array('path' => $ignore_file));

        if (RiseupBooleanHelpers::is_file_missing($ignore_file)) {
            $this->file_logger->debug('No uploadignore file found');
            $this->loaded = false;
            return false;
        }

        try {
            $content = file_get_contents($ignore_file);
            if ($content === false) {
                $this->file_logger->warn('Failed to read uploadignore file');
                $this->loaded = false;
                return false;
            }

            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);

                // Skip empty lines and comments.
                if ($line === '' || strpos($line, '#') === 0) {
                    continue;
                }

                // Handle negation patterns.
                if (strpos($line, '!') === 0) {
                    $pattern = substr($line, 1);
                    $this->negations[] = $this->compilePattern($pattern);
                } else {
                    $this->patterns[] = $this->compilePattern($line);
                }
            }

            $this->loaded = true;
            $this->file_logger->info('Uploadignore loaded', array(
                'patterns'  => count($this->patterns),
                'negations' => count($this->negations),
            ));
            return true;
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Failed to load uploadignore');
            $this->loaded = false;
            return false;
        }
    }

    /**
     * Check if a relative path should be ignored.
     *
     * @param string $relative_path The relative file path.
     *
     * @return bool True if the file should be ignored, false otherwise.
     */
    public function shouldIgnore($relative_path) {
        // Normalize path separators.
        $path = str_replace('\\', '/', $relative_path);
        $path = ltrim($path, '/');

        // Check if any pattern matches.
        $ignored = false;
        foreach ($this->patterns as $pattern) {
            if ($this->matchPattern($pattern, $path)) {
                $ignored = true;
                break;
            }
        }

        // If ignored, check for negation patterns.
        if ($ignored) {
            foreach ($this->negations as $pattern) {
                if ($this->matchPattern($pattern, $path)) {
                    return false; // Negated, don't ignore.
                }
            }
        }

        return $ignored;
    }

    /**
     * Get all active patterns.
     *
     * @return array Array of patterns.
     */
    public function getPatterns() {
        return $this->patterns;
    }

    /**
     * Get all negation patterns.
     *
     * @return array Array of negation patterns.
     */
    public function getNegations() {
        return $this->negations;
    }

    /**
     * Check if ignore file was loaded.
     *
     * @return bool True if loaded, false otherwise.
     */
    public function isLoaded() {
        return $this->loaded;
    }

    /**
     * Compile a gitignore-style pattern to regex.
     *
     * @param string $pattern The pattern to compile.
     *
     * @return array Compiled pattern info.
     */
    private function compilePattern($pattern) {
        $info = array(
            'original'   => $pattern,
            'anchored'   => false,
            'directory'  => false,
            'regex'      => '',
        );

        // Check if pattern is anchored to root.
        if (strpos($pattern, '/') === 0) {
            $info['anchored'] = true;
            $pattern = substr($pattern, 1);
        }

        // Check if pattern is directory-only.
        if (substr($pattern, -1) === '/') {
            $info['directory'] = true;
            $pattern = rtrim($pattern, '/');
        }

        // Convert gitignore pattern to regex.
        $regex = preg_quote($pattern, '/');

        // Handle ** (match any path segments).
        $regex = str_replace('\\*\\*', '.*', $regex);

        // Handle * (match any characters except /).
        $regex = str_replace('\\*', '[^/]*', $regex);

        // Handle ? (match single character except /).
        $regex = str_replace('\\?', '[^/]', $regex);

        if ($info['anchored']) {
            $regex = '^' . $regex;
        } else {
            // Pattern can match anywhere in path.
            $regex = '(^|/)' . $regex;
        }

        // If not directory-specific, match end of path or before /.
        if ($info['directory']) {
            $regex = $regex . '(/|$)';
        } else {
            $regex = $regex . '(/|$)';
        }

        $info['regex'] = '/' . $regex . '/i';

        return $info;
    }

    /**
     * Match a compiled pattern against a path.
     *
     * @param array  $pattern The compiled pattern.
     * @param string $path    The path to match.
     *
     * @return bool True if matches, false otherwise.
     */
    private function matchPattern($pattern, $path) {
        return preg_match($pattern['regex'], $path) === 1;
    }

    /**
     * Create an instance and load from a directory.
     *
     * @param string $plugin_dir The plugin directory.
     *
     * @return RiseupUploadIgnore The instance.
     */
    public static function fromDirectory($plugin_dir) {
        $instance = new self();
        $instance->load($plugin_dir);
        return $instance;
    }
}
