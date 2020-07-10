<?php
declare(strict_types=1);

namespace ChinthakaGodawita\CodeceptionTimekeeper;

use Codeception\Test\Loader;
use Generator;
use PHPUnit\Framework\DataProviderTestSuite;
use Robo\Exception\TaskException;
use Robo\Result;
use Robo\Task\BaseTask;

/**
 * Heavily inspired by Codeception/Task/SplitTestsByGroupsTask
 * @see https://github.com/Codeception/robo-paracept/blob/ff00b078921ddd072122cd5e8267fdb76f92d054/src/SplitTestsByGroups.php
 */
class TimeSplitterTask extends BaseTask
{

    /**
     * @var int Number of groups to split tests into.
     */
    private $groupCount;

    /**
     * @var string The (relative) location of the test time report file.
     */
    private $timeReportFile;

    /**
     * @var string The filename pattern under which groups will be saved.
     */
    private $groupOutputLoc = 'tests/_data/timekeeper/group_';

    /**
     * @var string The directory that holds test files.
     */
    private $testsFrom = 'tests';

    public function __construct(int $groupCount, string $timeReportFile)
    {
        $this->groupCount = $groupCount;
        $this->timeReportFile = $timeReportFile;
    }

    public function testsFrom(string $path): self
    {
        $this->testsFrom = $path;
        return $this;
    }

    public function groupsTo(string $path): self
    {
        $this->groupOutputLoc = $path;
        return $this;
    }

    /**
     * @return Result
     *
     * @throws TaskException
     * @throws \Exception
     */
    public function run(): Result
    {
        $testLoader = new Loader(['path' => $this->testsFrom]);
        $testLoader->loadTests($this->testsFrom);
        $tests = $testLoader->getTests();
        $testCount = count($tests);

        if ($this->groupCount > $testCount) {
            return Result::error(
                $this,
                "Provided group count ({$this->groupCount}) is more than the number of tests ({$testCount})!"
            );
        }

        $this->printTaskInfo(
            "Splitting {$testCount} tests into {$this->groupCount} groups of equal runtime..."
        );

        $timeReport = null;
        try {
            if (file_exists($this->timeReportFile)) {
                $timeReportRaw = file_get_contents($this->timeReportFile);
                if ($timeReportRaw !== false) {
                    $timeReport = TimeReport::fromJson($timeReportRaw);
                }
            }
        } catch (JsonException $e) {
            throw new TaskException(
                self::class,
                "Found a time report file but could not parse it: {$e->getMessage()}"
            );
        }

        // @TODO: Add test dependency support (i.e. testA depends on testB).
        $testIterator = $this->testIterator($tests);
        if ($timeReport === null) {
            $groups = $this->splitTestsByGroup($testIterator);
        } else {
            $groups = $this->splitTestsByRuntime($testIterator, $timeReport);
        }

        $basePath = dirname($this->groupOutputLoc);
        if (!file_exists($basePath)) {
            $success = mkdir($basePath, 0755, true);
            if (!$success) {
                throw new TaskException(
                    $this,
                    "Groups directory '{$basePath}' does not exist and could not be created, please check directory permissions."
                );
            }
        }

        $groupIdx = null;
        foreach ($groups as $idx => $tests) {
            if (\count($tests) === 0) {
                break;
            }

            $groupIdx = $idx + 1;
            $fileName = $this->groupOutputLoc . $groupIdx;
            $this->printTaskInfo("Writing group {$groupIdx} to: $fileName");
            $success = file_put_contents($fileName, implode("\n", $tests));
            if (!$success) {
                throw new TaskException(
                    self::class,
                    "Could not write test group to {$fileName}"
                );
            }
        }

        if ($groupIdx === null) {
            $this->printTaskWarning('No test groups were created');
        } else {
            $groupCount = $groupIdx + 1;
            $this->printTaskInfo("{$groupCount} test groups created");
        }

        return Result::success($this);
    }

    /**
     * Splits tests into equal groups, ignoring test runtimes.
     *
     * @param Generator|Test[] $tests
     *
     * @return array
     */
    private function splitTestsByGroup(Generator $tests): array
    {
        $groups = array_fill(0, $this->groupCount, []);
        $skippedTests = [];

        $groupIdx = 0;
        foreach ($tests as $test) {
            $testPath = $test->path();

            if ($test->isSkipped()) {
                $skippedTests[] = $testPath;
            } else {
                $groups[$groupIdx][] = $testPath;
            }

            $groupIdx++;
            if ($groupIdx === $this->groupCount) {
                $groupIdx = 0;
            }
        }

        // Add skipped tests onto the last group as they take relatively no time
        // to run.
        $maxGroupIdx = $this->groupCount - 1;
        if (count($skippedTests) > 0) {
            $groups[$maxGroupIdx] = array_merge($groups[$maxGroupIdx], $skippedTests);
        }

        return $groups;
    }

    /**
     * Splits tests into groups of _roughly_ equal runtimes.
     *
     * @param Generator|Test[] $tests
     * @param TimeReport $timeReport
     *
     * @return array
     */
    private function splitTestsByRuntime(Generator $tests, TimeReport $timeReport): array
    {
        $skippedTests = [];
        $testsWithRuntime = [];
        $testsWithoutRuntime = [];

        foreach ($tests as $test) {
            $testPath = $test->path();
            $runtime = $timeReport->getTime($testPath);

            if ($test->isSkipped()) {
                $skippedTests[] = $testPath;
            } elseif ($runtime === null) {
                $testsWithoutRuntime[] = $testPath;
            } else {
                $testsWithRuntime[$testPath] = $runtime;
            }
        }

        // Reverse sort, the larger runtimes are hard to fit than the smaller
        // ones, so lets get them out of the way first.
        arsort($testsWithRuntime);

        $sums = array_fill(0, $this->groupCount, 0);
        $groups = array_fill(0, $this->groupCount, []);

        foreach ($testsWithRuntime as $testPath => $runtime) {
            $idx = 0;
            $loops = 0;
            while(true) {
                $sum = $sums[$idx];
                $prevIdx = $idx - 1;
                if ($prevIdx < 0) {
                    $prevIdx = $this->groupCount - 1;
                }
                $nextIdx = $idx + 1;
                if ($nextIdx === $this->groupCount) {
                    $nextIdx = 0;
                }
                if ($sum === 0 || (($sum < $sums[$prevIdx] && $sum < $sums[$nextIdx]))) {
                    $sums[$idx] += $runtime;
                    $groups[$idx][] = $testPath;
                    break;
                }
                $idx++;
            }
        }

        // Split any tests without recorded runtimes equally between all groups.
        $groupIdx = 0;
        foreach ($testsWithoutRuntime as $testName) {
            $groups[$groupIdx][] = $testName;

            $groupIdx++;
            if ($groupIdx === $this->groupCount) {
                $groupIdx = 0;
            }
        }

        // Add skipped tests onto the last group as they take relatively no time
        // to run.
        $maxGroupIdx = $this->groupCount - 1;
        if (count($skippedTests) > 0) {
            $groups[$maxGroupIdx] = array_merge($groups[$maxGroupIdx], $skippedTests);
        }

        return $groups;
    }

    /**
     * @param array $tests
     *
     * @return Generator|Test[]
     */
    private function testIterator(array $tests): Generator
    {
        foreach ($tests as $test) {
            if ($test instanceof DataProviderTestSuite) {
                $test = current($test->tests());
            }

            yield new Test($test);
        }
    }

}
