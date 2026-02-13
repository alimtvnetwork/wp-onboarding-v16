<?php
/**
 * Riseup Asia Uploader - Upload Ignore Parser
 *
 * Parses .uploadignore files using gitignore-style pattern matching.
 * Shell class — pattern logic delegated to trait.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load trait files
require_once __DIR__ . '/Traits/UploadIgnorePatternTrait.php';

/**
 * Upload Ignore Parser class.
 */
class RiseupUploadIgnore {

    use UploadIgnorePatternTrait;

    /** @var array */
    private $patterns = array();
    /** @var array */
    private $negations = array();
    /** @var bool */
    private $loaded = false;
    /** @var RiseupFileLogger */
    private $file_logger;

    public function __construct() {
        $this->file_logger = RiseupFileLogger::get_instance();
    }

    /**
     * Load patterns from .uploadignore file.
     *
     * @param string $plugin_dir The plugin directory path.
     * @return bool True if file was loaded.
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

                if ($line === '' || strpos($line, '#') === 0) {
                    continue;
                }

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
     */
    public function shouldIgnore($relative_path) {
        $path = str_replace('\\', '/', $relative_path);
        $path = ltrim($path, '/');

        $ignored = false;
        foreach ($this->patterns as $pattern) {
            if ($this->matchPattern($pattern, $path)) {
                $ignored = true;
                break;
            }
        }

        if ($ignored) {
            foreach ($this->negations as $pattern) {
                if ($this->matchPattern($pattern, $path)) {
                    return false;
                }
            }
        }

        return $ignored;
    }

    /** @return array */
    public function getPatterns() {
        return $this->patterns;
    }

    /** @return array */
    public function getNegations() {
        return $this->negations;
    }

    /** @return bool */
    public function isLoaded() {
        return $this->loaded;
    }

    /**
     * Create an instance and load from a directory.
     */
    public static function fromDirectory($plugin_dir) {
        $instance = new self();
        $instance->load($plugin_dir);
        return $instance;
    }
}
