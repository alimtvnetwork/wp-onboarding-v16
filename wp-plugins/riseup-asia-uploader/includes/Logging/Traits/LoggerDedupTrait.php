<?php
/**
 * Logger Dedup Trait
 *
 * MD5-based deduplication for log entries.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait LoggerDedupTrait {

    /**
     * Check if a log entry is a duplicate using MD5 hashing.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     * @param string $file    Source file.
     * @param int    $line    Source line number.
     * @return bool True if this is a duplicate entry that should be skipped.
     */
    private function isDuplicate($level, $message, $file, $line) {
        $hashInput = $level . '|' . $message . '|' . basename($file) . '|' . $line;
        $hash = md5($hashInput);

        if (isset($this->dedupHashes[$hash])) {
            return true;
        }

        $this->dedupHashes[$hash] = true;
        return false;
    }

    /**
     * Clear the deduplication hash map.
     *
     * @return int Number of hashes that were cleared.
     */
    public function clearDedupHashes() {
        $count = count($this->dedupHashes);
        $this->dedupHashes = array();
        return $count;
    }
}
