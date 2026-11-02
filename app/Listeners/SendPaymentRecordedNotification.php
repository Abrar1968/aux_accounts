<?php

namespace App\Listeners;

use App\Events\PaymentPosted;
use App\Notifications\PaymentRecorded;
use App\Services\Notifications\NotificationService;

class SendPaymentRecordedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(PaymentPosted $e): void
    {
        $label = ['customer' => 'Customer receipt', 'expense' => 'Expense payment', 'salary' => 'Salary payment'][$e->kind] ?? 'Payment';
        $url = ['customer' => '/accounting/payments', 'expense' => '/accounting/expenses', 'salary' => '/accounting/payroll'][$e->kind] ?? '/';
        $this->notifications->send(new PaymentRecorded($label.' recorded', $e->code.' · ৳'.number_format($e->amount, 2), 'success', $url, ['kind' => $e->kind]));
    }
}
