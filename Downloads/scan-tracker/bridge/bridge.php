<?php

require __DIR__ . '/vendor/autoload.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

$mqttHost  = getenv('MQTT_HOST');
$mqttPort  = (int) getenv('MQTT_PORT');
$mqttUser  = getenv('MQTT_USER');
$mqttPass  = getenv('MQTT_PASS');
$mqttTopic = getenv('MQTT_TOPIC') ?: 'esp32/sensor1/jarak';
$clientId  = 'bridge-' . substr(md5(uniqid('', true)), 0, 8);

$dbHost = getenv('DB_HOST');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');

function connectDb(string $host, string $name, string $user, string $pass): PDO
{
    while (true) {
        try {
            $pdo = new PDO(
                "mysql:host={$host};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            echo "[DB] Terhubung ke MySQL.\n";
            return $pdo;
        } catch (PDOException $e) {
            echo "[DB] Belum siap ({$e->getMessage()}), retry 3 detik...\n";
            sleep(3);
        }
    }
}

$pdo = connectDb($dbHost, $dbName, $dbUser, $dbPass);

$connectionSettings = (new ConnectionSettings())
    ->setUsername($mqttUser)
    ->setPassword($mqttPass)
    ->setUseTls(true)
    ->setTlsVerifyPeer(true)
    ->setKeepAliveInterval(60)
    ->setConnectTimeout(10);

while (true) {
    try {
        $mqtt = new MqttClient($mqttHost, $mqttPort, $clientId);
        $mqtt->connect($connectionSettings, true);
        echo "[MQTT] Terhubung ke {$mqttHost}:{$mqttPort}, subscribe topic '{$mqttTopic}'\n";

        $mqtt->subscribe($mqttTopic, function (string $topic, string $message) use ($pdo) {
            echo "[MQTT] [{$topic}] {$message}\n";

            $data = json_decode($message, true);
            if (!is_array($data)) {
                echo "[MQTT] Payload bukan JSON valid, diabaikan.\n";
                return;
            }

            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO scans (status, jarak_cm, ip, ticks_ms, raw_json)
                     VALUES (:status, :jarak_cm, :ip, :ticks_ms, :raw)"
                );
                $stmt->execute([
                    ':status'   => $data['status'] ?? 'unknown',
                    ':jarak_cm' => $data['jarak_cm'] ?? null,
                    ':ip'       => $data['ip'] ?? null,
                    ':ticks_ms' => $data['ticks_ms'] ?? null,
                    ':raw'      => json_encode($data),
                ]);
            } catch (PDOException $e) {
                echo "[DB] Gagal insert: {$e->getMessage()}\n";
            }
        }, 0);

        $mqtt->loop(true);
    } catch (\Throwable $e) {
        echo "[MQTT] Error: {$e->getMessage()} - reconnect dalam 5 detik...\n";
        sleep(5);
    }
}
