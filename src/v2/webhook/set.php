<?php
    require_once '../../../vendor/autoload.php';
    require_once '../config.php';

    // $hook_url     = "https://$_SERVER[HTTP_HOST]/giuliettobot/src/v2/webhook/hook.php";
    $hook_url = "https://28a1-37-160-128-75.ngrok-free.app/v2/webhook/hook.php";
    $token = $config['dev']['token'];
    $username = $config['dev']['username'];

    try {
        // Create Telegram API object
        $telegram = new Longman\TelegramBot\Telegram($token, $username);

        // Set webhook
        $result = $telegram->setWebhook($hook_url);
        if ($result->isOk()) {
            echo $result->getDescription();
        }
    } catch (Longman\TelegramBot\Exception\TelegramException $e) {
        // log telegram errors
        echo $e->getMessage();
    }
?>