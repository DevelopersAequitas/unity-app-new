<?php

if (! function_exists('normalize_mobile_number')) {
    function normalize_mobile_number(?string $mobile): string
    {
        if (! is_string($mobile) || $mobile === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return strlen($digits) === 10 ? $digits : '';
    }
}
