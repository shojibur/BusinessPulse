<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The single server-side helper for toast messages. The id lets the frontend
 * show each message once, even when a page is restored by back/forward navigation.
 */
class Flash
{
    public static function toast(string $type, string $message): void
    {
        session()->flash('flash', [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'message' => $message,
        ]);
    }

    public static function success(string $message): void
    {
        self::toast('success', $message);
    }

    public static function error(string $message): void
    {
        self::toast('error', $message);
    }
}
