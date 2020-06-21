# Codeception TimeKeeper

A [Codeception](https://codeception.com/) extension & [Robo](https://robo.li) task that records test runtimes and lets you split tests into equal runtime-based groups for parallel runs

**Coming soon**

# Usage
## Codeception time reporting extension
Update your `codeception.yml` with:
```yaml
extensions:
    enabled:
        - ChinthakaGodawita\CodeceptionTimekeeper\Reporter:
              report_location: _data/time_report.json
```
