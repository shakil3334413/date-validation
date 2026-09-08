<?php

namespace Shakil\DateValidation\Rules;

final class RelativeMinDate extends RelativeDateRule
{
    protected function direction(): string
    {
        return 'min';
    }
}
