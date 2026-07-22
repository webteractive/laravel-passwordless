<?php

namespace Webteractive\Passwordless\Strategies\LoginCode;

class CodeGenerator
{
    public function generate(int $length): string
    {
        $length = max(6, min(10, $length));
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }

    public function normalize(string $code): string
    {
        // Strip everything that isn't a digit. Apple autofill inserts hyphens,
        // email clients sometimes inject NBSPs/zero-width chars, and users
        // routinely paste codes with spaces or "123-456" formatting.
        return preg_replace('/\D+/u', '', $code) ?? '';
    }
}
