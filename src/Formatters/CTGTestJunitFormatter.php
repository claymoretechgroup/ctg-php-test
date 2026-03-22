<?php
declare(strict_types=1);

namespace CTG\Test\Formatters;

use CTG\Test\CTGTestResult;

// JUnit XML output formatter — best-effort lossy mapping for CI integration
class CTGTestJunitFormatter implements CTGTestFormatterInterface {

    /**
     *
     * Static Methods
     *
     */

    // :: ARRAY, ARRAY -> STRING
    // Formats a report tree as JUnit XML
    // NOTE: Recovered maps to testcase with system-out — JUnit has no recovery concept
    // Trace inclusion is read from config['trace'] (defaults to false)
    public static function format(array $report, array $config = []): string {
        $includeTrace = $config['trace'] ?? false;
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');

        self::_writeSuite($xml, $report, $includeTrace);

        $xml->endDocument();
        return $xml->outputMemory();
    }

    // :: \XMLWriter, ARRAY, BOOL -> VOID
    // Writes a testsuite element (root or chain)
    private static function _writeSuite(\XMLWriter $xml, array $report, bool $includeTrace): void {
        $xml->startElement('testsuite');
        $xml->writeAttribute('name', $report['name']);
        $xml->writeAttribute('tests', (string) ($report['total'] ?? count($report['steps'] ?? [])));
        $xml->writeAttribute('failures', (string) ($report['failed'] ?? 0));
        $xml->writeAttribute('errors', (string) ($report['errored'] ?? 0));
        $xml->writeAttribute('skipped', (string) ($report['skipped'] ?? 0));
        $xml->writeAttribute('time', (string) (($report['duration_ms'] ?? 0) / 1000));

        foreach ($report['steps'] ?? [] as $step) {
            if ($step['type'] === 'chain') {
                self::_writeSuite($xml, $step, $includeTrace);
            } else {
                self::_writeCase($xml, $step, $includeTrace);
            }
        }

        $xml->endElement();
    }

    // :: \XMLWriter, ARRAY, BOOL -> VOID
    // Writes a testcase element for a stage or assert step
    private static function _writeCase(\XMLWriter $xml, array $step, bool $includeTrace): void {
        $xml->startElement('testcase');
        $xml->writeAttribute('name', $step['name']);
        $xml->writeAttribute('time', (string) ($step['duration_ms'] / 1000));

        $status = $step['status'];

        if ($status === CTGTestResult::STATUS_FAIL) {
            $xml->startElement('failure');
            $xml->writeAttribute('message', $step['message'] ?? 'assertion failed');
            if (isset($step['actual']) && isset($step['candidates'])) {
                $body = 'Candidates: ' . CTGTestResult::formatValue($step['candidates']) . "\n"
                      . 'Actual: ' . CTGTestResult::formatValue($step['actual']);
                $xml->text($body);
            } elseif (isset($step['actual']) && isset($step['expected'])) {
                $body = 'Expected: ' . CTGTestResult::formatValue($step['expected']) . "\n"
                      . 'Actual: ' . CTGTestResult::formatValue($step['actual']);
                $xml->text($body);
            }
            $xml->endElement();
        } elseif ($status === CTGTestResult::STATUS_ERROR) {
            $xml->startElement('error');
            $xml->writeAttribute('message', $step['message'] ?? 'error');
            if (isset($step['exception'])) {
                $body = $step['exception']['class'] . ': ' . $step['exception']['message'];
                if ($includeTrace && isset($step['exception']['trace'])) {
                    $body .= "\n" . $step['exception']['trace'];
                }
                $xml->text($body);
            }
            $xml->endElement();
        } elseif ($status === CTGTestResult::STATUS_SKIP) {
            $xml->startElement('skipped');
            $xml->endElement();
        } elseif ($status === CTGTestResult::STATUS_RECOVERED) {
            // Least bad option — JUnit has no recovery concept
            $xml->startElement('system-out');
            $lines = [];
            $lines[] = 'Recovery: ' . ($step['message'] ?? 'error handler invoked');
            if (isset($step['exception'])) {
                $lines[] = 'Original exception class: ' . $step['exception']['class'];
                $lines[] = 'Original exception message: ' . $step['exception']['message'];
                $lines[] = 'Original exception code: ' . $step['exception']['code'];
                if ($includeTrace && isset($step['exception']['trace'])) {
                    $lines[] = 'Trace: ' . $step['exception']['trace'];
                }
            }
            $xml->text(implode("\n", $lines));
            $xml->endElement();
        }

        $xml->endElement();
    }
}
