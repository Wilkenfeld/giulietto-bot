<?php
    require '../../vendor/autoload.php';
    require '../config/config.php';

    $hook_url     = "https://$_SERVER[HTTP_HOST]/giuliettobot/src/v2/webhook/hook.php";

    if (!isset($log)) {
        $log = new Log(LOG_FILE_PATH."/bot.log");
    }

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