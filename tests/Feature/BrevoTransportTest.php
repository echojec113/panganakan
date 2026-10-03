<?php

use App\Mail\Transport\BrevoTransport;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    config([
        'services.brevo.key' => 'test-brevo-key-12345',
        'mail.default' => 'brevo',
        'mail.from.address' => 'deplafamilycareclinic@gmail.com',
        'mail.from.name' => 'DEPLA FAMILY CARE MATERNITY & LYING IN',
    ]);

    Http::preventStrayRequests();
});

function brevoTransport(): BrevoTransport
{
    $transport = Mail::mailer('brevo')->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(BrevoTransport::class);

    return $transport;
}

function brevoEmail(): Email
{
    return (new Email())
        ->from(new Address('deplafamilycareclinic@gmail.com', 'DEPLA FAMILY CARE MATERNITY & LYING IN'))
        ->to('recipient@example.com')
        ->subject('Test Subject')
        ->text('Test body');
}

test('brevo transport can be resolved', function () {
    $transport = brevoTransport();

    expect((string) $transport)->toBe('brevo+api');
});

test('default mailer resolves to the brevo transport', function () {
    $transport = Mail::mailer()->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(BrevoTransport::class);
});

test('brevo mailer is registered in the mail config', function () {
    expect(config('mail.mailers.brevo'))->toBe(['transport' => 'brevo']);
});

test('brevo transport posts to the brevo endpoint', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-123'], 201),
    ]);

    brevoTransport()->send(brevoEmail());

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.brevo.com/v3/smtp/email';
    });
});

test('api key header exists without exposing value', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-123'], 201),
    ]);

    brevoTransport()->send(brevoEmail());

    Http::assertSent(function ($request) {
        $headers = array_change_key_case($request->headers(), CASE_LOWER);

        return ($headers['api-key'][0] ?? null) === 'test-brevo-key-12345';
    });
});

test('api key never appears in the request payload', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-123'], 201),
    ]);

    brevoTransport()->send(brevoEmail());

    Http::assertSent(function ($request) {
        return ! str_contains($request->body(), 'test-brevo-key-12345');
    });
});

test('sender mapping', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-123'], 201),
    ]);

    brevoTransport()->send(brevoEmail());

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $data['sender']['email'] === 'deplafamilycareclinic@gmail.com'
            && $data['sender']['name'] === 'DEPLA FAMILY CARE MATERNITY & LYING IN';
    });
});

test('to recipient mapping', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-123'], 201),
    ]);

    $email = brevoEmail()->to(new Address('recipient@example.com', 'John Doe'));

    brevoTransport()->send($email);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return count($data['to']) === 1
            && $data['to'][0]['email'] === 'recipient@example.com'
            && $data['to'][0]['name'] === 'John Doe';
    });
});

test('multiple recipients', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-123'], 201),
    ]);

    $email = brevoEmail()
        ->to(new Address('user1@example.com', 'User One'))
        ->addTo(new Address('user2@example.com', 'User Two'));

    brevoTransport()->send($email);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return count($data['to']) === 2
            && $data['to'][0]['email'] === 'user1@example.com'
            && $data['to'][1]['email'] === 'user2@example.com';
    });
});

test('cc mapping', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-123'], 201),
    ]);

    $email = brevoEmail()->cc(new Address('cc@example.com', 'CC Person'));

    brevoTransport()->send($email);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return isset($data['cc'])
            && $data['cc'][0]['email'] === 'cc@example.com'
            && $data['cc'][0]['name'] === 'CC Person';
    });
});

test('bcc mapping', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-123'], 201),
    ]);

    $email = brevoEmail()->bcc(new Address('bcc@example.com', 'BCC Person'));

    brevoTransport()->send($email);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return isset($data['bcc'])
            && $data['bcc'][0]['email'] === 'bcc@example.com'
            && $data['bcc'][0]['name'] === 'BCC Person';
    });
});

test('reply-to mapping', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-123'], 201),
    ]);

    $email = brevoEmail()->replyTo(new Address('reply@example.com', 'Reply Person'));

    brevoTransport()->send($email);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return isset($data['replyTo'])
            && $data['replyTo']['email'] === 'reply@example.com'
            && $data['replyTo']['name'] === 'Reply Person';
    });
});

test('subject mapping preserves utf-8 characters', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-123'], 201),
    ]);

    $subject = 'Prenatal Checkup Reminder: ñáéíóú — kumusta ka na?';
    $email = brevoEmail()->subject($subject);

    brevoTransport()->send($email);

    Http::assertSent(function ($request) use ($subject) {
        $data = $request->data();

        return $data['subject'] === $subject;
    });
});

test('html email mapping', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-123'], 201),
    ]);

    $email = brevoEmail()->html('<html><body><h1>Hello</h1></body></html>');

    brevoTransport()->send($email);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return isset($data['htmlContent'])
            && str_contains($data['htmlContent'], '<h1>Hello</h1>');
    });
});

test('plain text email mapping', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-123'], 201),
    ]);

    $email = brevoEmail()->text('Plain text body');

    brevoTransport()->send($email);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return isset($data['textContent'])
            && $data['textContent'] === 'Plain text body';
    });
});

test('multipart html and text mapping', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-123'], 201),
    ]);

    $email = brevoEmail()
        ->html('<html><body><p>HTML body</p></body></html>')
        ->text('Text body');

    brevoTransport()->send($email);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return isset($data['htmlContent'])
            && isset($data['textContent'])
            && str_contains($data['htmlContent'], 'HTML body')
            && $data['textContent'] === 'Text body';
    });
});

test('attachment mapping', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-123'], 201),
    ]);

    $email = brevoEmail()->attach('file contents', 'report.txt', 'text/plain');

    brevoTransport()->send($email);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return isset($data['attachment'][0])
            && $data['attachment'][0]['name'] === 'report.txt'
            && base64_decode($data['attachment'][0]['content']) === 'file contents';
    });
});

test('successful HTTP 201 response', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'msg-123'], 201),
    ]);

    $sentMessage = brevoTransport()->send(brevoEmail());

    expect($sentMessage)->toBeInstanceOf(SentMessage::class)
        ->and($sentMessage->getMessageId())->toBe('msg-123');
});

test('HTTP 400 failure', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['message' => 'Invalid request'], 400),
    ]);

    expect(fn () => brevoTransport()->send(brevoEmail()))
        ->toThrow(TransportException::class, 'Brevo rejected the email request.');
});

test('HTTP 401 authentication failure', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    expect(fn () => brevoTransport()->send(brevoEmail()))
        ->toThrow(TransportException::class, 'Brevo authentication failed.');
});

test('HTTP 403 authentication failure', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['message' => 'Forbidden'], 403),
    ]);

    expect(fn () => brevoTransport()->send(brevoEmail()))
        ->toThrow(TransportException::class, 'Brevo authentication failed.');
});

test('HTTP 429 rate limit', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['message' => 'Too many requests'], 429),
    ]);

    expect(fn () => brevoTransport()->send(brevoEmail()))
        ->toThrow(TransportException::class, 'Brevo rate limit reached.');
});

test('HTTP 5xx failure', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['message' => 'Server error'], 500),
    ]);

    expect(fn () => brevoTransport()->send(brevoEmail()))
        ->toThrow(TransportException::class, 'Brevo email service is temporarily unavailable.');
});

test('connection exception network failure', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => fn () => throw new ConnectionException('Connection refused'),
    ]);

    try {
        brevoTransport()->send(brevoEmail());
        $this->fail('Expected TransportException');
    } catch (TransportException $e) {
        expect($e->getMessage())->toBe('Unable to reach the Brevo email service.')
            ->and($e->getMessage())->not->toContain('test-brevo-key-12345');
    }
});

test('missing BREVO_API_KEY', function () {
    config(['services.brevo.key' => null]);

    expect(fn () => brevoTransport()->send(brevoEmail()))
        ->toThrow(TransportException::class, 'Brevo API key is not configured.');
});

test('blank BREVO_API_KEY', function () {
    config(['services.brevo.key' => '']);

    expect(fn () => brevoTransport()->send(brevoEmail()))
        ->toThrow(TransportException::class, 'Brevo API key is not configured.');
});

test('secrets are not present in thrown exception messages', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['message' => 'Error with key: test-brevo-key-12345'], 400),
    ]);

    try {
        brevoTransport()->send(brevoEmail());
        $this->fail('Expected TransportException');
    } catch (TransportException $e) {
        expect($e->getMessage())->not->toContain('test-brevo-key-12345')
            ->and($e->getMessage())->not->toContain('api-key');
    }
});

test('laravel mailer raw send passes through brevo transport', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'mailable-123'], 201),
    ]);

    Mail::mailer('brevo')->raw('Test raw message', function ($message) {
        $message->to('recipient@example.com')
            ->subject('Raw test');
    });

    Http::assertSent(function ($request) {
        $data = $request->data();

        return isset($data['to'])
            && $data['to'][0]['email'] === 'recipient@example.com'
            && $data['subject'] === 'Raw test'
            && str_contains($data['textContent'] ?? '', 'Test raw message');
    });
});

test('forgot password email is delivered through the brevo transport', function () {
    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'reset-123'], 201),
    ]);

    $user = \App\Models\User::factory()->create([
        'email' => 'staff@example.com',
        'name' => 'Test Staff',
    ]);

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertStatus(302)
        ->assertSessionHasNoErrors();

    Http::assertSent(function ($request) {
        $data = $request->data();

        return isset($data['to'][0]['email'])
            && $data['to'][0]['email'] === 'staff@example.com'
            && str_contains($data['htmlContent'] ?? '', 'reset-password');
    });
});
