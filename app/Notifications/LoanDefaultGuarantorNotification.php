<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\NotificationLog;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanDefaultGuarantorNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(
        public readonly Loan $loan,
        public readonly LoanInstallment $installment,
    ) {
    }

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolveMailOnly(
            $notifiable,
            \App\Services\NotificationPreferenceService::LOAN_ALERTS,
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return ['title' => $this->tr('Guarantor Debit: Borrower Default', 'خصم على الكفيل: تعثر المقترض'), 'body' => $this->tr('SAR :amount has been debited from your fund account as guarantor for :borrower\'s Loan #:loan.', 'تم خصم SAR :amount من حساب صندوقك بصفتك كفيلًا لقرض :borrower رقم :loan.', ['amount' => number_format((float) $this->installment->amount, 2), 'borrower' => $this->loan->member->user->name, 'loan' => $this->loan->id]), 'icon' => 'heroicon-o-exclamation-circle', 'color' => 'danger'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $borrower = $this->loan->member->user->name;
        $amount = 'SAR ' . number_format((float) $this->installment->amount, 2);
        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : app()->getLocale();
        $vars = ['name' => $notifiable->name, 'borrower' => $borrower, 'loan' => $this->loan->id, 'amount' => $amount, 'installment' => $this->installment->installment_number];
        $subject = EmailTemplateService::render(
            EmailTemplateService::get('loan_default_guarantor', 'subject', $locale, $this->tr('FundFlow — Guarantor Debit Notification', 'FundFlow — إشعار خصم على الكفيل')),
            $vars
        );
        $greeting = EmailTemplateService::render(
            EmailTemplateService::get('loan_default_guarantor', 'greeting', $locale, $this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name])),
            $vars
        );
        $bodyLines = EmailTemplateService::renderLines(
            EmailTemplateService::get('loan_default_guarantor', 'body', $locale, implode("\n", [
                $this->tr('As the guarantor for **:borrower** (Loan #:loan), the amount of **:amount** (installment #:installment) has been debited from your fund account due to the borrower\'s missed repayment.', 'بصفتك كفيلًا لـ **:borrower** (القرض رقم :loan)، تم خصم مبلغ **:amount** (القسط رقم :installment) من حساب صندوقك بسبب تعثر المقترض عن السداد.', ['borrower' => $borrower, 'loan' => $this->loan->id, 'amount' => $amount, 'installment' => $this->installment->installment_number]),
                $this->tr('You will be released from your guarantee once the borrower fully settles the fund\'s portion.', 'سيتم إخلاء مسؤوليتك ككفيل بعد سداد المقترض كامل حصة الصندوق.'),
            ])),
            $vars
        );
        $actionLabel = EmailTemplateService::render(
            EmailTemplateService::get('loan_default_guarantor', 'action_label', $locale, $this->tr('View My Account', 'عرض حسابي')),
            $vars
        );

        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => $subject, 'body' => "Debit {$amount} for {$borrower}.", 'status' => 'sent', 'sent_at' => now()]);
        $mail = (new MailMessage)->subject($subject)->greeting($greeting);
        foreach ($bodyLines as $line) {
            $mail->line($line);
        }
        return $mail->action($actionLabel, url('/member'));
    }
}
