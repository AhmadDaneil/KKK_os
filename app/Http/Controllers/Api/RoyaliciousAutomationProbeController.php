<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoyaliciousAutomationProbeController extends Controller
{
    public function __invoke(Request $request, string $token): JsonResponse
    {
        $configuredToken = (string) config('services.wabot.royalicious_automation_probe_token', '');

        if (! preg_match('/\A[a-f0-9]{64}\z/D', $configuredToken) || ! hash_equals($configuredToken, $token)) {
            return response()->json(['message' => 'Unauthorized.', 'code' => 'UNAUTHORIZED'], 401);
        }

        $body = $request->getContent();

        if (strlen($body) > 65536) {
            return response()->json(['message' => 'Payload too large.', 'code' => 'PAYLOAD_TOO_LARGE'], 413);
        }

        $decoded = json_decode($body, true);
        $payload = is_array($decoded) ? $decoded : $request->request->all();

        if ($payload === [] || array_is_list($payload)) {
            return response()->json(['message' => 'A non-empty object is required.', 'code' => 'INVALID_PAYLOAD'], 422);
        }

        $fieldPaths = $this->fieldPaths($payload);

        if ($fieldPaths === []) {
            return response()->json(['message' => 'No inspectable fields were received.', 'code' => 'INVALID_PAYLOAD'], 422);
        }

        Log::info('Royalicious automation probe received.', [
            'field_paths' => $fieldPaths,
            'payload_bytes' => strlen($body),
        ]);

        return response()->json(['success' => true, 'mode' => 'observe_only']);
    }

    /** @param array<array-key, mixed> $payload
     *  @return array<int, string>
     */
    private function fieldPaths(array $payload, string $prefix = '', int $depth = 0): array
    {
        $paths = [];

        foreach ($payload as $key => $value) {
            if (count($paths) >= 64) {
                break;
            }

            if (! is_string($key) || ! preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,39}\z/D', $key)) {
                continue;
            }

            $path = $prefix === '' ? $key : $prefix.'.'.$key;
            $paths[] = $path;

            if (is_array($value) && $depth < 2) {
                foreach ($this->fieldPaths($value, $path, $depth + 1) as $childPath) {
                    if (count($paths) >= 64) {
                        break;
                    }

                    $paths[] = $childPath;
                }
            }
        }

        return $paths;
    }
}
