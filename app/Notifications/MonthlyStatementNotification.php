<?php

namespace App\Notifications;

use App\Models\MonthlyStatement;
use App\Models\NotificationLog;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonthlyStatementNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly MonthlyStatement $statement,
    ) {}

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolveMailOnly(
            $notifiable,
            \App\Services\NotificationPreferenceService::STATEMENTS,
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        $period  = $this->statement->period_formatted;
        $closing = number_format((float) $this->statement->closing_balance, 2);
        $brand   = Setting::get('statement.brand_name', 'FundFlow');

        return [
            'title'   => "{$brand} — Statement Ready: {$period}",
            'body'    => "Your monthly statement for {$period} is ready. Closing balance: SAR {$closing}.",
            'icon'    => 'heroicon-o-document-chart-bar',
            'color'   => 'success',
            'actions' => [
                ['label' => 'View Statements', 'url' => url('/member/my-statements')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $statement = $this->statement;
        $period    = $statement->period_formatted;
        $brand     = Setting::get('statement.brand_name', 'FundFlow');
        $tagline   = Setting::get('statement.tagline', 'Member Fund Management');
        $closing   = number_format((float) $statement->closing_balance, 2);
        $disclaimer = Setting::get('statement.footer_disclaimer', 'This is a computer-generated statement.');

        $subject = "{$brand} — Monthly Statement: {$period}";

        // Generate PDF for attachment
        $pdfContent = null;
        try {
            $pdf = Pdf::loadView('pdf.monthly-statement', ['statement' => $statement]);
            $pdfContent = $pdf->output();
        } catch (\Throwable) {
            // non-fatal: send without attachment if PDF fails
        }

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Dear {$notifiable->name},")
            ->line("Your monthly account statement for **{$period}** has been generated.")
            ->line('')
            ->line("**Financial Summary**")
            ->line("Opening Balance: SAR " . number_format((float) $statement->opening_balance, 2))
            ->line("Contributions this month: SAR " . number_format((float) $statement->total_contributions, 2))
            ->line("Loan repayments this month: SAR " . number_format((float) $statement->total_repayments, 2))
            ->line("**Closing Balance: SAR {$closing}**")
            ->line('')
            ->action('View All Statements', url('/member/my-statements'))
            ->line('')
            ->line($disclaimer);

        if ($pdfContent !== null) {
            $mail->attachData(
                $pdfContent,
                "statement-{$statement->period}.pdf",
                ['mime' => 'application/pdf'],
            );
        }

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => $subject,
            'body'    => "Statement for {$period}. Closing balance: SAR {$closing}.",
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        return $mail;
    }
}
