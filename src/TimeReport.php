<?php
declare(strict_types=1);

namespace ChinthakaGodawita\CodeceptionTimekeeper;

use DateTimeInterface;
use JsonSerializable;

final class TimeReport implements JsonSerializable
{

    private const DATE_FORMAT = DateTimeInterface::ATOM;

    private $times;

    public function __construct(array $times = [])
    {
        $this->times = $times;
    }

    public function setTime(string $testPath, float $time): void
    {
        // Add on existing times, e.g. for tests with multiple scenarios.
        if (array_key_exists($testPath, $this->times)) {
            $time += $this->times[$testPath];
        }
        $this->times[$testPath] = $time;
    }

    public function getTime(string $testPath): ?float
    {
        if (!array_key_exists($testPath, $this->times)) {
            return null;
        }
        return $this->times[$testPath];
    }

    public function totalRuntime(): ?float
    {
        if (empty($this->times)) {
            return null;
        }
        return array_sum($this->times);
    }

    public function jsonSerialize()
    {
        return [
            'generated_at' => date(self::DATE_FORMAT),
            'count' => count($this->times),
            'times' => $this->times,
        ];
    }

    /**
     * @param string $json
     *
     * @return static
     *
     * @throws JsonException If decoding fails.
     */
    public static function fromJson(string $json): self
    {
        // We don't use 'JSON_THROW_ON_ERROR' for PHP 7.2 compatibility.
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $err = json_last_error_msg();
            throw new JsonException(
                "Could not decode JSON: {$err}"
            );
        }

        return new self($decoded['times']);
    }

    /**
     * @return string
     *
     * @throws JsonException If encoding fails.
     */
    public function toJson(): string
    {
        // We don't use 'JSON_THROW_ON_ERROR' for PHP 7.2 compatibility.
        $json = json_encode($this, JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false || json_last_error() !== JSON_ERROR_NONE) {
            $err = json_last_error_msg();
            throw new JsonException(
                "Could not encode JSON: {$err}"
            );
        }

        return $json;
    }

}
