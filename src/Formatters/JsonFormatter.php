<?php

// JSON output formatter for test reports
class JsonFormatter {

    /**
     *
     * Static Methods
     *
     */

    // :: ARRAY -> STRING
    // Formats a report tree as pretty-printed JSON
    public static function format(array $report): string {
        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
