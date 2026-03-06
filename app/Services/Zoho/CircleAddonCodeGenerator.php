<?php

namespace App\Services\Zoho;

use App\Enums\CircleBillingTerm;
use App\Models\Circle;
use Illuminate\Support\Str;

class CircleAddonCodeGenerator
{
    public function generate(Circle $circle, CircleBillingTerm $term): string
    {
        $base = strtoupper(substr(str_replace('-', '', (string) $circle->id), 0, 12));

        if ($base === '') {
            $base = strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12));
        }

        return sprintf('CRCL_%s_%s', $base, $term->suffix());
    }
}
