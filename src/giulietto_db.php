<?php

require_once 'log.php';

/**
 * GiuliettoBot database interface class
 *
 * @category Class
 * @author   Morrone Emanuele
 * @version 2.0
 */

class GiuliettoDB
{

    private $_conn;
    private $_log;


    /**
     * Set db connection and log path
     *
     * @param mysqli $mysqlConn Mysqli object for db connection
     */
    public function __construct($mysqlConn){
        $this->_conn = $mysqlConn;
    }

    /**
     * Set log file
     *
     * @param string $logFile File to save log
     */
    public function setLogFile($logFile){
        $this->_log = new Log($logFile);
    }

    public function query($query){
        try{
            return $this->_conn->query($query);
        }
        catch(Exception $e) {
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return $e->getCode();
        }
    }


//User table

    /**
     * Insert new user in the User table 
     * 
     * @param int $chatID The identifier of chat, if the user is a private account is equal to user id
     * @param string $fullName The name and surname of the private user or the title of the group, supergroup or channel
     * @param string $username Username, for private chats, supergroups and channels if available
     * @param string $room The room where the private user bed
     * @param string $type The type of user, it can be private, group, supergroup or channel
     * @param string $accountType The type of account
     * 
     * @return true|false Return true or false on failure
     */
    public function insertUser($chatID, $fullName, $username, $room, $type, $accountType, $language){
        try{
            $query = "INSERT INTO `User`(`ChatID`, `FullName`, `Username`, `Type`, `InscriptionDate`, `Room`, `Enabled`, `AccountType`, `Language`) VALUES (?,?,?,?,NOW(),?,TRUE,?,?)";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('isssiss', $chatID, $fullName, $username, $type, $room, $accountType,$language);

            return $stmt->execute();
        }
        catch(Exception $e) {
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * Delete User from the User table
     * 
     * @param int $chatID The identifier of chat, if the user is a private account is equal to user id
     * @return bool Return true or false on failure
     */
    public function deleteUser($chatID){
        try{
            $query = "DELETE FROM User where ChatID = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('i', $chatID);

            return $stmt->execute();
        }
        catch(Exception $e){

            $this->_log->append($e->getCode()." ".$e->getMessage()."\n".$e->getTraceAsString(),"error");
            return false;
        }
    }

    /**
     * Update the name of the user
     * 
     * @param int $chatID The identifier of chat, if the user is a private account is equal to user id
     * @param string $newName The new name of the user
     * 
     * @return bool Return true or false on failure
     */
    public function updateName($chatID, $newName){
        try{
            $query = "UPDATE User SET FullName = ? WHERE ChatID = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('si', $newName, $chatID);

            return $stmt->execute();
        }
        catch(Exception $e){
            $this->_log->append($e->getCode()." ".$e->getMessage()."\n".$e->getTraceAsString(),"error");
            return false;
        }
    }

    /**
     * Update the username of the user
     * 
     * @param int $chatID The identifier of chat, if the user is a private account is equal to user id
     * @param string $newUsername The new username of the user
     * 
     * @return bool Return true or false on failure
     */
    public function updateUsername($chatID, $newUsername){
        try{
            $query = "UPDATE User SET Username = ? WHERE ChatID = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('si', $newUsername, $chatID);

            return $stmt->execute();
        }
        catch(Exception $e){
            $this->_log->append($e->getCode()." ".$e->getMessage()."\n".$e->getTraceAsString(),"error");
            return false;
        }
    }
    
    /**
     * Update the email of the user
     * 
     * @param int $chatID The identifier of chat, if the user is a private account is equal to user id
     * @param string $newEmail The new email of the user
     * 
     * @return bool Return true or false on failure
     */
    public function updateEmail($chatID, $newEmail){

        if(!filter_var($newEmail, FILTER_VALIDATE_EMAIL)){
            return false;
        }

        try{
            $query = "UPDATE User SET Email = ? WHERE ChatID = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('si',  $newEmail, $chatID);

            return $stmt->execute();
        }
        catch(Exception $e){
            $this->_log->append($e->getCode()." ".$e->getMessage()."\n".$e->getTraceAsString(),"error");
            return false;
        }
    }

    /**
     * Update the room of the user
     * 
     * @param int $chatID The identifier of chat, if the user is a private account is equal to user id
     * @param int $newRoom The new room of the user
     * 
     * @return bool Return true or false on failure
     */
    public function updateRoom($chatID, $newRoom){
        try{
            $query = "UPDATE User SET Room = ? WHERE ChatID = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('ii', $newRoom, $chatID);

            return $stmt->execute();
        }
        catch(Exception $e){
            $this->_log->append($e->getCode()." ".$e->getMessage()."\n".$e->getTraceAsString(),"error");
            return false;
        }
    }

    /**
     * Update the room of the user
     *
     * @param int $chatID The identifier of chat, if the user is a private account is equal to user id
     * @param int $newAccountType The new account type of the user
     *
     * @return bool Return true or false on failure
     */
    public function updateAccountType($chatID, $newAccountType){
        try{
            $query = "UPDATE User SET AccountType = ? WHERE ChatID = ?";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('si', $newAccountType, $chatID);

            return $stmt->execute();
        }
        catch(Exception $e){
            $this->_log->append($e->getCode()." ".$e->getMessage()."\n".$e->getTraceAsString(),"error");
            return false;
        }
    }

    /**
     * Update the language of the user
     *
     * @param int $chatID The identifier of chat, if the user is a private account is equal to user id
     * @param string $language The language of the user
     *
     * @return bool Return true or false on failure
     */
    public function updateLanguage($chatID, $language){
        try{
            $query = "UPDATE User SET Language = ? WHERE ChatID = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('si', $language, $chatID);

            return $stmt->execute();
        }
        catch(Exception $e){
            $this->_log->append($e->getCode()." ".$e->getMessage()."\n".$e->getTraceAsString(),"error");
            return false;
        }
    }

    /**
     * Enable or disable user
     * 
     * @param int $chatID The identifier of chat, if the user is a private account is equal to user id
     *
     * @return bool true or false on failure
     */
    public function changeUserState($chatID){
        try{
            $query = "
                        UPDATE User 
                        SET Enabled = NOT Enabled
                        WHERE ChatID = ?;
            ";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('i', $chatID);

            return $stmt->execute();
        }
        catch(Exception $e){
            $this->_log->append($e->getCode()." ".$e->getMessage()."\n".$e->getTraceAsString(),"error");
            return false;
        }
    }

    /**
     * Get the user info
     *
     * @param int $chatID The chatID of the user
     *
     * @return array|false An associative array with the user info or false on failure
     */
    public function getUser($chatID){
        try{
            $query = "SELECT * FROM User U WHERE U.ChatID = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('i',$chatID);
            $stmt->execute();

            $result = $stmt->get_result();

            return $result->fetch_assoc();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * Get the chatID of user
     *
     * @param string $name The name of the user
     *
     * @return int|false The chatID of the user or false on failure
     */
    public function getChatID($name){
        try{

            $query = "SELECT U.ChatID FROM User U WHERE U.FullName = ?";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('s',$name);
            $stmt->execute();

            $result = $stmt->get_result();

            $result = $result->fetch_assoc();
            return $result["ChatID"];

        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * Get all the user registered
     *
     * @param bool $enabled If it is true get only enable users, if it is false get all the registered users
     *
     * @return mysqli_result|false Return a result-set or false on failure
     */
    public function getUserList($enabled = true){
        try{
            if($enabled){
                $query = "SELECT * FROM User WHERE Enabled IS TRUE ORDER BY Room;";
                $stmt = $this->_conn->prepare($query);
                $stmt->bind_param('i', $enabled);
            }
            else {
                $query = "SELECT * FROM User WHERE 1 ORDER BY Room;";
                $stmt = $this->_conn->prepare($query);
            }

            $stmt->execute();

            return $stmt->get_result();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * @param $chatID
     * @return false|mysqli_result
     */
    public function getRoommateList($chatID){
        try{
            $query = "SELECT * FROM User U WHERE U.ChatID <> ? AND U.Enabled IS TRUE AND U.Room = (SELECT U1.Room FROM User U1 WHERE U1.ChatID = ?);";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('ii',$chatID, $chatID);
            $stmt->execute();

            return $stmt->get_result();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * @param $notification
     * @return false|mysqli_result
     */
    public function getAllUsersForNotification($notification){
        try{
            $query = "SELECT U.* FROM User U INNER JOIN AccountType A ON U.AccountType = A.Name INNER JOIN Notification N ON A.Notification = N.Name WHERE N.$notification IS TRUE AND U.Enabled IS TRUE;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param("s", $notification);
            $stmt->execute();

            return $stmt->get_result();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * @param $room
     * @return false|mysqli_result
     */
    public function getUserInRoom($room){
        try{
            $query = "SELECT * FROM User U WHERE U.Room = ? AND U.Enabled IS TRUE;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param("i", $room);
            $stmt->execute();

            return $stmt->get_result();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }


//AccountType table

    /**
     * Get the name of the account type given the password
     *
     * @param string $password The encrypted password of the account
     *
     * @return string|false the name of the account type or false on failure
     */
    public function getAccountType($password){
        try{

            $query = "  SELECT Name AS AccountType
                        FROM AccountType
                        WHERE Password = ?;
                    ";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('s',$password);
            $stmt->execute();

            $result = $stmt->get_result();

            $result = $result->fetch_assoc();
            return $result["AccountType"];

        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * Get the list of the permission for the account type
     *
     * @param string $accountType The account type of the account
     *
     * @return array|false The array of the permission or false on failure
     */
    public function getPermission($accountType){
        try{

            $query = "
                        SELECT *
                        FROM Permission P
                        WHERE P.Name = (
                            SELECT A.Permission
                            FROM AccountType A
                            WHERE A.Name = ?
                        );
            ";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('s',$accountType);
            $stmt->execute();

            $result = $stmt->get_result();
            $result = $result->fetch_assoc();
            unset($result['Name']);

            return  $result;
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * Get the list of the allowed and not allowed notification for the account type
     *
     * @param string $accountType The account type of the account
     *
     * @return array|false The array of the notification or false on failure
     */
    public function getNotification($accountType){
        try{
            $query = "
                        SELECT * 
                        FROM Notification N
                        WHERE N.Name = (
                                SELECT A.Notification
                                FROM AccountType A
                                WHERE A.Name = ?
                        );
            ";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('s',$accountType);
            $stmt->execute();

            $result = $stmt->get_result();
            $result = $result->fetch_assoc();
            unset($result['Name']);

            return  $result;
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }


//Absence Table

    /**
     * Get users absent on a specific date
     *
     * @param $date string The date whose absences you want to view, the format of the date must be Y-m-d
     * @return mysqli_result|false Return a result-set or false on failure
     */
    public function getAbsentsList($date){
        try{
            $query = "SELECT U.ChatID, U.FullName, U.Username, U.Room, A.LeavingDate, A.ReturnDate FROM User U INNER JOIN Absence A ON U.ChatID = A.User WHERE ? BETWEEN A.LeavingDate AND A.ReturnDate;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('s',$date);
            $stmt->execute();

            return $stmt->get_result();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }

    }

    /**
     * Get users returning to the facility
     *
     * @param $date string The date whose absences you want to view, the format of the date must be Y-m-d
     * @return mysqli_result|false Return a result-set or false on failure
     */
    public function getIncomingRoomer($date){
        try{
            $query = "SELECT * FROM Absence A INNER JOIN User U ON A.User = U.ChatID WHERE A.ReturnDate = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('s',$date);
            $stmt->execute();

            return $stmt->get_result();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }

    }

    /**
     * Get all users absent
     *
     * @return mysqli_result|false Return a result-set or false on failure
     */
    public function getAbsenceReport(){
        try{
            $query = "SELECT * FROM Absence A INNER JOIN User U ON A.User = U.ChatID WHERE 1";
            $stmt = $this->_conn->prepare($query);
            $stmt->execute();

            return $stmt->get_result();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }

    }

    /**
     * Get all the absence for a specific user
     *
     * @param $chatID int The user chatID whose absence you want to view
     * @return mysqli_result|false Return a result-set or false on failure
     */
    public function getMyAbsence($chatID){
        try{
            $query = "Select * FROM Absence A WHERE A.User = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('i',$chatID);
            $stmt->execute();

            return $stmt->get_result();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }

    }

    /**
     * @param $chatID
     * @param $leavingDate
     * @param $returnDate
     * @return array|false|null
     */
    public function getAbsence($chatID, $leavingDate, $returnDate){
        try{
            $query = "SELECT * FROM Absence A WHERE A.User = ? AND A.LeavingDate = ? AND A.ReturnDate = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('iss',$chatID, $leavingDate, $returnDate);
            $stmt->execute();

            $result = $stmt->get_result();

            return $result->fetch_assoc();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * Insert new user in the User table
     *
     * @param int $chatID The identifier of chat, if the user is a private account is equal to user id
     * @param string $leavingDate The date when the use leave the house, the format is Y-m-d
     * @param string $returnDate The date when the use return to the house, the format is Y-m-d
     *
     * @return bool Return true or false on failure
     */
    public function insertAbsence($chatID, $leavingDate, $returnDate){
        try{
            $query = "INSERT INTO Absence (User, LeavingDate, ReturnDate) VALUES (?,?,?);";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('iss', $chatID, $leavingDate, $returnDate);

            return $stmt->execute();
        }
        catch(Exception $e) {
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * Update an absence user in the User table
     *
     * @param int $chatID The identifier of chat, if the user is a private account is equal to user id
     * @param string $leavingDate The date when the use leave the house, the format is Y-m-d
     * @param string $returnDate The date when the use return to the house, the format is Y-m-d
     *
     * @return bool Return true or false on failure
     */
    public function updateAbsence($chatID, $leavingDate, $returnDate, $newLeavingDate, $newReturnDate){
        try{
            $query = "UPDATE Absence A SET A.LeavingDate = ?, A.ReturnDate = ? WHERE A.User = ? AND A.LeavingDate = ? AND A.ReturnDate = ?; ";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('ssiss',  $newLeavingDate, $newReturnDate, $chatID, $leavingDate, $returnDate);

            return $stmt->execute();
        }
        catch(Exception $e) {
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * @param $chatID
     * @param $leavingDate
     * @param $returnDate
     * @return bool
     */
    public function deleteAbsence($chatID, $leavingDate, $returnDate){
        try{
            $query = "DELETE FROM Absence WHERE User = ? AND LeavingDate = ? AND ReturnDate = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('iss', $chatID, $leavingDate, $returnDate);

            return $stmt->execute();
        }
        catch(Exception $e) {
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }


//Guest Table

    /**
     *
     * Insert new guest in teh guest table
     *
     * @param $chatID int ChatID of the host user
     * @param $guestName string Name of the guest
     * @param $checkInDate string Guest arrival date
     * @param $leavingDate string Guest leaving date
     * @param $room int Room where the guest is staying
     *
     * @return bool Return true or false on failure
     */
    public function insertGuest($chatID, $guestName, $checkInDate, $leavingDate, $room, $registrationDate){
        try{
            $query = "INSERT INTO `Guest`(`User`, `Name`, `CheckInDate`, `LeavingDate`, `Room`, `RegistrationDate`) VALUES (?,?,?,?,?,?)";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('isssis', $chatID, $guestName, $checkInDate, $leavingDate, $room, $registrationDate);

            return $stmt->execute();
        }
        catch(Exception $e) {
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     *
     * Insert new guest in teh guest table
     *
     * @param $chatID int ChatID of the host user
     * @param $guestName string Name of the guest
     * @param $checkInDate string Guest arrival date
     * @param $leavingDate string Guest leaving date
     * @param $newCheckInDate string new Guest arrival date
     * @param $newLeavingDate string new Guest leaving date
     *
     * @return bool Return true or false on failure
     */
    public function updateGuest($chatID, $guestName, $checkInDate, $leavingDate, $newCheckInDate, $newLeavingDate){
        try{
            $query = "UPDATE Guest G SET G.CheckInDate = ?, G.LeavingDate = ? WHERE G.User = ? AND G.Name = ? AND G.CheckInDate = ? AND G.LeavingDate = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('sssiss', $newCheckInDate, $newLeavingDate, $chatID, $guestName, $checkInDate, $leavingDate);

            return $stmt->execute();
        }
        catch(Exception $e) {
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * Get the guest info
     *
     * @param $chatID int ChatID of the host user
     * @param $guestName string Name of the guest
     * @param $checkInDate string Guest arrival date
     * @param $leavingDate string Guest leaving date
     *
     * @return array|false An associative array with the guest info or false on failure
     */
    public function getGuest($chatID, $guestName, $checkInDate, $leavingDate){
        try{
            $query = "SELECT * FROM Guest G WHERE G.User = ? AND G.Name = ? AND G.CheckInDate = ? AND G.LeavingDate = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('isss',$chatID, $guestName, $checkInDate, $leavingDate);
            $stmt->execute();

            $result = $stmt->get_result();

            return $result->fetch_assoc();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

     /**
     * Get the guest info
     *
     * @param $id int unique id that identifies the guest
     *
     * @return array|false An associative array with the guest info or false on failure
     */
    public function getGuestById($id){
        try{
            $query = "SELECT * FROM Guest G WHERE G.ID = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('i',$id);
            $stmt->execute();

            $result = $stmt->get_result();

            return $result->fetch_assoc();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * Get guest on a specific date
     *
     * @param $checkInDate string The first date of the interval whose guests you want to view, the date format is Y-m-d
     * @param $leavingDate string The second date of the interval whose guests you want to view, the date format is Y-m-d
     * @return mysqli_result|false Return a result-set or false on failure
     */
    public function getGuestList($checkInDate, $leavingDate, $user = NULL){
        try{

            if(is_null($user)){
                $query = "
                    SELECT G.*, U.FullName
                    FROM Guest G INNER JOIN User U ON G.User = U.ChatID
                    WHERE G.CheckInDate <= ? AND ? <= G.LeavingDate;
                ";//leaving, checkIn
                $stmt = $this->_conn->prepare($query);
                $stmt->bind_param('ss',$leavingDate, $checkInDate);
            }
            else{
                $query = "
                        SELECT G.*, U.FullName
                        FROM Guest G INNER JOIN User U ON G.User = U.ChatID
                        WHERE G.User = ? AND
                        G.CheckInDate <= ? AND ? <= G.LeavingDate;";
                $stmt = $this->_conn->prepare($query);
                $stmt->bind_param('iss',$user, $leavingDate, $checkInDate);
            }

            $stmt->execute();

            return $stmt->get_result();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * Get all the guest for a specific user
     *
     * @param $chatID int The user chatID whose guests you want to view
     * @return mysqli_result|false Return a result-set or false on failure
     */
    public function getMyGuest($chatID){
        try{
            $query = "Select * FROM Guest A WHERE A.User = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('i',$chatID);
            $stmt->execute();

            return $stmt->get_result();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }

    }

    /**
     * @param $chatID
     * @param $guestName
     * @param $checkInDate
     * @param $leavingDate
     * @return bool
     */
    public function deleteGuest($chatID, $guestName, $checkInDate, $leavingDate){
        try{
            $query = "DELETE FROM Guest WHERE User = ? AND Name = ? AND CheckInDate = ? AND LeavingDate = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('isss', $chatID, $guestName, $checkInDate, $leavingDate);

            return $stmt->execute();
        }
        catch(Exception $e) {
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * Get all guest
     *
     * @return mysqli_result|false Return a result-set or false on failure
     */
    public function getGuestReport(){
        try{
            $query = "SELECT U.FullName, U.Username, U.Email, U.Room AS UserRoom, G.* FROM Guest G INNER JOIN User U ON G.User = U.ChatID WHERE 1";
            $stmt = $this->_conn->prepare($query);
            $stmt->execute();

            return $stmt->get_result();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * Get users returning to the facility
     *
     * @param $date string The date whose absences you want to view, the format of the date must be Y-m-d
     * @return mysqli_result|false Return a result-set or false on failure
     */
    public function getIncomingGuest($date){
        try{
            $query = "SELECT * FROM Guest G WHERE G.CheckInDate = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('s',$date);
            $stmt->execute();

            return $stmt->get_result();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }

    }


//Squad Table

    public function createGroup($groupName){
        try{
            $query = "INSERT INTO Squad VALUES(?);";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('s',$groupName);

            return $stmt->execute();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * @return array|false
     */
    public function getGroupList(){
        try{
            $query = "Select * FROM Squad WHERE 1 ORDER BY SUBSTR(Name FROM 1 FOR 2), SUBSTR(Name FROM -2 FOR 2);";
            $stmt = $this->_conn->prepare($query);
            $stmt->execute();

            $gruppi = $stmt->get_result();
            $ret = [];
            while($row = $gruppi->fetch_assoc()){
               $ret[$row["Name"]] = $row["LastExecution"];
            }

            return $ret;
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * @param $chatID
     * @param $groupName
     * @return bool
     */
    public function insertUserInGroup($chatID, $groupName){
        try{
            $query = "INSERT INTO Member(User,Squad) VALUES(?,?);";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('is', $chatID, $groupName);

            return $stmt->execute();
        }
        catch(Exception $e) {
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * @param $chatID
     * @param $groupName
     * @return bool
     */
    public function removeUserFromGroup($chatID, $groupName){
        try{
            $query = "DELETE FROM Member WHERE User = ? AND Squad = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('is', $chatID, $groupName);

            return $stmt->execute();
        }
        catch(Exception $e) {
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }


//Member Table

    /**
     * @param $groupName
     * @return array|false
     */
    public function getUserInGroup($groupName){
        try{
            $query = "SELECT U.* FROM Member M INNER JOIN User U ON M.User = U.ChatID WHERE M.Squad = ? AND U.Enabled IS TRUE;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('s',$groupName);
            $stmt->execute();

            $user =  $stmt->get_result();

            $ret = [];
            while($row = $user->fetch_assoc()){
                $ret[] = $row;
            }

            return $ret;
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }

    }

    /**
     * @param $chatID
     * @return array|false
     */
    public function getGroupsByUser($chatID){
        try{
            $query = "SELECT M.Squad, GROUP_CONCAT(E.TypeOfTurn ORDER BY E.TypeOfTurn) AS TypeOfTurn FROM Member M  LEFT JOIN Execution E ON M.Squad = E.Squad WHERE M.User = ? GROUP BY M.Squad ORDER BY E.Squad;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('i',$chatID);
            $stmt->execute();

            $group =  $stmt->get_result();

            $ret = [];
            while($row = $group->fetch_assoc()){
                $ret[$row['Squad']] = $row['TypeOfTurn'];
            }

            return $ret;
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }

    }

    /**
     * @param $date
     * @return array|false|null
     */
    public function getSeatsNum($date){
        try{
            $query = "       
                SELECT  NumUtenti.numUtenti, PostiTotali.postiTotali, NumAssenti.numAssenti, NumOspiti.numOspiti, PostiTotali.postiTotali - NumUtenti.numUtenti + NumAssenti.numAssenti - NumOspiti.numOspiti AS FreeSeats
                FROM (
                    
                    SELECT SUM(R.Beds) AS postiTotali
                    FROM Room R
                    WHERE 1
                
                ) AS PostiTotali INNER JOIN (
                
                    SELECT COUNT(*) AS numUtenti
                    FROM User U INNER JOIN 
                         AccountType AT ON U.AccountType = AT.Name INNER JOIN
                         Permission P ON AT.Permission = P.Name
                    WHERE U.Enabled IS TRUE AND 
                          P.IsOccupant IS TRUE
                
                ) AS NumUtenti INNER JOIN (
                
                    SELECT COUNT(*) AS numOspiti
                    FROM Guest G
                    WHERE ? BETWEEN G.CheckInDate AND G.LeavingDate 
                
                ) AS NumOspiti INNER JOIN (
                
                    SELECT COUNT(*) AS numAssenti
                    FROM Absence A
                    WHERE ? BETWEEN A.LeavingDate AND A.ReturnDate
                
                ) AS NumAssenti;
            ";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('ss',$date, $date);
            $stmt->execute();

            $result = $stmt->get_result();

            return $result->fetch_assoc();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * @param $firstUser int
     * @param $secondUser int
     * @param $firstGroup string
     * @param $secondGroup string
     * @return bool
     */
    public function swapGroup($firstUser, $secondUser, $firstGroup, $secondGroup){
        try{

            $this->_conn->autocommit(false);

            $query = "UPDATE Member SET Squad = ? WHERE User = ? AND Squad = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('sis',$secondGroup, $firstUser, $firstGroup);
            $stmt->execute();

            $stmt->bind_param('sis', $firstGroup, $secondUser, $secondGroup);
            $stmt->execute();

            return  $this->_conn->commit();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }


//Type of turn table

    public function createNewTypeOfTurn($name, $frequency, $firstExecution, $userBySquad, $groupFrequency){
        try{
            $query = "INSERT INTO `TypeOfTurn`(`Name`, `Frequency`, `FirstExecution`, `UsersBySquad`, `SquadFrequency`) VALUES (?,?,?,?,?)";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('sisii', $name, $frequency, $firstExecution, $userBySquad, $groupFrequency);

            return $stmt->execute();
        }
        catch(Exception $e) {
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    public function getTypeOfTurnList(){
        try{
            $query = "SELECT * FROM TypeOfTurn WHERE 1;";
            $stmt = $this->_conn->prepare($query);
            $stmt->execute();

            return $stmt->get_result();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * Get all the info for all the type of turn
     *
     * @return array|false Return a result-set or false on failure
     */
    public function getTypeOfTurn($turn){
        try{
            $query = "SELECT * FROM TypeOfTurn T WHERE T.Name = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('s',$turn);
            $stmt->execute();

            return $stmt->get_result()->fetch_assoc();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    public function incStep($name)
    {
        try{
            $query = "UPDATE TypeOfTurn SET CurrentStep = CurrentStep +1 WHERE Name = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('s',$name);

            return $stmt->execute();
        }
        catch(Exception $e){
            $this->_log->append($e->getCode()." ".$e->getMessage()."\n".$e->getTraceAsString(),"error");
            return false;
        }
    }

    public function updateTypeOfTurnFrequency($typeOfTurn, $newFrequency){
        try{
            $query = "UPDATE TypeOfTurn SET Frequency = ? WHERE Name = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('is', $newFrequency, $typeOfTurn);

            return $stmt->execute();
        }
        catch(Exception $e){
            $this->_log->append($e->getCode()." ".$e->getMessage()."\n".$e->getTraceAsString(),"error");
            return false;
        }
    }

    public function updateUserByGroup($typeOfTurn, $usersByGroup){
        try{
            $query = "UPDATE TypeOfTurn SET UsersBySquad = ? WHERE Name = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('is', $usersByGroup, $typeOfTurn);

            return $stmt->execute();
        }
        catch(Exception $e){
            $this->_log->append($e->getCode()." ".$e->getMessage()."\n".$e->getTraceAsString(),"error");
            return false;
        }
    }

//Execution table

    public function addExecution($typeOfTurn, $group, $step){
        try{
            $query = "INSERT INTO Execution VALUES(?,?,?);";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('ssi',$typeOfTurn, $group, $step);

            return $stmt->execute();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * @param $typeofTurn
     * @return false|int
     */
    public function getStepNumOfTurn($typeofTurn){
        try{
            $query = "SELECT COUNT(*) AS Num FROM Execution E WHERE E.TypeOfTurn = ?";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('s',$typeofTurn);
            $stmt->execute();

            $result = $stmt->get_result();

            return $result->fetch_assoc()["Num"];
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * @param $typeOfTurn
     * @param int $next
     * @return array|false|null
     */
    public function getGroupWillDoTheNextTurn($typeOfTurn, $next = 0){
        try{
            $turn = $this->getTypeOfTurn($typeOfTurn);

            if(is_null($turn["LastExecution"]) or  $turn["LastExecution"] == date("Y-m-d") ){
                $next += $turn["CurrentStep"];
            }
            else{
                $next += $turn["CurrentStep"]+1;
            }

            if($next > $this->getStepNumOfTurn($typeOfTurn)-1){
                $next = $next%$this->getStepNumOfTurn($typeOfTurn);
            }

            $query = "SELECT * FROM Execution E WHERE E.TypeOfTurn = ? AND E.Step = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('si',$typeOfTurn, $next);
            $stmt->execute();

            $result = $stmt->get_result();

            return $result->fetch_assoc();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

//Turn Execution History table

    /**
     * @param $date
     * @param $typeOfTurn
     * @return array|false|null
     */
    public function getUsersOfTurnPerformed($date, $typeOfTurn){
        try{
            $query = "SELECT TEH.Date, TEH.TypeOfTurn, GROUP_CONCAT(TEH.User SEPARATOR ', ') AS Users FROM TurnExecutionHistory TEH WHERE TEH.Date = ? AND TEH.TypeOfTurn = ? GROUP BY TEH.Date, TEH.TypeOfTurn;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('ss',$date, $typeOfTurn);
            $stmt->execute();

            $result = $stmt->get_result();
            return $result->fetch_assoc();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * @param $name string
     * @param $typeOfTurn string
     * @return int|false
     */
    public function getLastExecution($name, $typeOfTurn){
        try{
            $query = "SELECT UNIX_TIMESTAMP(MAX(TEH.Date)) AS LastExecution FROM TurnExecutionHistory TEH WHERE TEH.User = ? AND TEH.TypeOfTurn = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('ss',$name, $typeOfTurn);
            $stmt->execute();

            $result = $stmt->get_result();
            return $result->fetch_assoc()['LastExecution'];
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    /**
     * @return false|mysqli_result
     */
    public function getTurnHistory(){
        try{
            $query = "SELECT TEH.Date, TEH.TypeOfTurn, GROUP_CONCAT(TEH.User) AS Users FROM TurnExecutionHistory TEH GROUP BY TEH.Date, TEH.TypeOfTurn;";
            $stmt = $this->_conn->prepare($query);
            $stmt->execute();

            return $stmt->get_result();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

//Room table

    public function createRoom($room, $seats){
        try{
            $query = "INSERT INTO Room VALUES(?,?);";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('ii',$room, $seats);

            return $stmt->execute();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

    public function getUerInRoom($room){
        try{
            $query = "SELECT * FROM Room R INNER JOIN User U ON R.Num = U.Room WHERE R.Num = ?;";
            $stmt = $this->_conn->prepare($query);
            $stmt->bind_param('i',$room);
            $stmt->execute();

            return $stmt->get_result();
        }
        catch (Exception $e){
            $this->_log->append($e->getCode() . " " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
            return false;
        }
    }

}