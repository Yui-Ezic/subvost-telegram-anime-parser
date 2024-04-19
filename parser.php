<?php

/** @var int[] $messagesToIgnore */
$messagesToIgnore = require __DIR__ . '/messages-to-ignore.php';

/** @var array<int, array{
 *     title: string,
 *     url?: string|null,
 *     hashtags?: string[]|null
 * }> $parserHelper
 */
$parserHelper = require __DIR__ . '/parser-helper.php';

$messages = json_decode(file_get_contents(__DIR__ . '/result.json'), true)['messages'];

$titles = [];
foreach ($messages as $message) {
    if (in_array($message['id'], $messagesToIgnore) ||
        empty($message['text']) ||
        empty($message['media_type']) ||
        $message['media_type'] !== 'video_file'
    ) {
        continue;
    }


    // Validations
    if (!isset($parserHelper[$message['id']])) {
        $problem = match (true) {
            is_string($message['text']) => [
                'description' => 'Message text is not an array',
                'messageText' => $message['text']
            ],
            is_string($message['text'][0]) => [
                'description' => 'First element in message text is not an array',
                'messageText' => $message['text'][0]
            ],
            $message['text'][0]['type'] !== 'text_link' => [
                'description' => "First text element is not of type 'text_link'",
                'messageText' => $message['text'][0]['text']
            ],
            default => null
        };

        if ($problem !== null) {
            echo "\033[33m" . 'Message with id ' . $message['id'] . " ignored.\033[0m" . PHP_EOL .
                'Problem description: ' . $problem['description'] . PHP_EOL .
                "Please check it and add to 'messages-to-ignore.php' or to 'parser-helper.php'." . PHP_EOL .
                'Message text: ' . "\033[37m" . $problem['messageText'] . "\033[0m" . PHP_EOL;
            echo PHP_EOL;
            continue;
        }
    }

    // Parsing
    if (isset($parserHelper[$message['id']])) {
        $title = $parserHelper[$message['id']];
    } else {
        $hashtags = array_filter($message['text'], static function (array|string $text) {
            if (is_string($text)) {
                return false;
            }
            return $text['type'] === 'hashtag';
        });
        $hashtags = array_map(static function (array $text) {
            // remove '#' from begin
            return substr($text['text'], 1);
        }, $hashtags);

        $title = [
            'title' => $message['text'][0]['text'],
            'url' => $message['text'][0]['href'] ?? null,
            'hashtags' => array_values($hashtags)
        ];
    }
    $titles[$title['title']] = $title;
}

// Save to json
file_put_contents($jsonFilePath = __DIR__ . '/titles.json', json_encode($titles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Save to csv
$titlesCsvFile = fopen($csvFilePath = __DIR__ . '/titles.csv', 'w');
fputcsv($titlesCsvFile, ['title', 'url', 'hashtags']);
foreach ($titles as $title) {
    fputcsv($titlesCsvFile, [
        $title['title'],
        $title['url'] ?? null,
        isset($title['hashtags']) ? json_encode($title['hashtags'], JSON_UNESCAPED_UNICODE) : null,
    ]);
}
fclose($titlesCsvFile);

echo "\033[32m" . "Parsing is completed and results saved.\033[0m" . PHP_EOL .
    'Json: ' . $jsonFilePath . PHP_EOL .
    'Csv: ' . $csvFilePath . PHP_EOL;
