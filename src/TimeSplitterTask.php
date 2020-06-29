<?php
declare(strict_types=1);

namespace ChinthakaGodawita\CodeceptionTimekeeper;

use Codeception\Test\Descriptor as TestDescriptor;
use Codeception\Test\Loader;
use Codeception\TestInterface;
use Generator;
use PHPUnit\Framework\DataProviderTestSuite;
use PHPUnit\Framework\SelfDescribing;
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

        $this->printTaskInfo("Splitting {$testCount} into {$this->groupCount} groups of equal runtime...");

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

        foreach ($groups as $idx => $tests) {
            $fileName = $this->groupOutputLoc . $idx;
            $this->printTaskInfo("Writing group {$idx} to: $fileName");
            $success = file_put_contents($fileName, implode("\n", $tests));
            if (!$success) {
                throw new TaskException(
                    self::class,
                    "Could not write test group to {$fileName}"
                );
            }
        }

        return Result::success($this, "{$this->groupCount} test groups created");
    }

    private function splitTestsByGroup(Generator $tests): array
    {
        // @TODO.
        return [];
    }

    private function splitTestsByRuntime(Generator $tests, TimeReport $timeReport): array
    {
        $totalRuntime = $timeReport->totalRuntime();
        $avgRuntime = $totalRuntime / $this->groupCount;

        $skippedTests = [];
        $testsWithRuntime = [];
        $testsWithoutRuntime = [];

        foreach ($tests as $test) {
            $testMeta = $test->getMetadata();
            $testPath = $this->getTestRelativePath($test);
            $runtime = $timeReport->getTime($testPath);

            if ($testMeta->getSkip() !== null) {
                $skippedTests[] = $testPath;
            } elseif ($runtime === null) {
                $testsWithoutRuntime[] = $testPath;
            } else {
                $testsWithRuntime[$testPath] = $runtime;
            }
        }

        arsort($testsWithRuntime);

        $sums = array_fill(0, $this->groupCount, 0);
        $groups = array_fill(0, $this->groupCount, []);

        $addedOne = false;
        foreach ($testsWithRuntime as $testPath => $runtime) {
            $added = false;
            $idx = null;
            foreach ($sums as $idx => $sum) {
                if (($sum + $runtime) < $avgRuntime || !$addedOne) {
                    $groups[$idx][] = $testPath;
                    $sum += $runtime;
                    $sums[$idx] = $sum;
                    $addedOne = true;
                    $added = true;
                    break;
                }
            }
            if (!$added) {
                if ($idx === null) {
                    $idx = $this->groupCount - 1;
                }
                $groups[$idx][] = $testPath;
                $sums[$idx] += $runtime;
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

        $maxGroupIdx = $this->groupCount - 1;
        if (count($skippedTests) > 0) {
            $groups[$maxGroupIdx] = array_merge($groups[$maxGroupIdx], $skippedTests);
        }

        return $groups;
    }

    /**
     * @param array $tests
     *
     * @return Generator|TestInterface[]
     */
    private function testIterator(array $tests): Generator
    {
        foreach ($tests as $test) {
            if ($test instanceof DataProviderTestSuite) {
                $test = current($test->tests());
            }

            yield $test;
        }
    }

    /**
     * Get the path to a particular test, relative to the Robofile.
     *
     * @param SelfDescribing $test
     *
     * @return string
     */
    private function getTestRelativePath(SelfDescribing $test): string
    {
        $path = DIRECTORY_SEPARATOR . TestDescriptor::getTestFullName($test);
        // Robo updates PHP's current working directory to the location of the
        // Robofile.
        $currentDir = getcwd() . DIRECTORY_SEPARATOR;

        if (strpos($path, $currentDir) === 0) {
            $path = substr($path, strlen($currentDir));
        }

        return $path;
    }

}
