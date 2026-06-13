<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Anki\AnkiConnect;
use App\Anki\CardManager;
use App\Anki\MediaConverter;
use App\Anki\RetentionAnalyzer;
use App\Cli\CliFormatter;
use App\Core\Config;

$anki = new AnkiConnect();
$cardManager = new CardManager($anki);
$retentionAnalyzer = new RetentionAnalyzer($cardManager, $anki);

// Choose what to run based on arguments or just hardcode like before but cleaner
// For now, I'll keep the logic that was in get_cards.php but using the new system.

$args = array_slice($argv, 1);

if (empty($args)) {
    echo "Usage: php get_cards.php [action1] [param1] [action2] ...\n";
    echo "Actions: retention [n], frequency, replace [days], compounds [chars], reassign, contract, stats, convert-media [--dry-run], due [time]\n";
    exit(1);
}

while (!empty($args)) {
    $action = array_shift($args);

    switch ($action) {
        case 'due':
            $time = 'tomorrow';
            if (isset($args[0]) && strtotime($args[0]) !== false) {
                $time = array_shift($args);
            }
            try {
                $stats = $cardManager->getDueStats($time);
                CliFormatter::displayDueStats($time, $stats);
            } catch (RuntimeException $e) {
                echo "Error: " . $e->getMessage() . "\n";
            }
            break;

        case 'retention':
            $count = is_numeric($args[0] ?? null) ? (int)array_shift($args) : 10;
            $cards = $retentionAnalyzer->getCardsByRetention($count);
            CliFormatter::displayRetentionTable($cards);
            break;

        case 'frequency':
            $cards = $retentionAnalyzer->getCardsByTag(true);
            $firstFound = $retentionAnalyzer->getKanjiFirstAppearances();
            foreach ($cards as $tag => &$data) {
                $data['First'] = $firstFound[$tag] ?? 0;
            }
            unset($data);
            $notes = $cardManager->getAllNotes(Config::get('DECK_NAME'));
            $totalFreqSum = 0;
            $totalFreqCount = 0;
            foreach ($notes as $note) {
                $fv = (int)($note->fields->FreqSort->value ?? 9999999);
                if ($fv !== 9999999) {
                    $totalFreqSum += $fv;
                    $totalFreqCount++;
                }
            }
            $overallFreq = $totalFreqCount > 0 ? (int)round($totalFreqSum / $totalFreqCount) : 0;
            CliFormatter::displayFrequencyTable($cards, count($notes), $overallFreq);
            break;

        case 'replace':
            $days = is_numeric($args[0] ?? null) ? (int)array_shift($args) : 1;
            $cardManager->replaceWithNewerCard($days);
            break;

        case 'compounds':
            $chars = is_numeric($args[0] ?? null) ? (int)array_shift($args) : 4;
            $compounds = $cardManager->getCompounds($chars);
            CliFormatter::displayCompounds($compounds, $chars);
            break;

        case 'reassign':
            $cardManager->reassignPlaceholderFrequencies();
            break;

        case 'contract':
            $cardManager->contractDots();
            break;

        case 'stats':
            $stats = $cardManager->getMediaStats();
            CliFormatter::displayMediaStats($stats);
            break;

        case 'convert-media':
            $dryRun = false;
            if (!empty($args) && in_array($args[0], ['--dry-run', '--dryrun', '-n', 'dryrun'], true)) {
                $dryRun = true;
                array_shift($args);
            }
            $converter = new MediaConverter($anki);
            $converter->convertAll($dryRun);
            break;

        default:
            echo "Unknown action: $action\n";
            break;
    }
}
