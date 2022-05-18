<?php

namespace OpenXPort\Test\VCard;

final class TestUtils
{
    /* Casts object to array and filters null values */
    public static function toArray($obj)
    {
        $array = json_decode(json_encode($obj), true);

        $array = array_map(function ($item) {
            return is_array($item) ? self::toArray($item) : $item;
        }, $array);
        return array_filter($array, function ($item) {
            return $item !== "" && $item !== null && (!is_array($item) || count($item) > 0);
        });
    }
}
