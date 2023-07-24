<?php 
    require_once '../../../vendor/autoload.php';
    require_once './config.php';

    $token = $config['dev']['token'];
    $username = $config['dev']['username'];
    
    if (!isset($bot)) {
        $bot = new Longman\TelegramBot\Telegram($token, $username);
    }

    // echo $bot->getWebhookInfo()
?>