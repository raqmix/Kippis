<?php

namespace Tests\Support;

use App\Core\Services\FcmService;

/** Records pushes instead of calling FCM. */
class RecordingFcm extends FcmService
{
    public array $sent = [];
    public bool $fail = false;

    public function sendDataToToken(string $token, string $title, string $body, array $data): void
    {
        if ($this->fail) {
            throw new \RuntimeException('fcm down');
        }
        $this->sent[] = compact('token', 'title', 'body', 'data');
    }

    public function types(): array
    {
        return array_map(fn ($s) => $s['data']['type'], $this->sent);
    }
}
