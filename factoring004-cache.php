<?php

defined('ABSPATH') || exit;

require_once 'CacheInterface.php';

class CustomCache implements CacheInterface
{

    public static function get($key)
    {
        $filePath = __DIR__ . "/{$key}.json";

        if (self::exists($key) && !self::expired($key)) {
            return json_decode(file_get_contents($filePath), true);
        }

        return null;
    }

    public static function set($key, $value)
    {
        $filePath = __DIR__ . "/{$key}.json";

        $value['expires'] = time() + 3600;

        return file_put_contents($filePath, json_encode($value));
    }

    public static function delete($key)
    {
        if (self::exists($key)) {
            $filePath = __DIR__ . "/{$key}.json";

            unlink($filePath);
        }

        return true;
    }

    private static function exists($key)
    {
        $filePath = __DIR__ . "/{$key}.json";
        if (file_exists($filePath)) {
            return true;
        }

        return false;
    }

    private static function expired($key) {
        $filePath = __DIR__ . "/{$key}.json";

        $data = json_decode(file_get_contents($filePath),true);

        if ($data['expires'] >= time()) {
            return false;
        }

        self::delete($key);
        return true;
    }
}