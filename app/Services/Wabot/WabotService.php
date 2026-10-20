<?php

namespace App\Services\Wabot;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class WabotService
{
    /** @return array{success: bool, retryable: bool, http_status: int|null, response: string|null, error_code: string|null} */
    public function sendText(string $channel, string $phone, string $message): array
    {
        $channelCredentials = config('services.wabot.channels.'.$channel, []);
        $baseUrl = rtrim(trim((string) (($channelCredentials['base_url'] ?? null) ?: config('services.wabot.base_url'))), '/');
        $instanceId = trim((string) ($channelCredentials['instance_id'] ?? ''));
        $accessToken = trim((string) ($channelCredentials['access_token'] ?? ''));

        if (parse_url($baseUrl, PHP_URL_SCHEME) !== 'https'
            || ! is_string(parse_url($baseUrl, PHP_URL_HOST))
            || $instanceId === '' || $accessToken === ''
            || ! preg_match('/^60\d{8,11}$/', $phone)) {
            return $this->result(false, false, null, null, 'INVALID_CONFIGURATION_OR_PHONE');
        }

        $url = $baseUrl.'/api/send.php?'.http_build_query([
            'type' => 'text', 'instance_id' => $instanceId, 'access_token' => $accessToken,
        ], '', '&', PHP_QUERY_RFC3986);

        try {
            $response = Http::asForm()->connectTimeout(5)->timeout(15)->post($url, [
                'number' => $phone,
                'message' => $message,
            ]);
        } catch (ConnectionException) {
            return $this->result(false, true, null, null, 'CONNECTION_FAILED');
        }

        $body = mb_substr(str_replace([$accessToken, $instanceId], '[redacted]', $response->body()), 0, 4096);
        $status = $response->status();

        if ($response->successful()) {
            // Confirm the exact provider success contract against a real account response before refining this rule.
            return $this->result(true, false, $status, $body, null);
        }

        return $this->result(false, $status === 429 || $status >= 500, $status, $body, 'HTTP_'.$status);
    }

    /** @return array{success: bool, retryable: bool, http_status: int|null, response: string|null, error_code: string|null} */
    private function result(bool $success, bool $retryable, ?int $httpStatus, ?string $response, ?string $errorCode): array
    {
        return [
            'success' => $success,
            'retryable' => $retryable,
            'http_status' => $httpStatus,
            'response' => $response,
            'error_code' => $errorCode,
        ];
    }
}
