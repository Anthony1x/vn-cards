<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once "./main.php";

$router = new \Bramus\Router\Router();

$ankiclient = AnkiClient::get_instance();


// Define a POST route
$router->post('/process', function () use ($ankiclient) {
    header('Content-Type: application/json');

    $res = $ankiclient->get_cards_with_frequency(true);

    echo json_encode(['status' => 'success', 'result' => $res]);
});

$router->run();
