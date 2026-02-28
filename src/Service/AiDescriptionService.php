<?php

// src/Service/AiDescriptionService.php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiDescriptionService
{
    private HttpClientInterface $client;
    private string $apiKey;

    public function __construct(HttpClientInterface $client, string $apiKey)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
    }

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

        $response = $this->client->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'openai/gpt-oss-120b',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a professional educational content writer.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
            ]
        ]);

        $responseData = $response->toArray();

        return $responseData['choices'][0]['message']['content'] ?? 'Unable to generate description at this time.';
    }
}