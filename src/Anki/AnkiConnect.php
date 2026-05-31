<?php

declare(strict_types=1);

namespace App\Anki;

use App\Core\Logger;
use App\Core\Urgency;
use RuntimeException;
use JsonException;

class AnkiConnect
{
    private const string ANKI_PORT = "8765";
    private const string ANKI_URL = "http://localhost";

    /**
     * The core communication method with AnkiConnect.
     *
     * @param string $action
     * @param array $params
     * @return mixed
     * @throws RuntimeException
     */
    public function send(string $action, array $params = [])
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_PORT => self::ANKI_PORT,
            CURLOPT_URL => self::ANKI_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode([
                'action' => $action,
                'params' => empty($params) ? (object)[] : $params,
                'version' => 6
            ], JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "accept: application/json"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        if ($err) {
            $msg = "cURL reported error: $err";
            Logger::log($msg, Urgency::critical);
            throw new RuntimeException($msg);
        }

        try {
            return json_decode($response, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $msg = "JSON decode error: " . $e->getMessage();
            Logger::log($msg, Urgency::critical);
            throw new RuntimeException($msg);
        }
    }
}
