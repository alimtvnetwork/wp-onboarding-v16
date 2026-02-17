<?php
/**
 * Logger Dedup Trait — MD5-based deduplication for log entries.
 *
 * @package RiseupAsia\Logging\Traits
 * @since   1.4.0
 */

namespace RiseupAsia\Logging\Traits;

trait LoggerDedupTrait {

    /** Check if a log entry is a duplicate using MD5 hashing. */
    private function isDuplicate(
        string $level,
        string $message,
        string $file,
        int $line,
    ): bool {
        $hashInput = $level . '|' . $message . '|' . basename($file) . '|' . $line;
        $hash = md5($hashInput);

        if (isset($this->dedupHashes[$hash])) {
            return true;
        }

        $this->dedupHashes[$hash] = true;

        return false;
    }

    /** Clear the deduplication hash map. */
    public function clearDedupHashes(): int {
        $count = count($this->dedupHashes);
        $this->dedupHashes = array();

        return $count;
    }
}
