<?php
/**
 * Upload Ignore Parser
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

    private array $patterns = array();
    private array $negations = array();
    private bool $isLoaded = false;
    private RiseupFileLogger $fileLogger;

    public function __construct() {
        $this->fileLogger = RiseupFileLogger::getInstance();
    }

    /**
     * Load patterns from .uploadignore file.
     *
     * @param string $pluginDir The plugin directory path.
     * @return bool True if file was loaded.
     */
    public function load(string $pluginDir): bool {
        $ignoreFile = rtrim($pluginDir, '/\\') . '/' . IGNORE_FILENAME;
        $this->fileLogger->debug('Loading uploadignore', array('path' => $ignoreFile));

        if (RiseupBooleanHelpers::isFileMissing($ignoreFile)) {
            $this->fileLogger->debug('No uploadignore file found');
            $this->isLoaded = false;
            return false;
        }

        try {
            $content = file_get_contents($ignoreFile);
            if ($content === false) {
                $this->fileLogger->warn('Failed to read uploadignore file');
                $this->isLoaded = false;
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

            $this->isLoaded = true;
            $this->fileLogger->info('Uploadignore loaded', array(
                'patterns'  => count($this->patterns),
                'negations' => count($this->negations),
            ));
            return true;
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to load uploadignore');
            $this->isLoaded = false;
            return false;
        }
    }

    /**
     * Check if a relative path should be ignored.
     */
    public function shouldIgnore(string $relativePath): bool {
        $path = str_replace('\\', '/', $relativePath);
        $path = ltrim($path, '/');

        $isIgnored = false;
        foreach ($this->patterns as $pattern) {
            if ($this->matchPattern($pattern, $path)) {
                $isIgnored = true;
                break;
            }
        }

        if ($isIgnored) {
            foreach ($this->negations as $pattern) {
                if ($this->matchPattern($pattern, $path)) {
                    return false;
                }
            }
        }

        return $isIgnored;
    }

    public function getPatterns(): array {
        return $this->patterns;
    }

    public function getNegations(): array {
        return $this->negations;
    }

    public function isLoaded(): bool {
        return $this->isLoaded;
    }

    /**
     * Create an instance and load from a directory.
     */
    public static function fromDirectory(string $pluginDir): self {
        $instance = new self();
        $instance->load($pluginDir);
        return $instance;
    }
}
