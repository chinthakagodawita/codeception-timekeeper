<?php
declare(strict_types=1);

namespace ChinthakaGodawita\CodeceptionTimekeeper;

use Codeception\Test\Descriptor as TestDescriptor;
use Codeception\Test\Metadata;
use Codeception\Test\Test as BaseTest;

class Test
{

    /**
     * @var BaseTest
     */
    private $test;

    /**
     * @var Metadata
     */
    private $metadata;

    /**
     * @var string
     */
    private $path;

    public function __construct(BaseTest $test)
    {
        $this->test = $test;
    }

    /**
     * @return bool TRUE if this test has a `@skip` annotation on it.
     */
    public function isSkipped(): bool
    {
        if ($this->metadata === null) {
            $this->metadata = $this->test->getMetadata();
        }
        return $this->metadata->getSkip() !== null;
    }

    /**
     * Get relative path to a test file.
     *
     * @return string
     */
    public function path(): string
    {
        if ($this->path === null) {
            $path = DIRECTORY_SEPARATOR . TestDescriptor::getTestFullName($this->test);
            // Robo updates PHP's current working directory to the location of the
            // Robofile.
            $currentDir = getcwd() . DIRECTORY_SEPARATOR;

            if (strpos($path, $currentDir) === 0) {
                $path = substr($path, strlen($currentDir));
            }

            $this->path = $path;
        }

        return $this->path;
    }

}
