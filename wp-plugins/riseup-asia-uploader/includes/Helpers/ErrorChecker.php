<?php

namespace RiseupAsia\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ErrorType;

class ErrorChecker
{
    private const LABEL_FATAL = 'fatal';
    private const LABEL_WARNING = 'warning';
    private const LABEL_RECOVERABLE = 'recoverable';
    private const LABEL_UNKNOWN = 'unknown';
    private const LABEL_UNKNOWN_TYPE = 'UNKNOWN_ERROR_TYPE';

    public static function isFatalError(?array $error): bool {
        if ($error === null) { return false; }
        return in_array($error['type'], ErrorType::FATAL_TYPES, true);
    }

    public static function isWarning(?array $error): bool {
        if ($error === null) { return false; }
        return in_array($error['type'], ErrorType::WARNING_TYPES, true);
    }

    public static function isRecoverable(?array $error): bool {
        if ($error === null) { return false; }
        return in_array($error['type'], ErrorType::RECOVERABLE_TYPES, true);
    }

    public static function getSeverityLabel(?array $error): string {
        if ($error === null) { return self::LABEL_UNKNOWN; }
        if (self::isFatalError($error)) { return self::LABEL_FATAL; }
        if (self::isWarning($error)) { return self::LABEL_WARNING; }
        if (self::isRecoverable($error)) { return self::LABEL_RECOVERABLE; }
        return self::LABEL_UNKNOWN;
    }

    public static function getTypeLabel(int $type): string {
        return ErrorType::TYPE_LABELS[$type] ?? self::LABEL_UNKNOWN_TYPE;
    }

    public static function isInvalidPdoExtension(): bool {
        return BooleanHelpers::isClassMissing('PDO') || BooleanHelpers::isExtensionMissing('pdo_sqlite');
    }
}
