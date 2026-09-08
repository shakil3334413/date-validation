<?php

namespace Shakil\DateValidation\Rules;

final class RelativeMaxDate extends RelativeDateRule
{
    protected function direction(): string
    {
        return 'max';
    }
}
