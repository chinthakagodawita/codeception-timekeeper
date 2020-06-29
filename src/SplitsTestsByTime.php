<?php
declare(strict_types=1);

namespace ChinthakaGodawita\CodeceptionTimekeeper;

use Robo\Collection\CollectionBuilder;
use Robo\TaskAccessor;

/**
 * @mixin TaskAccessor
 */
trait SplitsTestsByTime
{

    /**
     * Splits tests into equal groups based off their runtimes.
     *
     * @param int $groupCount The number of groups to split tests into.
     * @param string $timeReportFile The relative path to the time report file
     * that provides test runtimes.
     *
     * @return CollectionBuilder|TimeSplitterTask
     */
    protected function taskSplitTestsByTime(
        int $groupCount,
        string $timeReportFile
    ): CollectionBuilder {
        return $this->task(TimeSplitterTask::class, $groupCount, $timeReportFile);
    }

}
