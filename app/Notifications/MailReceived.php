<?php

namespace App\Notifications;

/** A client replied to an email sent from the app (or a new email reached the inbox). */
class MailReceived extends AppNotification
{
    public const TYPE = 'mail_received';
}
