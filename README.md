# Laravel Relative Date Validation

Reusable Laravel rules with inclusive calendar-day boundaries, application timezone handling, rule objects, and pipe syntax.

Requires PHP 8.2+ and Laravel 12 or 13 (Laravel 13 requires PHP 8.3+).

## Installation

Install the package in any Laravel application with Composer:

~~~bash
composer require shakil3334413/date-validation
~~~

Laravel discovers the service provider automatically. If discovery is disabled, register Shakil\DateValidation\DateValidationServiceProvider in the application's providers.

This package is distributed through Packagist from the public GitHub repository. Stable releases use semantic version tags such as v1.0.0.

## Local Development Install

This repository is a Composer package. No Laravel application, Nginx virtual host, npm build, or compiled assets are needed here.

For local testing before a release, add this repository to the consuming Laravel application's composer.json:

~~~json
{
    "repositories": [
        {
            "type": "path",
            "url": "/var/www/html/date-validation",
            "options": {
                "symlink": true,
                "versions": {"shakil3334413/date-validation": "dev-production"}
            }
        }
    ]
}
~~~

Run in that application:

~~~bash
composer require shakil3334413/date-validation:dev-production
~~~

## Usage

~~~php
$request->validate([
    'start_date' => 'required|date|relative_min:today',
    'end_date' => ['required', 'date', 'relative_max:week'],
    'appointment' => 'required|date|relative_min:today|relative_max:week',
]);
~~~

Or use objects:

~~~php
use Shakil\DateValidation\Rules\RelativeMinDate;
use Shakil\DateValidation\Rules\RelativeMaxDate;

$request->validate([
    'appointment' => [
        'required',
        'date',
        new RelativeMinDate('today'),
        new RelativeMaxDate('week'),
    ],
]);
~~~

Laravel's existing min / max rules validate numbers, string lengths, array sizes, and file sizes. This package never replaces them. Use relative_min / relative_max for dates.

## Boundaries

For today = 2026-09-09:

| Offset | relative_min (on or after) | relative_max (on or before) |
| --- | --- | --- |
| today | 2026-09-09 | 2026-09-09 |
| yesterday | 2026-09-08 | 2026-09-08 |
| week | 2026-09-02 | 2026-09-16 |
| month | 2026-08-09 | 2026-10-09 |
| year | 2025-09-09 | 2027-09-09 |
| 7 | 2026-09-02 | 2026-09-16 |
| 30 | 2026-08-10 | 2026-10-09 |

~~~php
'date' => 'required|date|relative_min:today|relative_max:today', // Today only.
'date' => 'required|date|relative_min:week|relative_max:week', // Seven days each side.
'date' => 'required|date|relative_min:30|relative_max:30', // Thirty days each side.
~~~

A minimum alone allows all later dates; a maximum alone allows all earlier dates.

Keywords are lowercase and exact. Numeric offsets are positive whole days, as integers or decimal digit strings, within PHP/Carbon's representable range. Zero, negatives, fractions, leading zeroes, unknown keywords, and missing/extra parameters are configuration errors and throw InvalidArgumentException.

Month/year calculations use Carbon's no-overflow arithmetic: January 31 + one month is February 28/29; February 29 + one year is February 28.

## Dates and timezone

Both 2026-09-09 and 2026-09-09 12:30:00 work, along with absolute date strings supported by PHP/Carbon and DateTimeInterface objects. Invalid calendar dates and relative phrases such as "tomorrow" fail. Include Laravel's date rule for normal date validation, or date_format:Y-m-d when an exact format is required.

Comparisons use calendar dates in config('app.timezone'), ignoring time of day after timezone conversion. Thus relative_max:today includes 23:59:59 today. Explicit timezone offsets are converted to the application timezone first; this can change the calendar date. Inputs without a timezone use the application timezone. Day arithmetic uses calendar days across daylight-saving transitions.

Today is evaluated at validation time, so reused rule objects do not retain yesterday's boundary.

These are non-implicit rules. Add required to reject missing/empty values, and nullable to accept null.

## Messages

Defaults include:

- The start date must be today or later.
- The end date must be yesterday or earlier.
- The appointment must be on or after 2026-08-10.
- The appointment must be on or before 2026-10-09.

Exact boundary wording is intentional: a minimum alone does not enforce an upper limit, so "within the last month" could incorrectly imply future dates are rejected.

Pipe rules support Laravel's per-field messages:

~~~php
$request->validate(
    ['appointment' => 'required|date|relative_min:30'],
    ['appointment.relative_min' => 'Choose :attribute on or after :boundary (:offset days).'],
    ['appointment' => 'appointment date'],
);
~~~

You can also define relative_min / relative_max entries in lang/en/validation.php. Placeholders: :attribute, :boundary (YYYY-MM-DD), and :offset.

Customize defaults for both APIs:

~~~bash
php artisan vendor:publish --tag=relative-date-translations
~~~

Edit lang/vendor/relative-date/en/validation.php, or add the same file under another locale. Keys: min, max, min_today, max_today, min_yesterday, max_yesterday. Laravel custom attribute names work with both APIs. Object defaults use package translation keys; global validation.relative_min / validation.relative_max overrides apply to pipe rules.

## Add keywords

In your application's service provider boot method:

~~~php
use Carbon\CarbonImmutable;
use Shakil\DateValidation\RelativeDate;

app(RelativeDate::class)->extend(
    'fortnight',
    fn (CarbonImmutable $today, string $direction): CarbonImmutable =>
        $today->addDays($direction === 'min' ? -14 : 14),
);
~~~

Both APIs now accept fortnight. Return a CarbonImmutable boundary; the package normalizes it to the application calendar day.

## Development and branches

Package directory: /var/www/html/date-validation inside Nginx-WSL.

- staging: develop and test changes here.
- production: promote reviewed, tested commits here and tag stable releases.
- master: original branch, preserved.

The production branch contains the public package code. Tag stable releases from production.

~~~bash
cd /var/www/html/date-validation
composer install
composer test
composer validate --strict
~~~

GitHub Actions is configured for Laravel 12 and 13. Composer installs package source directly; there is no separate build command.

References: [Laravel custom rules](https://laravel.com/docs/13.x/validation#custom-validation-rules) and [Carbon calendar arithmetic](https://carbon.nesbot.com/docs/).
