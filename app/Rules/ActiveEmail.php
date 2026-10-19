<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ActiveEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = strtolower(trim((string) $value));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fail('Enter an email address that can receive mail.');

            return;
        }

        $domain = substr(strrchr($email, '@') ?: '', 1);
        if ($domain === '' || ! $this->domainCanReceiveMail($domain)) {
            $fail('That email domain cannot receive messages. Use an active email address.');
        }
    }

    private function domainCanReceiveMail(string $domain): bool
    {
        if (! function_exists('checkdnsrr')) {
            return true;
        }

        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
    }
}
