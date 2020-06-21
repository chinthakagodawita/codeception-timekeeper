<?php
declare(strict_types=1);

namespace ChinthakaGodawita\CodeceptionTimekeeper;

use Codeception\Event\PrintResultEvent;
use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Exception\ExtensionException;
use Codeception\Extension;

class Reporter extends Extension
{

    protected $config = [
        'report_location' => null,
    ];

    /**
     * @var string Relative path to where the time report is written.
     */
    private $reportLocation;

    /**
     * @var TimeReport
     */
    private $report;

    /**
     * @var string
     */
    private $codeceptionRootDir;

    /**
     * @var bool
     */
    private $writeReport = true;

    public static $events = [
        Events::TEST_END => 'afterTest',
        Events::TEST_FAIL => 'testFailure',
        Events::TEST_ERROR => 'testFailure',
        Events::RESULT_PRINT_AFTER => 'afterResult',
    ];

    /**
     * @throws ExtensionException On config error.
     */
    public function _initialize()
    {
        $reportLoc = $this->config['report_location'];
        if ($reportLoc === null) {
            throw new ExtensionException(
                $this,
                "The 'report_location' config option is not defined, this should be set to a relative path where the time report should be written."
            );
        }

        if (substr($reportLoc, -strlen('.json')) !== '.json') {
            throw new ExtensionException(
                $this,
                "Please specify a valid report location, with a JSON extension, e.g. '_data/time_report.json'"
            );
        }

        $this->reportLocation = codecept_absolute_path($reportLoc);

        // Try creating directory if it doesn't exist.
        $baseDir = dirname($this->reportLocation);
        if (!file_exists($baseDir)) {
            $success = mkdir($baseDir, 0755, true);
            if (!$success) {
                throw new ExtensionException(
                    $this,
                    "Report directory '{$baseDir}' does not exist and could not be created, please check directory permissions."
                );
            }
        }

        $this->report = new TimeReport();
        $this->codeceptionRootDir = $this->getRootDir();

        // Enable console printing for this extension.
        $this->options['silent'] = false;
    }

    /**
     * Keep track of test times after each test run.
     *
     * @param TestEvent $event
     */
    public function afterTest(TestEvent $event): void
    {
        $testMeta = $event->getTest()->getMetadata();
        $relativeTestPath = $testMeta->getFilename();

        if (strpos($relativeTestPath, $this->codeceptionRootDir) === 0) {
            $relativeTestPath = substr($relativeTestPath, strlen($this->codeceptionRootDir));
        }

        // We record times per individual test, not per test file.
        $relativeTestPath .= ":{$testMeta->getName()}";

        $this->report->setTime($relativeTestPath, $event->getTime());
    }

    public function testFailure(TestEvent $event): void
    {
        // Don't write report if a test fails as timings may be inaccurate.
        $this->writeReport = false;
    }

    /**
     * Write test report out once all tests have run.
     *
     * @param PrintResultEvent $event
     *
     * @throws ExtensionException If writing the test report fails.
     */
    public function afterResult(PrintResultEvent $event): void
    {
        if ($this->writeReport) {
            try {
                $reportJson = $this->report->toJson();
            } catch (JsonException $e) {
                throw new ExtensionException(
                    $this,
                    "Could not serialise time report: {$e->getMessage()}",
                    $e
                );
            }

            $success = file_put_contents(
                $this->reportLocation,
                $reportJson,
                LOCK_EX
            );

            if (!$success) {
                throw new ExtensionException(
                    $this,
                    "Could not write time report to '{$this->reportLocation}', please check file & directory permissions"
                );
            }

            $this->writeln(
                "<info>Test time report has been written to: {$this->reportLocation}</info>"
            );
        } else {
            $this->writeln(
                "<error>The test time report has not been output as one or more tests have failed.</error>"
            );
        }
    }

}
