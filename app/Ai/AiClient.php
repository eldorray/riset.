<?php

declare(strict_types=1);

namespace App\Ai;

use App\Billing\Billing;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Klien layanan AI OpenAI-compatible (POST {base_url}/chat/completions).
 * Kunci API hanya dibaca di server dari .env, tidak pernah dikirim ke browser.
 */
final class AiClient
{
    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $apiKey,
        private readonly ?string $model,
        private readonly int $timeout = 120,
        private readonly bool $jsonMode = true,
    ) {}

    public static function fromConfig(): self
    {
        /** @var array{base_url: ?string, api_key: ?string, model: ?string, timeout: int, json_mode: bool} $config */
        $config = config('services.ai');

        return new self($config['base_url'], $config['api_key'], $config['model'], $config['timeout'], $config['json_mode']);
    }

    /**
     * Kirim satu permintaan dan kembalikan objek JSON dari jawaban model.
     *
     * @return array<string, mixed>
     *
     * @throws AiException
     */
    public function json(string $system, string $prompt): array
    {
        $billing = app(Billing::class);
        $user = $billing->actor ?? request()->user();
        $id = $user ? $billing->reserve($user, Billing::credits(strlen($system) + strlen($prompt) + 2000, 8192), (string) $this->model, $billing->writingRunId ? 'Penulisan latar belakang' : (request()->route()?->getName() ?? 'AI')) : null;
        try {
            [$data, $usage] = $this->requestJson($system, $prompt, $id !== null);
            if ($id !== null) {
                $billing->usage($id, $usage);
            }

            return $data;
        } catch (Throwable $e) {
            if ($id !== null) {
                $billing->discardLast();
            }
            throw $e;
        }
    }

    /** @return array{array<string, mixed>, mixed} */
    private function requestJson(string $system, string $prompt, bool $metered): array
    {
        if (blank($this->baseUrl) || blank($this->model)) {
            throw new AiException('Layanan AI belum dikonfigurasi. Isi AI_BASE_URL dan AI_MODEL di .env.');
        }

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        if ($metered) {
            $payload['max_completion_tokens'] = 8192;
        }

        if ($this->jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        // php -S (artisan serve) dan banyak hosting membatasi skrip 30 detik, padahal satu
        // jawaban AI bisa lebih lama; tanpa ini proses PHP dimatikan di tengah permintaan.
        // CLI/antrean (batas 0) tidak disentuh.
        $limit = (int) ini_get('max_execution_time');

        if ($limit > 0 && $limit < $this->timeout + 30 && function_exists('set_time_limit')) {
            set_time_limit($this->timeout + 30);
        }

        try {
            $response = Http::acceptJson()
                ->when(filled($this->apiKey), fn ($http) => $http->withToken((string) $this->apiKey))
                ->timeout($this->timeout)
                ->post(rtrim((string) $this->baseUrl, '/').'/chat/completions', $payload)
                ->throw();
        } catch (ConnectionException $e) {
            throw new AiException('Layanan AI tidak dapat dihubungi. Coba lagi beberapa saat.', previous: $e);
        } catch (RequestException $e) {
            throw new AiException("Layanan AI menolak permintaan (HTTP {$e->response->status()}).", previous: $e);
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content)) {
            throw new AiException('Jawaban layanan AI kosong.');
        }

        // Sebagian model tetap membungkus JSON dengan ```json … ``` meski diminta JSON murni.
        $content = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)));
        $data = json_decode($content, true);

        if (! is_array($data)) {
            throw new AiException('Jawaban layanan AI tidak dapat dibaca.');
        }

        /** @var array<string, mixed> $data */
        return [$data, $response->json('usage')];
    }
}
