<?php

$messages = json_decode(file_get_contents(__DIR__ . '/result.json'), true)['messages'];

$titles = [];
foreach ($messages as $message) {
    if (isset($message['media_type']) && $message['media_type'] === 'video_file') {
        if (empty($message['text'])) {
            continue;
        }

        $knownMessages = [
            201 => [
                'title' => 'Этот вампир постоянно умирает 2'
            ],
            1422 => [
                'title' => 'Ван-Пис (2023) | Адаптация Netflix'
            ]
        ];

        // Validations
        if (!isset($knownMessages[$message['id']])) {
            if (is_string($message['text'])) {
                if (in_array($message['id'], ['29', '197', '512', '2786', '2997'])) {
                    continue;
                }
                echo 'Text is not array ' . $message['id'] . ' ' . $message['text'] . PHP_EOL;
                continue;
            }
            if (is_string($message['text'][0])) {
                if (in_array($message['id'], ['1643', '2439', '2778', '2790'])) {
                    continue;
                }
                echo 'Text first element is not array ' . $message['id'] . ' ' . $message['text'][0] . PHP_EOL;
                continue;
            }
        }



        // Process
        if (isset($knownMessages[$message['id']])) {
            $title = $knownMessages[$message['id']];
        } else {
            if ($message['text'][0]['type'] !== 'text_link') {
                echo 'First text is not text_link in message id ' . $message['id'] . PHP_EOL;
            }

            $hashtags = array_filter($message['text'], static function (array|string $text) {
                if (is_string($text)) {
                    return false;
                }
                return $text['type'] === 'hashtag';
            });
            $hashtags = array_map(static function(array $text){
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
}

// Save to json
file_put_contents(__DIR__ . '/titles.json', json_encode($titles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Save to csv
$titlesCsvFile = fopen('titles.csv', 'w');
fputcsv($titlesCsvFile, ['title', 'url', 'hashtags']);
foreach ($titles as $title) {
    fputcsv($titlesCsvFile, [
        $title['title'],
        $title['url'] ?? null,
        isset($title['hashtags']) ? json_encode($title['hashtags'], JSON_UNESCAPED_UNICODE) : null,
    ]);
}
fclose($titlesCsvFile);
