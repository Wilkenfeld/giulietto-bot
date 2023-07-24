<?php

$config = array(
  "prod" => array(
	"token" => "1960477482:AAFa9gP_zhLWIErxftmTxaSe9Wh2ceahNng",
    "username" => "Giulietto2_bot",
    "db_host" => "localhost",
    "db_name" => "my_giulietto",
    "db_username" => "",
    "db_password" => "",
    "db_table_prefix" => "",
    "tmp_file_path" => "../tmp_file/",
    "log_file_path" => "../log/",
    "files_path" => "../files/",
    "locale" =>  array(
          "it" => "it_IT.UTF-8",
          "en" => "en_US.UTF-8",
          "de" => "de_DE.UTF-8",
          "fr" => "fr_FR.UTF-8",
          "es" => "es_ES.UTF-8"
      )
  ),
  "dev" => array(
  	"token" => "5949361423:AAGPu1ce65kUqDlnnutQkvxdYTVmw5fXcmY",
    "username" => "GiuliettoTestBot",
    "db_host" => "localhost",
    "db_name" => "giulietto_db",
    "db_username" => "giulietto",
    "db_password" => "giulietto",
    "db_table_prefix" => "dev",
    "admins" => array(
        "550333131"
    ),
    "commands" => array("paths" => array(__DIR__ . '/CustomCommands'), "configurations" => array()),
    "tmp_file_path" => "../tmp_file/dev/",
    "log_file_path" => "../log/dev/",
    "files_path" => "../files/dev/",
    "locale" =>  array(
          "it" => "it_IT.UTF-8",
          "en" => "en_US.UTF-8",
          "de" => "de_DE.UTF-8",
          "fr" => "fr_FR.UTF-8",
          "es" => "es_ES.UTF-8"
      )
  )
    );

?>