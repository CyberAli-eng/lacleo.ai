<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use InvalidArgumentException;

class ClassCast implements CastsAttributes
{
    public function get($_model, string $_key, $value, array $_attributes)
    {
        if (! $value) {
            return null;
        }
        if (! class_exists($value)) {
            throw new InvalidArgumentException("Class {$value} does not exist");
        }

        return $value;
    }

    public function set($_model, string $_key, $value, array $_attributes)
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value) && class_exists($value)) {
            return $value;
        }
        if (is_object($value)) {
            return get_class($value);
        }
        throw new InvalidArgumentException('Invalid class value provided');
    }
}
