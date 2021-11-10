<?php

    class Log{
        private $_fileName;

        /**
         * @param string $fileName log file path
         */
        public function __construct($fileName)
        {
            if(empty($file_name)){
                $this->_fileName = LOG_FILE_PATH."unknown_log.log";
            }

            $this->_fileName = $fileName;
            if(!is_dir(dirname($fileName))){
                mkdir(dirname($fileName).'/', 0755, true);
            }
        }

        /**
         * 
         * @param string $text The text to append to the log file
         * @param string $typeOfLog The type of log
         * 
         * @return int|false The function returns the number of bytes that were written to the file, or false on failure.
         */
        public function append($text, $typeOfLog = "UNKNOWN"){
            $date = date("[Y-m-d H:i:s]", time());

            $typeOfLog = strtoupper($typeOfLog);
            $log = "$date [$typeOfLog] -> $text\n\n";
            return file_put_contents($this->_fileName,$log,FILE_APPEND);
        }
    }
