<?php

namespace App\Mail;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class GmailApiTransport extends AbstractTransport
{
    private ?string $accessToken = null;

    private int $accessTokenExpiresAt = 0;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $rawMessage = rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '=');

        try {
            $response = Http::withToken($this->token())
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', ['raw' => $rawMessage]);

            if ($response->status() === 401) {
                $this->accessToken = null;
                $response = Http::withToken($this->token())
                    ->acceptJson()
                    ->connectTimeout(5)
                    ->timeout(15)
                    ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', ['raw' => $rawMessage]);
            }
        } catch (ConnectionException $exception) {
            throw new TransportException('Unable to reach Gmail API over HTTPS.', previous: $exception);
        }

        if (! $response->successful() || ! is_string($response->json('id')) || $response->json('id') === '') {
            throw new TransportException('Gmail API did not accept the email (HTTP '.$response->status().').');
        }

        $message->setMessageId($response->json('id'));
    }

    private function token(): string
    {
        if ($this->accessToken !== null && $this->accessTokenExpiresAt > now()->getTimestamp()) {
            return $this->accessToken;
        }

        if ($this->clientId === '' || $this->clientSecret === '' || $this->refreshToken === '') {
            throw new TransportException('Gmail API OAuth credentials are not configured.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type' => 'refresh_token',
            ]);

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new TransportException('Gmail OAuth token refresh failed (HTTP '.$response->status().'). Reconnect the sender account.');
        }

        $this->accessToken = $token;
        $this->accessTokenExpiresAt = now()->getTimestamp() + max(0, (int) $response->json('expires_in', 3600) - 60);

        return $this->accessToken;
    }

    public function __toString(): string
    {
        return 'gmail-api';
    }
}
