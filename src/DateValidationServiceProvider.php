<?php

namespace Shakil\DateValidation;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Factory;
use InvalidArgumentException;

final class DateValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RelativeDate::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'relative-date');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/relative-date'),
        ], 'relative-date-translations');

        $this->callAfterResolving('validator', function (Factory $factory): void {
            foreach (['min', 'max'] as $direction) {
                $name = 'relative_'.$direction;

                $factory->extend($name, function ($attribute, $value, $parameters) use ($direction): bool {
                    $offset = $this->offset($parameters);
                    $dates = $this->app->make(RelativeDate::class);

                    return $dates->passes($value, $dates->boundary($offset, $direction), $direction);
                }, ':relative_message');

                $factory->replacer($name, function ($message, $attribute, $rule, $parameters, $validator) use ($direction): string {
                    $offset = $this->offset($parameters);
                    $dates = $this->app->make(RelativeDate::class);
                    $boundary = $dates->boundary($offset, $direction)->toDateString();
                    $translated = $this->app->make('translator')->get(
                        $dates->messageKey($offset, $direction),
                        ['attribute' => $validator->getDisplayableAttribute($attribute), 'boundary' => $boundary, 'offset' => $offset],
                    );

                    return strtr($message, [
                        ':relative_message' => $translated,
                        ':boundary' => $boundary,
                        ':offset' => $offset,
                    ]);
                });
            }
        });
    }

    private function offset(array $parameters): string
    {
        if (count($parameters) !== 1 || ! is_string($parameters[0]) || $parameters[0] === '') {
            throw new InvalidArgumentException('Relative date rules require exactly one offset.');
        }

        return $parameters[0];
    }
}
