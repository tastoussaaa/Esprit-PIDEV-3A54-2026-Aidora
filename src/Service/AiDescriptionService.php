<?php

// src/Service/AiDescriptionService.php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiDescriptionService
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $baseUrl,
        private readonly string $model
    ) {
    }

    /**
     * @param array{title?: string, category?: string, startDate?: string, endDate?: string} $data
     */
    public function generateDescription(array $data): string
    {
        $prompt = sprintf(
            "Generate a professional and engaging medical formation description using the following details:
- Title: %s
- Category: %s
- Start Date: %s
- End Date: %s
IMPORTANT:
Return the response strictly in clean HTML format.
Use <h3> for titles, <p> for paragraphs, <ul><li> for lists, and <table> for program schedule.
Do NOT use Markdown.
Do NOT use ** or ###.
Return only valid HTML without backticks.,
Please make it clear, concise, and suitable for students and professionals.",
            $data['title'] ?? '',
            $data['category'] ?? '',
            $data['startDate'] ?? '',
            $data['endDate'] ?? ''
        );

        $response = $this->client->request('POST', rtrim($this->baseUrl, '/') . '/api/generate', [
            'json' => [
                'model' => $this->model,
                'prompt' => "You are a professional educational content writer.\n\n" . $prompt,
                'stream' => false,
                'options' => [
                    'temperature' => 0.7,
                    'num_predict' => 600,
                ],
            ],
            'timeout' => 120,
        ]);

        $responseData = $response->toArray(false);

        return $responseData['response'] ?? 'Unable to generate description at this time.';
    }
}
