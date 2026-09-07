<?php

declare(strict_types=1);

namespace PHPForge\Debug\Exception;

use function sprintf;

/**
 * Exception message templates authored by this package.
 *
 * Use {@see Message::getMessage()} to format a template with `sprintf()` arguments.
 */
enum Message: string
{
    /**
     * Indicates that the active tab index does not identify a supplied tab.
     *
     * Format: "The active tab index must identify a supplied tab."
     */
    case ACTIVE_TAB_INDEX_INVALID = 'The active tab index must identify a supplied tab.';

    /**
     * Indicates that the maximum captured body size is not positive.
     *
     * Format: "The maximum body size must be greater than zero."
     */
    case BODY_SIZE_INVALID = 'The maximum body size must be greater than zero.';

    /**
     * Indicates that a collector ID is registered more than once.
     *
     * Format: "Duplicate debug collector ID: %s."
     */
    case COLLECTOR_ID_DUPLICATE = 'Duplicate debug collector ID: %s.';

    /**
     * Indicates that a collector ID is empty.
     *
     * Format: "Debug collector ID must not be empty."
     */
    case COLLECTOR_ID_EMPTY = 'Debug collector ID must not be empty.';

    /**
     * Indicates that the debug data directory cannot be created.
     *
     * Format: "Unable to create debug data directory: %s"
     */
    case DATA_DIRECTORY_CREATE_FAILED = 'Unable to create debug data directory: %s';

    /**
     * Indicates that permissions cannot be applied to the debug data directory.
     *
     * Format: "Unable to apply debug data directory mode: %s"
     */
    case DATA_DIRECTORY_MODE_FAILED = 'Unable to apply debug data directory mode: %s';

    /**
     * Indicates that permissions cannot be applied to a debug data file.
     *
     * Format: "Unable to apply debug data file mode for: %s"
     */
    case DATA_FILE_MODE_FAILED = 'Unable to apply debug data file mode for: %s';

    /**
     * Indicates that a debug data file cannot be read.
     *
     * Format: "Unable to read debug data file: %s"
     */
    case DATA_FILE_READ_FAILED = 'Unable to read debug data file: %s';

    /**
     * Indicates that a debug data file cannot be removed.
     *
     * Format: "Unable to remove debug data file: %s"
     */
    case DATA_FILE_REMOVE_FAILED = 'Unable to remove debug data file: %s';

    /**
     * Indicates that a debug data file cannot be replaced.
     *
     * Format: "Unable to replace debug data file: %s"
     */
    case DATA_FILE_REPLACE_FAILED = 'Unable to replace debug data file: %s';

    /**
     * Indicates that a debug data file cannot be restored during rollback.
     *
     * Format: "Unable to roll back debug data file: %s"
     */
    case DATA_FILE_ROLLBACK_FAILED = 'Unable to roll back debug data file: %s';

    /**
     * Indicates that the configured debug data path is not a directory.
     *
     * Format: "Debug data path is not a directory: %s"
     */
    case DATA_PATH_NOT_DIRECTORY = 'Debug data path is not a directory: %s';

    /**
     * Indicates that the configured history size is invalid.
     *
     * Format: "Invalid debug history size: %s"
     */
    case HISTORY_SIZE_INVALID = 'Invalid debug history size: %s';

    /**
     * Indicates that the debug data lock cannot be acquired.
     *
     * Format: "Unable to acquire debug data lock: %s"
     */
    case LOCK_ACQUIRE_FAILED = 'Unable to acquire debug data lock: %s';

    /**
     * Indicates that the debug data lock file cannot be opened.
     *
     * Format: "Unable to open debug data lock file: %s"
     */
    case LOCK_FILE_OPEN_FAILED = 'Unable to open debug data lock file: %s';

    /**
     * Indicates that the persisted debug manifest is empty.
     *
     * Format: "Debug manifest is empty: %s"
     */
    case MANIFEST_EMPTY = 'Debug manifest is empty: %s';

    /**
     * Indicates a manifest read failure while preserving the original exception.
     *
     * Format: "Unable to read debug manifest."
     */
    case MANIFEST_READ_ERROR = 'Unable to read debug manifest.';

    /**
     * Indicates that the specified debug manifest cannot be read.
     *
     * Format: "Unable to read debug manifest: %s"
     */
    case MANIFEST_READ_FAILED = 'Unable to read debug manifest: %s';

    /**
     * Indicates that the N+1 detection threshold is less than two.
     *
     * Format: "The N+1 threshold must be at least two."
     */
    case N_PLUS_ONE_THRESHOLD_INVALID = 'The N+1 threshold must be at least two.';

    /**
     * Indicates that a route definition field does not satisfy its expected shape.
     *
     * Format: "Route definition key '%s' must be %s."
     */
    case ROUTE_DEFINITION_INVALID = 'Route definition key \'%s\' must be %s.';

    /**
     * Indicates that a sensitive key pattern is not a valid PCRE pattern.
     *
     * Format: "Sensitive key pattern "%s" is not a valid PCRE pattern."
     */
    case SENSITIVE_KEY_PATTERN_INVALID = 'Sensitive key pattern "%s" is not a valid PCRE pattern.';

    /**
     * Indicates that a sensitive key prefix is empty.
     *
     * Format: "Sensitive key prefixes must not be empty."
     */
    case SENSITIVE_KEY_PREFIX_EMPTY = 'Sensitive key prefixes must not be empty.';

    /**
     * Indicates that a sidebar navigation group label is empty.
     *
     * Format: "Sidebar navigation group labels must not be empty."
     */
    case SIDEBAR_GROUP_LABEL_EMPTY = 'Sidebar navigation group labels must not be empty.';

    /**
     * Indicates that a persisted debug snapshot is empty.
     *
     * Format: "Debug snapshot is empty: %s"
     */
    case SNAPSHOT_EMPTY = 'Debug snapshot is empty: %s';

    /**
     * Indicates that a debug snapshot cannot be read.
     *
     * Format: "Unable to read debug snapshot: %s"
     */
    case SNAPSHOT_READ_FAILED = 'Unable to read debug snapshot: %s';

    /**
     * Indicates that a debug snapshot tag is invalid.
     *
     * Format: "Invalid debug snapshot tag: %s"
     */
    case SNAPSHOT_TAG_INVALID = 'Invalid debug snapshot tag: %s';

    /**
     * Indicates that a snapshot tag does not match its filename.
     *
     * Format: "Debug snapshot tag does not match its filename: %s"
     */
    case SNAPSHOT_TAG_MISMATCH = 'Debug snapshot tag does not match its filename: %s';

    /**
     * Indicates that a snapshot value violates the expected schema at a payload path.
     *
     * Format: "Invalid debug snapshot value at '%s': expected %s."
     */
    case SNAPSHOT_VALUE_INVALID = 'Invalid debug snapshot value at \'%s\': expected %s.';

    /**
     * Indicates that a temporary debug data file cannot be written.
     *
     * Format: "Unable to write temporary debug data file for: %s"
     */
    case TEMPORARY_FILE_WRITE_FAILED = 'Unable to write temporary debug data file for: %s';

    /**
     * Indicates that the storage transaction journal is invalid.
     *
     * Format: "Invalid debug storage transaction journal: %s"
     */
    case TRANSACTION_JOURNAL_INVALID = 'Invalid debug storage transaction journal: %s';

    /**
     * Formats the message without changing diagnostic argument values.
     *
     * @param int|string ...$argument Values to insert into the message template.
     *
     * @return string Formatted exception message.
     */
    public function getMessage(int|string ...$argument): string
    {
        return sprintf($this->value, ...$argument);
    }
}
