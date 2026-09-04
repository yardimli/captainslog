<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CroppedIcon implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_starts_with($value, 'data:image/png;base64,')) {
            $fail('The custom icon must be a cropped PNG image.');

            return;
        }

        $image = base64_decode(substr($value, strlen('data:image/png;base64,')), true);
        $dimensions = $image === false ? false : @getimagesizefromstring($image);
        if (! $dimensions || $dimensions[0] !== 128 || $dimensions[1] !== 128 || ($dimensions['mime'] ?? null) !== 'image/png') {
            $fail('The custom icon must be exactly 128 by 128 pixels.');
        }
    }
}
