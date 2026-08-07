<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\TermsOfServiceUpdatedNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Notification;

return new class extends Migration
{
    public function up(): void
    {
        // In non-production environments cap the recipient list so importing the prod DB does not flood Herd's mail
        // system with thousands of messages. Always include the clodan account... because reasons. Shut up! Go away.
        if (! app()->environment('production')) {
            $clodan = User::query()
                ->whereNotNull('email_verified_at')
                ->where('name', 'clodan')
                ->first();

            $clodanId = $clodan?->id;

            $recipients = User::query()
                ->whereNotNull('email_verified_at')
                ->when($clodanId, fn ($query) => $query->where('id', '!=', $clodanId))
                ->limit($clodan instanceof User ? 4 : 5)
                ->get();

            if ($clodan instanceof User) {
                $recipients->prepend($clodan);
            }

            Notification::send($recipients, new TermsOfServiceUpdatedNotification);

            return;
        }

        User::query()
            ->whereNotNull('email_verified_at')
            ->chunkById(500, function ($users): void {
                Notification::send($users, new TermsOfServiceUpdatedNotification);
            });
    }

    public function down(): void
    {
        // One-shot announcement; all go, no reverse.
    }
};
