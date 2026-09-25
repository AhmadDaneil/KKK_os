<?php

namespace App\Http\Controllers;

use App\Services\Calendar\JakimHijriCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class PublicCalendarController extends Controller
{
    public function convert(Request $request, JakimHijriCalendarService $calendar): JsonResponse|RedirectResponse
    {
        if ($request->header('X-KKK-Calendar-Request') !== '1') {
            return redirect()->to($this->safeReturnPath($request));
        }

        $validated = $request->validate([
            'calendar' => ['required', Rule::in(['gregorian'])],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        try {
            return response()->json($calendar->gregorianToHijri($validated['date']));
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Kalendar rasmi JAKIM tidak dapat dicapai. Sila cuba lagi.',
            ], 503);
        }
    }

    private function safeReturnPath(Request $request): string
    {
        $returnPath = $request->query('return_to');

        if (is_string($returnPath) && str_starts_with($returnPath, '/') && ! str_starts_with($returnPath, '//')) {
            return $returnPath;
        }

        return route('home');
    }
}
