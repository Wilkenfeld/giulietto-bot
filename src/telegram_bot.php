<?php

/**
 * Telegram api bot
 *
 * @category Class
 * @author   Morrone Emanuele
 * @version 2.0
 */

    require_once 'config/config.php';

    const MESSAGE = 100;
    const EDITED_MESSAGE = 101;
    const CALLBACK_QUERY = 102;
    const SELECT_DATE_INTERVALL = 103;
    const SELECT_SINGLE_DATE = 104;
    const MARKDOWN_2 =  "MarkdownV2";
    const HTML = "HTML";
    const MARKDOWN = "Markdown";

    const STATUS_FILE = "calendar_status.json";

    class TelegramBot{

        private $token;
        private $url;

        private $chatID;
        private $updateID;

        private $update;

        private $updateType;

        private $dataDaVisualizzare;

        private $tmp_file_path;

        /**
         * @param string $token Token del bot telegram
         */
        public function __construct($token){
            $this->token = $token;
            $this->url = "https://api.telegram.org/bot".$this->token;
            $this->dataDaVisualizzare = time();
        }

        /**
         * getToken
         *
         * Restituisce il token del bot
         *
         *
         * @return string Bot Token
         */
        public function getToken(){
            return $this->token;
        }

        /**
         * setChatID
         *
         * Imposta il chatID da utilizzare, utile quindo bisogna inviare un messaggio a un utente arbitrario
         *
         * @param int $chatID
         */
        public function setChatID($chatID){
            $this->chatID = $chatID;
        }

        /**
         * @param string $tmp_file_path
         */
        public function setTmpFilePath($tmp_file_path)
        {
            $this->tmp_file_path = $tmp_file_path;
        }

        /**
         * getUpdateID
         *
         * Restituisce l'ID dell Update
         *
         *
         * @return int Update ID
         */
        public function getUpdateID(){
            return $this->updateID;
        }

        /**
         * getChatID
         *
         * restituisce l'ID della Chat da cui è arrivato l`update
         *
         * @return int chat ID
         */
        public function getChatID(){
            return $this->chatID;
        }

        /**
         * getUpdateType
         *
         * Restituisce il tipo di Update ricevuto
         *
         * I tipi di update attualmente supportati sono: MESSAGE, EDITED_MESSAGE, CALLBACK_QUERY
         *
         * per ulteriori informazioni sull`oggetto UPTADE visitare https://core.telegram.org/bots/api#update
         * @return int message, edited_message o callback_query
         */
        public function getUpdateType(){
            return $this->updateType;
        }

        /**
         * getUpdate
         *
         * Legge gli aggiornameli del bot dal server telegram e restituisce un oggetto JSON
         * contenente le informazioni del relativo tipo di UPDATE
         *
         * I tipi di update attualmente supportati sono: MESSAGE, EDITED_MESSAGE, CALLBACK_QUERY
         *
         * per ulteriori informazioni sull`oggetto UPDATE visitare https://core.telegram.org/bots/api#update
         * @return array|false Json Object o false se l`oggetto ricevuto non è supportato
         */
        public function getUpdate(){
            $this->update = file_get_contents("php://input");
            $this->update = json_decode($this->update,TRUE);

            $this->updateID = $this->update["update_id"];

            if($this->update["message"] !== NULL){
                $this->chatID = $this->update["message"]["chat"]["id"];
                $this->updateType = MESSAGE;
                return $this->update["message"];
            }
            elseif($this->update["edited_message"] !== NULL){
                $this->chatID = $this->update["edited_message"]["chat"]["id"];
                $this->updateType = EDITED_MESSAGE;
                return  $this->update["edited_message"];
            }
            elseif($this->update["callback_query"] !== NULL){
                $this->chatID = $this->update["callback_query"]["from"]["id"];
                $this->updateType = CALLBACK_QUERY;
                return  $this->update["callback_query"];
            }

            return false;
        }

        /**
         * sendMessage
         *
         * Invia un normale messaggio di testo
         *
         * @param string $messageText Testo del messaggio da inviare
         * @param string|null $replyMarkup
         * @param string|null $parseMode Accepted value is MarkdownV2, HTML and Markdown
         *
         */
        public function sendMessage(string $messageText, string $replyMarkup = null, string $parseMode = null){
            $url = $this->url."/sendMessage?chat_id=".$this->chatID."&text=".urlencode(utf8_encode($messageText));

            if(!is_null($replyMarkup)){
                $url .= "&reply_markup=".urlencode($replyMarkup);;
            }

            if(!is_null($parseMode)){
                $url .= "&parse_mode=$parseMode";
            }

            return file_get_contents($url);
        }

        /**
         * sendDocument
         *
         * Invia un documento
         *
         * @param string $document Url del documento da inviare
         */
        public function sendDocument($document, $caption = null){
            $url = $this->url."/sendDocument?chat_id=".$this->chatID;

            $post_fields = array('chat_id'=> $this->chatID, 'document'=> new CURLFile( realpath($document) ) );

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-Type:multipart/form-data"
            ));
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
            return curl_exec($ch);
        }

        /**
          * sendMessageForceReply
          *
          * Invia una tastiera di risposta personalizzata
          *
          * @param string $messageText Testo del messaggio da inviare
          *
          */
        public function sendMessageForceReply($messageText){

            $force = array(
                'force_reply' => true,
                'selective' => true
              );

              $encodedMarkup = json_encode ($force, true);

            $url = $this->url."/sendMessage?chat_id=".$this->chatID."&text=".urlencode($messageText)."&reply_markup=".urlencode($encodedMarkup);
            return file_get_contents($url);
        }

        /**
          * editMessageText
          *
          * Modifica il testo di un messaggio inviato
          *
          * @param string $newMessage Nuovo testo del messaggio da inviare
          * @param string $messageID ID del messaggio da modificare
          */
        public function editMessageText($messageID, $newMessage){
             $url = $this->url."/editMessageText?chat_id=".$this->chatID."&message_id=".$messageID."&text=".urlencode($newMessage);
             return file_get_contents($url);
        }

        /**
         * editMessageReplyMarkup
         *
         * Modifica il testo di un messaggio inviato
         *
         * @param string $newReplyMarkup Nuovo testo del messaggio da inviare
         * @param string $messageID ID del messaggio da modificare
         */
        public function editMessageReplyMarkup($messageID, $newReplyMarkup){
            $url = $this->url."/editMessageReplyMarkup?chat_id=".$this->chatID."&message_id=".$messageID."&reply_markup=".urlencode($newReplyMarkup);
            return file_get_contents($url);
        }

        /**
         * deleteMessage
         *
         * Elimina un messaggio nella chat
         *
         * @param int $messageID L'ID del messaggio da eliminare
         */
        public function deleteMessage($messageID){
            $url = $this->url."/deleteMessage?chat_id=".$this->chatID."&message_id=".$messageID;
            return file_get_contents($url);
        }

        // Funzioni calendario
    //--------------------------------------------------------------------------------------------------------------------------------------------

        /**
         * sendCalendar
         *
         * Invia il calendario per la selezione di una data sotto forma di reply_markup
         *
         * @param string $message [optional] Messaggio da inviare associato al calendario. Default "Usa le frecce per selezionare anno mese e giorno
         * @param int $dataIniziale Timestamp iniziale da mostrare sul calendario
         */
        public function sendCalendar($dataIniziale, $message = 'Usa le frecce per selezionare anno mese e giorno', $type = SELECT_SINGLE_DATE){

            //Data attuale
            $this->dataDaVisualizzare = $dataIniziale;

            //Suddivisione della data in giorno mese e anno
            $anno = date('Y', $this->dataDaVisualizzare);
            $mese = date('m', $this->dataDaVisualizzare);
            $giorno = date('d', $this->dataDaVisualizzare);

            //Imposta lo status del bot a SELECT_DATE
            $status['status'] = $type;
            //Imposta la data iniziale a null
            $status['dataDaVisualizzare'] = $dataIniziale;
            //Scrive tutto in un file json per tenere salvato lo stato della selezione data
            $this->createStatusFile(json_encode($status));

            //Reply keyboard definita secondo le specifiche delle Telegram Bot API https://core.telegram.org/bots/api#replykeyboardmarkup
            $calendar = "{
                \"inline_keyboard\":
                    [
                        [
                            { 
                                \"text\":\"<\",
                                \"callback_data\":\"prevYear\"
                            },
                            {
                                \"text\":\"$anno\",
                                \"callback_data\":\"null\"
                            },
                            {
                                \"text\":\">\",
                                \"callback_data\":\"nextYear\"
                            }
                        ],
                        [
                            { 
                                \"text\":\"<\",
                                \"callback_data\":\"prevMonth\"
                            },
                            {
                                \"text\":\"$mese\",
                                \"callback_data\":\"null\"
                            },
                            {
                                \"text\":\">\",
                                \"callback_data\":\"nextMonth\"
                            }
                        ],
                        [
                            { 
                                \"text\":\"<\",
                                \"callback_data\":\"prevDay\"
                            },
                            {
                                \"text\":\"$giorno\",
                                \"callback_data\":\"null\"
                            },
                            {
                                \"text\":\">\",
                                \"callback_data\":\"nextDay\"
                            }
                        ],
                        [
                            {\"text\":\"Conferma\",\"callback_data\":\"dataConfirm\"}
                        ],
                        [
                            {\"text\":\"Annulla\",\"callback_data\":\"dataCancel\"}
                        ]
                    ]
            }";

            //Invia il messaggio con il calendario
            $url = $this->url."/sendMessage?chat_id=".$this->chatID."&text=".urlencode($message)."&reply_markup=".urlencode($calendar);
            file_get_contents($url);

        }

        /**
         * selectDate
         *
         * Si occupa di gestire la selezione della data
         *
         * @return int Unix Time Stamp
         */
        public function selectDate(){

            $data = file_get_contents($this->tmp_file_path.STATUS_FILE);//Legge il file
            $data = json_decode($data,true);//Seleziona la data attualmente salvata

            //Cambia la data da visualizzare (che inizialmente è quella del giorno corrente) con quella letta dal file
            //che è quella dello stato precedente del calendario
            $this->dataDaVisualizzare = $data["dataDaVisualizzare"];

            //Aggiorna il calendario richiamando la funzione di aggiornamento e salva lo stato di aggiornamento
            //(FALSE per inserimento annullato, TRUE per data confermata, UNIX TIMESTAMP per nuova data selezionata)
            return $this->calendarUpdate($data["status"]);
        }

        /**
         * getTypeDateSelection
         *
         * @return string Restituisce il tipo di selezione data
         */
        public function getTypeDateSelection(){

            if(!file_exists($this->tmp_file_path.STATUS_FILE)){
                return false;
            }

            $data = file_get_contents($this->tmp_file_path.STATUS_FILE);
            $data = json_decode($data,true);

            return $data["status"];
        }

        /**
         * calendarUpdate
         *
         * Gestisce le operazioni dei pulsanti del calendario inviato con il metodo sendCalendar
         *
         * @return int|bool La data selezionata in formato UNIX timestamp quando si preme il pulsando di conferma, false Quando si annulla l`inserimento della data, true Se il cambio della data è avvenuto senza problemi
         */
        private function calendarUpdate($type){

            $callback = $this->update['callback_query'];

            $callbackData = $callback['data'];
            $callbackMessageID = $callback['message']['message_id'];

            if($callbackData == "prevYear"){//Decrementa di un anno
                $this->decreaseYear();
                $this->editCalendar($callbackMessageID);//Invia il calendario modificato
            }
            elseif($callbackData == "prevMonth"){//Decrementa di un mese
                $this->decreaseMonth();
                $this->editCalendar($callbackMessageID);
            }
            elseif($callbackData == "prevDay"){//Decrementa di un giorno
                $this->decreaseDay();
                $this->editCalendar($callbackMessageID);
            }
            elseif($callbackData == "nextYear"){//Incrementa di un anno
                $this->increaseYear();
                $this->editCalendar($callbackMessageID);
            }
            elseif($callbackData == "nextMonth"){//Incrementa di un mese
                $this->increaseMonth();
                $this->editCalendar($callbackMessageID);
            }
            elseif($callbackData == "nextDay"){//Incrementa di un giorno
                $this->increaseDay();
                $this->editCalendar($callbackMessageID);
            }
            elseif($callbackData == "dataConfirm"){//Quando vine premuto il pulsante di conferma
                $this->deleteMessage($callbackMessageID);//elimina il messaggio contenente il calendario
                unlink($this->tmp_file_path.STATUS_FILE);//Elimina il file usato per mantenere lo stato del calendario
                return $this->dataDaVisualizzare;//Restituisce la data selezionata
            }
            elseif($callbackData == "dataCancel"){
                $this->deleteMessage($callbackMessageID);//Elimina il messaggio contenente il calendario
                unlink($this->tmp_file_path.STATUS_FILE);//Elimina tutti i file temporanei usati per mantenere lo stato del calendario
                return false;
            }

            $status['status'] = $type ;//Tipo di stato
            $status['dataDaVisualizzare'] = $this->dataDaVisualizzare;//Data attualmente visualizzabile sul calendario per la selezione
            $this->createStatusFile(json_encode($status));//Salva le informazioni scritte sopra nel file status.json e contiene di volta in volta lo stato in cui è la selezione
                                                                       //della data da visualizzare e la data attualmente visualizzabile sul calendario

            return true;
        }

        /**
         * editCalendar
         *
         * Aggiorna la data visualizzata sul calendario
         * 
         * @param int $messageID L'ID del messaggio contenente il calendario
         */
        private function editCalendar($messageID){

            //Suddivisione della data in giorno mese e anno            
            $anno = date('Y', $this->dataDaVisualizzare);
            $mese = date('m', $this->dataDaVisualizzare);
            $giorno = date('d', $this->dataDaVisualizzare);

            //Reply keyboard definita secondo le specifiche delle Telegram Bot API https://core.telegram.org/bots/api#replykeyboardmarkup
            $calendar = "{
                \"inline_keyboard\":
                    [
                        [
                            { 
                                \"text\":\"<\",
                                \"callback_data\":\"prevYear\"
                            },
                            {
                                \"text\":\"$anno\",
                                \"callback_data\":\"null\"
                            },
                            {
                                \"text\":\">\",
                                \"callback_data\":\"nextYear\"
                            }
                        ],
                        [
                            { 
                                \"text\":\"<\",
                                \"callback_data\":\"prevMonth\"
                            },
                            {
                                \"text\":\"$mese\",
                                \"callback_data\":\"null\"
                            },
                            {
                                \"text\":\">\",
                                \"callback_data\":\"nextMonth\"
                            }
                        ],
                        [
                            { 
                                \"text\":\"<\",
                                \"callback_data\":\"prevDay\"
                            },
                            {
                                \"text\":\"$giorno\",
                                \"callback_data\":\"null\"
                            },
                            {
                                \"text\":\">\",
                                \"callback_data\":\"nextDay\"
                            }
                        ],
                        [
                            {\"text\":\"Conferma\",\"callback_data\":\"dataConfirm\"}
                        ],
                        [
                            {\"text\":\"Annulla\",\"callback_data\":\"dataCancel\"}
                        ]
                    ]
            }";

            //Invia il messaggio con il calendario che mostra la nuova data
            $url = $this->url."/editMessageReplyMarkup?chat_id=".$this->chatID."&message_id=".$messageID."&reply_markup=".urlencode($calendar);
            file_get_contents($url);
        }
    //--------------------------------------------------------------------------------------------------------------------------------------------

        //Funzioni manipolazione data
    //--------------------------------------------------------------------------------------------------------------------------------------------

        private function increaseYear(){
            $newDate = strtotime('+1 year',$this->dataDaVisualizzare);
            $this->dataDaVisualizzare = $newDate;
        }

        private function increaseMonth(){
            $newDate = strtotime('+1 month',$this->dataDaVisualizzare);
            $this->dataDaVisualizzare = $newDate;
        }

        private function increaseDay(){
            $newDate = strtotime('+1 day',$this->dataDaVisualizzare);
            $this->dataDaVisualizzare = $newDate;
        }

        private function decreaseYear(){
            $newDate = strtotime('-1 year',$this->dataDaVisualizzare);
            $this->dataDaVisualizzare = $newDate;
        }

        private function decreaseMonth(){
            $newDate = strtotime('-1 month',$this->dataDaVisualizzare);
            $this->dataDaVisualizzare = $newDate;
        }

        private function decreaseDay(){
            $newDate = strtotime('-1 day',$this->dataDaVisualizzare);
            $this->dataDaVisualizzare = $newDate;
        }
    //--------------------------------------------------------------------------------------------------------------------------------------------

        //Funzioni di utilità
    //--------------------------------------------------------------------------------------------------------------------------------------------
        private function createStatusFile($content){
            
            $file = fopen($this->tmp_file_path.STATUS_FILE, 'w');
            fwrite($file,$content);

            fclose($file);
        }
    //--------------------------------------------------------------------------------------------------------------------------------------------
    }
    