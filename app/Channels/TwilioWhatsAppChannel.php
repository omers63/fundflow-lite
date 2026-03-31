<?php

namespace App\Channels;

use App\Models\NotificationLog;
use Twilio\Rest\Client;

class TwilioWhatsAppChannel
{
    public function send(mixed $notifiable, mixed $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $phone = $notifiable->routeNotificationFor('twilio', $notification)
            ?? $notifiable->phone;

        if (! $phone) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);

        if (! $message) {
            return;
        }

        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $from = config('services.twilio.whatsapp_from');

        if (! $accountSid || ! $authToken || ! $from) {
            NotificationLog::create([
                'user_id' => $notifiable->id ?? null,
                'channel' => 'whatsapp',
                'subject' => null,
                'body' => $message,
                'status' => 'failed',
                'error_message' => 'Twilio WhatsApp credentials not configured.',
                'sent_at' => now(),
            ]);
            return;
        }

        try {
            $client = new Client($accountSid, $authToken);
            $client->messages->create(
                'whatsapp:' . $phone,
                ['from' => $from, 'body' => $message]
            );

            NotificationLog::create([
                'user_id' => $notifiable->id ?? null,
                'channel' => 'whatsapp',
                'subject' => null,
                'body' => $message,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            NotificationLog::create([
                'user_id' => $notifiable->id ?? null,
                'channel' => 'whatsapp',
                'subject' => null,
                'body' => $message,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'sent_at' => now(),
            ]);
        }
    }
}
