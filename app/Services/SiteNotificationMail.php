<?php

namespace App\Services;

use App\Models\HotelContact;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SiteNotificationMail
{
    public static function adminTo(): string
    {
        return self::teamRecipients()[0] ?? '';
    }

    /**
     * Hotel inbox, plus every Admin and Super Admin account.
     *
     * @return list<string>
     */
    public static function teamRecipients(): array
    {
        $emails = [];

        foreach ([
            config('mail.notification.admin_to'),
            config('mail.notification.admin_cc'),
            HotelContact::query()->value('email'),
            Setting::query()->value('email'),
        ] as $candidate) {
            self::pushEmail($emails, $candidate);
        }

        User::query()
            ->whereIn('role_id', [1, 2])
            ->pluck('email')
            ->each(function ($email) use (&$emails) {
                self::pushEmail($emails, $email);
            });

        return array_values($emails);
    }

    /**
     * @param  array<string, string>  $emails
     */
    private static function pushEmail(array &$emails, mixed $candidate): void
    {
        $email = strtolower(trim((string) $candidate));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $emails[$email] = $email;
    }

    private static function resendReady(): bool
    {
        $key = config('resend.api_key') ?? config('services.resend.key');
        if (is_string($key) && trim($key) !== '') {
            return true;
        }

        Log::warning('Site notification skipped: RESEND_API_KEY is empty.');

        return false;
    }

    public static function adminCc(): ?string
    {
        $cc = config('mail.notification.admin_cc');

        return filled($cc) ? (string) $cc : null;
    }

    /**
     * Send to primary inbox + CC (team notifications).
     */
    public static function sendToTeam(Mailable $mailable): bool
    {
        if (! self::resendReady()) {
            return false;
        }

        $recipients = self::teamRecipients();
        if ($recipients === []) {
            Log::warning('Site notification: no hotel or admin email is configured.');

            return false;
        }
        try {
            Mail::to($recipients)->send($mailable);

            return true;
        } catch (\Throwable $e) {
            Log::error('Site notification mail to team failed', [
                'message' => $e->getMessage(),
                'mailer' => config('mail.default'),
                'exception' => $e,
            ]);

            return false;
        }
    }

    public static function sendToGuest(string $email, Mailable $mailable): bool
    {
        $email = trim($email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if (! self::resendReady()) {
            return false;
        }
        try {
            Mail::to($email)->send($mailable);

            return true;
        } catch (\Throwable $e) {
            Log::error('Site notification mail to guest failed', [
                'message' => $e->getMessage(),
                'mailer' => config('mail.default'),
                'email' => $email,
                'exception' => $e,
            ]);

            return false;
        }
    }
}
