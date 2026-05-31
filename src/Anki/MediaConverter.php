<?php

declare(strict_types=1);

namespace App\Anki;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Urgency;
use Exception;
use RuntimeException;

class MediaConverter
{
    private const string IMAGE_TARGET_EXT = 'avif';
    private const string AUDIO_TARGET_EXT = 'opus';

    private const array IMAGE_SKIP_EXTENSIONS = ['svg'];
    private const array AUDIO_SKIP_EXTENSIONS = [];

    private const string MAGICK_BIN = 'magick';
    private const string FFMPEG_BIN = 'ffmpeg';

    private const int IMAGE_QUALITY = 80;
    private const int IMAGE_MAX_HEIGHT = 600;
    private const string AUDIO_BITRATE = '96k';

    private const string BACKUP_DIR = '.vn-cards-backup';
    private const string SKIP_LIST_FILE = '.vn-cards-skip-list.json';

    private const int HASH_SUFFIX_LENGTH = 6;

    private const int NOTES_INFO_CHUNK = 500;
    private const int POLL_INTERVAL_USEC = 50_000;

    private AnkiConnect $anki;
    private string $mediaDir;
    private int $concurrency;

    public function __construct(AnkiConnect $anki)
    {
        $this->anki = $anki;

        $prefix = trim((string)Config::get('PREFIX'));
        if ($prefix === '') {
            throw new RuntimeException('PREFIX (Anki media path) is not configured in .env');
        }

        $this->mediaDir = rtrim($prefix, '/');
        if (!is_dir($this->mediaDir)) {
            throw new RuntimeException("Anki media directory not found: {$this->mediaDir}");
        }

        $this->concurrency = $this->detectConcurrency();
    }

    public function convertAll(bool $dryRun = false): void
    {
        echo $dryRun
            ? "\033[1;33m=== DRY RUN — no files or notes will be modified ===\033[0m\n\n"
            : "\033[1;32m=== Live conversion ===\033[0m\n\n";

        if (!$dryRun) {
            $this->preflightCheck();
        }

        echo "Fetching every note across every deck...\n";
        $notes = $this->fetchAllNotes();
        echo "  -> " . count($notes) . " notes found.\n\n";

        echo "Scanning fields for non-AVIF images and non-Opus audio...\n";
        $skipList = $this->loadSkipList();
        [$conversions, $references, $skipped] = $this->planConversions($notes, $skipList);
        echo "  -> " . count($conversions['image']) . " image(s) to convert.\n";
        if (!empty($skipped['image'])) {
            echo "  -> " . count($skipped['image']) . " image(s) skipped (extensions: "
                . implode(', ', self::IMAGE_SKIP_EXTENSIONS) . ").\n";
        }
        echo "  -> " . count($conversions['audio']) . " audio file(s) to convert.\n";
        if (!empty($skipped['audio'])) {
            echo "  -> " . count($skipped['audio']) . " audio file(s) skipped (extensions: "
                . implode(', ', self::AUDIO_SKIP_EXTENSIONS) . ").\n";
        }
        $preSkipped = $skipped['skip_list'] ?? [];
        if (!empty($preSkipped)) {
            echo "  -> " . count($preSkipped) . " file(s) skipped (known to grow on conversion).\n";
        }
        echo "  -> " . count($references) . " note(s) reference these files.\n\n";

        if (empty($conversions['image']) && empty($conversions['audio'])) {
            echo "Nothing to do.\n";
            return;
        }

        if ($dryRun) {
            $this->printDryRun($conversions, $references);
            return;
        }

        echo "Measuring media folder size before conversion...\n";
        $sizeBefore = $this->totalMediaSize();
        echo "  -> " . $this->formatBytes($sizeBefore) . "\n\n";

        $this->backupOriginals($conversions);

        $converted = $this->performConversions($conversions);
        $beforeRevert = $converted;
        $converted = $this->revertGrownFiles($converted);
        $revertedSources = array_diff_key($beforeRevert, $converted);
        $this->addToSkipList($revertedSources);
        $this->applyNoteUpdates($references, $converted);
        $this->deleteOriginals(array_keys($converted));

        $this->cleanupBackup();

        echo "\nMeasuring media folder size after conversion...\n";
        $sizeAfter = $this->totalMediaSize();
        $this->printSizeReport($sizeBefore, $sizeAfter);

        echo "\n\033[1;32mDone.\033[0m\n";
    }

    private function preflightCheck(): void
    {
        foreach ([self::MAGICK_BIN, self::FFMPEG_BIN] as $bin) {
            $code = 1;
            $out = [];
            exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null', $out, $code);
            if ($code !== 0) {
                throw new RuntimeException("Required binary '$bin' not found in PATH");
            }
        }
    }

    private function fetchAllNotes(): array
    {
        $ids = $this->anki->send('findNotes', ['query' => 'deck:*'])->result ?? [];
        $notes = [];

        foreach (array_chunk($ids, self::NOTES_INFO_CHUNK) as $chunk) {
            $info = $this->anki->send('notesInfo', ['notes' => $chunk])->result ?? [];
            foreach ($info as $note) {
                if (!empty((array)$note)) {
                    $notes[] = $note;
                }
            }
        }

        return $notes;
    }

    /**
     * @return array{0: array{image: array<string,string>, audio: array<string,string>},
     *               1: array<int, array{modelName: string, fields: array<string, array{original: string, files: list<array{type: string, source: string, target: string}>}>}>,
     *               2: array{image: array<string,true>, audio: array<string,true>}}
     */
    private function planConversions(array $notes, array $skipList = []): array
    {
        $conversions = ['image' => [], 'audio' => []];
        $claimedTargets = ['image' => [], 'audio' => []];
        $skipped = ['image' => [], 'audio' => [], 'skip_list' => []];
        $references = [];

        foreach ($notes as $note) {
            $noteId = (int)$note->noteId;

            foreach ($note->fields as $fieldName => $fieldData) {
                $value = (string)($fieldData->value ?? '');
                $fieldFiles = [];

                foreach ($this->extractImages($value) as $filename) {
                    if ($this->hasExtension($filename, self::IMAGE_TARGET_EXT)) {
                        continue;
                    }
                    if ($this->shouldSkip($filename, self::IMAGE_SKIP_EXTENSIONS)) {
                        $skipped['image'][$filename] = true;
                        continue;
                    }
                    if (!isset($conversions['image'][$filename])) {
                        if ($this->isInSkipList($filename, $skipList)) {
                            $skipped['skip_list'][$filename] = true;
                            continue;
                        }
                        $target = $this->pickTarget($filename, self::IMAGE_TARGET_EXT, $claimedTargets['image']);
                        $conversions['image'][$filename] = $target;
                        $claimedTargets['image'][$target] = $filename;
                    }
                    $fieldFiles[] = ['type' => 'image', 'source' => $filename, 'target' => $conversions['image'][$filename]];
                }

                foreach ($this->extractAudio($value) as $filename) {
                    if ($this->hasExtension($filename, self::AUDIO_TARGET_EXT)) {
                        continue;
                    }
                    if ($this->shouldSkip($filename, self::AUDIO_SKIP_EXTENSIONS)) {
                        $skipped['audio'][$filename] = true;
                        continue;
                    }
                    if (!isset($conversions['audio'][$filename])) {
                        if ($this->isInSkipList($filename, $skipList)) {
                            $skipped['skip_list'][$filename] = true;
                            continue;
                        }
                        $target = $this->pickTarget($filename, self::AUDIO_TARGET_EXT, $claimedTargets['audio']);
                        $conversions['audio'][$filename] = $target;
                        $claimedTargets['audio'][$target] = $filename;
                    }
                    $fieldFiles[] = ['type' => 'audio', 'source' => $filename, 'target' => $conversions['audio'][$filename]];
                }

                if (empty($fieldFiles)) {
                    continue;
                }

                if (!isset($references[$noteId])) {
                    $references[$noteId] = [
                        'modelName' => (string)($note->modelName ?? '?'),
                        'fields' => [],
                    ];
                }
                $references[$noteId]['fields'][(string)$fieldName] = [
                    'original' => $value,
                    'files' => $fieldFiles,
                ];
            }
        }

        return [$conversions, $references, $skipped];
    }

    private function shouldSkip(string $filename, array $skipExtensions): bool
    {
        if (empty($skipExtensions)) {
            return false;
        }
        return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), $skipExtensions, true);
    }

    private function pickTarget(string $source, string $targetExt, array $claimedTargets): string
    {
        $stem = pathinfo($source, PATHINFO_FILENAME);
        $naive = "$stem.$targetExt";

        $isClaimed = isset($claimedTargets[$naive]);
        $existsOnDisk = is_file($this->mediaDir . '/' . $naive);

        if (!$isClaimed && !$existsOnDisk) {
            return $naive;
        }

        $hash = substr(md5($source), 0, self::HASH_SUFFIX_LENGTH);
        return "$stem-$hash.$targetExt";
    }

    private function extractImages(string $html): array
    {
        if (!preg_match_all('/<img\s[^>]*src=(["\'])([^"\']+)\1[^>]*>/i', $html, $m)) {
            return [];
        }
        return array_values(array_unique($m[2]));
    }

    private function extractAudio(string $html): array
    {
        if (!preg_match_all('/\[sound:([^\]]+)\]/', $html, $m)) {
            return [];
        }
        return array_values(array_unique($m[1]));
    }

    private function hasExtension(string $filename, string $ext): bool
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === $ext;
    }

    private function printDryRun(array $conversions, array $references): void
    {
        echo "\033[1;36m--- File conversions (theoretical) ---\033[0m\n";
        foreach ($conversions['image'] as $source => $target) {
            echo "  \033[36mIMAGE\033[0m  $source  →  $target"
                . $this->dryRunFlags($source, $target, self::IMAGE_TARGET_EXT) . "\n";
        }
        foreach ($conversions['audio'] as $source => $target) {
            echo "  \033[35mAUDIO\033[0m  $source  →  $target"
                . $this->dryRunFlags($source, $target, self::AUDIO_TARGET_EXT) . "\n";
        }

        echo "\n\033[1;36m--- Affected notes ---\033[0m\n";
        foreach ($references as $noteId => $data) {
            echo "Note \033[1m#$noteId\033[0m  ({$data['modelName']})\n";
            foreach ($data['fields'] as $fieldName => $info) {
                echo "  Field \033[1m$fieldName\033[0m:\n";
                foreach ($info['files'] as $f) {
                    $label = $f['type'] === 'image' ? "\033[36mIMAGE\033[0m" : "\033[35mAUDIO\033[0m";
                    echo "    [$label]  {$f['source']}  →  {$f['target']}\n";
                }
            }
        }
    }

    private function dryRunFlags(string $source, string $target, string $targetExt): string
    {
        $flags = [];
        if (!is_file($this->mediaDir . '/' . $source)) {
            $flags[] = "\033[1;31m[source missing]\033[0m";
        }
        $naive = pathinfo($source, PATHINFO_FILENAME) . '.' . $targetExt;
        if ($target !== $naive) {
            $flags[] = "\033[1;33m[hash-suffixed]\033[0m";
        }
        return $flags ? '  ' . implode(' ', $flags) : '';
    }

    /**
     * @return array<string,string>  source filename => target filename, only for successful conversions
     */
    private function performConversions(array $conversions): array
    {
        $imageCmds = $this->buildImageCommands($conversions['image']);
        $audioCmds = $this->buildAudioCommands($conversions['audio']);
        $allCmds = $imageCmds + $audioCmds;

        if (empty($allCmds)) {
            return [];
        }

        echo "Running " . count($allCmds) . " conversion(s) with {$this->concurrency}-way parallelism...\n";
        $results = $this->runParallel($allCmds);

        $converted = [];
        foreach ($results as $source => $success) {
            if (!$success) {
                continue;
            }
            $target = $conversions['image'][$source] ?? $conversions['audio'][$source] ?? null;
            if ($target === null) {
                continue;
            }
            $targetPath = $this->mediaDir . '/' . $target;
            if (!is_file($targetPath) || filesize($targetPath) === 0) {
                Logger::log("Conversion claimed success but target missing/empty: $target", Urgency::normal);
                continue;
            }
            $converted[$source] = $target;
        }

        echo "  -> " . count($converted) . "/" . count($allCmds) . " conversion(s) succeeded.\n";
        return $converted;
    }

    private function buildImageCommands(array $plan): array
    {
        $cmds = [];
        foreach ($plan as $source => $target) {
            $srcPath = $this->mediaDir . '/' . $source;
            if (!is_file($srcPath)) {
                Logger::log("Image not found in media dir: $source", Urgency::normal);
                continue;
            }
            $tgtPath = $this->mediaDir . '/' . $target;
            $resize = sprintf('x%d>', self::IMAGE_MAX_HEIGHT);
            $cmds[$source] = sprintf(
                '%s %s -quality %d -resize %s %s',
                self::MAGICK_BIN,
                escapeshellarg($srcPath),
                self::IMAGE_QUALITY,
                escapeshellarg($resize),
                escapeshellarg($tgtPath)
            );
        }
        return $cmds;
    }

    private function buildAudioCommands(array $plan): array
    {
        $cmds = [];
        foreach ($plan as $source => $target) {
            $srcPath = $this->mediaDir . '/' . $source;
            if (!is_file($srcPath)) {
                Logger::log("Audio not found in media dir: $source", Urgency::normal);
                continue;
            }
            $tgtPath = $this->mediaDir . '/' . $target;
            $cmds[$source] = sprintf(
                '%s -nostdin -y -loglevel error -i %s -c:a libopus -b:a %s -vn %s',
                self::FFMPEG_BIN,
                escapeshellarg($srcPath),
                escapeshellarg(self::AUDIO_BITRATE),
                escapeshellarg($tgtPath)
            );
        }
        return $cmds;
    }

    /**
     * @param  array<string,string> $commands  key (source filename) => shell command
     * @return array<string,bool>              key => exit-code-zero success
     */
    private function runParallel(array $commands): array
    {
        $results = [];
        $running = [];
        $queue = $commands;
        $total = count($commands);
        $done = 0;
        $nextReport = 10;

        while ($queue || $running) {
            while (count($running) < $this->concurrency && $queue) {
                $key = (string)array_key_first($queue);
                $cmd = $queue[$key];
                unset($queue[$key]);

                $descriptors = [
                    0 => ['file', '/dev/null', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ];
                $proc = @proc_open($cmd, $descriptors, $pipes);

                if (!is_resource($proc)) {
                    $results[$key] = false;
                    $done++;
                    continue;
                }

                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);
                $running[$key] = ['proc' => $proc, 'pipes' => $pipes, 'stderr' => ''];
            }

            foreach ($running as $key => $r) {
                $running[$key]['stderr'] .= (string)stream_get_contents($r['pipes'][2]);
                stream_get_contents($r['pipes'][1]);

                $status = proc_get_status($r['proc']);
                if ($status['running']) {
                    continue;
                }

                $running[$key]['stderr'] .= (string)stream_get_contents($r['pipes'][2]);
                fclose($r['pipes'][1]);
                fclose($r['pipes'][2]);
                proc_close($r['proc']);

                $success = $status['exitcode'] === 0;
                $results[$key] = $success;
                if (!$success) {
                    $err = trim($running[$key]['stderr']);
                    Logger::log("Conversion failed for '$key': $err", Urgency::normal);
                }

                unset($running[$key]);
                $done++;

                if ($done >= $nextReport || $done === $total) {
                    echo "  [$done/$total]\n";
                    $nextReport = $done + 10;
                }
            }

            if ($running) {
                usleep(self::POLL_INTERVAL_USEC);
            }
        }

        return $results;
    }

    private function applyNoteUpdates(array $references, array $converted): void
    {
        echo "\nUpdating note fields...\n";
        $updated = 0;

        foreach ($references as $noteId => $data) {
            $updates = [];
            foreach ($data['fields'] as $fieldName => $info) {
                $value = $info['original'];
                $changed = false;
                foreach ($info['files'] as $f) {
                    if (!isset($converted[$f['source']])) {
                        continue;
                    }
                    $newName = $converted[$f['source']];
                    $value = $f['type'] === 'image'
                        ? $this->replaceImageRef($value, $f['source'], $newName)
                        : $this->replaceAudioRef($value, $f['source'], $newName);
                    $changed = true;
                }
                if ($changed && $value !== $info['original']) {
                    $updates[$fieldName] = $value;
                }
            }

            if (empty($updates)) {
                continue;
            }

            try {
                $res = $this->anki->send('updateNoteFields', [
                    'note' => ['id' => $noteId, 'fields' => $updates],
                ]);
                if (!empty($res->error)) {
                    Logger::log("updateNoteFields error for note $noteId: {$res->error}", Urgency::normal);
                    continue;
                }
                $updated++;
            } catch (Exception $e) {
                Logger::log("Failed to update note $noteId: " . $e->getMessage(), Urgency::normal);
            }
        }

        echo "  -> Updated $updated note(s).\n";
    }

    private function replaceImageRef(string $html, string $oldName, string $newName): string
    {
        return (string)preg_replace_callback(
            '/(<img\s[^>]*src=)(["\'])([^"\']+)\2([^>]*>)/i',
            fn($m) => $m[3] === $oldName ? $m[1] . $m[2] . $newName . $m[2] . $m[4] : $m[0],
            $html
        );
    }

    private function replaceAudioRef(string $html, string $oldName, string $newName): string
    {
        return (string)preg_replace_callback(
            '/\[sound:([^\]]+)\]/',
            fn($m) => $m[1] === $oldName ? "[sound:$newName]" : $m[0],
            $html
        );
    }

    private function deleteOriginals(array $sources): void
    {
        $deleted = 0;
        foreach ($sources as $source) {
            $path = $this->mediaDir . '/' . $source;
            if (is_file($path) && @unlink($path)) {
                $deleted++;
            }
        }
        echo "  -> Deleted $deleted original file(s).\n";
    }

    private function backupOriginals(array $conversions): void
    {
        $backupDir = $this->mediaDir . '/' . self::BACKUP_DIR;
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        $sources = array_keys($conversions['image']) + array_keys($conversions['audio']);
        $copied = 0;
        foreach ($sources as $source) {
            $srcPath = $this->mediaDir . '/' . $source;
            if (!is_file($srcPath)) {
                continue;
            }
            copy($srcPath, $backupDir . '/' . $source);
            $copied++;
        }
        echo "  -> Backed up $copied original file(s).\n";
    }

    private function revertGrownFiles(array $converted): array
    {
        $filtered = [];
        $reverted = 0;
        foreach ($converted as $source => $target) {
            $sourcePath = $this->mediaDir . '/' . $source;
            $targetPath = $this->mediaDir . '/' . $target;

            $sourceSize = is_file($sourcePath) ? filesize($sourcePath) : 0;
            $targetSize = is_file($targetPath) ? filesize($targetPath) : 0;

            if ($targetSize > $sourceSize) {
                @unlink($targetPath);
                Logger::log("Reverted $source ($sourceSize bytes) -> $target ($targetSize bytes): converted file was larger", Urgency::normal);
                $reverted++;
            } else {
                $filtered[$source] = $target;
            }
        }
        echo "  -> Reverted $reverted conversion(s) where the result was larger than the original.\n";
        return $filtered;
    }

    private function cleanupBackup(): void
    {
        $backupDir = $this->mediaDir . '/' . self::BACKUP_DIR;
        if (!is_dir($backupDir)) {
            return;
        }
        foreach (new \DirectoryIterator($backupDir) as $file) {
            if ($file->isDot()) {
                continue;
            }
            @unlink($file->getPathname());
        }
        @rmdir($backupDir);
    }

    private function loadSkipList(): array
    {
        $path = $this->mediaDir . '/' . self::SKIP_LIST_FILE;
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private function saveSkipList(array $skipList): void
    {
        $path = $this->mediaDir . '/' . self::SKIP_LIST_FILE;
        file_put_contents($path, json_encode($skipList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function isInSkipList(string $filename, array $skipList): bool
    {
        if (!array_key_exists($filename, $skipList)) {
            return false;
        }
        $sourcePath = $this->mediaDir . '/' . $filename;
        $currentSize = is_file($sourcePath) ? filesize($sourcePath) : 0;
        return $currentSize === $skipList[$filename];
    }

    private function addToSkipList(array $sources): void
    {
        if (empty($sources)) {
            return;
        }
        $skipList = $this->loadSkipList();
        foreach ($sources as $source => $target) {
            $sourcePath = $this->mediaDir . '/' . $source;
            if (is_file($sourcePath)) {
                $skipList[$source] = filesize($sourcePath);
            }
        }
        $this->saveSkipList($skipList);
        echo "  -> Added " . count($sources) . " file(s) to skip list.\n";
    }

    private function totalMediaSize(): int
    {
        $out = (string)@shell_exec('du -sb ' . escapeshellarg($this->mediaDir) . ' 2>/dev/null');
        if (preg_match('/^(\d+)/', trim($out), $m)) {
            return (int)$m[1];
        }
        return 0;
    }

    private function printSizeReport(int $before, int $after): void
    {
        $diff = $after - $before;
        $pct = $before > 0 ? ($diff / $before) * 100 : 0.0;
        $sign = $diff > 0 ? '+' : '';
        $color = $diff < 0 ? "\033[1;32m" : ($diff > 0 ? "\033[1;31m" : "\033[1m");

        echo "\n\033[1;36m--- Media folder size ---\033[0m\n";
        echo "  Before:  " . $this->formatBytes($before) . "\n";
        echo "  After:   " . $this->formatBytes($after) . "\n";
        echo "  Delta:   {$color}{$sign}" . $this->formatBytes($diff) . sprintf(' (%s%.2f%%)', $sign, $pct) . "\033[0m\n";
    }

    private function formatBytes(int $bytes): string
    {
        $abs = abs($bytes);
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $i = 0;
        $size = (float)$abs;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        $signed = $bytes < 0 ? -$size : $size;
        return sprintf('%.2f %s', $signed, $units[$i]);
    }

    private function detectConcurrency(): int
    {
        $n = (int)trim((string)@shell_exec('nproc 2>/dev/null'));
        return max(1, $n ?: 4);
    }
}
