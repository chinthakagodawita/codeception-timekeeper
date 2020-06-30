# Codeception TimeKeeper

A [Codeception](https://codeception.com/) extension & [Robo](https://robo.li) task that records test runtimes and lets you split tests into equal runtime-based groups for parallel runs

# Usage
First, install this package:
```bash
composer require chinthakagodawita/codeception-timekeeper
```

## Codeception time reporting extension

Update your `codeception.yml` with:
```yaml
extensions:
    enabled:
        - ChinthakaGodawita\CodeceptionTimekeeper\Reporter:
              report_location: _data/time_report.json
```

## Parallel test runs via Robo

[Install Robo](https://robo.li/):
```bash
composer require --dev consolidation/robo
```

Update your `Robofile`:
```php
<?php

class RoboFile extends \Robo\Tasks {

    use \ChinthakaGodawita\CodeceptionTimekeeper\SplitsTestsByTime;

    public function taskSplitTests(): \Robo\Result
    {
        $groups = 3;
        $timeReportLocation = '_data/time_report.json';
        return $this->taskSplitTestsByTime($groups, $timeReportLocation)
          ->projectRoot('.')
          ->testsFrom('tests')
          ->groupsTo('tests/_data/timekeeper/group_')
          ->run();
    }

}
```

# Troubleshooting

### Help! I'm seeing PHP compatibility errors
If you're seeing errors similar to any of the below, then you've hit a variation of [codeception/codeception#5031](https://github.com/Codeception/Codeception/issues/5031)
* ```ERROR: Declaration of Codeception\Test\Test::run(?PHPUnit\Framework\TestResult $result = NULL) must be compatible with PHPUnit\Framework\Test::run(?PHPUnit\Framework\TestResult $result = NULL): PHPUnit\Framework\TestResult```
* ```PHP Fatal error:  Declaration of Codeception\Test\Test::toString() must be compatible with PHPUnit\Framework\SelfDescribing::toString(): string```
* ```ERROR: Declaration of Codeception\Test\Test::toString() must be compatible with PHPUnit\Framework\SelfDescribing::toString(): string```

The simplest way to fix this, [till a real fix lands upstream](https://github.com/Codeception/Codeception/pull/5894), is to add the following to the very top of your `Robofile` instead of just relying on Composer's autoloader:

```php
<?php

require_once 'vendor/autoload.php';
require_once 'vendor/codeception/codeception/autoload.php';
```

Update these paths depending on where your `Robofile` lives in relation to Composer's `vendor` directory.

This forces Codeception's autoloader to fire and redeclare the PHPUnit classes that it needs to function.

# Inspiration
* [robo-paracept](https://github.com/Codeception/robo-paracept) from the Codeception folks
