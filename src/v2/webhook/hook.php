<?php
    require_once '../../../vendor/autoload.php';
    require_once '../config.php';

    $token = $config['dev']['token'];
    $username = $config['dev']['username'];

    try {
        // Create Telegram API object
        $telegram = new Longman\TelegramBot\Telegram($token, $username);

        // Connect to database
        $mysql_credentials = array(
            "host" => $config['dev']["db_host"],
            "user" => $config['dev']["db_username"],
            "password" => $config['dev']["db_password"],
            "database" => $config['dev']['db_name']
        );

        $telegram->enableMySql($mysql_credentials, $config['dev']['db_table_prefix']. '_');

        // Set admins
        $telegram->enableAdmins($config['dev']['admins']);

        // Set commands
        $telegram->addCommandsPaths($config['dev']['commands']['paths']);

        // Handle telegram webhook request
        $telegram->handle();
    } catch (Longman\TelegramBot\Exception\TelegramException $e) {
        // Silence is golden!
        // log telegram errors
        echo $e->getMessage();
    }

?>