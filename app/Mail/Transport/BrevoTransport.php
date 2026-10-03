<?php

namespace App\Mail\Transport;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class BrevoTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $apiKey = config('services.brevo.key');

        if (blank($apiKey)) {
            throw new TransportException('Brevo API key is not configured.');
        }

        $email = $message->getOriginalMessage();

        if (!$email instanceof Email) {
            throw new TransportException('Brevo transport only supports Symfony Email messages.');
        }

        $payload = $this->buildPayload($email, $message->getEnvelope());

        try {
            $response = Http::withHeaders([
                'api-key' => $apiKey,
                'Accept' => 'application/json',
            ])->asJson()->post('https://api.brevo.com/v3/smtp/email', $payload);
        } catch (ConnectionException $e) {
            throw new TransportException('Unable to reach the Brevo email service.');
        }

        if ($response->successful()) {
            $data = $response->json();
            $messageId = $data['messageId'] ?? null;
            if ($messageId) {
                $message->setMessageId($messageId);
            }
            return;
        }

        $status = $response->status();

        throw new TransportException($this->sanitizeErrorMessage($status));
    }

    protected function buildPayload(Email $email, Envelope $envelope): array
    {
        $payload = [];

        $from = $email->getFrom();
        if (!empty($from)) {
            $sender = $from[0];
            $payload['sender'] = [
                'email' => $sender->getAddress(),
                'name' => $sender->getName() ?: null,
            ];
        }

        $to = $this->mapAddresses($email->getTo());
        if (!empty($to)) {
            $payload['to'] = $to;
        }

        $cc = $this->mapAddresses($email->getCc());
        if (!empty($cc)) {
            $payload['cc'] = $cc;
        }

        $bcc = $this->mapAddresses($email->getBcc());
        if (!empty($bcc)) {
            $payload['bcc'] = $bcc;
        }

        $replyTo = $this->mapAddresses($email->getReplyTo());
        if (!empty($replyTo)) {
            $payload['replyTo'] = $replyTo[0];
        }

        $subject = $email->getSubject();
        if ($subject !== null) {
            $payload['subject'] = $subject;
        }

        $htmlBody = $email->getHtmlBody();
        $textBody = $email->getTextBody();

        if ($htmlBody !== null) {
            $payload['htmlContent'] = $htmlBody;
        }

        if ($textBody !== null) {
            $payload['textContent'] = $textBody;
        }

        $attachments = $email->getAttachments();
        if (!empty($attachments)) {
            $payload['attachment'] = $this->mapAttachments($attachments);
        }

        return $payload;
    }

    protected function mapAddresses(array $addresses): array
    {
        return array_map(function (Address $address) {
            $mapped = ['email' => $address->getAddress()];
            if ($address->getName() !== '') {
                $mapped['name'] = $address->getName();
            }
            return $mapped;
        }, $addresses);
    }

    protected function mapAttachments(array $attachments): array
    {
        return array_map(function ($attachment) {
            $mapped = [
                'content' => base64_encode($attachment->getBody()),
                'name' => $attachment->getFilename() ?? 'attachment',
            ];
            return $mapped;
        }, $attachments);
    }

    protected function sanitizeErrorMessage(int $status): string
    {
        return match (true) {
            $status === 400 => 'Brevo rejected the email request.',
            $status === 401, $status === 403 => 'Brevo authentication failed.',
            $status === 429 => 'Brevo rate limit reached.',
            $status >= 500 => 'Brevo email service is temporarily unavailable.',
            default => 'Brevo email delivery failed.',
        };
    }

    public function __toString(): string
    {
        return 'brevo+api';
    }
}
