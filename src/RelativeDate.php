<?php

namespace Shakil\DateValidation;

use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use InvalidArgumentException;
use Throwable;

final class RelativeDate
{
    /** @var array<string, Closure(CarbonImmutable, string): CarbonImmutable> */
    private array $keywords;

    public function __construct()
    {
        $this->keywords = [
            'today' => fn (CarbonImmutable $today, string $direction) => $today,
            'yesterday' => fn (CarbonImmutable $today, string $direction) => $today->subDay(),
            'week' => fn (CarbonImmutable $today, string $direction) => $today->addDays($direction === 'min' ? -7 : 7),
            'month' => fn (CarbonImmutable $today, string $direction) => $direction === 'min'
                ? $today->subMonthNoOverflow() : $today->addMonthNoOverflow(),
            'year' => fn (CarbonImmutable $today, string $direction) => $direction === 'min'
                ? $today->subYearNoOverflow() : $today->addYearNoOverflow(),
        ];
    }

    public function extend(string $keyword, Closure $resolver): void
    {
        if (! preg_match('/^[a-z][a-z_]*$/D', $keyword)) {
            throw new InvalidArgumentException('Relative date keywords must contain lowercase letters and underscores.');
        }

        $this->keywords[$keyword] = $resolver;
    }

    public function boundary(string|int $offset, string $direction): CarbonImmutable
    {
        if (! in_array($direction, ['min', 'max'], true)) {
            throw new InvalidArgumentException('Relative date direction must be min or max.');
        }

        $today = CarbonImmutable::today(config('app.timezone'));

        if (isset($this->keywords[(string) $offset])) {
            return ($this->keywords[(string) $offset])($today, $direction)
                ->setTimezone($today->timezone)->startOfDay();
        }

        if (! preg_match('/^[1-9][0-9]*$/D', (string) $offset)
            || filter_var($offset, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('Relative date offsets must be a supported keyword or a positive integer number of days.');
        }

        try {
            return $today->addDays($direction === 'min' ? -(int) $offset : (int) $offset);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Relative date offset is outside the supported date range.', 0, $exception);
        }
    }

    public function passes(mixed $value, CarbonImmutable $boundary, string $direction): bool
    {
        $date = $this->parse($value);

        return $date !== null && ($direction === 'min' ? $date->gte($boundary) : $date->lte($boundary));
    }

    private function parse(mixed $value): ?CarbonImmutable
    {
        try {
            if ($value instanceof DateTimeInterface) {
                return CarbonImmutable::instance($value)->setTimezone(config('app.timezone'))->startOfDay();
            }

            if (! is_string($value) || trim($value) === '') {
                return null;
            }

            // Match Laravel's date validation: reject relative phrases and invalid calendar dates.
            $parts = date_parse($value);
            if ($parts['error_count'] > 0 || $parts['warning_count'] > 0
                || ! checkdate((int) $parts['month'], (int) $parts['day'], (int) $parts['year'])) {
                return null;
            }

            return CarbonImmutable::parse($value, config('app.timezone'))
                ->setTimezone(config('app.timezone'))->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    public function messageKey(string|int $offset, string $direction): string
    {
        return 'relative-date::validation.'.(in_array($offset, ['today', 'yesterday'], true)
            ? $direction.'_'.$offset : $direction);
    }
}
