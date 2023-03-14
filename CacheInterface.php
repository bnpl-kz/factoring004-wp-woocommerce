<?php

defined('ABSPATH') || exit;

interface CacheInterface
{
    public static function get($key);
    public static function set($key, $value);
    public static function delete($key);
}