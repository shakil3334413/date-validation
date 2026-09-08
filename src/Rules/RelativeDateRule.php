<?php

namespace Shakil\DateValidation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Shakil\DateValidation\RelativeDate;

abstract class RelativeDateRule implements ValidationRule
{
    public function __construct(protected string|int $offset)
    {
    }

    abstract protected function direction(): string;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $dates = app(RelativeDate::class);
        $boundary = $dates->boundary($this->offset, $this->direction());

        if (! $dates->passes($value, $boundary, $this->direction())) {
            $fail($dates->messageKey($this->offset, $this->direction()))->translate([
                'boundary' => $boundary->toDateString(),
                'offset' => $this->offset,
            ]);
        }
    }
}
