<?php

if (!function_exists('strip_punctuation')) {
    function strip_punctuation($string)
    {
        return trim(preg_replace([
            '/[^[^\p{L}\d]+/u',
            '/[\s]+/',
        ], [
            ' ',
            ' ',
        ], $string));
    }
}
