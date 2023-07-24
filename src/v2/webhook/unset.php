<?php

/**
 * This file is part of the PHP Telegram Bot example-bot package.
 * https://github.com/php-telegram-bot/example-bot/
 *
 * (c) PHP Telegram Bot Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * This file is used to unset / delete the webhook.
 */


 require_once '../../../vendor/autoload.php';
 require_once '../config.php';

try {
    // Create Telegram API object
    $telegram = new Longman\TelegramBot\Telegram($config['dev']['api_key'], $config['dev']['bot_username']);

    // Unset / delete the webhook
    $result = $telegram->deleteWebhook();

    echo $result->getDescription();
} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    echo $e->getMessage();
}