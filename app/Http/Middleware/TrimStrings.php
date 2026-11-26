<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class TrimStrings extends Middleware
{
    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected $except = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /** Selain dirapikan spasinya, tanda pisah panjang (em/en dash) diganti tanda minus biasa "-". */
    protected function transform($key, $value)
    {
        $value = parent::transform($key, $value);

        return is_string($value) && ! in_array($key, $this->except, true)
            ? str_replace(["\u{2014}", "\u{2013}"], '-', $value)
            : $value;
    }
}
