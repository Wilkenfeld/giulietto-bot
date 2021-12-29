<?php

    require_once 'telegram_bot.php';
    require_once 'giulietto_db.php';
    require_once 'log.php';
    require_once 'config/config.php';
    require_once 'email.php';

    $bot = new TelegramBot(TOKEN);
    $db = null;

    $log = new Log(LOG_FILE_PATH."/bot.log");

    //Lettura aggiornamenti dai server telegram
    $update = $bot->getUpdate();
    $updateType = $bot->getUpdateType();

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Creazione della connessione al Database
    try{
        $conn = new mysqli(DB_HOST,DB_USERNAME,DB_PASSWORD, DB_NAME);
        $db = new GiuliettoDB($conn);
    }
    catch(Exception $e){
        $log->append($e->getCode()." ".$e->getMessage()."\n".$e->getTraceAsString(),"error");
        $bot->sendMessage("There was a connection error, the bot is temporarily out of service.");
        exit();
    }

    $chatID = $bot->getChatID();

    $db->setLogFile(LOG_FILE_PATH.'/'.preg_replace('/\s+/',"_",$update["from"]["first_name"]).'/'.preg_replace('/\s+/',"_",$update["from"]["first_name"]).".log");

    $user = $db->getUser($chatID);

    if($user === false){
        $bot->sendMessage("An error has occurred, the bot is temporarily out of service.");
        exit;
    }
    elseif(empty($user)){

        if($update["chat"]["type"] === "private" and preg_match('/^(pw|Pw|PW|pW)(\s+)(\w+)$/',$update["text"],$words)){
            //Recupera account type da password
            $accountType = $db->getAccountType($words[3]);

            if(empty($accountType)){
                $bot->sendMessage(_('Non esiste nessun account associato a questa password'));
            }
            else{
                //Lettura concatenazione e salvataggio di nome e cognome
                $fullName = $update["chat"]["first_name"]." ".$update["chat"]["last_name"];
                //Eliminazione eventuali spazi iniziali e finali
                $fullName = trim($fullName);

                $result = $db->insertUser($chatID,$fullName,$update["chat"]["username"],null,$update["chat"]["type"], $accountType, $update['from']['language_code']);

                if($result === true){
                    $bot->sendMessage(_("Benvenuto nel bot di Villa Giulia, clicca qui per aprire in menù => /menu"));

                    $newUserNotification = $db->getAllUsersForNotification('NewUser');
                    $_users = [];

                    while($row = $newUserNotification->fetch_assoc()){
                        $_users[] = $row['ChatID'];
                    }

                    sendNotification($_users, $fullName._(' ha appena effettuato l\'accesso con l\'account: ').$accountType, [$chatID]);
                }
                else{
                    $bot->sendMessage(_("Si è verificato un errore, registrazione non riuscita"));
                }
            }
        }
        else{
            $bot->sendMessage("Effettua l'accesso per poter utilizzare il bot, per accedere invia un messaggio in questo formato: \"pw password\", dove password è la password che ti è stata fornita per l'accesso");
        }
        exit;
    }

    setlocale(LC_ALL, LOCALE[$user["Language"]]);
    bindtextdomain('messages','../locale');
    textdomain('messages');

    define("MAIN_KEYBOARD_TEXT" , array(
            "AbsenceList" => _("Lista assenti")." \u{1F4CB}",
            "GuestList" => _("Lista ospiti")." \u{1F4CB}",
            "UserList" => _("Lista utenti")." \u{1F4CB}",
            "RoomList" => _('Lista camere')." \u{1F4CB}",
            "ChangeEmail" => _("Modifica email")." \u{1F4E7}",
            "NewAbsence" => _("Assenza")." \u{1F44B}",
            "NewGuest" => _("Ospite")." \u{1F6CF}",
            "GroupList" => _("Gruppi")." \u{1F4CB}",
            "MyGuestList" => _("I miei ospiti")." \u{1F4CB}",
            "MyAbsenceList" => _("Le mie assenze")." \u{1F4CB}",
            "TurnList" => _("Turni")." \u{1F9F9}",
            "GetGuideLine" => _("Linee guida")." \u{1F4D6}",
            "NewGuideLine" => _("Carica Linee guida")." \u{1F4D6}",
            "TypeOfTurnList" => _("Tipi turno")." \u{1F4CB}",
            "SwapGroup" => _("Scambia gruppo")." \u{1F503}",
            "RearrangeGroups" => _("Riorganizza Gruppi")." \u{1F500}",
            "TurnCalendar" => _("Calendario turni")." \u{1F5D3}",
            "ReportsMaintenance" => _("Segnala")." \u{1F6A8}",
            "ManageMaintenance" => _("Manutenzioni")." \u{1F6E0}"
        )
    );

    define("EXPORT_KEYBOARD_TEXT" , array(
            "ExportUserList" => _("Lista utenti"),
            "ExportGuest" => _("Ospiti"),
            "ExportAbsence" => _("Assenze"),
        )
    );

    $permission = $db->getPermission($user['AccountType']);

    if($permission === false){
        $bot->sendMessage(_("Si è verificato un errore, il bot è momentaneamente fuori servizio."));
        exit();
    }

    $array_keyboard = createPermissionKeyboard($permission, MAIN_KEYBOARD_TEXT);

    if(($permission["ExportUserList"] or $permission["ExportGuest"] or $permission["ExportAbsence"]) == true){
        $array_keyboard[] = [['text' => _("Esporta")." \u{1F4DD}"], ['text' => _('Impostazioni')."\u{2699}"]];
    }
    else{
        $array_keyboard[] = [['text' => _('Impostazioni')."\u{2699}"]];
    }

    $keyboard = json_encode(['keyboard'=>  $array_keyboard, 'resize_keyboard'=> true] ,JSON_PRETTY_PRINT);

    $notification = $db->getNotification($user['AccountType']);
    if($notification === false){
        $bot->sendMessage(_("Si è verificato un errore, il bot è momentaneamente fuori servizio."));
        exit();
    }

    define("TmpFileUser_path", TMP_FILE_PATH.preg_replace('/\s+/','_',$user["FullName"])."/");
    $bot->setTmpFilePath(TmpFileUser_path);
    const Guest_document = FILES_PATH . "Documenti_ospiti/";

    if(is_dir(TmpFileUser_path) == false){
        mkdir(TmpFileUser_path, 0755, true);
    }

    $messageInLineKeyboardPath = TmpFileUser_path.'messageInLineKeyboard.json';

    if($bot->getTypeDateSelection() == SELECT_SINGLE_DATE){
        //Se viene inviato un qualsiasi altro messaggio mentre si sta selezionando un data restituisce questo messaggio
        if ($bot->getUpdateType() === MESSAGE) {
            $bot->sendMessage(_("Prima di poter fare altro devi annullare o concludere l'inserimento della data"));
        }

        $date = $bot->selectDate();

        $path = TmpFileUser_path."calendar.json";
        $calendar = file_get_contents($path);
        $calendar = json_decode($calendar,true);

        //Se l'inserimento della data è stato annullato
        if ($date === false) {
            unlink($path);
        }
        elseif($date !== true){

            if($calendar["Type"] == 'NewTurn') {

                $file = file_get_contents(TmpFileUser_path.'tmpNewTurn.json');
                $file = json_decode($file, true);

                if( $db->createNewTypeOfTurn($file['Name'], $file['Frequency'], date('Y-m-d',$date), $file['UsersByGroup'], $file['GroupFrequency']) ){
                    $bot->sendMessage(_('Nuovo turno creato'), $keyboard);
                }
                else{
                    $bot->sendMessage(_('Si è verificato un errore nella creazione del nuovo turno'), $keyboard);
                }

                unlink(TmpFileUser_path.'tmpNewTurn.json');
            }

            unlink($path);
        }
    }
    elseif($bot->getTypeDateSelection() == SELECT_DATE_INTERVALL) { //Se è in corso la selezione di un intervallo di date
        //Se viene inviato un qualsiasi altro messaggio mentre si sta selezionando un data restituisce questo messaggio
        if ($bot->getUpdateType() === MESSAGE) {
            $bot->sendMessage(_("Prima di poter fare altro devi annullare o concludere l'inserimento della data"));
        }

        $calendarFile = TmpFileUser_path."calendar.json";
        $calendar = file_get_contents($calendarFile);
        $calendar = json_decode($calendar,true);

        $date = $bot->selectDate();

        //Se l'inserimento della data è stato annullato
        if ($date === false) {
            unlink($calendarFile);
        }
        elseif ($date !== true) { //Se la data viene confermata

            if (empty($calendar["FirstDate"])) {
                $calendar["FirstDate"] = $date;
                file_put_contents($calendarFile, json_encode($calendar));

                if($calendar['Type'] == 'NewAbsence'){
                    $bot->sendCalendar($date, _("Usa le frecce per selezionare la data dell'ultimo giorno di assenza "), SELECT_DATE_INTERVALL);
                }
                elseif($calendar['Type'] == 'UpdateAbsence'){
                    $fileName = TmpFileUser_path."updateAbsence.json";

                    $oldAbsence = file_get_contents($fileName);
                    $oldAbsence = json_decode($oldAbsence, true);

                    $bot->sendCalendar($oldAbsence['ReturnDate'], _("Usa le frecce per selezionare la nuova data di ritorno, o lascia quella vecchia"), SELECT_DATE_INTERVALL);
                }
                elseif($calendar['Type'] == 'NewGuest'){
                    $bot->sendCalendar($date, _("Usa le frecce per selezionare la data di partenza dell'ospite"), SELECT_DATE_INTERVALL);
                }
                elseif($calendar['Type'] == 'UpdateGuest'){
                    $fileName = TmpFileUser_path.'updateGuest.json';

                    $file = file_get_contents($fileName);
                    $file = json_decode($file, true);

                    $bot->sendCalendar($file['LeavingDate'], _("Usa le frecce per selezionare la nuova data di partenza dell'ospite, o lascia quella vecchia"), SELECT_DATE_INTERVALL);
                }
                else{
                    $bot->sendCalendar($date, _("Usa le frecce per selezionare la seconda data"), SELECT_DATE_INTERVALL);
                }
            }
            else {
                $calendar["SecondDate"] = $date;
                file_put_contents($calendarFile, json_encode($calendar));

                //inserimento ospite
                if($calendar["FirstDate"] > $calendar["SecondDate"]){
                    $bot->sendMessage(_("La prima data deve essere antecedente alla seconda"));
                }
                elseif($calendar["Type"] == 'NewGuest') {
                    //inserimento della data di arrivo in struttura
                    checkNewGuestInput($calendar["FirstDate"], $chatID, $keyboard);

                    //inserimento della data di partenza dalla struttura
                    checkNewGuestInput($calendar["SecondDate"], $chatID, $keyboard);
                }
                elseif($calendar['Type'] == 'UpdateGuest'){

                    //file temporaneo contenente i dati dell'ospite da inserire;
                    $fileName = TmpFileUser_path.'updateGuest.json';

                    //Lettura dei dati dell'ospite dal file;
                    $file = file_get_contents($fileName);
                    $file = json_decode($file, true);

                    $bot->deleteMessage($file['MessageID']);

                    $guest = $db->getGuest($file["User"], $file["GuestName"], date('Y-m-d',$file['CheckInDate']), date('Y-m-d',$file['LeavingDate']));

                    if( $file['CheckInDate'] < strtotime('+2 days') ){
                        $bot->sendMessage(_("Modifica non riuscita, l'ospite deve essere registrato e confermato con 2 giorni d'anticipo, prova a ricontrollare le date inserite"),$keyboard);

                        sendMessageEditGuest($guest, $permission, $messageInLineKeyboardPath);
                    }
                    else{

                        $date = [];
                        for($i = $calendar['FirstDate']; $i <= $calendar['SecondDate']; $i = strtotime("+1 days",$i)){
                            if($db->getSeatsNum(date('Y-m-d',$i))["FreeSeats"] <= 0){
                                $date[] = $i;
                            }
                        }

                        if(!empty($date)){
                            $msg = _("Nei seguenti giorni non sono disponibili posti in struttura, scegli un altra data").PHP_EOL;
                            $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                            foreach ($date as $value){
                                $msg .= strftime('%d %B %Y',$value).PHP_EOL;
                            }

                            $bot->sendMessage($msg);
                            sendMessageEditGuest($guest, $permission, $messageInLineKeyboardPath);
                            exit;
                        }

                        $numGuest = $db->getGuestList(date('Y-m-d',$file["CheckInDate"]), date('Y-m-d',$file["LeavingDate"]), $file["User"])->num_rows;

                        //Un ospite è quello da modificare quindi non si considera usano > invece che >=
                        if($numGuest > 2){
                            $msg = _("Non puoi ospitare più di due persone nello stesso periodo, stai gia ospitando:"."\n");
                            $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                            $guestList = $db->getGuestList(date('Y-m-d',$file["CheckInDate"]),date('Y-m-d',$file["LeavingDate"]),$file["User"]);
                            while($row = $guestList->fetch_assoc()){
                                $msg .= " - ".$row["Name"]._(" dal ").$row["CheckInDate"]._(" al ").$row["LeavingDate"].",\n";
                            }

                            $bot->sendMessage($msg);
                            sendMessageEditGuest($guest, $permission, $messageInLineKeyboardPath);
                            exit;
                        }

                        $roommateList = $db->getRoommateList($chatID);

                        $guest = $db->getGuest($file["User"], $file["GuestName"], date('Y-m-d',$calendar['FirstDate']), date('Y-m-d',$calendar['SecondDate']));

                        if(empty($guest)){
                            //Se l'utente non ha un compagno di stanza la registrazione viene eseguita direttamente senza richiedere la conferma al suo compagno
                            if($roommateList->num_rows == 0){
                                if($db->updateGuest($file['User'],$file["GuestName"], date('Y-m-d',$file['CheckInDate']), date('Y-m-d',$file['LeavingDate']), date('Y-m-d',$calendar['FirstDate']), date('Y-m-d',$calendar['SecondDate']))){

                                    $guest = $db->getGuest($file["User"], $file["GuestName"], date('Y-m-d',$calendar['FirstDate']), date('Y-m-d',$calendar['SecondDate']));

                                    sendMessageEditGuest($guest, $permission, $messageInLineKeyboardPath);

                                    $msg = _("Il periodo di permanenza in struttura di")." ".$file['GuestName']." "._("ospitato da")." ".$db->getUser($file['User'])['FullName']." "._("è stato modificato, ora sarà ospite dal")." ".date('Y-m-d',$file['CheckInDate'])." "._("al")." ".date('Y-m-d',$file['LeavingDate']);
                                    $bot->sendMessage($msg);
                                }
                                else{
                                    $bot->sendMessage(_("Non è stato possibile modificare il periodo di permanenza dell'ospite"), $keyboard);
                                }
                            }
                            else{
                                $userMsgID = [];

                                while($roommate = $roommateList->fetch_assoc()){

                                    $keyboardYesNo = "{
                                            \"inline_keyboard\":
                                                [
                                                    [
                                                        { 
                                                            \"text\":\"Yes\",
                                                            \"callback_data\":\"guestUpdateAccepted-$chatID\"
                                                        },
                                                        {
                                                            \"text\":\"No\",
                                                            \"callback_data\":\"guestUpdateRefused-$chatID\"
                                                        }
                                                    ]
                                                ]
                                            }";

                                    $arrivo = date( 'd-m-Y' ,$calendar['FirstDate']);
                                    $partenza = date( 'd-m-Y' ,$calendar['SecondDate']);
                                    $message = _("Il/La tuo/a compagno/a di stanza ").$user["FullName"]._(" vorrebbe cambiare il periodo di permanenza di ").$file['GuestName']._(" nella vostra stanza, il nuovo periodo di permanenza è dal ").$arrivo._(" al ").$partenza._(", per te va bene?");

                                    $bot->setChatID($roommate["ChatID"]);
                                    $msgResult = json_decode($bot->sendMessage($message, $keyboardYesNo),true);

                                    $userMsgID[$roommate["ChatID"]] = $msgResult["result"]["message_id"];
                                }

                                $file['NewCheckInDate'] = $calendar['FirstDate'];
                                $file['NewLeavingDate'] = $calendar['SecondDate'];
                                $file['MessageID'] = $userMsgID;
                                $file['RequestsSent'] = true;
                                $file['UsersWhoHaveAccepted'] = 0;
                                $file['UserInRoom'] = $roommateList->num_rows;
                                file_put_contents($fileName, json_encode($file, JSON_PRETTY_PRINT));

                                $bot->setChatID($chatID);
                                if($roommateList->num_rows > 1){
                                    $bot->sendMessage(_("Attendi la conferma dei tuoi compagni di stanza, ti arriverà una notifica quando confermeranno"), $keyboard);
                                }
                                else{
                                    $bot->sendMessage(_("Attendi la conferma del tuo compagno di stanza, ti arriverà una notifica quando confermerà"), $keyboard);
                                }
                            }
                        }
                        else{
                            $bot->sendMessage(_("L'ospite risulta già registrato"), $keyboard);
                            unlink($fileName);
                        }
                    }
                }
                elseif($calendar["Type"] == 'GuestList'){

                    for($day = $calendar["FirstDate"]; $day <= $calendar["SecondDate"]; $day = strtotime("+1 day",$day)){

                        $msg = "- - - - - - - - - ".date("d-m-Y", $day)." - - - - - - - - -\n";

                        $guest = $db->getGuestList(date("Y-m-d", $day),date("Y-m-d", $day));

                        if($guest->num_rows == 0){
                            $msg .= _("Nessun ospite").PHP_EOL;
                            $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                        }
                        else{
                            while ($row = $guest->fetch_assoc()) {
                                $msg .= $row["FullName"]." - ".$row["Name"]." - Camera ".$row["Room"]."\n";
                                $msg .= _("parte il ").strftime('%e %h %Y', strtotime($row["LeavingDate"])).PHP_EOL;
                                $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                            }
                        }
                        $bot->sendMessage($msg);
                    }
                }
                elseif($calendar["Type"] == 'NewAbsence') {

                    if($calendar['SecondDate'] < time()){
                        $bot->sendMessage(_("Non puoi registrare un assenza che termina in un giorno antecedente ad oggi"));
                        exit;
                    }

                    try{
                        $db->insertAbsence($chatID, date('Y-m-d',$calendar['FirstDate']), date('Y-m-d',$calendar['SecondDate']));

                        $bot->sendMessage(_("Nuova assenza registrata dal").' '.strftime('%d %h %Y',$calendar['FirstDate']).' '._('al').' '.strftime('%d %h %Y',$calendar['SecondDate']));

                        $users = $db->getAllUsersForNotification('NewAbsence');

                        while($row = $users->fetch_assoc()){
                            if($row['ChatID'] !== $chatID){
                                $bot->setChatID($row['ChatID']);
                                $bot->sendMessage($user['FullName'].' '._('sarà assente dal').' '.strftime('%d %h %Y',$calendar['FirstDate']).' '._('al').' '. strftime('%d %h %Y',$calendar['SecondDate']));
                            }
                        }
                    }
                    catch(Exception $e){
                        if($e->getMessage() == 'Date overlap'){
                            $bot->sendMessage(_('Esiste già un assenza registrata che si sovrappone alla nuova da te inserita, puoi modificare quella già registrata dalla sezione \'Le mie assenza\''));
                        }
                        else{
                            $bot->sendMessage(_("Non è stato possibile registrare l'assenza"));
                        }
                    }
                }
                elseif($calendar['Type'] == 'UpdateAbsence'){

                    $fileName = TmpFileUser_path."updateAbsence.json";

                    //Lettura dei dati dell'ospite dal file;
                    $oldAbsence = file_get_contents($fileName);
                    $oldAbsence = json_decode($oldAbsence, true);

                    $leavingDate = $calendar['FirstDate'];
                    $returnDate = $calendar['SecondDate'];

                    if($db->updateAbsence($oldAbsence['ChatID'],date('Y-m-d',$oldAbsence['LeavingDate']), date('Y-m-d',$oldAbsence['ReturnDate']), date('Y-m-d',$leavingDate), date('Y-m-d',$returnDate))){
                        $msg = "- - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                        $msg.= _("Dal ").strftime('%d %h %Y',$leavingDate)._(" al ").strftime('%d %h %Y',$returnDate).PHP_EOL;
                        $msg.= "- - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";

                        $keyboardAbsence = [];

                        if($permission['UpdateAbsence']){
                            $keyboardAbsence[] = ['text' => _("Modifica")." \u{270F}", 'callback_data' => "updateAbsence-$leavingDate-$returnDate"];
                        }

                        if($permission['DeleteAbsence']){
                            $keyboardAbsence[] = ['text' => _('Elimina')." \u{274C}", 'callback_data' => "deleteAbsence-$leavingDate-$returnDate"];
                        }

                        $keyboardAbsence = json_encode(['inline_keyboard'=> [$keyboardAbsence]],JSON_PRETTY_PRINT);

                        $bot->editMessageText($oldAbsence['MessageID'], $msg);
                        $bot->editMessageReplyMarkup($oldAbsence['MessageID'], $keyboardAbsence);

                        $messageInLineKeyboard = file_get_contents($messageInLineKeyboardPath);
                        $messageInLineKeyboard = json_decode($messageInLineKeyboard, true);
                        $messageInLineKeyboard[$oldAbsence['MessageID']] = $msg;
                        file_put_contents($messageInLineKeyboardPath ,json_encode($messageInLineKeyboard, JSON_PRETTY_PRINT));

                        $users = $db->getAllUsersForNotification('NewAbsence');

                        while($row = $users->fetch_assoc()){
                            if($row['ChatID'] !== $chatID){
                                $bot->setChatID($row['ChatID']);
                                $bot->sendMessage($user['FullName'].' '._('ha modificato la sua assenza dal').' '.strftime('%d %h %Y',$oldAbsence['LeavingDate']).' '._('al').' '. strftime('%d %h %Y',$oldAbsence['ReturnDate']).' '._(', ora sarà assente dal').' '.strftime('%d %h %Y',$leavingDate).' '._('al').' '. strftime('%d %h %Y',$returnDate));
                            }
                        }
                    }
                    else{
                        $bot->sendMessage(_("Non è stato possibile modificare l'assenze"), $keyboard);
                    }

                    unlink($fileName);
                }
                elseif($calendar["Type"] == 'AbsenceList') {

                    $endDate = date("Y-m-d", $calendar["SecondDate"]);

                    for($day = $calendar["FirstDate"]; date("Y-m-d", $day) <= $endDate; $day = strtotime("+1 day",$day)){

                        $msg = "- - - - - - - - - ".date("d-m-Y", $day)." - - - - - - - - -\n";

                        $absents = $db->getAbsentsList(date("Y-m-d",$day));

                        if($absents->num_rows == 0){
                            $msg .= _("Nessun assente")."\n";
                            $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                        }
                        else{
                            while ($row = $absents->fetch_assoc()) {
                                if (empty($row["Username"])) {
                                    $msg .= $row["FullName"] . " - Camera " . $row["Room"] . "\n";

                                } else {
                                    $msg .= $row["FullName"] . " - @" . $row["Username"] . " - Camera " . $row["Room"] . "\n";
                                }
                                $msg .= _("ritorna il ").strftime('%e %h %Y',strtotime($row["ReturnDate"])).PHP_EOL;
                                $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                            }
                        }
                        $bot->sendMessage($msg);
                    }
                }

                unlink($calendarFile);
            }
        }
    }
    elseif($updateType == CALLBACK_QUERY){

        $callbackMessageID = $update['message']['message_id'];

        if($user['Enabled']){
            if(preg_match("/^(guestAccepted|guestRefused)(-)(\d+)$/",$update["data"],$words)){
                if($words[1] == "guestAccepted"){
                    $bot->editMessageText($callbackMessageID, _("Hai accettato la presenza di un ospite nella tua camera"));

                    $roommateName = $db->getUser($words[3])["FullName"];
                    $roommateName = preg_replace('/\s+/','_',$roommateName);

                    $fileName = TMP_FILE_PATH."$roommateName/tmpGuest.json";

                    $ospite = file_get_contents($fileName);
                    $ospite = json_decode($ospite, true);

                    $ospite['UsersWhoHaveAccepted']++;

                    if($ospite['UsersWhoHaveAccepted'] == $ospite['UserInRoom']){
                        if(guestInput($fileName) === true){
                            $bot->setChatID($words[3]);
                            $bot->sendMessage(_("Ospite confermato e registrato"));
                        }
                        unlink($fileName);
                    }
                    else{
                        $bot->setChatID($words[3]);
                        $bot->sendMessage(_("Il tuo ospite è stato accettato da ").$user["FullName"]);
                        file_put_contents($fileName, json_encode($ospite));
                    }
                }
                else{

                    $bot->EditMessageText($callbackMessageID,("Hai rifiutato la presenza di un ospite nella tua camera"));

                    $roommateName = $db->getUser($words[3])["FullName"];
                    $roommateName = preg_replace('/\s+/','_',$roommateName);

                    $fileName = TMP_FILE_PATH."$roommateName/tmpGuest.json";

                    $ospite = file_get_contents($fileName);
                    $ospite = json_decode($ospite, true);

                    foreach($ospite["UserMessageID"] as $key => $value){
                        if($key !== $chatID){
                            $bot->setChatID($key);
                            $bot->deleteMessage($value);
                        }
                    }

                    $bot->setChatID($words[3]);
                    $bot->sendMessage($user["FullName"]._(" non ha accettato il tuo ospite, l'ospite non verrà registrato."));
                    unlink($fileName);
                }
            }
            if(preg_match("/^(guestUpdateAccepted|guestUpdateRefused)(-)(\d+)$/",$update["data"],$words)){

                $roommateName = $db->getUser($words[3])["FullName"];
                $roommateName = preg_replace('/\s+/','_',$roommateName);

                $fileName = TMP_FILE_PATH."$roommateName/updateGuest.json";

                $guestFile = file_get_contents($fileName);
                $guestFile = json_decode($guestFile, true);

                if($words[1] == "guestUpdateAccepted"){
                    $bot->editMessageText($callbackMessageID, _("Hai accettato la modifica del periodo di permanenza dell'ospite nella tua camera"));

                    $guestFile['UsersWhoHaveAccepted']++;

                    $bot->setChatID($guestFile['User']);

                    if($guestFile['UsersWhoHaveAccepted'] == $guestFile['UserInRoom']){

                        if($db->updateGuest($guestFile['User'],$guestFile["GuestName"], date('Y-m-d',$guestFile['CheckInDate']),  date('Y-m-d',$guestFile['LeavingDate']), date('Y-m-d',$guestFile['NewCheckInDate']), date('Y-m-d',$guestFile['NewLeavingDate']))){
                            $bot->sendMessage(_("Il periodo di permanenza di").' '.$guestFile["GuestName"].' '._("è stato modificato"));

                            $msg = _("Il periodo di permanenza in struttura di")." ".$guestFile['GuestName']." "._("ospitato da")." ".$db->getUser($guestFile['User'])['FullName']." "._("è stato modificato, ora sarà ospite dal")." ".date('Y-m-d',$guestFile['CheckInDate'])." "._("al")." ".date('Y-m-d',$guestFile['LeavingDate']);
                            $bot->sendMessage($msg);
                        }
                        else{
                            $bot->setChatID($words[3]);

                            $bot->sendMessage(_("Non è stato possibile modificare il periodo di permanenza dell'ospite"), $keyboard);
                        }
                        unlink($fileName);
                    }
                    else{
                        $bot->setChatID($words[3]);
                        $bot->sendMessage(_("La modifica del periodo di permanenza del tuo ospite è stata accettata da ").$user["FullName"]);
                        file_put_contents($fileName, json_encode($guestFile));
                    }
                }
                elseif($words[1] == "guestUpdateRefused"){

                    $bot->editMessageText($callbackMessageID, _("Hai rifiutato la modifica del periodo di permanenza dell'ospite nella tua camera"));

                    foreach($guestFile["UserMessageID"] as $key => $value){
                        if($key !== $chatID){
                            $bot->setChatID($key);
                            $bot->deleteMessage($value);
                        }
                    }

                    $bot->setChatID($words[3]);
                    $bot->sendMessage($user["FullName"]._(" non ha accettato la modifica del periodo di permanenza del tuo ospite, il periodo di permanenza non verrà cambiato."));
                    unlink($fileName);
                }
            }
            elseif(preg_match("/^(deleteAbsence)(-)(\d+)(-)(\d+)$/",$update["data"],$words)){
                if($permission['DeleteAbsence']){
                    if($db->deleteAbsence($chatID, date('Y-m-d',$words[3]), date('Y-m-d',$words[5]))){
                        $bot->deleteMessage($callbackMessageID);

                        $users = $db->getAllUsersForNotification('DeletedAbsence');

                        while($row = $users->fetch_assoc()){
                            if($row['ChatID'] !== $chatID){
                                $bot->setChatID($row['ChatID']);
                                $bot->sendMessage($user['FullName'].' '._('ha eliminato l\'assenza dal').' '.strftime('%d %h %Y',$words[3]).' '._('al').' '. strftime('%d %h %Y',$words[5]));
                            }
                        }
                    }
                    else{
                        $bot->sendMessage(_("Non è stato possibile eliminare l'assenza"), $keyboard);
                    }
                }
            }
            elseif(preg_match("/^(updateAbsence)(-)(\d+)(-)(\d+)$/",$update["data"],$words)){

                if($permission['UpdateAbsence']){
                    $path = TmpFileUser_path."calendar.json";
                    $type['Type'] = 'UpdateAbsence';
                    file_put_contents($path,json_encode($type));

                    $updateAbsenceFilePath = TmpFileUser_path.'updateAbsence.json';
                    $updateAbsenceFile['ChatID'] = $chatID;
                    $updateAbsenceFile['LeavingDate'] = $words[3];
                    $updateAbsenceFile['ReturnDate'] = $words[5];
                    $updateAbsenceFile['MessageID'] = $callbackMessageID;

                    file_put_contents($updateAbsenceFilePath, json_encode($updateAbsenceFile));

                    $bot->sendCalendar($words[3], _("Usa le frecce per selezionare la nuova data di partenza, o lascia quella vecchia"),SELECT_DATE_INTERVALL);
                }
            }
            elseif(preg_match("/^(deleteGuest)(-)(\d+)$/",$update["data"],$words)){
                if($permission['DeleteGuest']){

                    $guest = $db->getGuestById($words[3]);

                    if($db->deleteGuest($guest['User'], $guest['Name'],$guest['CheckInDate'], $guest['LeavingDate'])){
                        $bot->deleteMessage($callbackMessageID);

                        $users = $db->getAllUsersForNotification('DeletedAbsence');

                        while($row = $users->fetch_assoc()){
                            $bot->setChatID($row['ChatID']);
                            $bot->sendMessage($guest['Name'].' '._('non sarà più ospite in camera').' '.$guest['Room'].' '._('dal').' '.strftime('%d %h %Y',strtotime($guest['CheckInDate'])).' '._('al').' '. strftime('%d %h %Y',strtotime($guest['LeavingDate'])));
                        }
                    }
                    else{
                        $bot->sendMessage(_("Non è stato possibile cancellare la registrazione dell'ospite"), $keyboard);
                    }

                    unlink(TmpFileUser_path.'updateGuest.json');
                }
            }
            elseif(preg_match("/^(updateGuest)(-)(\d+)$/",$update["data"],$words)){

                if($permission['UpdateGuest']){
                    $path = TmpFileUser_path."calendar.json";
                    $type['Type'] = 'UpdateGuest';
                    file_put_contents($path,json_encode($type));

                    $file = file_get_contents(TmpFileUser_path.'updateGuest.json');
                    $file = json_decode($file, true);

                    if(!$file or !$file['RequestsSent']){
                        
                        $guest = $db->getGuestById($words[3]);

                        $file['GuestID'] = $words[3];
                        $file['User'] = $guest['User'];
                        $file['GuestName'] = $guest['Name'];
                        $file['CheckInDate'] = strtotime($guest['CheckInDate']);
                        $file['LeavingDate'] = strtotime($guest['LeavingDate']);
                        $file['RequestsSent'] = false;
                        $file['MessageID'] = $callbackMessageID;
                        file_put_contents(TmpFileUser_path.'updateGuest.json', json_encode($file, JSON_PRETTY_PRINT));

                        $bot->sendCalendar($file['CheckInDate'], _("Usa le frecce per selezionare la nuova data di arrivo, o lascia quella vecchia"),SELECT_DATE_INTERVALL);
                    }
                    else{
                        $bot->sendMessage(_("È in corso la modifica di un altro ospite, attendi che sia confermato per poterne modificare un altro"));
                    }
                }
            }
            elseif(preg_match("/^(deleteUser)(-)(-?\d+)$/",$update["data"],$words)){
                if($permission['DeleteUser']){
                    $bot->deleteMessage($callbackMessageID);

                    $file['ChatID'] = $words[3];
                    file_put_contents(TmpFileUser_path.'deleteUser.json', json_encode($file, JSON_PRETTY_PRINT));

                    $otp['Type'] = 'DeleteUser';
                    $otp['OTP'] = rand(1000,9999);
                    file_put_contents(TmpFileUser_path.'otp.json', json_encode($otp, JSON_PRETTY_PRINT));

                    $msg = _('Eliminando un utente verranno eliminate in automatico anche tutte le sue assenze registrate, i suoi ospiti e verrà rimosso da tutti i gruppi di cui fa parte. Per confermare l\'eliminazione scrivi il seguente codice:');
                    $bot->sendMessageForceReply($msg);
                    $bot->sendMessage($otp['OTP']);
                }
            }
            elseif(preg_match("/^(enableDisableUser)(-)(-?\d+)$/",$update["data"],$words)){
                if($permission['ChangeUserState']){
                    if($db->changeUserState($words[3])){

                        $userChatID = $words[3];
                        $_user = $db->getUser($userChatID);

                        $keyboardUser = keyboardEditUser($permission, $_user);
                        $bot->editMessageReplyMarkup($callbackMessageID,$keyboardUser);

                        $messageInLineKeyboard = file_get_contents($messageInLineKeyboardPath);
                        $messageInLineKeyboard = json_decode($messageInLineKeyboard, true);
                        $messageInLineKeyboard[$callbackMessageID] = $update['message']['text'];
                        file_put_contents($messageInLineKeyboardPath ,json_encode($messageInLineKeyboard, JSON_PRETTY_PRINT));
                    }
                    else{
                        $bot->sendMessage(_("Non è stato possibile abilitare/disabilitare l'utente"), $keyboard);
                    }
                }
            }
            elseif(preg_match("/^(changeNameUser)(-)(-?\d+)$/",$update["data"],$words)){
                if($permission['ChangeNameUser']){
                    $bot->deleteMessage($callbackMessageID);
                    $file['ChatID'] = $words[3];
                    file_put_contents(TmpFileUser_path.'changeNameUser.json', json_encode($file, JSON_PRETTY_PRINT));

                    $bot->sendMessageForceReply(_('Invia il nuovo nome:'));
                }
            }
            elseif(preg_match("/^(changeRoom)(-)(\d+)$/",$update["data"],$words)){
                if($permission['ChangeUserRoom']){
                    $bot->deleteMessage($callbackMessageID);
                    $file['ChatID'] = $words[3];
                    file_put_contents(TmpFileUser_path.'changeRoom.json', json_encode($file, JSON_PRETTY_PRINT));

                    $rooms = $db->query('SELECT R.Num, R.Beds AS TotalBeds, COUNT(U.ChatID) AS OccupiedBeds FROM Room R LEFT JOIN User U ON R.Num = U.Room AND U.Enabled IS TRUE GROUP BY R.Num HAVING COUNT(*) < R.Beds;');
                    $_rooms = [];
                    while($row = $rooms->fetch_assoc()){
                        $_rooms[] = $row['Num'];
                    }

                    $file1['Type'] = 'ChangeUserRoom';
                    file_put_contents(TmpFileUser_path.'selectRoom.json', json_encode($file1));

                    $roomsKeyboard = createUserKeyboard($_rooms,[[['text' => _('Nessuna camera')]],[['text' => "\u{1F3E1}"]]]);
                    $bot->sendMessage(_('Scegli la camera da assegnare fra quelle disponibili: '), $roomsKeyboard);
                }
            }
            elseif(preg_match("/^(insertUserInGroup|deleteUserFromGroup)(-)(-?\d+)$/",$update["data"],$words)){
                if($permission['InsertUserInGroup']){
                    $bot->deleteMessage($callbackMessageID);
                    if($words[1] == 'insertUserInGroup'){
                        $file['Type'] = 'insertUser';

                        $fullGroupList = $db->getGroupList();
                        $userGroupList = $db->getGroupsByUser($words[3]);

                        $groupList = array_diff_key($fullGroupList,$userGroupList);

                        if($groupList === false){
                            $bot->sendMessage(_("Non è stato possibile recuperare la lista dei gruppi"));
                        }
                        elseif(sizeof($groupList) == 0) {
                            $bot->sendMessage(_("Non esiste nessun gruppo"));
                        }
                        else{
                            $groupsKeyboard = createUserKeyboard(array_keys($groupList),[ [['text' => "\u{1F3E1}"]] ]);
                            $bot->sendMessage(_('Scegli in quale gruppo vuoi inserire l\'utente: '), $groupsKeyboard);
                        }
                    }
                    elseif($words[1] == 'deleteUserFromGroup'){
                        $file['Type'] = 'deleteUserFromGroup';

                        $groupList = $db->getGroupsByUser($words[3]);

                        if($groupList === false){
                            $bot->sendMessage(_("Non è stato possibile recuperare la lista dei gruppi"));
                        }
                        elseif(sizeof($groupList) == 0) {
                            $bot->sendMessage(_('L\'utente non appartiene a nessun gruppo'));
                        }
                        else{
                            $groupsKeyboard = createUserKeyboard(array_keys($groupList),[ [['text' => "\u{1F3E1}"]] ]);
                            $bot->sendMessage(_('Scegli da quale gruppo vuoi rimuovere l\'utente: '), $groupsKeyboard);
                        }
                    }

                    $file['ChatID'] = $words[3];
                    file_put_contents(TmpFileUser_path.'selectGroup.json', json_encode($file, JSON_PRETTY_PRINT));
                }
            }
            elseif(preg_match("/^(swapAccepted|swapRefused)(-)(\d+)$/",$update["data"],$words)){

                $fromUser = $db->getUser($words[3]);

                $data = file_get_contents(TMP_FILE_PATH.preg_replace('/\s+/','_',$fromUser["FullName"])."/".'swapGroupData.json');
                $data = json_decode($data, true);

                $bot->deleteMessage($callbackMessageID);
                $bot->setChatID($data['From']);
                if($words[1] == 'swapAccepted'){

                    if($db->swapGroup($data['From'], $data['To'], $data['FromGroup'], $data['ToGroup'])){
                        $bot->sendMessage($db->getUser($data['To'])['FullName']._(' ha accettato lo scambio da '.$data['FromGroup'].' a '.$data['ToGroup'].' scambio effettuato'));
                    }
                    else{
                        $bot->sendMessage(_('Si è verificato un problema nel cambio di gruppo'));
                    }
                }
                else{
                    $bot->sendMessage($db->getUser($data['To'])['FullName']._(' ha rifiutato lo scambio da '.$data['FromGroup'].' a '.$data['ToGroup']));
                }

                unlink(TMP_FILE_PATH.preg_replace('/\s+/','_',$fromUser["FullName"]));
            }
            elseif(preg_match("/^(changeTypeOfTurnFrequency)(-)(\w+)$/",$update["data"],$words)){
                if($permission['EditTypeOfTurn']){
                    $bot->deleteMessage($callbackMessageID);
                    $file['TypeOfTurn'] = $words[3];
                    file_put_contents(TmpFileUser_path.'editTypeOfTurn.json', json_encode($file, JSON_PRETTY_PRINT));

                    $bot->sendMessageForceReply(_('Invia la nuova frequenza del turno:'));
                }
            }
            elseif(preg_match("/^(changeUserByGroup)(-)(\w+)$/",$update["data"],$words)){
                if($permission['EditTypeOfTurn']){
                    $bot->deleteMessage($callbackMessageID);
                    $file['TypeOfTurn'] = $words[3];
                    file_put_contents(TmpFileUser_path.'editTypeOfTurn.json', json_encode($file, JSON_PRETTY_PRINT));

                    $bot->sendMessageForceReply(_('Invia da quanti utenti deve essere composto il gruppo:'));
                }
            }
            elseif(preg_match("/^(AddRoomInGroup)(-)(\d+)$/",$update["data"],$words)){
                if($permission['InsertUserInGroup']){
                    $bot->editMessageText($callbackMessageID,$update['message']['text']);
                    $file['Room'] = $words[3];
                    $file['Type'] = 'insertRoom';
                    file_put_contents(TmpFileUser_path.'selectGroup.json', json_encode($file, JSON_PRETTY_PRINT));

                    $groupList = $db->getGroupList();
                    $groupsKeyboard = createUserKeyboard(array_keys($groupList));
                    $bot->sendMessage(_('Scegli in quale gruppo vuoi inserire gli utenti nella camera: '), $groupsKeyboard);
                }
            }
            elseif(preg_match("/^(deleteReport)(-)(\d+)$/",$update["data"],$words)){
                if($permission['ManageMaintenance']){
                    $bot->deleteMessage($callbackMessageID);
                    $db->deleteReport($words[3]);
                }
            }
            elseif(preg_match("/^(resolved)(-)(\d+)$/",$update["data"],$words)){
                if($permission['ManageMaintenance']){
                    $bot->deleteMessage($callbackMessageID);
                    $db->setMaintenanceAsDone($bot->getChatID(),$words[3]);
                }
            }
        }
        else{
            $bot->sendMessage(_("Il tuo account è stato disabilitato, non ti sarà possibile utilizzare il bot fino a quando non verrà riattivato."));
        }
    }
    elseif($updateType == MESSAGE){

        $messageInLineKeyboard = file_get_contents($messageInLineKeyboardPath);
        $messageInLineKeyboard = json_decode($messageInLineKeyboard, true);
        foreach($messageInLineKeyboard as $key => $value){
            $bot->editMessageText($key, $value);
        }
        unlink($messageInLineKeyboardPath);

        if($update["chat"]["type"] === "private"){

            //Change account type
            if(preg_match('/^(pw|Pw|PW|pW)(\s+)(\w+)$/',$update["text"],$words)){
                //Recupera account type da password
                $accountType = $db->getAccountType($words[3]);

                if(empty($accountType)){
                    $bot->sendMessage(_('Non esiste nessun account associato a questa password'));
                    exit;
                }

                if($user['AccountType'] == $accountType){
                    $bot->sendMessage(_('Sei gia registrato'));
                }
                else{
                    $db->updateAccountType($chatID, $accountType);
                    $bot->sendMessage(_("Sei passato all'account di tipo: ").$accountType.PHP_EOL._('Clicca per ricaricare il menù')." => /menu");

                    $newUserNotification = $db->getAllUsersForNotification('NewUser');
                    $_users = [];

                    while($row = $newUserNotification->fetch_assoc()){
                        $_users[] = $row['ChatID'];
                    }

                    sendNotification($_users, $user['FullName']._(' è passato all\'account di tipo: ').$accountType, [$chatID]);
                }
            }
            else{
                //Se lo username dell'utente è cambiato viene aggiornato nel database
                if($user['Username'] != $update['chat']['username']){
                    $db->updateUsername($chatID,$update['chat']['username']);
                }

                if(!$user['Enabled']){
                    $bot->sendMessage(_("Il tuo account è stato disabilitato, non ti sarà possibile utilizzare il bot fino a quando non verrà riattivato."));
                    exit;
                }

                $keyOfText = array_search($update["text"],MAIN_KEYBOARD_TEXT);

                if($update["reply_to_message"]["text"] == _("Invia il file con le nuove linee guida:")){

                    if($permission["NewGuideLine"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    if(empty($update["document"]["file_id"])){
                        $bot->sendMessage(_('Invia il file dalla sezione file di telegram'));
                        $bot->sendMessageForceReply(_("Invia il file con le nuove linee guida:"));
                    }
                    elseif(pathinfo($update["document"]["file_name"], PATHINFO_EXTENSION) == 'pdf'){
                        if(downloadDocument(FILES_PATH,$update["document"]["file_id"],"Linee_guida")){
                            $bot->sendMessage(_("Nuove linee guida caricate"), $keyboard);
                        }
                        else
                        {
                            $bot->sendMessage(_("Si è verificato un errore nel salvataggio del file"), $keyboard);
                        }
                    }
                    else{
                        $bot->sendMessage(_('Il file deve essere in formato pdf'));
                    }
                }
                elseif($update["reply_to_message"]["text"] == _("Invia la tua nuova email:")){

                    if($permission["ChangeEmail"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    if($db->updateEmail($chatID,$update["text"]) === false){
                        $bot->sendMessage(_("Non è stato possibile aggiornare l'e-mail, controlla che sia in un formato valido."), $keyboard);
                    }
                    else{
                        $bot->sendMessage(_("E-mail aggiornata, la tua nuova e-mail è: ").strtolower($update["text"]), $keyboard);
                    }
                }
                elseif($update["reply_to_message"]["text"] == _('Invia il numero di posti della camera:')){

                    if($permission["NewRoom"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $roomsCount = $db->query('SELECT COUNT(*) AS roomsCount FROM Room;');
                    $roomsCount = $roomsCount->fetch_assoc()['roomsCount'];

                    if($db->createRoom($roomsCount+1, $update['text'])){
                        $bot->sendMessage(_('Camera').' '.($roomsCount+1).' '._('aggiunta'), $keyboard);
                    }
                    else{
                        $bot->sendMessage(_('Si è verificato un errore nell\'aggiungere la camera'), $keyboard);
                    }
                }
                elseif($update["reply_to_message"]["text"] == _("Scrivi Nome e Cognome dell'ospite da registrare:")) {

                    if($permission["NewGuest"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $text = $update["text"];
                    checkNewGuestInput($text, $chatID, $keyboard);
                }
                elseif($update["reply_to_message"]["text"] == _("Scrivi Nome e Cognome dell'ospite da eliminare:")) {

                    if($permission["DeleteGuest"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    //file temporaneo contenente i dati dell'ospite da inserire
                    $fileName = TmpFileUser_path."TmpEliminaOspite.json";
                    if($update["text"] == null){
                        $bot->sendMessage(_("Il nome non può essere vuoto"));
                        $bot->sendMessageForceReply(_("Scrivi Nome e Cognome dell'ospite da eliminare:"));
                    }
                    else{
                        $file["Nome"] = $update["text"];
                        file_put_contents($fileName,json_encode($file, JSON_PRETTY_PRINT));
                        $bot->sendCalendar(time(), _("Usa le frecce per selezionare la prima data dell'assenza che vuoi eliminare"),SELECT_DATE_INTERVALL);
                    }

                }
                elseif($update["reply_to_message"]["text"] == _("Scatta una foto del fronte del documento dell'ospite ed inviala:")){

                    if($permission["NewGuest"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    //Prende l'ultimo elemento dell' array associativo che contiene l'immagine alla risoluzione più alta
                    $text = end($update["photo"]);
                    checkNewGuestInput($text["file_id"], $chatID, $keyboard);
                }
                elseif($update["reply_to_message"]["text"] == _("Scatta una foto del retro del documento dell'ospite ed inviala:")){

                    if($permission["NewGuest"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    //Prende l'ultimo elemento dell' array associativo che contiene l'immagine alla risoluzione più alta
                    $text = end($update["photo"]);
                    checkNewGuestInput($text["file_id"], $chatID, $keyboard);
                }
                elseif($update["reply_to_message"]["text"] == _('Invia il nuovo nome:')){
                    if($permission["ChangeNameUser"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $_chatID = file_get_contents(TmpFileUser_path.'changeNameUser.json');
                    $_chatID = json_decode($_chatID, true);
                    $_chatID = $_chatID['ChatID'];
                    $_user = $db->getUser($_chatID);

                    if($db->updateName($_chatID,$update['text'])){

                        $users = $db->getUserList(false);
                        $usersName = [];
                        while($row = $users->fetch_assoc()){
                            $usersName[] = $row['FullName'];
                        }
                        $usersKeyboard = createUserKeyboard($usersName, [[['text' => _('Visualizza utenti disabilitati')]],[['text' => "\u{1F3E1}"]]]);

                        $oldName = preg_replace('/\s+/','_',$_user['FullName']);
                        $newName = preg_replace('/\s+/','_',$update['text']);

                        rename(TMP_FILE_PATH.$oldName,TMP_FILE_PATH.$newName);

                        rename(LOG_FILE_PATH.$oldName,LOG_FILE_PATH.$newName);
                        rename(LOG_FILE_PATH.$newName."/$oldName.log",LOG_FILE_PATH.$newName."/$newName.log");

                        $bot->sendMessage(_('Nome modificato'), $usersKeyboard);

                        sendUser($db->getUser($_chatID), $permission,  TMP_FILE_PATH.$newName.'/messageInLineKeyboard.json');
                    }
                    else{
                        $users = $db->getUserList(false);
                        $usersName = [];
                        while($row = $users->fetch_assoc()){
                            $usersName[] = $row['FullName'];
                        }
                        $usersKeyboard = createUserKeyboard($usersName, [[['text' => _('Visualizza utenti disabilitati')]],[['text' => "\u{1F3E1}"]]]);
                        $bot->sendMessage(_("Non è stato possibile cambiare il nome da ").$_user['FullName']._(" a ").$update['text'], $usersKeyboard);
                    }

                    $file['Type'] = 'SelectUserForEdit';
                    file_put_contents(TmpFileUser_path.'selectUser.json', json_encode($file, JSON_PRETTY_PRINT));

                    unlink(TmpFileUser_path.'changeNameUser.json');
                }
                elseif($update["reply_to_message"]["text"] == _('Eliminando un utente verranno eliminate in automatico anche tutte le sue assenze registrate, i suoi ospiti e verrà rimosso da tutti i gruppi di cui fa parte. Per confermare l\'eliminazione scrivi il seguente codice:')){

                    if($permission["DeleteUser"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $_chatID = file_get_contents(TmpFileUser_path.'deleteUser.json');
                    $_chatID = json_decode($_chatID, true);
                    $_chatID = $_chatID['ChatID'];
                    $_user = $db->getUser($_chatID);

                    $otp = file_get_contents(TmpFileUser_path.'otp.json');
                    $otp = json_decode($otp, true);

                    if($otp['Type'] == 'DeleteUser') {

                        if ($otp['OTP'] == $update['text']) {
                            if($db->deleteUser($_chatID)){
                                $bot->sendMessage($_user['FullName']._(' eliminato'), $keyboard);
                            }
                            else{
                                $bot->sendMessage(_("Non è stato possibile eliminare l'utente"), $keyboard);
                            }
                        }
                        else{
                            $bot->sendMessage(_('Il codice inserito non è valido, l\'utente non verrà eliminato'), $keyboard);
                        }

                    }

                    unlink(TmpFileUser_path.'otp.json');
                    unlink(TmpFileUser_path.'deleteUser.json');
                }
                elseif($update["reply_to_message"]["text"] == _('Invia la nuova frequenza del turno:')){
                    if($permission["EditTypeOfTurn"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    if(filter_var($update["text"], FILTER_VALIDATE_INT) === false or $update['text'] <= 0){
                        $bot->sendMessage(_('Il numero inserito non è valido, deve essere un numero maggiore di 0'));
                        $bot->sendMessageForceReply(_('Invia la nuova frequenza del turno:'));
                        exit;
                    }

                    $_typeOfTurn = file_get_contents(TmpFileUser_path.'editTypeOfTurn.json');
                    $_typeOfTurn = json_decode($_typeOfTurn, true);
                    $_typeOfTurn = $_typeOfTurn['TypeOfTurn'];

                    $typeOfTurnList = $db->getTypeOfTurnList();
                    $_typeOfTurnList = [];
                    while($row = $typeOfTurnList->fetch_assoc()){
                        $_typeOfTurnList[] = $row['Name'];
                    }

                    if($permission['NewTypeOfTurn']){
                        $typeOfTurnKeyboard = createUserKeyboard($_typeOfTurnList,[[['text' => _('Crea nuovo turno')]],[['text' => "\u{1F3E1}"]]]);
                    }
                    else{
                        $typeOfTurnKeyboard = createUserKeyboard($_typeOfTurnList,[[['text' => "\u{1F3E1}"]]]);
                    }

                    if($db->updateTypeOfTurnFrequency($_typeOfTurn,$update['text'])){

                        $bot->sendMessage(_('Frequenza cambiata'), $typeOfTurnKeyboard);

                        sendMessageEditTypeOfTurn($db->getTypeOfTurn($_typeOfTurn), $permission, $messageInLineKeyboardPath);
                    }
                    else{
                        $bot->sendMessage(_("Non è stato possibile cambiare la frequenza"), $typeOfTurnKeyboard);
                    }

                    unlink(TmpFileUser_path.'editTypeOfTurn.json');
                }
                elseif($update["reply_to_message"]["text"] == _('Invia da quanti utenti deve essere composto il gruppo:')){
                    if($permission["EditTypeOfTurn"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    if(filter_var($update["text"], FILTER_VALIDATE_INT) === false or $update['text'] <= 0){
                        $bot->sendMessage(_('Il numero inserito non è valido, deve essere un numero maggiore di 0'));
                        $bot->sendMessageForceReply(_('Invia da quanti utenti deve essere composto il gruppo:'));
                        exit;
                    }

                    $_typeOfTurn = file_get_contents(TmpFileUser_path.'editTypeOfTurn.json');
                    $_typeOfTurn = json_decode($_typeOfTurn, true);
                    $_typeOfTurn = $_typeOfTurn['TypeOfTurn'];

                    $typeOfTurnList = $db->getTypeOfTurnList();
                    $_typeOfTurnList = [];
                    while($row = $typeOfTurnList->fetch_assoc()){
                        $_typeOfTurnList[] = $row['Name'];
                    }

                    if($permission['NewTypeOfTurn']){
                        $typeOfTurnKeyboard = createUserKeyboard($_typeOfTurnList,[[['text' => _('Crea nuovo turno')]],[['text' => "\u{1F3E1}"]]]);
                    }
                    else{
                        $typeOfTurnKeyboard = createUserKeyboard($_typeOfTurnList,[[['text' => "\u{1F3E1}"]]]);
                    }

                    if($db->updateUserByGroup($_typeOfTurn,$update['text'])){

                        $bot->sendMessage(_('Numero di utenti per gruppo modificato'), $typeOfTurnKeyboard);

                        sendMessageEditTypeOfTurn($db->getTypeOfTurn($_typeOfTurn), $permission, $messageInLineKeyboardPath);
                    }
                    else{
                        $bot->sendMessage(_("Non è stato possibile cambiare il numero di utenti per gruppo"), $typeOfTurnKeyboard);
                    }

                    unlink(TmpFileUser_path.'editTypeOfTurn.json');
                }
                elseif($update["reply_to_message"]["text"] == _("Scrivi il nome del nuovo turno da creare:")){
                    if($permission["NewTypeOfTurn"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $file['Name'] = $update['text'];
                    file_put_contents(TmpFileUser_path.'tmpNewTurn.json', json_encode($file, JSON_PRETTY_PRINT));

                    $bot->sendMessageForceReply(_("Scrivi ogni quanti giorni deve essere eseguito il turno:"));
                }
                elseif($update["reply_to_message"]["text"] == _("Scrivi ogni quanti giorni deve essere eseguito il turno:")){
                    if($permission["NewTypeOfTurn"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    if(filter_var($update["text"], FILTER_VALIDATE_INT) === false){
                        $bot->sendMessage(_('Il numero inserito non è valido'));
                        $bot->sendMessageForceReply(_("Scrivi ogni quanti giorni deve essere eseguito il turno:"));
                        exit;
                    }

                    $file = file_get_contents(TmpFileUser_path.'tmpNewTurn.json');
                    $file = json_decode($file, true);
                    $file['Frequency'] = $update['text'];
                    file_put_contents(TmpFileUser_path.'tmpNewTurn.json', json_encode($file, JSON_PRETTY_PRINT));

                    $bot->sendMessageForceReply(_("Scrivi quante volte consecutivamente un gruppo deve eseguire il turno:"));
                }
                elseif($update["reply_to_message"]["text"] == _("Scrivi quante volte consecutivamente un gruppo deve eseguire il turno:")){
                    if($permission["NewTypeOfTurn"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    if(filter_var($update["text"], FILTER_VALIDATE_INT) === false){
                        $bot->sendMessage(_('Il numero inserito non è valido'));
                        $bot->sendMessageForceReply(_("Scrivi quante volte consecutivamente un gruppo deve eseguire il turno:"));
                        exit;
                    }

                    $file = file_get_contents(TmpFileUser_path.'tmpNewTurn.json');
                    $file = json_decode($file, true);
                    $file['GroupFrequency'] = $update['text'];
                    file_put_contents(TmpFileUser_path.'tmpNewTurn.json', json_encode($file, JSON_PRETTY_PRINT));

                    $bot->sendMessageForceReply(_("Scrivi da quanti utenti deve indicativamente essere composto un gruppo:"));
                }
                elseif($update["reply_to_message"]["text"] == _("Scrivi da quanti utenti deve indicativamente essere composto un gruppo:")){
                    if($permission["NewTypeOfTurn"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    if(filter_var($update["text"], FILTER_VALIDATE_INT) === false){
                        $bot->sendMessage(_('Il numero inserito non è valido'));
                        $bot->sendMessageForceReply(_("Scrivi ogni quanti giorni deve essere eseguito il turno:"));
                        exit;
                    }

                    $file = file_get_contents(TmpFileUser_path.'tmpNewTurn.json');
                    $file = json_decode($file, true);
                    $file['UsersByGroup'] = $update['text'];
                    file_put_contents(TmpFileUser_path.'tmpNewTurn.json', json_encode($file, JSON_PRETTY_PRINT));

                    $path = TmpFileUser_path."calendar.json";
                    $type['Type'] = 'NewTurn';
                    file_put_contents($path,json_encode($type));

                    $bot->sendCalendar(time(), _("Usa le frecce per selezionare la data in cui il turno inizierà ad essere eseguito"));
                }
                elseif($update["reply_to_message"]["text"] == _('Scrivi il seguente codice per confermare:')){
                    if($permission["RearrangeGroups"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $otp = file_get_contents(TmpFileUser_path.'otp.json');
                    $otp = json_decode($otp, true);

                    if($otp['Type'] == 'RearrangeGroups'){

                        if( $otp['OTP'] == $update['text']){

                            $usersList = $db->query(' SELECT U.ChatID
                                                        FROM User U INNER JOIN AccountType A_T ON U.AccountType = A_T.Name
                                                        INNER JOIN Permission P ON A_T.Permission = P.Name
                                                        LEFT JOIN ( 
                                                            SELECT * FROM Absence A WHERE A.LeavingDate >= CURRENT_DATE()
                                                        ) AS D ON U.ChatID = D.User 
                                                        WHERE D.User IS NULL AND 
                                                              U.Enabled IS TRUE AND
                                                              P.PostedInGroup IS TRUE
                                                        ORDER BY U.Room;');
                            $users = [];
                            while($row = $usersList->fetch_assoc()){
                                $users[$row['ChatID']] = 0;
                            }

                            $db->query('DELETE FROM Execution WHERE 1;');
                            $db->query('DELETE FROM Member WHERE 1;');
                            $db->query('DELETE FROM Squad WHERE 1;');

                            $typeOfTurnList = $db->query('SELECT T.UsersBySquad, GROUP_CONCAT(T.Name) AS TypeOfTurns FROM TypeOfTurn T WHERE 1 GROUP BY T.UsersBySquad;');
                            while($row = $typeOfTurnList->fetch_assoc()){

                                $typeOfTurn = explode(',', $row['TypeOfTurns']);
                                //Ordina per ultima esecuzione probabilmente non serve
                                /*
                                    foreach(array_keys($users) as $key){
                                    $lastExecution = 0;
                                    foreach($typeOfTurn as $t){
                                        $lastExecution = max([$lastExecution, $db->getLastExecution($db->getUser($key)['FullName'], $t)]);
                                    }
                                    $users[$key] = $lastExecution;
                                }
                                arsort($users);

                                 */

                                $userInGroup = 0;
                                $groupNum = 1;
                                $numOfGroups = 1;
                                foreach (array_keys($users) as $key){

                                    //Controllare se non ci sono abbastanza utenti per fare un gruppo completo
                                    if( $userInGroup == 0 and (sizeof($users) - array_search($key, array_keys($users)) ) <= ($row['UsersBySquad']*0.5) ){
                                        if(sizeof($typeOfTurn) > 1){
                                            $db->insertUserInGroup($key, implode($typeOfTurn).' '.$groupNum);
                                        }
                                        else{
                                            $db->insertUserInGroup($key, "$typeOfTurn[0] $groupNum");
                                        }

                                        if($groupNum == $numOfGroups){
                                            $groupNum = 1;
                                        }
                                        else{
                                            $groupNum++;
                                        }
                                    }
                                    else{
                                        if($userInGroup == 0){

                                            $db->createGroup(implode($typeOfTurn).' '.$numOfGroups);
                                            $db->insertUserInGroup($key, implode($typeOfTurn).' '.$numOfGroups);
                                            foreach ($typeOfTurn as $t){
                                                $typeOfTurnGroupFrequency = $db->getTypeOfTurn($t)['SquadFrequency'];
                                                for($i = $typeOfTurnGroupFrequency-1; $i>=0; $i-- ){
                                                    $db->addExecution($t, implode($typeOfTurn).' '.$numOfGroups, ($numOfGroups*$typeOfTurnGroupFrequency)-1-$i);
                                                }
                                            }
                                        }
                                        else{
                                            if(sizeof($typeOfTurn) > 1){
                                                $db->insertUserInGroup($key, implode($typeOfTurn).' '.$numOfGroups);
                                            }
                                            else{
                                                $db->insertUserInGroup($key, "$typeOfTurn[0] $numOfGroups");
                                            }
                                        }

                                        $userInGroup++;

                                        if($userInGroup == $row['UsersBySquad']){
                                            $userInGroup = 0;
                                            $numOfGroups++;
                                        }
                                    }
                                }
                            }

                            $bot->sendMessage("Ecco i nuovi gruppi");

                            $groupList = $db->getGroupList();
                            $rowNumber = sizeof($groupList);
                            $i=0;
                            foreach (array_keys($groupList) as $key){
                                $i++;
                                $userInGroup = $db->getUserInGroup($key);
                                if($i == $rowNumber){
                                    $bot->sendMessage(userInGroup($key,$userInGroup),$keyboard);
                                }
                                else{
                                    $bot->sendMessage(userInGroup($key,$userInGroup));
                                }
                            }

                            $myChatID = $chatID;
                            foreach (array_keys($users) as $key){
                                if($key !== $myChatID){
                                    $bot->setChatID($key);
                                    $bot->sendMessage( _('Il calendario dei turni ed i gruppi sono stati aggiornati, ora fai parte dei seguenti gruppi:') );

                                    $groups = $db->getGroupsByUser($key);

                                    $msg = '';
                                    foreach(array_keys($groups) as $group){
                                        $userInGroup = $db->getUserInGroup($group);

                                        $msg .= "- - - - - - - - - - ".$group." - - - - - - - - - - \n";

                                        foreach($userInGroup as $user){

                                            $msg .= $user["FullName"];

                                            if(!empty($user["Username"])){
                                                $msg .= " - @".$user["Username"].PHP_EOL;
                                            }
                                            else{
                                                $msg .= PHP_EOL;
                                            }
                                        }

                                        $msg .= PHP_EOL;
                                    }

                                    $bot->sendMessage($msg);
                                }
                            }
                        }
                        else{
                            $bot->sendMessage(_('Il codice inserito non è valido, i gruppi non verranno riorganizzati'));
                        }

                    }

                    unlink(TmpFileUser_path.'otp.json');
                }
                elseif($update["reply_to_message"]["text"] == _("Descrivi il problema:")){
                    if($permission["ReportsMaintenance"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    if($db->reportsMaintenance($chatID, $update["text"])){
                        $bot->sendMessage(_("Segnalazione inviata"), $keyboard);

                        $users = $db->getAllUsersForNotification('NewReport');

                        $msg = "- - - - - "._("Nuova segnalazione")." - - - - -\n";
                        $msg.= _("Segnalato da: ").$user['FullName'].PHP_EOL.PHP_EOL;
                        $msg.= $update["text"].PHP_EOL;
                        $msg.= "- - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";

                        while($row = $users->fetch_assoc()){
                            $bot->setChatID($row['ChatID']);
                            $bot->sendMessage($msg);
                        }
                    }
                    else{
                        $bot->sendMessage(_("Si è verificato un errore nella registrazione della segnalazione"), $keyboard);
                    }
                }
                elseif($update["text"] == _("Linee guida")." \u{1F4D6}") { //invia il pdf con le linee guida
                    if(file_exists(FILES_PATH."/Linee_guida.pdf")){

                        $bot->sendDocument(FILES_PATH.'Linee_guida.pdf');

                    }else{
                        $bot->sendMessage("Il file delle linee guida non è disponibile");
                    }
                }
                elseif($update["text"] == _("Lista assenti")." \u{1F4CB}"){

                    if($permission["AbsenceList"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $path = TmpFileUser_path."calendar.json";
                    $type['Type'] = 'AbsenceList';
                    file_put_contents($path,json_encode($type));
                    $bot->sendCalendar(time(),_("Seleziona il giorno per cui vuoi visualizzare le assenze"),SELECT_DATE_INTERVALL);
                }
                elseif($update["text"] == _("Lista ospiti")." \u{1F4CB}"){

                    if($permission["GuestList"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $path = TmpFileUser_path."calendar.json";
                    $type['Type'] = 'GuestList';
                    file_put_contents($path,json_encode($type));
                    $bot->sendCalendar(time(),_("Seleziona il giorno per cui vuoi visualizzare gli ospiti"),SELECT_DATE_INTERVALL);
                }
                elseif($update["text"] == _("Lista utenti")." \u{1F4CB}"){

                    if($permission["UserList"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $users = $db->getUserList(false);

                    if($users === false){
                        $bot->sendMessage(_("Non è stato possibile recuperare la lista degli utenti"));
                    }
                    else{

                        sendUserList($db->getUserList(false));

                        $usersName = [];
                        while($row = $users->fetch_assoc()){
                            $usersName[] = $row['FullName'];
                        }

                        if($permission['ChangeUserState'] or $permission['DeleteUser'] or $permission['ChangeNameUser'] or $permission['ChangeUserRoom']){
                            $file['Type'] = 'SelectUserForEdit';

                            file_put_contents(TmpFileUser_path.'selectUser.json', json_encode($file, JSON_PRETTY_PRINT));

                            $usersKeyboard = createUserKeyboard($usersName, [[['text' => _('Visualizza utenti disabilitati')]],[['text' => "\u{1F3E1}"]]]);
                            $bot->sendMessage(_('Seleziona un utente per modificarlo:'), $usersKeyboard);
                        }
                    }
                }
                elseif($update["text"] == _("Lista camere")." \u{1F4CB}"){

                    if($permission["ChangeUserRoom"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $rooms = $db->query('SELECT R.Num, R.Beds AS TotalBeds, COUNT(U.ChatID) AS OccupiedBeds FROM Room R LEFT JOIN User U ON R.Num = U.Room AND U.Enabled IS TRUE GROUP BY R.Num;');

                    $msg = '- - - - - - '._('Lista Camere').' - - - - - -'.PHP_EOL;
                    $_rooms = [];
                    while($row = $rooms->fetch_assoc()){
                        $_rooms[] = $row['Num'];
                        $msg .= _('Camera ').$row['Num'].': '._('Posti occupati').' '.$row['OccupiedBeds'].'/'.$row['TotalBeds'].PHP_EOL;
                    }

                    if($permission["NewRoom"]){
                        $roomsKeyboard = createUserKeyboard($_rooms,[[['text' => _('Aggiungi nuova camera')]], [['text' => "\u{1F3E1}"]]]);
                    }
                    else{
                        $roomsKeyboard = createUserKeyboard($_rooms,[[['text' => "\u{1F3E1}"]]]);
                    }

                    $file['Type'] = 'SeeUserInRoom';
                    file_put_contents(TmpFileUser_path.'selectRoom.json', json_encode($file, JSON_PRETTY_PRINT));

                    $bot->sendMessage($msg);
                    $bot->sendMessage(_('Seleziona una camera per vederne i membri:'), $roomsKeyboard);
                }
                elseif($update["text"] == _("Aggiungi nuova camera")){

                    if($permission["NewRoom"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $bot->sendMessageForceReply(_('Invia il numero di posti della camera:'));
                }
                elseif($update["text"] == _("Visualizza utenti disabilitati")){
                    $disabledUser = $db->query('SELECT * FROM User U WHERE U.Enabled IS FALSE;');

                    if($disabledUser->num_rows == 0){
                        $bot->sendMessage(_('Nessun utente disabilitato'));
                        exit;
                    }

                    $msg = "- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";

                    $usersName = [];
                    while($row = $disabledUser->fetch_assoc()){
                        $usersName[] = $row['FullName'];

                        $msg .= $row["FullName"];

                        if(!empty($row["Username"]) ){
                            $msg .= " - @".$row["Username"];
                        }

                        if(!empty($row['Room'])){
                            $msg .= _(" - Camera ").$row["Room"];
                        }

                        if($row['Type'] == 'group'){
                            $msg .= _(" - Gruppo ").PHP_EOL;
                        }
                        elseif($row['Type'] == 'private'){
                            $msg .= _(" - Privato ").PHP_EOL;
                        }
                        elseif($row['Type'] == 'channel'){
                            $msg .= _(" - Canale ").PHP_EOL;
                        }
                        elseif($row['Type'] == 'supergroup'){
                            $msg .= _(" - Super-gruppo ").PHP_EOL;
                        }

                        $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                    }
                    $bot->sendMessage($msg);
                }
                elseif($update["text"] == _("Tutti i gruppi")){
                    $groupList = $db->getGroupList();

                    $rowNumber = sizeof($groupList);
                    $i=0;
                    foreach ($groupList as $key => $value){
                        $i++;
                        $userList = $db->getUserInGroup($key);
                        if($i == $rowNumber){
                            $bot->sendMessage(userInGroup($key,$userList),$keyboard);
                        }
                        else{
                            $bot->sendMessage(userInGroup($key,$userList));
                        }
                    }
                }
                elseif($update["text"] == _("Modifica email")." \u{1F4E7}"){

                    if($permission["ChangeEmail"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    if(empty($user["Email"])){
                        $bot->sendMessage(_("Attualmente non hai impostata nessuna email"));
                    }else{
                        $bot->sendMessage(_("L'e-mail attualmente impostata è ").$user["Email"]);
                    }
                    $bot->sendMessageForceReply(_("Invia la tua nuova email:"));
                }
                elseif($update["text"] == _("Assenza")." \u{1F44B}") {

                    if($permission["NewAbsence"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $path = TmpFileUser_path."calendar.json";
                    $type['Type'] = 'NewAbsence';
                    file_put_contents($path,json_encode($type));

                    $bot->sendCalendar(time(), _("Usa le frecce per selezionare la data di partenza"),SELECT_DATE_INTERVALL);
                }
                elseif($update["text"] == _("Ospite")." \u{1F6CF}") {

                    if($permission["NewGuest"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    if(is_null($user['Room'])){
                        $bot->sendMessage(_('Per poter registrare un ospite devi prima essere assegnato ad una camera'));
                        exit;
                    }

                    //file temporaneo contenente i dati dell'ospite da inserire
                    $fileName = TmpFileUser_path."tmpGuest.json";

                    //Lettura dei dati dell'ospite dal file;
                    $ospite = file_get_contents($fileName);

                    //Se non è in corso la registrazione di un altro ospite
                    if (($ospite === false) || (strpos($ospite, "null") !== false)) {
                        $file['ChatID'] = $chatID;
                        $file['Name'] = null;
                        $file['CheckInDate'] = null;
                        $file['LeavingDate'] = null;
                        $file['RegistrationDate'] = date("Y-m-d h:i:sa");
                        $file['FrontDocument'] = null;
                        $file['BackDocument'] = null;
                        $file['UserInRoom'] = $db->getUserInRoom($user["Room"])->num_rows;
                        $file['UsersWhoHaveAccepted'] = 1;
                        file_put_contents($fileName, json_encode($file, JSON_PRETTY_PRINT));
                        $bot->sendMessage(_("ATTENZIONE: un ospite va CONFERMATO E REGISTRATO almeno 72 ore prima dell'arrivo in struttura, la prima data utile che hai a disposizione è il").' '.strftime('%d %h %Y', strtotime('+3 days')));
                        $bot->sendMessageForceReply(_("Scrivi Nome e Cognome dell'ospite da registrare:"));
                    }
                    else{
                        $bot->sendMessage(_("È in corso la registrazione di un altro ospite, attendi che sia confermato per poterne registrare un altro"));
                    }
                }
                elseif($update["text"] == _("Gruppi")." \u{1F4CB}"){

                    if($permission["GroupList"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $msg = _("Gruppi di cui fai parte: ".PHP_EOL);

                    $groups = $db->getGroupsByUser($chatID);

                    if(empty($groups)){
                        $msg .= _('Nessuno');
                    }

                    foreach (array_keys($groups) as $key){
                        $msg .= $key.PHP_EOL;
                    }
                    $bot->sendMessage($msg);

                    $groupList = $db->getGroupList();

                    if($groupList === false){
                        $bot->sendMessage(_("Non è stato possibile recuperare la lista dei gruppi"));
                    }
                    elseif(sizeof($groupList) == 0) {
                        if($permission['NewGroup']){
                            $groupKeyboard = createUserKeyboard(null, [ [ ['text' => _("Nuovo gruppo")] ], [['text' => "\u{1F3E1}"]] ]);
                            $bot->sendMessage(_("Non esiste nessun gruppo"), $groupKeyboard);
                        }
                        else{
                            $bot->sendMessage(_("Non esiste nessun gruppo"));
                        }
                    }
                    else{
                        if($permission['NewGroup']){
                            $groupKeyboard = createUserKeyboard(array_keys($groupList),[ [['text' => _("Tutti i gruppi")]], [['text' => _("Nuovo gruppo")]], [['text' => "\u{1F3E1}"]] ]);
                        }
                        else{
                            $groupKeyboard = createUserKeyboard(array_keys($groupList),[ [['text' => _("Tutti i gruppi")]], [['text' => "\u{1F3E1}"]] ]);
                        }

                        $file['Type'] = 'viewUserInGroup';
                        file_put_contents(TmpFileUser_path.'selectGroup.json', json_encode($file, JSON_PRETTY_PRINT));

                        $bot->sendMessage(_("Seleziona un gruppo per vederne i membri:"), $groupKeyboard);
                    }
                }
                elseif($update["text"] == _("Nuovo gruppo")){

                    if($permission["NewGroup"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $bot->sendMessage('Da fare');

                }
                elseif($update["text"] == _("Le mie assenze")." \u{1F4CB}"){

                    if($permission["MyAbsenceList"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $msg = "- - - - - - - - Le tue assenze - - - - - - - -"."\n\n";

                    $absentsList = $db->getMyAbsence($chatID);

                    if($absentsList->num_rows == 0){
                        $msg .= _("Nessuna assenza registrata").PHP_EOL;
                        $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                        $bot->sendMessage($msg);
                    }
                    else{
                        $messageID = 0;
                        while($row = $absentsList->fetch_assoc()){
                            $leavingDate = strtotime($row["LeavingDate"]);
                            $returnDate = strtotime($row["ReturnDate"]);

                            if($permission["DeleteAbsence"] == false and $permission['UpdateAbsence'] == false){
                                $msg .= _("Dal ").strftime('%d %h %Y',$leavingDate)._(" al ").strftime('%d %h %Y',$returnDate).PHP_EOL;
                                $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                            }
                            else{
                                $msg = "- - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                                $msg .= _("Dal ").strftime('%d %h %Y',$leavingDate)._(" al ").strftime('%d %h %Y',$returnDate).PHP_EOL;
                                $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";

                                $keyboardAbsence = [];

                                if($permission['UpdateAbsence']){
                                    $keyboardAbsence[] = ['text' => _("Modifica")." \u{270F}", 'callback_data' => "updateAbsence-$leavingDate-$returnDate"];
                                }

                                if($permission['DeleteAbsence']){
                                    $keyboardAbsence[] = ['text' => _('Elimina')." \u{274C}", 'callback_data' => "deleteAbsence-$leavingDate-$returnDate"];
                                }

                                $keyboardAbsence = json_encode(['inline_keyboard'=> [$keyboardAbsence]],JSON_PRETTY_PRINT);

                                if($returnDate < time()){
                                    $bot->sendMessage($msg);
                                }
                                else{
                                    $msgResult = json_decode($bot->sendMessage($msg,$keyboardAbsence),true);

                                    $messageInLineKeyboard = file_get_contents($messageInLineKeyboardPath);
                                    $messageInLineKeyboard = json_decode($messageInLineKeyboard, true);
                                    $messageInLineKeyboard[$msgResult["result"]["message_id"]] = $msg;
                                    file_put_contents($messageInLineKeyboardPath ,json_encode($messageInLineKeyboard, JSON_PRETTY_PRINT));
                                }
                            }
                        }

                        if($permission["DeleteAbsence"] == false and $permission["UpdateAbsence"] == false){
                            $bot->sendMessage($msg);
                        }
                    }
                }
                elseif($update["text"] == _("I miei ospiti")." \u{1F4CB}"){

                    if($permission["MyGuestList"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $msg = "- - - - - - - - - I tuoi ospiti - - - - - - - - -"."\n\n";

                    $guestList = $db->getMyGuest($chatID);

                    if($guestList->num_rows == 0){
                        $msg .= _("Nessun ospite registrato").PHP_EOL;
                        $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                        $bot->sendMessage($msg);
                    }
                    else{
                        while($row = $guestList->fetch_assoc()){
                            $checkInDate = strtotime($row["CheckInDate"]);
                            $leavingDate = strtotime($row["LeavingDate"]);

                            if(($permission["DeleteGuest"] and $permission["UpdateGuest"]) == false){
                                $msg .= $row["Name"]._(" dal ").strftime('%e %h %Y', $checkInDate)._(" al ").strftime('%e %h %Y', $leavingDate).PHP_EOL;
                                $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                            }
                            else{
                                sendMessageEditGuest($row, $permission, $messageInLineKeyboardPath);
                            }
                        }

                        if($permission["DeleteGuest"] == false){
                            $bot->sendMessage($msg);
                        }
                    }
                }
                elseif($update["text"] == _("Turni")." \u{1F9F9}"){
                    $typeOfTurnList = $db->getTypeOfTurnList();

                    if($typeOfTurnList->num_rows === 0){
                        $bot->sendMessage(_('Nessun turno in programma'));
                        exit;
                    }

                    $_typeOfTurnList = [];
                    while($row = $typeOfTurnList->fetch_assoc()){
                        if($row['Frequency'] == 0 or $db->getStepNumOfTurn($row['Name']) == 0){
                            continue;
                        }

                        $_typeOfTurnList[] = $row['Name'];
                    }

                    $typeOfTurnKeyboard = createUserKeyboard($_typeOfTurnList,[[['text' => "\u{1F3E1}"]]]);

                    $bot->sendMessage(_('Seleziona un turno per visualizzarlo'), $typeOfTurnKeyboard);

                    $file['Type'] = 'Show';
                    file_put_contents(TmpFileUser_path.'selectTypeOfTurn.json', json_encode($file, JSON_PRETTY_PRINT));
                }
                elseif($update["text"] == _("Carica Linee guida")." \u{1F4D6}"){

                    if($permission["NewGuideLine"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $bot->sendMessageForceReply(_("Invia il file con le nuove linee guida:"));
                }
                elseif($update["text"] == _("Esporta")." \u{1F4DD}"){

                    if(($permission["ExportUserList"] or $permission["ExportGuest"] or $permission["ExportAbsence"]) == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $exportKeyboard = createPermissionKeyboard($permission,EXPORT_KEYBOARD_TEXT);
                    $exportKeyboard[] = [['text' => "\u{1F3E1}"]];
                    $exportKeyboard = json_encode(['keyboard'=>  $exportKeyboard, 'resize_keyboard'=> true] ,JSON_PRETTY_PRINT);

                    $bot->sendMessage(_("Scegli cosa vuoi esportare"), $exportKeyboard);
                }
                elseif($update["text"] == _("Lista utenti")){
                    if($permission["ExportUserList"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    if(empty($user["Email"])){
                        $bot->sendMessage(_("Imposta un email per poter ricevere il file"), $keyboard);
                        exit;
                    }

                    $bot->sendMessage("Attendi qualche istante...");

                    $userList = $db->getUserList(false);
                    $output = '"Nome";';
                    $output.= '"Username telegram";';
                    $output.= '"Email";';
                    $output.= '"Data registrazione";';
                    $output.= '"Camera";';
                    $output.= '"Stato";';
                    $output.="\n";

                    while($row = $userList->fetch_assoc()){
                        $output.='"'.$row['FullName'].'";';

                        if(empty($row['Username'])){
                            $output.='"(vuoto)";';
                        }
                        else{
                            $output.='"@'.$row['Username'].'";';
                        }

                        if(empty($row['Email'])){
                            $output.='"(vuoto)";';
                        }
                        else{
                            $output.='"'.$row['Email'].'";';
                        }

                        $output.='"'.$row['InscriptionDate'].'";';
                        $output.='"'.$row['Room'].'";';


                        if($row['Enabled'] == 0){
                            $output.='"Disabilitato";';
                        }
                        else{
                            $output.='"Abilitato";';
                        }
                        $output.="\n";
                    }

                    $filePath = FILES_PATH.'export_archive/user_list/'.date('Y');
                    if (!is_dir($filePath)){
                        mkdir($filePath,0755, true);
                    }

                    file_put_contents("$filePath/Lista_utenti_".date('d_m_Y').".csv",$output);

                    $from = 'giuliettobot@casadellostudentevillagiulia.it';
                    $fromName = 'GiuliettoBot';
                    $subject = _("Lista utenti registrati al ").date('d-m-Y');
                    $file[0] = "$filePath/Lista_utenti_".date('d_m_Y').".csv";

                    if (!email($user["Email"], $from, $fromName, $subject, '', $file)) {
                        $bot->sendMessage(_("Si è verificato un errore nell'inviare l'email"), $keyboard);
                    }
                    else{
                        $bot->sendMessage(_("Email inviata correttamente, controlla la tua casella di posta elettronica per consultare il file"), $keyboard);
                    }
                }
                elseif($update["text"] == _("Ospiti")){
                    if($permission["ExportGuest"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    if(empty($user["Email"])){
                        $bot->sendMessage(_("Imposta un email per poter ricevere il file"), $keyboard);
                        exit;
                    }

                    $bot->sendMessage("Attendi qualche istante...");

                    $userList = $db->getGuestReport();
                    $output = '"Nome utente";';
                    $output.= '"Username telegram";';
                    $output.= '"Email";';
                    $output.= '"Camera utente";';
                    $output.= '"Nome Ospite";';
                    $output.= '"Data arrivo";';
                    $output.= '"Data partenza";';
                    $output.= '"Camera";';
                    $output.= '"Data registrazione";';
                    $output.="\n";

                    while($row = $userList->fetch_assoc()){
                        $output.='"'.$row['FullName'].'";';

                        if(empty($row['Username'])){
                            $output.='"(vuoto)";';
                        }
                        else{
                            $output.='"@'.$row['Username'].'";';
                        }

                        if(empty($row['Email'])){
                            $output.='"(vuoto)";';
                        }
                        else{
                            $output.='"'.$row['Email'].'";';
                        }

                        $output.='"'.$row['UserRoom'].'";';
                        $output.='"'.$row['Name'].'";';
                        $output.='"'.$row['CheckInDate'].'";';
                        $output.='"'.$row['LeavingDate'].'";';
                        $output.='"'.$row['Room'].'";';
                        $output.='"'.$row['RegistrationDate'].'";';
                        $output.="\n";
                    }

                    $filePath = FILES_PATH.'export_archive/guest/'.date('Y');
                    if (!is_dir($filePath)){
                        mkdir($filePath,0755, true);
                    }

                    file_put_contents("$filePath/Lista_ospiti_".date('d_m_Y').".csv",$output);

                    $from = 'giuliettobot@casadellostudentevillagiulia.it';
                    $fromName = 'GiuliettoBot';
                    $subject = _("Lista ospiti registrati al ").date('d-m-Y');
                    $file[0] = "$filePath/Lista_ospiti_".date('d_m_Y').".csv";

                    if (!email($user["Email"], $from, $fromName, $subject, '', $file)) {
                        $bot->sendMessage(_("Si è verificato un errore nell'inviare l'email"), $keyboard);
                    }
                    else{
                        $bot->sendMessage(_("Email inviata correttamente, controlla la tua casella di posta elettronica per consultare il file"), $keyboard);
                    }
                }
                elseif($update["text"] == _("Assenze")){
                    if($permission["ExportAbsence"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    if(empty($user["Email"])){
                        $bot->sendMessage(_("Imposta un email per poter ricevere il file"), $keyboard);
                        exit;
                    }

                    $bot->sendMessage("Attendi qualche istante...");

                    $userList = $db->getAbsenceReport();
                    $output = '"Nome";';
                    $output.= '"Username telegram";';
                    $output.= '"Email";';
                    $output.= '"Camera";';
                    $output.= '"Data partenza";';
                    $output.= '"Data ritorno";';
                    $output.="\n";

                    while($row = $userList->fetch_assoc()){
                        $output.='"'.$row['FullName'].'";';

                        if(empty($row['Username'])){
                            $output.='"(vuoto)";';
                        }
                        else{
                            $output.='"@'.$row['Username'].'";';
                        }

                        if(empty($row['Email'])){
                            $output.='"(vuoto)";';
                        }
                        else{
                            $output.='"'.$row['Email'].'";';
                        }

                        $output.='"'.$row['Room'].'";';
                        $output.='"'.$row['LeavingDate'].'";';
                        $output.='"'.$row['ReturnDate'].'";';
                        $output.="\n";
                    }

                    $filePath = FILES_PATH.'export_archive/absence/'.date('Y');
                    if (!is_dir($filePath)){
                        mkdir($filePath,0755, true);
                    }

                    file_put_contents("$filePath/Lista_assenze_".date('d_m_Y').".csv",$output);

                    $from = 'giuliettobot@casadellostudentevillagiulia.it';
                    $fromName = 'GiuliettoBot';
                    $subject = _("Lista assenze registrate al ").date('d-m-Y');
                    $file[0] = "$filePath/Lista_assenze_".date('d_m_Y').".csv";

                    if (!email($user["Email"], $from, $fromName, $subject, '', $file)) {
                        $bot->sendMessage(_("Si è verificato un errore nell'inviare l'email"), $keyboard);
                    }
                    else{
                        $bot->sendMessage(_("Email inviata correttamente, controlla la tua casella di posta elettronica per consultare il file"), $keyboard);
                    }

                }
                elseif($update["text"] == _("Calendario turni")." \u{1F5D3}"){
                    if($permission["TurnCalendar"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $typeOfTurnList = $db->getTypeOfTurnList();

                    if(empty($typeOfTurnList)){
                        $bot->sendMessage("Nessun turno in programma");
                        exit;
                    }

                    $_typeOfTurnList = [];
                    while($row = $typeOfTurnList->fetch_assoc()){
                        if ($db->getStepNumOfTurn($row['Name']) == 0) {
                            continue;
                        }

                        $_typeOfTurnList[] = $row['Name'];
                    }

                    $typeOfTurnKeyboard = createUserKeyboard($_typeOfTurnList,[[['text' => "\u{1F3E1}"]]]);

                    $bot->sendMessage(_('Seleziona un turno per visualizzarne il calendario'), $typeOfTurnKeyboard);

                    $file['Type'] = 'ShowCalendar';
                    file_put_contents(TmpFileUser_path.'selectTypeOfTurn.json', json_encode($file, JSON_PRETTY_PRINT));
                }
                elseif($update["text"] == _("Tipi turno")." \u{1F4CB}"){
                    if($permission["TypeOfTurnList"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $typeOfTurnList = $db->getTypeOfTurnList();

                    if(empty($typeOfTurnList)){
                        $bot->sendMessage(_('Non risulta registrato nessun turno'));
                        exit;
                    }

                    $_typeOfTurnList = [];
                    while($row = $typeOfTurnList->fetch_assoc()){
                        $_typeOfTurnList[] = $row['Name'];
                    }

                    if($permission['NewTypeOfTurn']){
                        $typeOfTurnKeyboard = createUserKeyboard($_typeOfTurnList,[[['text' => _('Crea nuovo turno')]],[['text' => "\u{1F3E1}"]]]);
                    }
                    else{
                        $typeOfTurnKeyboard = createUserKeyboard($_typeOfTurnList,[[['text' => "\u{1F3E1}"]]]);
                    }

                    $bot->sendMessage(_('Seleziona un turno per visualizzarlo e modificarlo'), $typeOfTurnKeyboard);

                    $file['Type'] = 'Edit';
                    file_put_contents(TmpFileUser_path.'selectTypeOfTurn.json', json_encode($file, JSON_PRETTY_PRINT));
                }
                elseif($update["text"] == _('Crea nuovo turno')){
                    if($permission["NewTypeOfTurn"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $bot->sendMessageForceReply(_("Scrivi il nome del nuovo turno da creare:"));
                }
                elseif($update["text"] == _("Scambia gruppo")." \u{1F503}"){
                    if($permission["SwapGroup"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $file['Type'] = 'SelectUserForSwapTurn';
                    file_put_contents(TmpFileUser_path.'selectUser.json', json_encode($file, JSON_PRETTY_PRINT));

                    $userList = [];
                    $users = $db->getUserList();
                    while($row = $users->fetch_assoc()){
                        $_permission = $db->getPermission($row['AccountType']);
                        if($row['ChatID'] != $chatID and $_permission['PostedInGroup']){
                            $userList[$row['ChatID']] = $row['FullName'];
                        }
                    }

                    if(empty($userList)){
                        $bot->sendMessage(_('Nessun utente disponibile'));
                    }
                    else{
                        $userKeyboard = createUserKeyboard($userList,[[['text' => "\u{1F3E1}"]]]);
                        $bot->sendMessage(_("Con chi vuoi fare a scambio di gruppo?"), $userKeyboard);
                    }
                }
                elseif($update["text"] == _("Riorganizza Gruppi")." \u{1F500}"){
                    if($permission["RearrangeGroups"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $bot->sendMessage("No");

                    /*
                    $otp['Type'] = 'RearrangeGroups';
                    $otp['OTP'] = rand(1000,9999);
                    file_put_contents(TmpFileUser_path.'otp.json', json_encode($otp, JSON_PRETTY_PRINT));

                    $bot->sendMessageForceReply(_('Scrivi il seguente codice per confermare:'));
                    $bot->sendMessage($otp['OTP']);*/
                }
                elseif($update["text"] == _("Segnala")." \u{1F6A8}"){
                    if($permission["ReportsMaintenance"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $bot->sendMessageForceReply(_("Descrivi il problema:"));
                }
                elseif($update["text"] == _("Manutenzioni")." \u{1F6E0}"){
                    if($permission["ManageMaintenance"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $bot->sendMessage(_("Quali manutenzioni vuoi visualizzare?"), createUserKeyboard([_("Da risolvere"), _("Risolte")],[[['text' => "\u{1F3E1}"]]]));
                }
                elseif($update["text"] == _("Da risolvere")){
                    $reportsToDo = $db->getReport();

                    if($reportsToDo->num_rows == 0){
                        $bot->sendMessage(_("Nessuna manutenzione da fare"));
                        exit;
                    }

                    $messageInLineKeyboard = file_get_contents($messageInLineKeyboardPath);
                    $messageInLineKeyboard = json_decode($messageInLineKeyboard, true);

                    while($row = $reportsToDo->fetch_assoc()){

                        $keyboardReport = [];

                        $msg = "- - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                        $msg.= _("Segnalato da: ").$db->getUser($row['WhoReports'])['FullName'].PHP_EOL;
                        $msg.= _("Data segnalazione: ").date('d-m-Y G:i:s', strtotime($row['ReportsDateTime'])).PHP_EOL.PHP_EOL;
                        $msg.= $row['Description'].PHP_EOL;
                        $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";

                        $keyboardReport[] = ['text' => _('Elimina')." \u{274C}", 'callback_data' => "deleteReport-".$row['ID']];
                        $keyboardReport[] = ['text' => _('Risolta')." \u{2714}", 'callback_data' => "resolved-".$row['ID']];
                        $keyboardReport = json_encode(['inline_keyboard'=> [$keyboardReport] ],JSON_PRETTY_PRINT);

                        $msgResult = json_decode($bot->sendMessage($msg, $keyboardReport), true);

                        $messageInLineKeyboard[$msgResult["result"]["message_id"]] = $msg;
                        file_put_contents($messageInLineKeyboardPath ,json_encode($messageInLineKeyboard, JSON_PRETTY_PRINT));
                    }
                }
                elseif($update["text"] == _("Risolte")){
                    $reportsSolved = $db->getReport(true);

                    if($reportsSolved->num_rows == 0){
                        $bot->sendMessage(_("Nessuna manutenzione risolta"));
                        exit;
                    }

                    while($row = $reportsSolved->fetch_assoc()){
                        $msg = "- - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                        $msg.= _("Segnalato da: ").$db->getUser($row['WhoReports'])['FullName'].PHP_EOL;
                        $msg.= _("Data segnalazione: ").date('d-m-Y G:i:s', strtotime($row['ReportsDateTime'])).PHP_EOL;
                        $msg.= _("Risolto da: ").$db->getUser($row['WhoResolve'])['FullName'].PHP_EOL;
                        $msg.= _("Data risoluzione: ").date('d-m-Y G:i:s', strtotime($row['ResolutionDateTime'])).PHP_EOL.PHP_EOL;
                        $msg.= $row['Description'].PHP_EOL;
                        $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";

                        $bot->sendMessage($msg);
                    }
                }
                elseif($update["text"] == _('Impostazioni')."\u{2699}"){
                    $key = array(
                        _("Cambia lingua")." \u{1F524}",
                        //_("Formato data")
                    );

                    $roomsKeyboard = createUserKeyboard($key,[[['text' => "\u{1F3E1}"]]]);
                    $bot->sendMessage(_('Menu impostazioni: '), $roomsKeyboard);
                }
                elseif($update["text"] == _("Cambia lingua")." \u{1F524}"){
                    $langArrayKeyboard[] = [['text' => _("Italiano")]];
                    $langArrayKeyboard[] = [['text' => "Klingon"]];
                    $langArrayKeyboard[] = [['text' => "\u{1F3E1}"]];
                    $langKeyboard = json_encode(['keyboard'=>  $langArrayKeyboard, 'resize_keyboard'=> true] ,JSON_PRETTY_PRINT);
                    $bot->sendMessage(_("Seleziona la lingua"), $langKeyboard);
                }
                elseif($update["text"] == _("Italiano")){
                    if($db->updateLanguage($chatID,'it')){
                        $bot->sendMessage(_("Lingua italiana impostata, ricarica il menu => /menu"), $keyboard);
                    }
                    else{
                        $bot->sendMessage(_("Si è verificato un problema nell'impostare la lingua da te richiesta"), $keyboard);
                    }
                }
                elseif($update["text"] == _("Inglese")){
                    if($db->updateLanguage($chatID,'en')){
                        $bot->sendMessage("The English language has been set, reload the menu => /menu", $keyboard);
                    }
                    else{
                        $bot->sendMessage(_("Si è verificato un problema nell'impostare la lingua da te richiesta"), $keyboard);
                    }
                }
                elseif($update["text"] == _("Klingon")){
                    if($db->updateLanguage($chatID,'de')){
                        $bot->sendMessage("tlhInganpu' QongDaq tlhInganpu' tlhInganpu' => /menu", $keyboard);
                    }
                    else{
                        $bot->sendMessage(_("Si è verificato un problema nell'impostare la lingua da te richiesta"), $keyboard);
                    }
                }
                elseif(preg_match('/^('._('Nessuna camera').'|(\d+))$/',$update["text"],$words)){//Modifica stanza

                    $selectType = file_get_contents(TmpFileUser_path.'selectRoom.json');
                    $selectType = json_decode($selectType, true);
                    $selectType = $selectType['Type'];

                    if($selectType == 'ChangeUserRoom'){

                        $changeRoom = file_get_contents(TmpFileUser_path.'changeRoom.json');

                        if(!$permission["ChangeUserRoom"] or !$changeRoom){
                            $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                            exit;
                        }

                        if($words[1] == _('Nessuna camera')){
                            $words[1] = NULL;
                        }

                        $changeRoom = json_decode($changeRoom, true);
                        $_chatID = $changeRoom['ChatID'];

                        $_user = $db->getUser($_chatID);

                        if($_user["Room"] == $words[1]){
                            if($words[1] == NULL){
                                $bot->sendMessage($_user['FullName'].' '._("risulta già senza una camera assegnata"));
                            }
                            else{
                                $bot->sendMessage($_user['FullName']._(" è già in camera ").$words[1]);
                            }
                            exit;
                        }

                        $users = $db->getUserList(false);
                        $usersName = [];
                        while($row = $users->fetch_assoc()){
                            $usersName[] = $row['FullName'];
                        }
                        $usersKeyboard = createUserKeyboard($usersName, [[['text' => _('Visualizza utenti disabilitati')]],[['text' => "\u{1F3E1}"]]]);

                        if($db->updateRoom($_chatID,$words[1]) === false){
                            $bot->sendMessage(_("Non è stato possibile assegnare ").$user['FullName']._(" alla camera ").$words[1],$usersKeyboard);
                        }
                        else{

                            $bot->sendMessage(_('Camera modificata'), $usersKeyboard);

                            $users = $db->getUserList(false);
                            $usersName = [];
                            while($row = $users->fetch_assoc()){
                                $usersName[] = $row['FullName'];
                            }
                            $usersKeyboard = createUserKeyboard($usersName, [[['text' => _('Visualizza utenti disabilitati')]],[['text' => "\u{1F3E1}"]]]);

                            sendUser($db->getUser($_chatID), $permission, $messageInLineKeyboardPath);

                            if($_chatID != $chatID){
                                $bot->setChatID($_chatID);
                                if(!is_null($words[1])){
                                    $bot->sendMessage(_("Sei stato assegnato alla camera ").$words[1]);
                                }
                            }
                        }

                        $file['Type'] = 'SelectUserForEdit';
                        file_put_contents(TmpFileUser_path.'selectUser.json', json_encode($file, JSON_PRETTY_PRINT));

                        unlink(TmpFileUser_path.'changeRoom.json');
                    }
                    elseif($selectType == 'SeeUserInRoom'){

                        $roomNum = $words[1];
                        $users = $db->getUserInRoom($roomNum);

                        $msg = "- - - - - - "._("Utenti in camera").' '.$roomNum."  - - - - - -\n";

                        if($users->num_rows == 0){
                            $msg .= _('Vuota');
                        }
                        else{
                            while($row = $users->fetch_assoc()){
                                $msg .= $row['FullName'];

                                if(!empty($row['Username'])){
                                    $msg .= ' - @'.$row['Username'];
                                }

                                $msg .= PHP_EOL;
                            }
                        }

                        $keyboardAddRoomInGroup = json_encode(['inline_keyboard'=> [[['text' => _('Aggiungi ad un gruppo'), 'callback_data' => "AddRoomInGroup-$roomNum"]]] ],JSON_PRETTY_PRINT);

                        $msgResult = json_decode( $bot->sendMessage($msg, $keyboardAddRoomInGroup),true);

                        $messageInLineKeyboard = file_get_contents($messageInLineKeyboardPath);
                        $messageInLineKeyboard = json_decode($messageInLineKeyboard, true);
                        $messageInLineKeyboard[$msgResult["result"]["message_id"]] = $msg;
                        file_put_contents($messageInLineKeyboardPath ,json_encode($messageInLineKeyboard, JSON_PRETTY_PRINT));
                    }
                    else{
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti") . " \u{1F97A}", $keyboard);
                    }
                }
                elseif(preg_match("/^([\w\s]+)( => )([\w\s]*)$/", $update['text'], $words) and array_key_exists($words[1],$db->getGroupList()) and array_key_exists($words[3],$db->getGroupList())){
                    $swapGroup = file_get_contents(TmpFileUser_path.'swapGroup.json');
                    $swapGroup = json_decode($swapGroup, true);

                    if($permission["SwapGroup"] == false or !in_array($update['text'], $swapGroup['AllowedSwap'])){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $from = $swapGroup['from'];
                    $to = $swapGroup['to'];

                    $fromName = $db->getUser($from)['FullName'];
                    $toName = $db->getUser($to)['FullName'];

                    $fromGroup = $words[1];
                    $toGroup = $words[3];

                    $bot->sendMessage(_('Attendi che ').$toName._(' accetti o rifiuti lo scambio'), $keyboard);

                    $file['From'] = $from;
                    $file['To'] = $to;
                    $file['FromGroup'] = $fromGroup;
                    $file['ToGroup'] = $toGroup;
                    file_put_contents(TmpFileUser_path.'swapGroupData.json', json_encode($file, JSON_PRETTY_PRINT));

                    $acceptText = _('Accetto');
                    $refuseText = _('Rifiuto');

                    $keyboardYesNoSwap = "{
                                        \"inline_keyboard\":
                                            [
                                                [
                                                    { 
                                                        \"text\":\"$acceptText\",
                                                        \"callback_data\":\"swapAccepted-$from\"
                                                    },
                                                    {
                                                        \"text\":\"$refuseText\",
                                                        \"callback_data\":\"swapRefused-$from\"
                                                    }
                                                ]
                                            ]
                                        }";

                    $bot->setChatID($to);
                    $bot->sendMessage($fromName._(' ti propone di passare da ').$toGroup._(' a ').$fromGroup, $keyboardYesNoSwap);

                    unlink(TmpFileUser_path.'swapGroup.json');
                }
                elseif(preg_match("/^(\/broadcast)(\s+)(.*)$/",$update["text"],$words)){
                    if($permission["BroadcastMsg"] == false){
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                        exit;
                    }

                    $usersList = $db->getUserList();
                    $_users = [];
                    while($row = $usersList->fetch_assoc()){
                        $_users[] = $row['ChatID'];
                    }

                    sendNotification($_users, $words[3],$bot);
                }
                elseif($update["text"] == "/start"){
                    $bot->sendMessage(_("Bentornato, come posso aiutarti?"),$keyboard);
                }
                elseif($update["text"] == "/menu" or $update["text"] == "\u{1F3E1}"){
                    $bot->sendMessage("Menù",$keyboard);

                    $files = glob(TmpFileUser_path.'*'); // get all file names

                    foreach($files as $file){ // iterate files
                        if(is_file($file) and $file !== basename('tmpGuest.json') and $file !== basename('updateGuest.json') ) {
                            unlink($file); // delete file
                        }
                    }
                }
                elseif($update["text"] == '/reset'){
                    $files = glob(TmpFileUser_path.'*'); // get all file names

                    foreach($files as $file){ // iterate files
                        if(is_file($file)) {
                            unlink($file); // delete file
                        }
                    }

                    $fileLink = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                    $setWebhook = "https://api.telegram.org/bot".TOKEN."/setWebhook?url=$fileLink?drop_pending_updates=true";
                    $res = file_get_contents($setWebhook);

                    $res = json_decode($res, true);

                    if($res['ok']){
                        $bot->sendMessage(_('Reset completato'), $keyboard);
                    }
                    else{
                        file_put_contents(TmpFileUser_path.'reset_setWebhook.json', json_encode($res, JSON_PRETTY_PRINT));
                    }
                }
                elseif(array_key_exists($update["text"],$db->getGroupList())){

                    $purpose = file_get_contents(TmpFileUser_path.'selectGroup.json');
                    $purpose = json_decode($purpose, true);

                    if($purpose['Type'] == 'insertUser'){

                        $_user = $db->getUser($purpose['ChatID']);

                        sendUser($_user, $permission, $messageInLineKeyboardPath);

                        if(!$db->insertUserInGroup($purpose['ChatID'], $update['text'])){
                            $bot->sendMessage(_("Non è stato possibile inserire l'utente nel gruppo ").$update['text'],$keyboard);
                        }
                        else{

                            $users = $db->getUserList(false);
                            $usersName = [];
                            while($row = $users->fetch_assoc()){
                                $usersName[] = $row['FullName'];
                            }
                            $usersKeyboard = createUserKeyboard($usersName, [[['text' => _('Visualizza utenti disabilitati')]],[['text' => "\u{1F3E1}"]]]);

                            $bot->sendMessage($_user['FullName']._(" inserito nel gruppo ").$update['text'],$usersKeyboard);

                            $bot->setChatID($purpose['ChatID']);
                            $bot->sendMessage(_("Sei stato inserito nel gruppo ").$update['text']);
                            $bot->sendMessage(userInGroup($update['text'], $db->getUserInGroup($update['text'])));
                        }

                        unlink(TmpFileUser_path.'selectGroup.json');
                    }
                    elseif($purpose['Type'] == 'deleteUserFromGroup'){

                        $_user = $db->getUser($purpose['ChatID']);

                        sendUser($_user, $permission, $messageInLineKeyboardPath);

                        if(!$db->removeUserFromGroup($purpose['ChatID'], $update['text'])){
                            $bot->sendMessage(_("Non è stato possibile rimuovere l'utente dal gruppo ").$update['text'],$keyboard);
                        }
                        else{

                            $users = $db->getUserList(false);
                            $usersName = [];
                            while($row = $users->fetch_assoc()){
                                $usersName[] = $row['FullName'];
                            }
                            $usersKeyboard = createUserKeyboard($usersName, [[['text' => _('Visualizza utenti disabilitati')]],[['text' => "\u{1F3E1}"]]]);
                            $bot->sendMessage($_user['FullName']._(" rimosso dal gruppo ").$update['text'],$usersKeyboard);

                            $bot->setChatID($purpose['ChatID']);
                            $bot->sendMessage(_("Sei stato rimosso dal gruppo ").$update['text']);
                        }

                        unlink(TmpFileUser_path.'selectGroup.json');
                    }
                    elseif($purpose['Type'] == 'insertRoom'){
                        $userInRoom = $db->getUserInRoom($purpose['Room']);

                        $myChatID = $chatID;

                        while($row = $userInRoom->fetch_assoc()){

                            if(!in_array($row, $db->getUserInGroup($update['text']))){
                                if(!$db->insertUserInGroup($row['ChatID'], $update['text'])){
                                    $bot->sendMessage(_("Non è stato possibile inserire").' '.$row['FullName'].' '._("nel gruppo ").$update['text']);
                                }
                                else{
                                    $bot->setChatID($row['ChatID']);
                                    $bot->sendMessage(_("Sei stato inserito nel gruppo ").$update['text']);
                                }
                            }
                        }

                        $rooms = $db->query('SELECT R.Num, R.Beds AS TotalBeds, COUNT(U.ChatID) AS OccupiedBeds FROM Room R LEFT JOIN User U ON R.Num = U.Room AND U.Enabled IS TRUE GROUP BY R.Num;');

                        $_rooms = [];
                        while($row = $rooms->fetch_assoc()){
                            $_rooms[] = $row['Num'];
                        }

                        if($permission["NewRoom"]){
                            $roomsKeyboard = createUserKeyboard($_rooms,[[['text' => _('Aggiungi nuova camera')]], [['text' => "\u{1F3E1}"]]]);
                        }
                        else{
                            $roomsKeyboard = createUserKeyboard($_rooms,[[['text' => "\u{1F3E1}"]]]);
                        }

                        $bot->setChatID($myChatID);
                        $userInGroup = $db->getUserInGroup($update['text']);
                        $bot->sendMessage(userInGroup($update['text'], $userInGroup), $roomsKeyboard);

                        unlink(TmpFileUser_path.'selectGroup.json');
                    }
                    elseif($purpose['Type'] == 'viewUserInGroup'){
                        $userList = $db->getUserInGroup($update["text"]);
                        $bot->sendMessage(userInGroup($update["text"],$userList));
                    }
                    else{
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti") . " \u{1F97A}", $keyboard);

                        $files = glob(TmpFileUser_path . '*'); // get all file names
                        foreach ($files as $file) { // iterate files
                            if (is_file($file) and $file !== 'tmpGuest.json') {
                                unlink($file); // delete file
                            }
                        }
                    }
                }
                elseif(file_exists(TmpFileUser_path.'selectUser.json') and !empty($db->getChatID($update["text"])) and $db->getUser($db->getChatID($update["text"])) != false){

                    $selectType = file_get_contents(TmpFileUser_path.'selectUser.json');
                    $selectType = json_decode($selectType, true);

                    if($selectType['Type'] == 'SelectUserForEdit'){

                        sendUser($db->getUser($db->getChatID($update['text'])), $permission, $messageInLineKeyboardPath);

                    }
                    elseif($selectType['Type'] == 'SelectUserForSwapTurn'){
                        if($permission["SwapGroup"] == false){
                            $bot->sendMessage(_("Mi dispiace ma non so come aiutarti")." \u{1F97A}",$keyboard);
                            exit;
                        }

                        $swapGroup['from'] = $chatID;
                        $swapGroup['to'] = $db->getChatID($update["text"]);


                        $fromGroups = $db->getGroupsByUser($chatID);
                        $toGroups = $db->getGroupsByUser($db->getChatID($update['text']));

                        $buttonText = [];
                        foreach($fromGroups as $key => $value){
                            if(!in_array($key, array_keys($toGroups))){
                                foreach($toGroups as $key1 => $value1){
                                    if( ($value == $value1) and !in_array($key1, array_keys($fromGroups)) ){
                                        $buttonText[] = $key.' => '.$key1;
                                    }
                                }
                            }
                        }

                        if(empty($buttonText)){
                            $bot->sendMessage(_('Non è disponibile alcun gruppo per effettuare lo scambio'));
                        }
                        else{
                            $swapGroup['AllowedSwap'] = $buttonText;
                            file_put_contents(TmpFileUser_path.'swapGroup.json', json_encode($swapGroup, JSON_PRETTY_PRINT));

                            $swapKeyboard = createUserKeyboard($buttonText,[[['text' => "\u{1F3E1}"]]]);
                            $bot->sendMessage(_('Scegli lo scambio da fare:'), $swapKeyboard);
                        }
                    }
                    else{
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti") . " \u{1F97A}", $keyboard);

                        $files = glob(TmpFileUser_path . '*'); // get all file names
                        foreach ($files as $file) { // iterate files
                            if (is_file($file) and $file !== 'tmpGuest.json') {
                                unlink($file); // delete file
                            }
                        }
                    }

                }
                elseif($db->getTypeOfTurn($update["text"]) != false){

                    $file = file_get_contents(TmpFileUser_path.'selectTypeOfTurn.json');
                    $file = json_decode($file, true);

                    if($file['Type'] == 'Show'){
                        $typeOfTurn = $db->getTypeOfTurn($update['text']);

                        $frequency = $typeOfTurn["Frequency"];
                        $lastExecution = $typeOfTurn["LastExecution"];
                        $firstExecution = $typeOfTurn["FirstExecution"];
                        $turnName = $typeOfTurn["Name"];

                        if(is_null($lastExecution)){
                            $msg = "- - - - - -  "._("Turno")." $turnName  - - - - - - \n";
                            $msg.= _('Il turno inizierà il').' '.strftime('%e %h %Y', strtotime($firstExecution)).PHP_EOL._('con il gruppo').' '.$db->getGroupWillDoTheNextTurn($turnName,0)["Squad"].PHP_EOL;
                        }
                        else{

                            $passedDays = date_diff(new DateTime($lastExecution),new DateTime(date("Y-m-d")))->days;
                            $remainingDays = $frequency - $passedDays;

                            $users = $db->getUsersOfTurnPerformed(date("Y-m-d", strtotime("-$passedDays days") ),$turnName)["Users"];

                            $msg = "- - - - - -  "._("Turno")." $turnName  - - - - - -\n";

                            if($passedDays == 1){
                                if(empty($users)){
                                    $msg.= _("Ieri: Non presente nello storico").PHP_EOL;
                                }
                                else{
                                    $msg.= _("Ieri: ").$users.PHP_EOL;
                                }
                            }
                            elseif($passedDays == 0){
                                if(empty($users)){
                                    $msg.= _("Oggi: Non presente nello storico").PHP_EOL;
                                }
                                else{
                                    $msg.= _("Oggi: ").$users.PHP_EOL;
                                }
                            }
                            else{
                                if(empty($users)){
                                    $msg.= $passedDays._(" giorni fà: Non presente nello storico").PHP_EOL;
                                }
                                else{
                                    $msg.= $passedDays._(" giorni fà: ").$users.PHP_EOL;
                                }
                            }

                            if($remainingDays == 0){
                                $msg.= _("Oggi: ").$db->getGroupWillDoTheNextTurn($turnName,0)["Squad"].PHP_EOL;
                            }
                            elseif($remainingDays == 1){
                                $msg.= _("Domani: ").$db->getGroupWillDoTheNextTurn($turnName)["Squad"].PHP_EOL;
                            }
                            else{
                                $msg.= _("Tra")." $remainingDays "._("giorni: ").$db->getGroupWillDoTheNextTurn($turnName)["Squad"].PHP_EOL;
                            }

                            $myGroups = $db->getGroupsByUser($chatID);
                            $_myGroups = [];
                            foreach($myGroups as $key => $value){
                                if(strpos( $value, $turnName) !== false){
                                    $_myGroups[] = $key;
                                }
                            }

                            for($i=1; $i<=$db->getStepNumOfTurn($turnName); $i++){
                                $group =$db->getGroupWillDoTheNextTurn($turnName, $i)['Squad'];

                                if(in_array($group, $_myGroups)){
                                    $days = ($i*$frequency) - $passedDays;
                                    $msg .= _("Tuo prossimo: ").strftime('%e %h %Y', strtotime("+$days days")).PHP_EOL;
                                    break;
                                }
                            }
                        }

                        $msg .= "- - - - - - - - - - - - - - - - - - - - - - - -\n";

                        $bot->sendMessage($msg);
                    }
                    elseif($file['Type'] == 'Edit'){
                        sendMessageEditTypeOfTurn($db->getTypeOfTurn($update["text"]), $permission, $messageInLineKeyboardPath);
                    }
                    elseif($file['Type'] == 'ShowCalendar'){
                        $typeOfTurn = $db->getTypeOfTurn($update['text']);

                        $frequency = $typeOfTurn["Frequency"];
                        $lastExecution = $typeOfTurn["LastExecution"];
                        $firstExecution = $typeOfTurn["FirstExecution"];
                        $turnName = $typeOfTurn["Name"];

                        $msg = "- - - - - -  "._("Turno")." $turnName  - - - - - -\n";

                        $passedDays = date_diff(new DateTime($lastExecution),new DateTime(date("Y-m-d")))->days;

                        for($i=0; $i<$db->getStepNumOfTurn($turnName); $i++){

                            $days = (($i*$frequency) - $passedDays);

                            $msg .= strftime('%e %h %Y', strtotime("+$days days")).': '.$db->getGroupWillDoTheNextTurn($turnName,$i)["Squad"].PHP_EOL;
                        }

                        $bot->sendMessage($msg);

                        $bot->sendMessage(_("Nelle date successive i turni verranno eseguiti ciclicamente dai gruppi nell'ordine mostrato"));
                    }
                    else{
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti") . " \u{1F97A}", $keyboard);

                        $files = glob(TmpFileUser_path . '*'); // get all file names
                        foreach ($files as $file) { // iterate files
                            if (is_file($file) and $file !== 'tmpGuest.json') {
                                unlink($file); // delete file
                            }
                        }
                    }
                }
                else {
                    $quote = $db->getQuoteByMsg($update['text']);
                    if(!empty($quote)){
                        $bot->sendMessage($quote['Response']);
                    }
                    else{
                        $bot->sendMessage(_("Mi dispiace ma non so come aiutarti") . " \u{1F97A}", $keyboard);
                    }

                    $files = glob(TmpFileUser_path . '*'); // get all file names
                    foreach ($files as $file) { // iterate files
                        if (is_file($file) and $file !== 'tmpGuest.json') {
                            unlink($file); // delete file
                        }
                    }
                }
            }
        }
    }

    $conn->close();

/**
 * @param $text string
 * @param $chatID int
 * @param $keyboard string
 */
function checkNewGuestInput(string $text, int $chatID, string $keyboard){

    global $bot;
    global $db;

    //file temporaneo contenente i dati dell'ospite da inserire;
    $fileName = TmpFileUser_path."tmpGuest.json";

    //Lettura dei dati dell'ospite dal file;
    $ospite = file_get_contents($fileName);
    $ospite = json_decode($ospite, true);


    if ($ospite['Name'] == null) {

        if(empty($text)){
            $bot->sendMessage(_("Il nome non può essere vuoto"));
            $bot->sendmessageForceReply(_("Inserisci Nome e Cognome dell'ospite da registrare:"));
        }

        $ospite['Name'] = $text;
        file_put_contents($fileName, json_encode($ospite));

        $path = TmpFileUser_path."calendar.json";
        $type['Type'] = 'NewGuest';
        file_put_contents($path,json_encode($type));
        $bot->sendCalendar(strtotime('+3 days'), _("Usa le frecce per selezionare la data di arrivo dell'ospite"),SELECT_DATE_INTERVALL);
    }
    elseif ($ospite['CheckInDate'] == null) {
        $ospite['CheckInDate'] = $text;
        file_put_contents($fileName, json_encode($ospite));
    }
    elseif ($ospite['LeavingDate'] == null) {
        $ospite['LeavingDate'] = $text;
        file_put_contents($fileName, json_encode($ospite));
        $bot->sendmessageForceReply(_("Scatta una foto del fronte del documento dell'ospite ed inviala:"));
    }
    elseif ($ospite['FrontDocument'] == null) {

        if(empty($text)){
            $bot->sendMessage(_("La foto inviata non è valida, scatta semplicemente una foto ed inviala"));
            $bot->sendmessageForceReply(_("Scatta una foto del fronte del documento dell'ospite ed inviala:"));
        }else{
            $ospite['FrontDocument'] = $text;
            file_put_contents($fileName, json_encode($ospite));
            $bot->sendmessageForceReply(_("Scatta una foto del retro del documento dell'ospite ed inviala:"));
        }
    }
    elseif ($ospite['BackDocument'] == null){

        if(empty($text)){
            $bot->sendMessage(_("La foto inviata non è valida, scatta semplicemente una foto ed inviala"));
            $bot->sendmessageForceReply(_("Scatta una foto del retro del documento dell'ospite ed inviala:"));
        }
        else{
            $ospite['BackDocument'] = $text;

            $user = $db->getUser($chatID);

            $ospite["Room"] = $user["Room"];
            file_put_contents($fileName, json_encode($ospite));

            if($ospite["CheckInDate"] < strtotime('+2 days')){
                $bot->sendMessage(_("Registrazione non riuscita, l'ospite deve essere registrato e confermato con 2 giorni d'anticipo, prova a ricontrollare le date inserite"),$keyboard);
                unlink($fileName);
            }
            else{

                $date = [];
                for($i = $ospite["CheckInDate"]; $i <= $ospite["LeavingDate"]; $i = strtotime("+1 days",$i)){
                    if($db->getSeatsNum(date('Y-m-d',$i))["FreeSeats"] <= 0){
                        $date[] = $i;
                    }
                }

                if(!empty($date)){
                    $msg = _("Nei seguenti giorni non sono disponibili posti in struttura, scegli un altra data").PHP_EOL;
                    $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                    foreach ($date as $value){
                        $msg .= strftime('%d %B %Y',$value).PHP_EOL;
                    }

                    $bot->sendMessage($msg,$keyboard);
                    unlink($fileName);
                    exit;
                }

                $numGuest = $db->getGuestList(date('Y-m-d',$ospite["CheckInDate"]), date('Y-m-d',$ospite["LeavingDate"]), $ospite["ChatID"])->num_rows;

                if($numGuest >= 2){
                    $bot->setChatID($ospite["ChatID"]);

                    $msg = _("Non puoi ospitare più di due persone nello stesso periodo, stai gia ospitando:"."\n");
                    $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
                    $guestList = $db->getGuestList(date('Y-m-d',$ospite["CheckInDate"]),date('Y-m-d',$ospite["LeavingDate"]),$ospite["ChatID"]);
                    while($row = $guestList->fetch_assoc()){
                        $msg .= " - ".$row["Name"]._(" dal ").$row["CheckInDate"]._(" al ").$row["LeavingDate"].",\n";
                    }
                    $bot->sendMessage($msg,$keyboard);

                    unlink($fileName);
                    exit;
                }

                $guest = $db->getGuest($ospite["ChatID"], $ospite["Name"], date('Y-m-d',$ospite["CheckInDate"]), date('Y-m-d',$ospite["LeavingDate"]));

                $roommateList = $db->getRoommateList($chatID);

                if(empty($guest)){
                    //Se l'utente non ha un compagno di stanza la registrazione viene eseguita direttamente senza richiedere la conferma al suo compagno
                    if($roommateList->num_rows == 0){
                        if(guestInput($fileName) === true){
                            $bot->setChatID($chatID);
                            $bot->sendMessage(_("Ospite confermato e registrato"), $keyboard);
                        }
                        unlink($fileName);
                    }
                    else{
                        $roommateList = $db->getRoommateList($chatID);

                        $userMsgID = [];

                        while($roommate = $roommateList->fetch_assoc()){

                        $keyboardYesNo = "{
                                            \"inline_keyboard\":
                                                [
                                                    [
                                                        { 
                                                            \"text\":\"Yes\",
                                                            \"callback_data\":\"guestAccepted-$chatID\"
                                                        },
                                                        {
                                                            \"text\":\"No\",
                                                            \"callback_data\":\"guestRefused-$chatID\"
                                                        }
                                                    ]
                                                ]
                                            }";

                            $arrivo = date( 'd/m/Y' ,$ospite['CheckInDate']);
                            $partenza = date( 'd/m/Y' ,$ospite['LeavingDate']);
                            $message = _("Il/La tuo/a compagno/a di stanza ").$user["FullName"]._(" vorrebbe ospitare qualcuno nella vostra camera dal ").$arrivo._(" al ").$partenza._(", per te va bene?");

                            $bot->setChatID($roommate["ChatID"]);
                            $msgResult = json_decode($bot->sendMessage($message, $keyboardYesNo),true);

                            $userMsgID[$roommate["ChatID"]] = $msgResult["result"]["message_id"];
                        }

                        $ospite["UserMessageID"] = $userMsgID;
                        file_put_contents($fileName, json_encode($ospite));

                        $bot->setChatID($chatID);
                        if($roommateList->num_rows >1){
                            $bot->sendMessage(_("Attendi la conferma dei tuoi compagni di stanza, ti arriverà una notifica quando confermeranno"), $keyboard);
                        }
                        else{
                            $bot->sendMessage(_("Attendi la conferma del tuo compagno di stanza, ti arriverà una notifica quando confermerà"), $keyboard);
                        }
                    }
                }
                else{
                    $bot->sendMessage(_("L'ospite risulta già registrato"), $keyboard);
                    unlink($fileName);
                }

            }
        }
    }
}

function guestInput($guest_file){

    global $bot;
    global $db;

    //Lettura dei dati dell'ospite dal file;
    $ospite = file_get_contents($guest_file);
    $ospite = json_decode($ospite, true);

    $user = $db->getUser($ospite["ChatID"]);

    $fileFolderName = preg_replace('/\s+/', '_', strtolower($ospite["Name"]));
    $documento[0] = downloadDocument(Guest_document.$fileFolderName, $ospite['FrontDocument'], "Fronte_$fileFolderName");
    $documento[1] = downloadDocument(Guest_document.$fileFolderName, $ospite['BackDocument'], "Retro_$fileFolderName");

    if($db->insertGuest($ospite["ChatID"], $ospite["Name"], date('Y-m-d',$ospite["CheckInDate"]), date('Y-m-d',$ospite["LeavingDate"]), $ospite["Room"])){
        $arrivo = date("d-m-Y", $ospite["CheckInDate"]);
        $partenza = date("d-m-Y", $ospite["LeavingDate"]);

        $msg = _("Nuovo ospite presente in camera ") . $ospite["Room"] . _(" dal ") . $arrivo . _(" al ") . $partenza;
        $usersList = $db->getAllUsersForNotification("NewGuest");
        $_users = [];
        while($row = $usersList->fetch_assoc()){
            $_users[] = $row['ChatID'];
        }

        sendNotification($_users,$msg,array($ospite["ChatID"]));

        $from = 'giuliettobot@casadellostudentevillagiulia.it';
        $fromName = 'GiuliettoBot';
        $subject = _("Ospite in camera ") . $ospite["Room"];
        $text = _("Nuovo ospite , ") . $ospite["Name"] . _(" in camera ") . $ospite["Room"] . _(" dal ") . date("Y-m-d", $ospite["CheckInDate"]) . _(" al ") . date("Y-m-d", $ospite["LeavingDate"]) . " ospitato da " . $user["FullName"];
        $file[0] = Guest_document . $fileFolderName . "/" . $documento[0];
        $file[1] = Guest_document . $fileFolderName . "/" . $documento[1];

        $to = $db->getAllUsersForNotification("EmailNewGuest");

        while ($user = $to->fetch_assoc()) {
            if(!empty($user["Email"])){
                if (!email($user["Email"], $from, $fromName, $subject, $text, $file)) {
                    $bot->sendMessage(_("Si è verificato un errore nell'inviare la mail con i documenti dell'ospite alla tua e-mail: \n" . $user["Email"] . "\n verifica che sia corretta."));
                }
            }
        }
        return true;
    }
    $bot->setChatID($ospite["ChatID"]);
    $bot->sendMessage(_("Si è verificato un problema nella registrazione dell'ospite"));
    return false;
}

function sendNotification($usersList, $msg, $exception = []){

    global $bot;

    foreach($usersList as $user) {
        if(!in_array($user,$exception)){
            $bot->setChatID($user);
            $bot->sendMessage($msg);
        }
    }
}

function downloadDocument($path, $documentID, $documentName){

    //Legge le informazioni del file inviato
    $url = "https://api.telegram.org/bot".TOKEN."/getFile?file_id=$documentID";
    $fileInfo = file_get_contents($url);
    $file = json_decode($fileInfo,true);

    $filePath = $file["result"]["file_path"];

    //Crea la cartella (se non esiste) per salvare i documenti dell'ospite
    if(is_dir($path) == false){
        mkdir($path, 0755, true);
    }

    //scarica il file del documento sul server
    $url = "https://api.telegram.org/file/bot".TOKEN."/$filePath";//link del file sui server telegram
    $ext = pathinfo($url, PATHINFO_EXTENSION);//estensione del file
    $documentName = "$documentName.$ext";//nome completo del documento da creare
    //Il documento viene scaricato ogni volta perché potrebbe essere stato rinnovato
    $res = file_put_contents("$path/$documentName", file_get_contents($url));

    if(!$res){
        return $res;
    }

    return $documentName;
}

function createUserKeyboard($buttonTextList, $end = [], $oneTimeKeyboard = false){
    $rowNumber = sizeof($buttonTextList);
    $keyboard = [];
    $rowButton = [];
    $buttonNum = 0;
    $i = 0;
    foreach($buttonTextList as $value){
        $rowButton[] = ['text'=> $value];
        $buttonNum++;

        if($buttonNum == 2 or $i == $rowNumber-1){
            $keyboard[] = $rowButton;
            $buttonNum = 0;
            $rowButton = [];
        }
        $i++;
    }

    if(!empty($end)){
        foreach($end as $row){
            $keyboard[] = $row;
        }
    }
    return json_encode(['keyboard'=> $keyboard, 'resize_keyboard'=> true, 'one_time_keyboard'=>$oneTimeKeyboard],JSON_PRETTY_PRINT);
}

function userInGroup($groupName, $userInGroup): string
{
    $msg = "- - - - - - - - - - ".$groupName." - - - - - - - - - - \n";

    if(count($userInGroup) == 0){
        $msg.= "Il gruppo è vuoto";
    }else{
        foreach($userInGroup as $user){

            $msg .= $user["FullName"];

            if(!empty($user["Username"])){
                $msg .= " - @".$user["Username"].PHP_EOL;
            }
            else{
                $msg .= PHP_EOL;
            }
        }
    }
    return $msg;
}

/**
 * @param $permission array The associative array of the account permission
 * @return array Return the reply keyboard to send or false on failure
 */
function createPermissionKeyboard(array $permission, $KeyText = null): array
{
    $keyboard = [];

    end($permission);
    $lastElement = key($permission);
    reset($permission);

    $buttonNum = 0;
    $row = [];
    foreach($permission as $key => $value){
        $text = $KeyText[$key];
        if($value == true and !empty($text)){
            $row[] = ['text'=> $text];
            $buttonNum++;
        }

        if($buttonNum == 2 or $key == $lastElement){
           $keyboard[] = $row;
           $buttonNum = 0;
           $row = [];
        }
    }
    return $keyboard;
}

function keyboardEditUser($permission, $user){
    $keyboardUserFirstRow = [];
    $keyboardUserSecondRow = [];
    $keyboardUserThirdRow = [];

    $chatID = $user['ChatID'];

    if($permission['ChangeNameUser']){
        $keyboardUserFirstRow[] = ['text' => _('Cambia nome')." \u{270F}", 'callback_data' => "changeNameUser-$chatID"];
    }

    if($permission['ChangeUserRoom']){
        $keyboardUserFirstRow[] = ['text' => _('Cambia camera')." \u{270F}", 'callback_data' => "changeRoom-$chatID"];
    }

    if($permission['InsertUserInGroup']){
        $keyboardUserSecondRow[] = ['text' => _('Inserisci in gruppo'), 'callback_data' => "insertUserInGroup-$chatID"];
        $keyboardUserSecondRow[] = ['text' => _('Rimuovi da gruppo'), 'callback_data' => "deleteUserFromGroup-$chatID"];
    }

    if($permission['ChangeUserState']){
        if($user["Enabled"]){
            $keyboardUserThirdRow[] = ['text' => _('Disabilita'), 'callback_data' => "enableDisableUser-$chatID"];
        }
        else{
            $keyboardUserThirdRow[] = ['text' => _('Abilita'), 'callback_data' => "enableDisableUser-$chatID"];
        }
    }

    if($permission['DeleteUser']){
        $keyboardUserThirdRow[] = ['text' => _('Elimina')." \u{274C}", 'callback_data' => "deleteUser-$chatID"];
    }

    return json_encode(['inline_keyboard'=> [$keyboardUserFirstRow, $keyboardUserSecondRow, $keyboardUserThirdRow] ],JSON_PRETTY_PRINT);
}

function sendMessageEditTypeOfTurn($typeOfTurn, $permission, $messageInLineKeyboardPath){

    global $bot, $db;

    $msg = '- - - - - -  '._("Turno").' '.$typeOfTurn['Name'].'  - - - - - -'.PHP_EOL;
    $msg .= _('Frequenza').': '.$typeOfTurn['Frequency'].' '._('giorni').PHP_EOL;
    $msg .= _('Prima esecuzione').': '.$typeOfTurn['FirstExecution'].PHP_EOL;
    if(is_null($typeOfTurn['LastExecution'])){
        $msg .= _('Ultima esecuzione').': '._('Mai eseguito').PHP_EOL;
        $msg .= _('Step corrente').': '._('parte da').' '.($typeOfTurn['CurrentStep']+1).'/'.$db->getStepNumOfTurn($typeOfTurn['Name']).PHP_EOL;
    }
    else{
        $msg .= _('Ultima esecuzione').': '.$typeOfTurn['LastExecution'].PHP_EOL;
        $msg .= _('Step corrente').': '.($typeOfTurn['CurrentStep']+1).'/'.$db->getStepNumOfTurn($typeOfTurn['Name']).PHP_EOL;
    }
    $msg .= _('Utenti per gruppo').': '.$typeOfTurn['UsersBySquad'].PHP_EOL;
    $msg .= _('Frequenza gruppo').': '.$typeOfTurn['SquadFrequency'].PHP_EOL;

    $keyboardFirstRow = [];
    $keyboardSecondRow = [];

    $keyboardFirstRow[] = ['text' => _('Frequenza')." \u{270F}", 'callback_data' => "changeTypeOfTurnFrequency-".$typeOfTurn['Name']];
    $keyboardSecondRow[] = ['text' => _('Utenti per gruppo')." \u{270F}", 'callback_data' => "changeUserByGroup-".$typeOfTurn['Name']];

    $typeOfTurnEditKeyboard =  json_encode(['inline_keyboard'=> [$keyboardFirstRow, $keyboardSecondRow] ],JSON_PRETTY_PRINT);

    if($permission['EditTypeOfTurn']){

        $msgResult = json_decode($bot->sendMessage($msg, $typeOfTurnEditKeyboard), true);

        $messageInLineKeyboard = file_get_contents($messageInLineKeyboardPath);
        $messageInLineKeyboard = json_decode($messageInLineKeyboard, true);
        $messageInLineKeyboard[$msgResult["result"]["message_id"]] = $msg;
        file_put_contents($messageInLineKeyboardPath ,json_encode($messageInLineKeyboard, JSON_PRETTY_PRINT));
    }
    else{
        $bot->sendMessage($msg);
    }
}

function sendMessageEditGuest($guest, $permission, $messageInLineKeyboardPath){

    global $bot;

    $msg = "- - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
    $msg .= $guest['Name']._(" dal ").strftime('%e %h %Y', strtotime($guest['CheckInDate']))._(" al ").strftime('%e %h %Y', strtotime($guest['LeavingDate'])).PHP_EOL;
    $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";

    $keyboardGuest = [];

    $guestId = $guest['ID'];

    if($permission['UpdateGuest']){
        $keyboardGuest[] = ['text' => _("Modifica")." \u{270F}", 'callback_data' => "updateGuest-$guestId"];
    }

    if($permission['DeleteGuest']){
        $keyboardGuest[] = ['text' => _('Elimina')." \u{274C}", 'callback_data' => "deleteGuest-$guestId"];
    }

    $keyboardGuest = json_encode(['inline_keyboard'=> [$keyboardGuest]],JSON_PRETTY_PRINT);

    if(strtotime($guest['LeavingDate']) < time()){
        $bot->sendMessage($msg);
    }
    else{
        $msgResult = json_decode($bot->sendMessage($msg,$keyboardGuest),true);

        $messageInLineKeyboard = file_get_contents($messageInLineKeyboardPath);
        $messageInLineKeyboard = json_decode($messageInLineKeyboard, true);
        $messageInLineKeyboard[$msgResult["result"]["message_id"]] = $msg;
        file_put_contents($messageInLineKeyboardPath ,json_encode($messageInLineKeyboard, JSON_PRETTY_PRINT));
    }
}

function sendUserList($usersList){

    global $bot;

    $msg = _("Risultano registrati ").$usersList->num_rows._(" utenti").PHP_EOL.PHP_EOL;
    $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";

    while($row = $usersList->fetch_assoc()){
        $msg .= $row["FullName"];

        if(!empty($row["Username"]) ){
            $msg .= " - @".$row["Username"];
        }

        if(!empty($row['Room'])){
            $msg .= _(" - Camera ").$row["Room"];
        }

        if(!empty($row['Email'])){
            $msg .= PHP_EOL.$row["Email"];
        }

        $msg .= PHP_EOL;


        $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";
    }

    $bot->sendMessage($msg);
}

function sendUser($user, $permission, $messageInLineKeyboardPath){

    global $bot;

    $msg = "- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";

    $msg .= _('Nome:').' '.$user['FullName'].PHP_EOL;

    if(!empty($user["Username"]) ){
        $msg .= _('Username:')." @".$user["Username"].PHP_EOL;
    }

    if(!empty($user['Room'])){
        $msg .= _("Camera:").' '.$user["Room"].PHP_EOL;
    }
    else{
        $msg .= _("Camera: Nessuna").PHP_EOL;
    }

    $msg .= _('Tipo:').' ';
    if($user['Type'] == 'group'){
        $msg .= _("Gruppo").PHP_EOL;
    }
    elseif($user['Type'] == 'private'){
        $msg .= _("Privato").PHP_EOL;
    }
    elseif($user['Type'] == 'channel'){
        $msg .= _("Canale").PHP_EOL;
    }
    elseif($user['Type'] == 'supergroup'){
        $msg .= _("Super-gruppo").PHP_EOL;
    }

    if(!empty($user['Email'])){
        $msg .= _("Email:").' '.$user["Email"].PHP_EOL;
    }
    else{
        $msg .= _("Email: Non impostata").PHP_EOL;
    }

    $msg .= _("Data iscrizione:").' '.$user["InscriptionDate"].PHP_EOL;
    $msg .= _("Tipo di account:").' '.$user["AccountType"].PHP_EOL;

    $msg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";

    $keyboardUser = keyboardEditUser($permission, $user);
    $msgResult = json_decode($bot->sendMessage($msg, $keyboardUser),true);

    $messageInLineKeyboard = file_get_contents($messageInLineKeyboardPath);
    $messageInLineKeyboard = json_decode($messageInLineKeyboard, true);
    $messageInLineKeyboard[$msgResult["result"]["message_id"]] = $msg;
    file_put_contents($messageInLineKeyboardPath ,json_encode($messageInLineKeyboard, JSON_PRETTY_PRINT));
}