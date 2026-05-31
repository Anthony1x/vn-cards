<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Anki\AnkiConnect;
use App\Anki\CardManager;
use App\Anki\RetentionAnalyzer;
use Bramus\Router\Router;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

$router = new Router();

$anki = new AnkiConnect();
$cardManager = new CardManager($anki);
$retentionAnalyzer = new RetentionAnalyzer($cardManager, $anki);

// Define routes
$router->get('/tags/all', function () use ($anki) {
    header('Content-Type: application/json');
    try {
        $res = $anki->send('getTags');
        echo json_encode(['status' => 'success', 'result' => $res->result]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
});

$router->post('/deduplication/run', function () use ($cardManager) {
    header('Content-Type: application/json');

    try {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $tags = $data['tags'] ?? [];
        $days = $data['days'] ?? 7;

        ob_start();
        $cardManager->replaceWithNewerCard((int)$days, (array)$tags);
        $output = ob_get_clean();

        echo json_encode(['status' => 'success', 'output' => $output]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
});

$router->get('/tags', function () use ($retentionAnalyzer) {
    header('Content-Type: application/json');

    try {
        $res = $retentionAnalyzer->getCardsByTag(true);
        echo json_encode(['status' => 'success', 'result' => $res]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
});

$router->get('/deduplication/preview', function () use ($cardManager) {
    header('Content-Type: application/json');

    try {
        $count = $cardManager->getDeduplicationPreview(7); // Check last 7 days
        echo json_encode(['status' => 'success', 'result' => ['count' => $count]]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
});

$router->post('/process', function () use ($retentionAnalyzer) {
    header('Content-Type: application/json');

    try {
        $res = $retentionAnalyzer->getCardsByTag(true);
        echo json_encode(['status' => 'success', 'result' => $res]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
});

// Add more routes as needed
$router->get('/sources/stats', function () use ($retentionAnalyzer) {
    header('Content-Type: application/json');
    try {
        $res = $retentionAnalyzer->getSourceStats();
        echo json_encode(['status' => 'success', 'result' => $res]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
});

$router->get('/difficulty/distribution', function () use ($retentionAnalyzer) {
    header('Content-Type: application/json');
    try {
        $res = $retentionAnalyzer->getDifficultyDistribution();
        echo json_encode(['status' => 'success', 'result' => $res]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
});

$router->get('/health', function() {
    echo json_encode(['status' => 'ok']);
});

$router->run();
