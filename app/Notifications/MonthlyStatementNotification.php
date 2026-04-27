<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Models\MonthlyStatement;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Services\EmailTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonthlyStatementNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(
        public readonly MonthlyStatement $statement,
    ) {
    }

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolveMailOnly(
            $notifiable,
            \App\Services\NotificationPreferenceService::STATEMENTS,
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        $period = $this->statement->period_formatted;
        $closing = number_format((float) $this->statement->closing_balance, 2);
        $brand = Setting::get('statement.brand_name', 'FundFlow');

        return [
            'title' => $this->tr(':brand — Statement Ready: :period', ':brand — الكشف جاهز: :period', ['brand' => $brand, 'period' => $period]),
            'body' => $this->tr('Your monthly statement for :period is ready. Closing balance: SAR :closing.', 'كشفك الشهري لفترة :period جاهز. الرصيد الختامي: SAR :closing.', ['period' => $period, 'closing' => $closing]),
            'icon' => 'heroicon-o-document-chart-bar',
            'color' => 'success',
            'actions' => [
                ['label' => $this->tr('View Statements', 'عرض الكشوفات'), 'url' => url('/member/my-statements')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $statement = $this->statement;
        $period = $statement->period_formatted;
        $brand = Setting::get('statement.brand_name', 'FundFlow');
        $tagline = Setting::get('statement.tagline', 'Member Fund Management');
        $closing = number_format((float) $statement->closing_balance, 2);
        $disclaimer = Setting::statementFooterDisclaimer();

        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : app()->getLocale();
        $vars = [
            'name' => $notifiable->name,
            'brand' => $brand,
            'period' => $period,
            'opening' => number_format((float) $statement->opening_balance, 2),
            'contributions' => number_format((float) $statement->total_contributions, 2),
            'repayments' => number_format((float) $statement->total_repayments, 2),
            'closing' => $closing,
            'tagline' => $tagline,
        ];
        $subject = EmailTemplateService::render(
            EmailTemplateService::get('monthly_statement', 'subject', $locale, $this->tr(':brand — Monthly Statement: :period', ':brand — كشف شهري: :period', ['brand' => $brand, 'period' => $period])),
            $vars
        );

        // Generate PDF for attachment
        $pdfContent = null;
        try {
            $pdf = Pdf::loadView('pdf.monthly-statement', ['statement' => $statement]);
            $pdfContent = $pdf->output();
        } catch (\Throwable) {
            // non-fatal: send without attachment if PDF fails
        }

        $greeting = EmailTemplateService::render(
            EmailTemplateService::get('monthly_statement', 'greeting', $locale, $this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name])),
            $vars
        );
        $bodyLines = EmailTemplateService::renderLines(
            EmailTemplateService::get('monthly_statement', 'body', $locale, implode("\n", [
                $this->tr('Your monthly account statement for **:period** has been generated.', 'تم إنشاء كشف حسابك الشهري لفترة **:period**.', ['period' => $period]),
                $this->tr('**Financial Summary**', '**الملخص المالي**'),
                $this->tr('Opening Balance: SAR :amount', 'الرصيد الافتتاحي: SAR :amount', ['amount' => number_format((float) $statement->opening_balance, 2)]),
                $this->tr('Contributions this month: SAR :amount', 'مساهمات هذا الشهر: SAR :amount', ['amount' => number_format((float) $statement->total_contributions, 2)]),
                $this->tr('Loan repayments this month: SAR :amount', 'سداد القروض هذا الشهر: SAR :amount', ['amount' => number_format((float) $statement->total_repayments, 2)]),
                $this->tr('**Closing Balance: SAR :amount**', '**الرصيد الختامي: SAR :amount**', ['amount' => $closing]),
            ])),
            $vars
        );
        $actionLabel = EmailTemplateService::render(
            EmailTemplateService::get('monthly_statement', 'action_label', $locale, $this->tr('View All Statements', 'عرض جميع الكشوفات')),
            $vars
        );
        $closingLine = EmailTemplateService::render(
            EmailTemplateService::get('monthly_statement', 'closing', $locale, $disclaimer),
            $vars
        );

        $mail = (new MailMessage)->subject($subject)->greeting($greeting);
        foreach ($bodyLines as $line) {
            $mail->line($line);
        }
        $mail->action($actionLabel, url('/member/my-statements'))->line($closingLine);

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
            'body' => $this->tr('Statement for :period. Closing balance: SAR :closing.', 'كشف فترة :period. الرصيد الختامي: SAR :closing.', ['period' => $period, 'closing' => $closing]),
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $mail;
    }
}
