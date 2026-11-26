<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A provider base URL a platform admin may enter. The server will call it, so it must not point inside the network:
 * https only, no credentials in the URL, no query or fragment, and no localhost, private or reserved IP address.
 * Local development may use http://localhost (APP_ENV=local only). A host name that later resolves to a private
 * address is a residual risk recorded in docs/risks.md (R-22).
 */
class SafeProviderUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a web address.');

            return;
        }

        $parts = parse_url(trim($value));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if ($parts === false || $host === '' || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            $fail('The :attribute must be a plain web address such as https://api.example.com/v1 (no user name, query or fragment).');

            return;
        }

        $internal = $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')
            || (filter_var($host, FILTER_VALIDATE_IP) !== false && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false);

        if (app()->isLocal() && $internal && in_array($scheme, ['http', 'https'], true)) {
            return;
        }

        if ($scheme !== 'https') {
            $fail('The :attribute must start with https://.');

            return;
        }

        if ($internal) {
            $fail('The :attribute must be a public address, not localhost or a private network address.');
        }
    }
}
