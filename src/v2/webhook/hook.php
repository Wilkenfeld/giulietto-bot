<?php
    require '../../vendor/autoload.php';
    require '../config/config.php';

    if ($isset($log)) {
        $log = new Log();
    }

    $token = $config['dev']['token'];
    $username = $config['dev']['username'];

    try {
        // Create Telegram API object
        $telegram = new Longman\TelegramBot\Telegram($token, $username);
    
        // Handle telegram webhook request
        $telegram->handle();
    } catch (Longman\TelegramBot\Exception\TelegramException $e) {
        // Silence is golden!
        // log telegram errors
        echo $e->getMessage();
    }

?>