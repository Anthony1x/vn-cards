<?php

declare(strict_types=1);

namespace App\Cli;

use App\Core\Config;

class CliFormatter
{
    public static function displayFrequencyTable(array $cards, ?int $totalNotes = null, ?int $summaryFreq = null): void
    {
        $max_key_width = 0;
        $max_count_width = 0;
        $max_first_count_width = 0;
        $max_freq_width = 0;

        $totalCount = 0;
        $totalFirst = 0;
        $totalFreq = 0;
        $tagCount = count($cards);

        $firstData = [];

        foreach ($cards as $key => $value) {
            $max_key_width = max($max_key_width, mb_strwidth((string)$key, 'UTF-8'));
            $max_count_width = max($max_count_width, strlen((string)$value['Count']));
            $max_freq_width = max($max_freq_width, strlen((string)($value['Freq'] ?? '')));

            $firstCount = $value['First'] ?? 0;
            $count = $value['Count'];
            $pct = $count > 0 ? number_format($firstCount / $count * 100, 1) : '0.0';
            $firstData[$key] = ['count' => $firstCount, 'pct' => $pct];
            $max_first_count_width = max($max_first_count_width, strlen((string)$firstCount));

            $totalCount += $value['Count'];
            $totalFirst += $firstCount;
            $totalFreq += $value['Freq'] ?? 0;
        }

        $avgFreq = $summaryFreq ?? ($tagCount > 0 ? (int)round($totalFreq / $tagCount) : 0);
        $summaryCount = $totalNotes ?? $totalCount;
        $summaryPct = $summaryCount > 0 ? number_format($totalFirst / $summaryCount * 100, 1) : '0.0';
        $max_first_count_width = max($max_first_count_width, strlen((string)$totalFirst));

        $max_key_width = max($max_key_width, 5);
        $max_count_width = max($max_count_width, strlen((string)$summaryCount));
        $max_first_width = max($max_first_count_width + 8, strlen('First'));
        $max_freq_width = max($max_freq_width, strlen((string)$avgFreq));

        $max_key_width = max($max_key_width, 3);
        $max_count_width = max($max_count_width, 5);
        $max_freq_width = max($max_freq_width, 4);

        $header = sprintf(
            "%-{$max_key_width}s | %{$max_count_width}s | %-{$max_first_width}s | %{$max_freq_width}s",
            'Tag', 'Count', 'First', 'Freq'
        );
        echo $header . "\n";
        echo str_repeat('-', strlen($header)) . "\n";

        $colors = [
            'VN'     => "\033[1;36m", // Bold Cyan
            'Book'   => "\033[1;33m", // Bold Yellow
            'アニメ' => "\033[1;35m", // Bold Magenta
            '漫画'   => "\033[1;32m", // Bold Green
        ];

        foreach ($cards as $key => $value) {
            $current_key_width = mb_strwidth((string)$key, 'UTF-8');
            $key_padding = str_repeat(' ', $max_key_width - $current_key_width);

            $count_str = (string)$value['Count'];
            $count_padding = str_repeat(' ', $max_count_width - strlen($count_str));

            $fd = $firstData[$key];
            $pctPart = '-' . sprintf('%5s', $fd['pct']) . '%';
            $countPart = str_pad((string)$fd['count'], $max_first_count_width, ' ', STR_PAD_LEFT);
            $first_str = $countPart . ' ' . $pctPart;
            $first_padding = str_repeat(' ', $max_first_width - strlen($first_str));

            $freq_str = (string)$value['Freq'];
            $freq_padding = str_repeat(' ', $max_freq_width - strlen($freq_str));

            $display_key = $key;
            foreach ($colors as $prefix => $color_code) {
                if (str_starts_with((string)$key, $prefix . "::") || $key === $prefix) {
                    $display_key = $color_code . $key . "\033[0m";
                    break;
                }
            }

            echo "{$display_key}{$key_padding} | {$count_padding}{$count_str} | {$first_str}{$first_padding} | {$freq_padding}{$freq_str}\n";
        }

        echo str_repeat('-', strlen($header)) . "\n";
        $summaryPctPart = '-' . sprintf('%5s', $summaryPct) . '%';
        $summaryCountPart = str_pad((string)$totalFirst, $max_first_count_width, ' ', STR_PAD_LEFT);
        $summaryFirstStr = $summaryCountPart . ' ' . $summaryPctPart;
        $tag_padding = str_repeat(' ', $max_key_width - 5);
        $count_padding = str_repeat(' ', $max_count_width - strlen((string)$summaryCount));
        $first_padding = str_repeat(' ', $max_first_width - strlen($summaryFirstStr));
        $freq_padding = str_repeat(' ', $max_freq_width - strlen((string)$avgFreq));
        echo "Total{$tag_padding} | {$count_padding}{$summaryCount} | {$summaryFirstStr}{$first_padding} | {$freq_padding}{$avgFreq}\n";
    }

    public static function displayRetentionTable(array $cards): void
    {
        $frontField = Config::get('FRONT_FIELD');
        $data = [];

        foreach ($cards as $card) {
            $word = $card->fields->{$frontField}->value ?? 'Unknown';
            $word = strip_tags($word);
            $retention = number_format($card->retention_rate * 100, 1) . '%';
            $data[] = [
                'word' => $word,
                'retention' => $retention,
                'reps' => (string)$card->reps,
                'lapses' => (string)$card->lapses,
                'ivl' => (string)($card->interval ?? 0) . 'd'
            ];
        }

        $widths = [
            'word' => mb_strlen('Word'),
            'retention' => strlen('Retention'),
            'reps' => strlen('Reps'),
            'lapses' => strlen('Lapses'),
            'ivl' => strlen('Ivl'),
        ];

        foreach ($data as $row) {
            $widths['word'] = max($widths['word'], mb_strwidth($row['word'], 'UTF-8'));
            $widths['retention'] = max($widths['retention'], strlen($row['retention']));
            $widths['reps'] = max($widths['reps'], strlen($row['reps']));
            $widths['lapses'] = max($widths['lapses'], strlen($row['lapses']));
            $widths['ivl'] = max($widths['ivl'], strlen($row['ivl']));
        }

        $header_line = sprintf(
            "%-{$widths['word']}s | %-{$widths['retention']}s | %-{$widths['reps']}s | %-{$widths['lapses']}s | %-{$widths['ivl']}s",
            'Word', 'Retention', 'Reps', 'Lapses', 'Ivl'
        );
        echo "\n" . $header_line . "\n";
        echo str_repeat('-', strlen($header_line)) . "\n";

        foreach ($data as $row) {
            $word_padding = str_repeat(' ', $widths['word'] - mb_strwidth($row['word'], 'UTF-8'));
            printf(
                "%s%s | %{$widths['retention']}s | %{$widths['reps']}s | %{$widths['lapses']}s | %{$widths['ivl']}s\n",
                $row['word'],
                $word_padding,
                $row['retention'],
                $row['reps'],
                $row['lapses'],
                $row['ivl']
            );
        }
        echo "\n";
    }

    public static function displayCompounds(array $compounds, int $characters): void
    {
        echo "Found " . count($compounds) . " $characters-character compounds:\n";
        $chunks = array_chunk($compounds, 13);
        foreach ($chunks as $chunk) {
            echo implode(" ", $chunk) . "\n";
        }
    }

    public static function displayMediaStats(array $stats): void
    {
        echo "\nCard Media Statistics:\n";
        echo str_repeat('-', 25) . "\n";
        printf("%-18s: %d\n", "Image, No Audio", $stats['image_no_audio']);
        printf("%-18s: %d\n", "Image & Audio", $stats['image_audio']);
        printf("%-18s: %d\n", "Neither", $stats['neither']);
        
        if ($stats['audio_no_image'] > 0) {
            printf("%-18s: %d\n", "Audio, No Image", $stats['audio_no_image']);
        }

        echo str_repeat('-', 25) . "\n";
        printf("%-18s: %d\n", "Total Cards", $stats['total']);
        echo "\n";
    }

    public static function displayDueStats(string $time, array $stats): void
    {
        echo "\nDue Statistics for: $time (Day {$stats['diffDays']})\n";
        echo str_repeat('-', 40) . "\n";
        printf("%-25s: %d\n", "Due on this day", $stats['onDay']);
        printf("%-25s: %d\n", "Total due from this day", $stats['cumulative']);
        echo "\n";
    }
}
