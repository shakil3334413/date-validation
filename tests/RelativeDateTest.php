<?php

namespace Shakil\DateValidation\Tests;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Shakil\DateValidation\DateValidationServiceProvider;
use Shakil\DateValidation\RelativeDate;
use Shakil\DateValidation\Rules\RelativeMinDate;
use Shakil\DateValidation\Rules\RelativeMaxDate;

class RelativeDateTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [DateValidationServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Dhaka']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-09 12:30:00', 'Asia/Dhaka'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public static function boundaries(): iterable
    {
        foreach ([
            ['today', '2026-09-09', '2026-09-09'],
            ['yesterday', '2026-09-08', '2026-09-08'],
            ['week', '2026-09-02', '2026-09-16'],
            ['month', '2026-08-09', '2026-10-09'],
            ['year', '2025-09-09', '2027-09-09'],
            [1, '2026-09-08', '2026-09-10'],
            [7, '2026-09-02', '2026-09-16'],
            [30, '2026-08-10', '2026-10-09'],
            [90, '2026-06-11', '2026-12-08'],
        ] as [$offset, $min, $max]) {
            yield (string) $offset => [$offset, $min, $max];
        }
    }

    #[DataProvider('boundaries')]
    public function test_boundaries_in_both_apis(string|int $offset, string $min, string $max): void
    {
        foreach (['min' => $min, 'max' => $max] as $direction => $boundary) {
            $object = $direction === 'min' ? new RelativeMinDate($offset) : new RelativeMaxDate($offset);
            foreach (['date|relative_'.$direction.':'.$offset, ['date', $object]] as $rules) {
                $this->assertTrue($this->app['validator']->make(['day' => $boundary.' 23:59:59'], ['day' => $rules])->passes());
                $outside = CarbonImmutable::parse($boundary)->addDays($direction === 'min' ? -1 : 1)->toDateString();
                $this->assertFalse($this->app['validator']->make(['day' => $outside], ['day' => $rules])->passes());
            }
        }
    }

    public function test_combined_rules_and_builtin_rules(): void
    {
        foreach (['today' => ['2026-09-09', '2026-09-16'], 'week' => ['2026-09-02', '2026-09-16'], 30 => ['2026-08-10', '2026-10-09']] as $min => [$start, $end]) {
            $max = $min === 'today' ? 'week' : $min;
            $rules = ['day' => "required|date|relative_min:$min|relative_max:$max"];
            foreach ([$start, $end] as $value) {
                $this->assertTrue($this->app['validator']->make(['day' => $value], $rules)->passes());
            }
            foreach ([CarbonImmutable::parse($start)->subDay(), CarbonImmutable::parse($end)->addDay()] as $value) {
                $this->assertFalse($this->app['validator']->make(['day' => $value->toDateString()], $rules)->passes());
            }
        }
        $factory = $this->app['validator'];
        $rules = ['number' => 'numeric|min:7|max:10', 'name' => 'string|min:3|max:5', 'items' => 'array|min:1|max:2'];
        $this->assertTrue($factory->make(['number' => 8, 'name' => 'abcd', 'items' => [1]], $rules)->passes());
        $this->assertFalse($factory->make(['number' => 6, 'name' => 'ab', 'items' => []], $rules)->passes());
    }

    public function test_calendar_overflow_and_leap_years(): void
    {
        $dates = $this->app->make(RelativeDate::class);
        foreach ([
            ['2024-01-31', 'month', 'max', '2024-02-29'],
            ['2026-01-31', 'month', 'max', '2026-02-28'],
            ['2024-03-31', 'month', 'min', '2024-02-29'],
            ['2024-02-29', 'year', 'min', '2023-02-28'],
            ['2024-02-29', 'year', 'max', '2025-02-28'],
        ] as [$now, $offset, $direction, $expected]) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse($now, 'Asia/Dhaka'));
            $this->assertSame($expected, $dates->boundary($offset, $direction)->toDateString());
        }
    }

    public function test_timezone_conversion_dst_and_date_objects(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-08 20:00:00', 'UTC'));
        $rules = ['day' => 'relative_min:today|relative_max:today'];
        foreach (['2026-09-09', '2026-09-08T20:00:00Z', new \DateTimeImmutable('2026-09-08T20:00:00Z')] as $date) {
            $this->assertTrue($this->app['validator']->make(['day' => $date], $rules)->passes());
        }
        $this->assertFalse($this->app['validator']->make(['day' => '2026-09-09T23:00:00Z'], $rules)->passes());
        config(['app.timezone' => 'America/New_York']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-08 12:00:00', 'America/New_York'));
        $this->assertSame('2026-03-01', $this->app->make(RelativeDate::class)->boundary('week', 'min')->toDateString());
    }

    public function test_invalid_dates_and_optional_values(): void
    {
        foreach (['garbage', 'tomorrow', '2026-02-30', '2026-13-01', [], 123, true] as $value) {
            foreach (['relative_min:today', [new RelativeMaxDate('today')]] as $rules) {
                $this->assertFalse($this->app['validator']->make(['day' => $value], ['day' => $rules])->passes());
            }
        }
        $this->assertTrue($this->app['validator']->make(['day' => null], ['day' => 'nullable|relative_min:today'])->passes());
        $this->assertTrue($this->app['validator']->make([], ['day' => 'relative_min:today'])->passes());
        $this->assertFalse($this->app['validator']->make([], ['day' => 'required|relative_min:today'])->passes());
    }

    public static function invalidOffsets(): iterable
    {
        foreach (['0', '-1', '1.5', 'unknown', '', 'today,week', '99999999999999999999999999'] as $offset) {
            yield [$offset];
        }
    }

    #[DataProvider('invalidOffsets')]
    public function test_bad_rule_configuration_throws(string $offset): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->app['validator']->make(['day' => '2026-09-09'], ['day' => 'relative_min:'.$offset])->passes();
    }

    public function test_messages_translations_and_customization(): void
    {
        $factory = $this->app['validator'];
        foreach (['relative_min:today', [new RelativeMinDate('today')]] as $rules) {
            $validator = $factory->make(['day' => '2026-09-08'], ['day' => $rules], [], ['day' => 'start date']);
            $this->assertSame('The start date must be today or later.', $validator->errors()->first('day'));
        }
        $validator = $factory->make(['day' => '2026-08-01'], ['day' => 'relative_min:30']);
        $this->assertSame('The day must be on or after 2026-08-10.', $validator->errors()->first('day'));
        $validator = $factory->make(['day' => '2026-08-01'], ['day' => 'relative_min:30'], [
            'day.relative_min' => ':attribute needs :boundary (offset :offset).',
        ]);
        $this->assertSame('day needs 2026-08-10 (offset 30).', $validator->errors()->first('day'));
        $this->app['translator']->addLines(['validation.relative_max' => 'Limit :boundary.'], 'en');
        $this->assertSame('Limit 2026-09-16.', $factory->make(['day' => '2026-10-01'], ['day' => 'relative_max:week'])->errors()->first('day'));
        $this->app['translator']->addLines(['validation.min_today' => 'Localized :attribute.'], 'en', 'relative-date');
        $this->assertSame('Localized day.', $factory->make(['day' => '2026-08-01'], ['day' => [new RelativeMinDate('today')]])->errors()->first('day'));
    }

    public function test_extensions_and_reused_rule_calculate_today_at_validation_time(): void
    {
        $dates = $this->app->make(RelativeDate::class);
        $dates->extend('fortnight', fn (CarbonImmutable $today, string $direction) => $today->addDays($direction === 'min' ? -14 : 14));
        $this->assertSame('2026-09-23', $dates->boundary('fortnight', 'max')->toDateString());
        $this->assertTrue($this->app['validator']->make(['day' => '2026-09-23'], ['day' => 'relative_max:fortnight'])->passes());
        $rule = new RelativeMinDate('today');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10', 'Asia/Dhaka'));
        $this->assertFalse($this->app['validator']->make(['day' => '2026-09-09'], ['day' => [$rule]])->passes());
    }
}
