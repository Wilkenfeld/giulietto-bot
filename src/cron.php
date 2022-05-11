<?php
    require_once 'giulietto_db.php';
    require_once 'telegram_bot.php';
    include_once 'config/config.php';

    $bot = new TelegramBot(TOKEN);
    $log = new Log(LOG_FILE_PATH."cron.log");

    // Creazione della connessione al Database
    try{
        $conn = new mysqli(DB_HOST,DB_USERNAME,DB_PASSWORD, DB_NAME);
        $db = new GiuliettoDB($conn);
    }
    catch(Exception $e){
        $log->append($e->getCode()." ".$e->getMessage()."\n".$e->getTraceAsString(),"error");
        exit();
    }

    $db->setLogFile(LOG_FILE_PATH.'cron.log');

    //notificaTurni($bot,$db,$log);
    reminderAggiornamentoAssenza($bot,$db);
    rientroInquilino($bot,$db);
    arrivoOspiti($bot, $db);
   
    $conn->close();

/**
 * @param $bot TelegramBot
 * @param $db GiuliettoDB
 * @param $log Log
 */
function notificaTurni(TelegramBot $bot, GiuliettoDB $db, Log $log){
        
    $turnType = $db->getTurnTypeList();

    while($turn = $turnType->fetch_assoc()){

        $log->append(json_encode($turn,JSON_PRETTY_PRINT));

        $passedDays = date_diff(new DateTime($turn['LastExecution']),new DateTime(date("Y-m-d")))->days;

        $log->append("Giorni passati $passedDays");

        if((is_null($turn['LastExecution']) and $turn["FirstExecution"] == date('Y-m-d')) or $turn['Frequency'] == $passedDays){

            $group = $db->getGroupWillDoTheNextTurn($turn['Name'])['Squad'];
            $log->append("Gruppo");
            $log->append(json_encode($group,JSON_PRETTY_PRINT));
            $users = $db->getUserInGroup($group);
            $absents = $db->getAbsentsList(date('Y-m-d'));

            $absentsChatID = [];
            while($row = $absents->fetch_assoc()){
                $absentsChatID[] = $row["ChatID"];
            }

            foreach($users as $user){
                if(!in_array($user['ChatID'],$absentsChatID)){
                    $log->append($user['FullName']);
                    $bot->setChatID($user['ChatID']);
                    $bot->sendMessage("Ricordati di fare il turno ".$turn['Name']);
                }
                else{
                    $log->append($user['FullName'].' assente');
                }
            }

            if(!is_null($turn['LastExecution']) and $turn["FirstExecution"] != date('Y-m-d')){
                $db->incStep($turn['ID']);
            }
        }
    }
}

/**
 * @param $bot TelegramBot
 * @param $db GiuliettoDB
 */
function rientroInquilino(TelegramBot $bot, GiuliettoDB $db){
    $absence = $db->getIncomingRoomer(date('Y-m-d'));

    if($absence->num_rows > 0){
        $msg = "- - - - - - - - - - Oggi ritorna - - - - - - - - - -".PHP_EOL.PHP_EOL;
        while($row = $absence->fetch_assoc()){
            $msg.= $row["FullName"]." - Camera ".$row["Room"].PHP_EOL;
            $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
        }

        $user = $db->getAllUsersForNotification("IncomingRoomer");
        while($row = $user->fetch_assoc()){
            $bot->setChatID($row["ChatID"]);
            $bot->sendMessage($msg);
        }
    }
}

/**
 * @param $bot TelegramBot
 * @param $db GiuliettoDB
 */
function reminderAggiornamentoAssenza(TelegramBot $bot, GiuliettoDB $db){
    $absence = $db->getIncomingRoomer(date('Y-m-d', strtotime('+1 day')));

    if($absence->num_rows > 0){
        $msg = _("Il tuo ritorno in struttura è previsto per domani, se prolunghi la tua assenza ricordati di aggiornare la data di rientro");
        while($row = $absence->fetch_assoc()){
            $bot->setChatID($row["User"]);
            $bot->sendMessage($msg);
        }
    }
}

/**
 * @param $bot TelegramBot
 * @param $db GiuliettoDB
 */
function arrivoOspiti(TelegramBot $bot, GiuliettoDB $db){
    $guest = $db->getIncomingGuest(date('Y-m-d'));

    if($guest->num_rows > 0){
        $msg = "- - - Ospiti in arrivo oggi - - -\n\n";
        while($row = $guest->fetch_assoc()){
            $msg.= $row['Name']." - Camera ".$row["Room"]."\n";
        }

        $user = $db->getAllUsersForNotification("IncomingGuest");
        while($row = $user->fetch_assoc()){
            $bot->setChatID($row["ChatID"]);
            $bot->sendMessage($msg);
        }
    }
}