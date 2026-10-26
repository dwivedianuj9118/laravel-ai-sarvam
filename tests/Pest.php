<?php

use Dwivedianuj9118\LaravelAiSarvam\Tests\TestCase;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;

uses(TestCase::class)->in('Feature', 'Live');

function fakeSarvamChatResponse(string $text = 'नमस्ते'): PromiseInterface
{
    return Http::response([
        'id' => 'chatcmpl-1',
        'object' => 'chat.completion',
        'created' => 1,
        'model' => 'sarvam-105b',
        'choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => $text],
            'finish_reason' => 'stop',
        ]],
        'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 3, 'total_tokens' => 15],
    ]);
}

function fakeSarvamAudioResponse(string $audio = 'fake-wav-bytes'): PromiseInterface
{
    return Http::response([
        'request_id' => 'req_1',
        'audios' => [base64_encode($audio)],
    ]);
}

function fakeSarvamTranscriptionResponse(string $transcript = 'आज का पंचांग', array $extra = []): PromiseInterface
{
    return Http::response([
        'request_id' => 'req_1',
        'transcript' => $transcript,
        'language_code' => 'hi-IN',
        ...$extra,
    ]);
}

function multipartField(Illuminate\Http\Client\Request $request, string $name): ?string
{
    foreach ($request->data() as $part) {
        if (($part['name'] ?? null) === $name) {
            return (string) $part['contents'];
        }
    }

    return null;
}
