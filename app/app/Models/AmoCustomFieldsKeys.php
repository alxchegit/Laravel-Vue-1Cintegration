<?php


namespace App\Models;


class AmoCustomFieldsKeys
{

    public static  function getKey(string $key): int
    {
        return (int)env('CF_'.$key, 0);
    }
}
