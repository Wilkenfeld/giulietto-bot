<?php

require_once 'absencesController.php';
require_once '../config/config.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode( '/', $uri );

if (!isset($conn)) {
    try{
        $conn = new mysqli(DB_HOST,DB_USERNAME,DB_PASSWORD, DB_NAME);
        $db = new GiuliettoDB($conn);
    }
    catch(Exception $e){
        $log->append($e->getCode()." ".$e->getMessage()."\n".$e->getTraceAsString(),"error");
        exit();
    }
}

// all of our endpoints start with /absences
// everything else results in a 404 Not Found
if ($uri[1] !== 'absences') {
    header("HTTP/1.1 404 Not Found");
}

// the user id is, of course, optional and must be a number:
$userId = null;
if (isset($uri[2])) {
    $userId = (int) $uri[2];
}

$requestMethod = $_SERVER["REQUEST_METHOD"];

// pass the request method and user ID to the PersonController and process the HTTP request:
$controller = new PersonController($dbConnection, $requestMethod, $userId);
$controller->processRequest();

?>