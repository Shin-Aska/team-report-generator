<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class LlmController extends Controller
{
    public function proxy(Request $request)
    {
        // CORS allowlist from env (comma separated list). Supports '*' wildcard per label.
        $allowed = array_filter(array_map('trim', explode(',', (string) env('LLM_ALLOWED_ORIGINS', 'http://localhost,http://127.0.0.1,http://localhost:3000,http://127.0.0.1:3000,http://localhost:5173,http://127.0.0.1:5173,https://richardorilla.website,https://*.richardorilla.website,http://richardorilla.website,http://*.richardorilla.website'))));
        $origin = $request->headers->get('Origin', '');
        $isAllowed = $this->originAllowed($origin, $allowed);

        $headers = [];
        if ($origin && $isAllowed) {
            $headers['Access-Control-Allow-Origin'] = $origin;
            $headers['Vary'] = 'Origin';
            $headers['Access-Control-Allow-Credentials'] = 'false';
            $headers['Access-Control-Allow-Headers'] = 'Content-Type';
            $headers['Access-Control-Allow-Methods'] = 'POST, OPTIONS';
            $headers['Access-Control-Max-Age'] = '600';
        }

        if ($request->isMethod('options')) {
            return response('', 204, $headers);
        }

        if (!$request->isMethod('post')) {
            return response()->json(['error' => 'Method Not Allowed'], 405, $headers);
        }

        // Rate limit: 10 req/min per IP
        $ip = $request->header('X-Forwarded-For') ?: $request->ip();
        $ip = trim(explode(',', (string) $ip)[0]);
        $key = 'llm:'.$ip;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $retry = RateLimiter::availableIn($key);
            return response()->json(['error' => 'Too Many Requests', 'message' => 'Rate limit exceeded. Try again later.'], 429, array_merge($headers, ['Retry-After' => (string) $retry]));
        }
        RateLimiter::hit($key, 60);

        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return response()->json(['error' => 'Server misconfiguration: GEMINI_API_KEY not set'], 500, $headers);
        }

        $raw = $request->getContent();
        if ($raw === '' || $raw === false) {
            return response()->json(['error' => 'Empty request body'], 400, $headers);
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'Invalid JSON payload', 'details' => json_last_error_msg()], 400, $headers);
        }

        $model = env('GEMINI_MODEL', 'gemini-2.5-flash-preview-05-20');
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.urlencode($model).':generateContent?key='.$apiKey;

        // Retry with exponential backoff
        $delayMs = 1000;
        $lastErr = null;
        for ($i=0; $i<3; $i++) {
            try {
                $resp = Http::timeout(30)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->asJson()
                    ->post($url, $decoded);

                if ($resp->successful()) {
                    return response($resp->body(), $resp->status(), array_merge($headers, ['Content-Type' => 'application/json']));
                }
                $lastErr = 'Upstream error (HTTP '.$resp->status().'): '.$resp->body();
            } catch (\Throwable $e) {
                $lastErr = 'HTTP error: '.$e->getMessage();
            }
            usleep($delayMs * 1000);
            $delayMs *= 2;
        }

        return response()->json([
            'error' => 'Bad Gateway',
            'message' => 'Failed to reach Gemini after retries',
            'details' => $lastErr,
        ], 502, $headers);
    }

    protected function originAllowed(string $origin, array $allowed): bool
    {
        if ($origin === '' || empty($allowed)) return false;
        foreach ($allowed as $pattern) {
            // convert wildcard to regex
            $regex = '/^'.str_replace(['*','/'], ['[^.]*','\/'], preg_quote($pattern, '/')).'$/i';
            // Handle our own wildcard transform correctly:
            $regex = '/^'.str_replace('\*', '[^.]*', preg_quote($pattern, '/')).'$/i';
            if (preg_match($regex, $origin)) return true;
        }
        return in_array($origin, $allowed, true);
    }
}
