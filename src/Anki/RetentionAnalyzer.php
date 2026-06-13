<?php

declare(strict_types=1);

namespace App\Anki;

use App\Core\Config;

class RetentionAnalyzer
{
    private CardManager $cardManager;
    private AnkiConnect $anki;

    public function __construct(CardManager $cardManager, AnkiConnect $anki)
    {
        $this->cardManager = $cardManager;
        $this->anki = $anki;
    }

    public function getCardsByRetention(int $n = 10): array
    {
        $deck = Config::get('DECK_NAME');
        $query = "$deck prop:reps>0 -is:suspended";
        $card_ids = $this->anki->send('findCards', ['query' => $query])->result;

        if (empty($card_ids)) {
            return [];
        }

        $reviewed_cards = $this->anki->send('cardsInfo', ['cards' => $card_ids])->result;

        foreach ($reviewed_cards as $card) {
            $card->retention_rate = ($card->reps - $card->lapses) / $card->reps;
        }

        usort($reviewed_cards, function ($a, $b) {
            if ($a->retention_rate === $b->retention_rate) {
                return $b->reps <=> $a->reps;
            }
            return $a->retention_rate <=> $b->retention_rate;
        });

        return array_slice($reviewed_cards, 0, $n);
    }

    public function getCardsByTag(bool $withFrequency = false): array
    {
        $deck = Config::get('DECK_NAME');
        $notes = $this->cardManager->getAllNotes($deck);

        if ($withFrequency) {
            $tag_data = [];
            foreach ($notes as $note) {
                $freq_value = (int)($note->fields->FreqSort->value ?? 9999999);

                foreach ($this->getNormalizedTags($note->tags) as $tag) {
                    if (!isset($tag_data[$tag])) {
                        $tag_data[$tag] = ['Count' => 0, 'TotalFreq' => 0, 'FreqCount' => 0];
                    }
                    $tag_data[$tag]['Count']++;
                    if ($freq_value !== 9999999) {
                        $tag_data[$tag]['TotalFreq'] += $freq_value;
                        $tag_data[$tag]['FreqCount']++;
                    }
                }
            }

            foreach ($tag_data as $tag => &$data) {
                $data['Freq'] = $data['FreqCount'] > 0 ? (int)round($data['TotalFreq'] / $data['FreqCount']) : 0;
                unset($data['TotalFreq'], $data['FreqCount']);
            }

            uasort($tag_data, fn($a, $b) => $b['Count'] <=> $a['Count']);
            return $tag_data;
        }

        $tag_counts = [];
        foreach ($notes as $note) {
            foreach ($this->getNormalizedTags($note->tags) as $tag) {
                $tag_counts[$tag] = ($tag_counts[$tag] ?? 0) + 1;
            }
        }
        arsort($tag_counts);
        return $tag_counts;
    }

    public function getSourceStats(): array
    {
        $deck = Config::get('DECK_NAME');
        $notes = $this->cardManager->getAllNotes($deck);

        // Map noteId to note for fast lookup
        $note_map = [];
        foreach ($notes as $note) {
            $note_map[(int)$note->noteId] = $note;
        }

        // Initialize stats per source tag
        $source_stats = [];

        // Aggregate total cards and frequencies from notes
        foreach ($notes as $note) {
            $normalized_tags = $this->getNormalizedTags($note->tags);
            $freq_value = (int)($note->fields->FreqSort->value ?? 9999999);

            foreach ($normalized_tags as $tag) {
                // We only group by Book:: tags to track media sources
                if (!str_starts_with($tag, "Book::")) {
                    continue;
                }

                if (!isset($source_stats[$tag])) {
                    $source_stats[$tag] = [
                        'tag' => $tag,
                        'name' => str_replace('Book::', '', $tag),
                        'total_cards' => 0,
                        'total_freq' => 0,
                        'freq_count' => 0,
                        'reps' => 0,
                        'lapses' => 0,
                        'reviewed_cards' => 0,
                    ];
                }

                $source_stats[$tag]['total_cards']++;
                if ($freq_value !== 9999999) {
                    $source_stats[$tag]['total_freq'] += $freq_value;
                    $source_stats[$tag]['freq_count']++;
                }
            }
        }

        // Query reviewed cards with Book tags to compute retention
        $query = "$deck tag:Book::* prop:reps>0 -is:suspended";
        $card_ids = $this->anki->send('findCards', ['query' => $query])->result;

        if (!empty($card_ids)) {
            $reviewed_cards = $this->anki->send('cardsInfo', ['cards' => $card_ids])->result;
            foreach ($reviewed_cards as $card) {
                $note_id = (int)$card->note;
                if (!isset($note_map[$note_id])) {
                    continue;
                }
                $note = $note_map[$note_id];
                $normalized_tags = $this->getNormalizedTags($note->tags);

                foreach ($normalized_tags as $tag) {
                    if (!str_starts_with($tag, "Book::")) {
                        continue;
                    }
                    if (isset($source_stats[$tag])) {
                        $source_stats[$tag]['reps'] += $card->reps;
                        $source_stats[$tag]['lapses'] += $card->lapses;
                        $source_stats[$tag]['reviewed_cards']++;
                    }
                }
            }
        }

        // Compute final stats
        foreach ($source_stats as &$stats) {
            $stats['avg_freq'] = $stats['freq_count'] > 0 ? (int)round($stats['total_freq'] / $stats['freq_count']) : 0;
            $stats['retention_rate'] = $stats['reps'] > 0 ? (float)round(($stats['reps'] - $stats['lapses']) / $stats['reps'], 4) : 1.0;

            // Cleanup temporary aggregations
            unset($stats['total_freq']);
            unset($stats['freq_count']);
        }

        // Sort by card count descending
        uasort($source_stats, fn($a, $b) => $b['total_cards'] <=> $a['total_cards']);

        return array_values($source_stats);
    }

    public function getDifficultyDistribution(): array
    {
        $deck = Config::get('DECK_NAME');
        $notes = $this->cardManager->getAllNotes($deck);

        $distribution = [
            'core' => 0,          // 1 - 5,000
            'common' => 0,        // 5,001 - 15,000
            'intermediate' => 0,  // 15,001 - 30,000
            'advanced' => 0,      // 30,001 - 50,000
            'obscure' => 0,       // 50,001+ (or placeholder)
        ];

        $total_freq = 0;
        $freq_count = 0;

        foreach ($notes as $note) {
            $freq_value = (int)($note->fields->FreqSort->value ?? 9999999);
            if ($freq_value === 9999999) {
                $distribution['obscure']++;
                continue;
            }

            $total_freq += $freq_value;
            $freq_count++;

            if ($freq_value <= 5000) {
                $distribution['core']++;
            } elseif ($freq_value <= 15000) {
                $distribution['common']++;
            } elseif ($freq_value <= 30000) {
                $distribution['intermediate']++;
            } elseif ($freq_value <= 50000) {
                $distribution['advanced']++;
            } else {
                $distribution['obscure']++;
            }
        }

        $average = $freq_count > 0 ? (int)round($total_freq / $freq_count) : 0;

        return [
            'distribution' => $distribution,
            'average' => $average,
            'total' => count($notes),
        ];
    }

    public function getKanjiFirstAppearances(): array
    {
        $parentDeck = '日本語';
        $miningDeck = "deck:{$parentDeck}::Mining";
        $frontField = Config::get('FRONT_FIELD');
        $starterDeckNames = [
            "Core 2.3k",
            "Kaishi 1.5k",
        ];

        $starterNoteIds = [];
        foreach ($starterDeckNames as $name) {
            $deckQuery = "deck:\"{$parentDeck}::{$name}\"";
            $ids = $this->anki->send('findNotes', ['query' => $deckQuery])->result;
            if (!empty($ids)) {
                $starterNoteIds = array_merge($starterNoteIds, $ids);
            }
        }
        $starterNoteIdSet = array_flip($starterNoteIds);

        $miningNotes = $this->cardManager->getAllNotes($miningDeck);

        $starterNotes = [];
        foreach ($starterDeckNames as $name) {
            $deckQuery = "deck:\"{$parentDeck}::{$name}\"";
            $notes = $this->cardManager->getAllNotes($deckQuery);
            $starterNotes = array_merge($starterNotes, $notes);
        }

        $allNotes = array_merge($miningNotes, $starterNotes);

        usort($allNotes, fn($a, $b) => (int)$a->noteId <=> (int)$b->noteId);

        $seenKanji = [];
        $firstFoundPerTag = [];

        foreach ($allNotes as $note) {
            $expression = $note->fields->{$frontField}->value ?? '';
            if (empty($expression)) {
                $expression = $note->fields->Word->value ?? '';
            }
            preg_match_all('/\p{Han}/u', $expression, $matches);
            $kanjiChars = $matches[0] ?? [];

            $noteId = (int)$note->noteId;
            $isStarter = isset($starterNoteIdSet[$noteId]);

            foreach ($kanjiChars as $kanji) {
                if (isset($seenKanji[$kanji])) {
                    continue;
                }
                $seenKanji[$kanji] = true;

                if ($isStarter) {
                    continue;
                }

                $tags = $this->getNormalizedTags($note->tags);
                foreach ($tags as $tag) {
                    if ($tag === 'NSFW') {
                        continue;
                    }
                    $firstFoundPerTag[$tag] = ($firstFoundPerTag[$tag] ?? 0) + 1;
                }
            }
        }

        return $firstFoundPerTag;
    }

    private function getNormalizedTags(array $tags): array
    {
        $normalized = array_map(function ($tag) {
            if (str_starts_with($tag, "Book::")) {
                $parts = explode("::", $tag);
                if (count($parts) > 2) {
                    return $parts[0] . "::" . $parts[1];
                }
            }
            return $tag;
        }, $tags);

        $normalized = array_filter($normalized, function ($tag) {
            return !str_starts_with($tag, "manualSuspend");
        });

        return array_values(array_unique($normalized));
    }
}

