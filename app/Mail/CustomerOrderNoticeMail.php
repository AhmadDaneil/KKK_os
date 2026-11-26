<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CustomerOrderNoticeMail extends Mailable
{
    /**
     * @param  array{customerName: string, orderReference: string, title: string, bodyText: string, statusLabel: ?string, amount: ?string, actionUrl: string, actionLabel: string}  $notice
     */
    public function __construct(public string $subjectLine, public array $notice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('customer_reminders.from_address'),
                (string) config('customer_reminders.from_name'),
            ),
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-order-notice',
            text: 'emails.customer-order-notice-text',
            with: $this->notice,
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
