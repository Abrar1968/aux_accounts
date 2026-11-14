<?php

namespace App\Console\Commands;

use App\Services\Mail\MailboxService;
use Illuminate\Console\Command;

/** Every minute: read new messages from the company mailbox into the Inbox (read-only IMAP). */
class FetchMail extends Command
{
    protected $signature = 'mail:fetch {--limit=40 : Messages per run}';

    protected $description = 'Import new email (client replies) from the company mailbox into the Inbox';

    public function handle(MailboxService $mailbox): int
    {
        $r = $mailbox->sync((int) $this->option('limit'));
        if ($r['skipped']) {
            $this->line('Mailbox not configured — skipped.');

            return self::SUCCESS;
        }
        if ($r['error']) {
            $this->error('Mailbox: '.$r['error']);

            return self::FAILURE;
        }
        $this->info("Imported {$r['new']} message(s), {$r['linked']} linked to sent emails.");

        return self::SUCCESS;
    }
}
