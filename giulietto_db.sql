-- phpMyAdmin SQL Dump
-- version 4.1.7
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Aug 04, 2022 at 10:36 AM
-- Server version: 8.0.26
-- PHP Version: 5.6.40

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `my_gsvg`
--
CREATE DATABASE IF NOT EXISTS `my_gsvg` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `my_gsvg`;

-- --------------------------------------------------------

--
-- Table structure for table `Absence`
--

DROP TABLE IF EXISTS `Absence`;
CREATE TABLE IF NOT EXISTS `Absence` (
  `User` bigint NOT NULL,
  `LeavingDate` date NOT NULL,
  `ReturnDate` date NOT NULL,
  PRIMARY KEY (`User`,`LeavingDate`,`ReturnDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `Absence`
--
DROP TRIGGER IF EXISTS `NewAbsence`;
DELIMITER //
CREATE TRIGGER `NewAbsence` BEFORE INSERT ON `Absence`
 FOR EACH ROW BEGIN 
    
    IF New.LeavingDate > New.ReturnDate THEN
    	SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'returnDate antecedente a leavingDate';
    END IF;
    
    
    IF EXISTS(SELECT * FROM `Absence` A WHERE New.LeavingDate <= A.ReturnDate AND New.ReturnDate >= A.LeavingDate AND New.User = A.User) THEN
    
    	SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Date overlap';
    
    END IF;
END
//
DELIMITER ;
DROP TRIGGER IF EXISTS `UpdateAbsence`;
DELIMITER //
CREATE TRIGGER `UpdateAbsence` BEFORE UPDATE ON `Absence`
 FOR EACH ROW BEGIN
	
   IF New.LeavingDate > New.ReturnDate THEN
    	SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'returnDate antecedente a leavingDate';
    END IF;
    
END
//
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `AccountType`
--

DROP TABLE IF EXISTS `AccountType`;
CREATE TABLE IF NOT EXISTS `AccountType` (
  `Name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Password` binary(64) NOT NULL,
  `Permission` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Notification` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`Name`) USING BTREE,
  UNIQUE KEY `Name` (`Password`) USING BTREE,
  UNIQUE KEY `Permission_Notification` (`Permission`,`Notification`),
  KEY `Notification` (`Notification`),
  KEY `Permission` (`Permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Execution`
--

DROP TABLE IF EXISTS `Execution`;
CREATE TABLE IF NOT EXISTS `Execution` (
  `TurnType` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Squad` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Step` int unsigned NOT NULL,
  PRIMARY KEY (`Squad`,`TurnType`,`Step`),
  UNIQUE KEY `TypeOfTurn` (`TurnType`,`Step`),
  KEY `Turn` (`TurnType`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Guest`
--

DROP TABLE IF EXISTS `Guest`;
CREATE TABLE IF NOT EXISTS `Guest` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `User` bigint NOT NULL,
  `Name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `CheckInDate` date NOT NULL,
  `LeavingDate` date NOT NULL,
  `Room` tinyint unsigned NOT NULL,
  `RegistrationDate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`User`,`CheckInDate`,`Name`,`LeavingDate`) USING BTREE,
  UNIQUE KEY `ID` (`ID`) USING BTREE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=4 ;

-- --------------------------------------------------------

--
-- Table structure for table `Member`
--

DROP TABLE IF EXISTS `Member`;
CREATE TABLE IF NOT EXISTS `Member` (
  `User` bigint NOT NULL,
  `Squad` int unsigned NOT NULL,
  PRIMARY KEY (`User`,`Squad`),
  KEY `Squad` (`Squad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Notification`
--

DROP TABLE IF EXISTS `Notification`;
CREATE TABLE IF NOT EXISTS `Notification` (
  `Name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `NewUser` tinyint(1) NOT NULL DEFAULT '0',
  `NewGuest` tinyint(1) NOT NULL DEFAULT '0',
  `EmailNewGuest` tinyint(1) NOT NULL DEFAULT '0',
  `NewAbsence` tinyint(1) NOT NULL DEFAULT '0',
  `DeletedAbsence` tinyint(1) NOT NULL DEFAULT '0',
  `IncomingGuest` tinyint(1) NOT NULL DEFAULT '0',
  `IncomingRoomer` tinyint(1) NOT NULL DEFAULT '0',
  `NewReport` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Permission`
--

DROP TABLE IF EXISTS `Permission`;
CREATE TABLE IF NOT EXISTS `Permission` (
  `Name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ExportUserList` tinyint(1) NOT NULL DEFAULT '0',
  `ExportGuest` tinyint(1) NOT NULL DEFAULT '0',
  `ExportAbsence` tinyint(1) NOT NULL DEFAULT '0',
  `BroadcastMsg` tinyint(1) NOT NULL DEFAULT '0',
  `PostedInGroup` tinyint(1) NOT NULL DEFAULT '0',
  `ReportsMaintenance` tinyint(1) NOT NULL DEFAULT '0',
  `ManageMaintenance` tinyint(1) NOT NULL DEFAULT '0',
  `ShowUser` tinyint(1) NOT NULL DEFAULT '0',
  `ManageUser` tinyint(1) NOT NULL DEFAULT '0',
  `ShowGroups` tinyint(1) NOT NULL DEFAULT '0',
  `ManageGroups` tinyint(1) NOT NULL DEFAULT '0',
  `SwapGroup` tinyint(1) DEFAULT '0',
  `SetEmail` tinyint(1) NOT NULL DEFAULT '0',
  `UploadGuideLine` tinyint(1) NOT NULL DEFAULT '0',
  `ManageTurnType` tinyint(1) NOT NULL DEFAULT '0',
  `ShowRooms` tinyint(1) NOT NULL DEFAULT '0',
  `ManageRoom` tinyint(1) NOT NULL DEFAULT '0',
  `ManageMyAbsence` tinyint(1) NOT NULL DEFAULT '0',
  `ShowAbsents` tinyint(1) NOT NULL DEFAULT '0',
  `ManageMyGuest` tinyint(1) NOT NULL DEFAULT '0',
  `ShowGuests` tinyint(1) NOT NULL DEFAULT '0',
  `ShowTurnCalendar` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Table structure for table `Quote`
--

DROP TABLE IF EXISTS `Quote`;
CREATE TABLE IF NOT EXISTS `Quote` (
  `Author` bigint DEFAULT NULL,
  `Msg` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Response` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`Msg`),
  KEY `Author` (`Author`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Report`
--

DROP TABLE IF EXISTS `Report`;
CREATE TABLE IF NOT EXISTS `Report` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `Description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `WhoReports` bigint NOT NULL,
  `ReportsDateTime` datetime NOT NULL,
  `WhoResolve` bigint DEFAULT NULL,
  `ResolutionDateTime` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `WhoReports` (`WhoReports`),
  KEY `WhoResolve` (`WhoResolve`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Table structure for table `Room`
--

DROP TABLE IF EXISTS `Room`;
CREATE TABLE IF NOT EXISTS `Room` (
  `Num` tinyint unsigned NOT NULL,
  `Beds` tinyint unsigned NOT NULL DEFAULT '2',
  PRIMARY KEY (`Num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Squad`
--

DROP TABLE IF EXISTS `Squad`;
CREATE TABLE IF NOT EXISTS `Squad` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `Name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=6 ;

-- --------------------------------------------------------

--
-- Table structure for table `TurnExecutionHistory`
--

DROP TABLE IF EXISTS `TurnExecutionHistory`;
CREATE TABLE IF NOT EXISTS `TurnExecutionHistory` (
  `Date` date NOT NULL,
  `TurnType` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `User` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`Date`,`TurnType`,`User`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TurnType`
--

DROP TABLE IF EXISTS `TurnType`;
CREATE TABLE IF NOT EXISTS `TurnType` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `Name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Frequency` int NOT NULL,
  `FirstExecution` date NOT NULL,
  `LastExecution` date DEFAULT NULL,
  `CurrentStep` int NOT NULL DEFAULT '0',
  `UsersBySquad` tinyint unsigned NOT NULL DEFAULT '2',
  `SquadFrequency` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT AUTO_INCREMENT=3 ;

--
-- Triggers `TurnType`
--
DROP TRIGGER IF EXISTS `IncStep`;
DELIMITER //
CREATE TRIGGER `IncStep` BEFORE UPDATE ON `TurnType`
 FOR EACH ROW BEGIN

	DECLARE NumRow INT DEFAULT 0;
    
    SELECT COUNT(*) INTO NumRow
    FROM Execution E
    WHERE E.TurnType = NEW.Name;
    
    IF OLD.CurrentStep < NEW.CurrentStep THEN
      #IF OLD.FirstExecution > CURRENT_DATE THEN
      #	SIGNAL SQLSTATE '45000'
      #    SET MESSAGE_TEXT = "Turno non ancora attivo";
      #END IF;
      
      IF NEW.CurrentStep > NumRow-1 THEN
        SET NEW.CurrentStep = 0;
      END IF;
      
      SET NEW.LastExecution = CURRENT_DATE;
        
    END IF;
END
//
DELIMITER ;
DROP TRIGGER IF EXISTS `SaveInHistory`;
DELIMITER //
CREATE TRIGGER `SaveInHistory` AFTER UPDATE ON `TurnType`
 FOR EACH ROW BEGIN

	DECLARE users TEXT;

	IF OLD.CurrentStep < NEW.CurrentStep THEN
    	
        INSERT INTO TurnExecutionHistory(Date, TurnType, User)
        SELECT CURRENT_DATE, NEW.Name, U.FullName
        FROM Member M INNER JOIN User U ON M.User = U.ChatID
        WHERE M.Squad = (
            SELECT E.Squad
            FROM Execution E
            WHERE E.Step = NEW.CurrentStep AND E.TurnType = NEW.Name
        ) AND U.Enabled IS TRUE;
    END IF;

END
//
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `User`
--

DROP TABLE IF EXISTS `User`;
CREATE TABLE IF NOT EXISTS `User` (
  `ChatID` bigint NOT NULL,
  `FullName` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Username` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Type` enum('private','group') COLLATE utf8mb4_unicode_ci NOT NULL,
  `Email` varchar(319) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `InscriptionDate` datetime NOT NULL,
  `Room` tinyint unsigned DEFAULT NULL,
  `Enabled` tinyint(1) NOT NULL DEFAULT '1',
  `AccountType` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Language` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ChatID`),
  UNIQUE KEY `FullName` (`FullName`),
  UNIQUE KEY `Username` (`Username`),
  KEY `AccountType` (`AccountType`),
  KEY `Room` (`Room`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `User`
--
DROP TRIGGER IF EXISTS `NewUser`;
DELIMITER //
CREATE TRIGGER `NewUser` BEFORE INSERT ON `User`
 FOR EACH ROW BEGIN

	DECLARE numUserInRoom INT DEFAULT 0;
    DECLARE Beds INT DEFAULT 0;
    
    SELECT COUNT(*) INTO numUserInRoom
    FROM User U
    WHERE U.Room = New.Room AND U.Enabled IS TRUE;
    
    SELECT R.Beds INTO Beds
    FROM Room R
    WHERE R.Num = New.room;
    
    IF numUserInRoom >= Beds AND New.Room IS NOT NULL THEN
    	SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La stanza è al completo';
    END IF;
    
END
//
DELIMITER ;
DROP TRIGGER IF EXISTS `UpdateRoom`;
DELIMITER //
CREATE TRIGGER `UpdateRoom` BEFORE UPDATE ON `User`
 FOR EACH ROW BEGIN

	DECLARE numUserInRoom INT DEFAULT 0;
    DECLARE Beds INT DEFAULT 0;
    
    SELECT COUNT(*) INTO numUserInRoom
    FROM User U
    WHERE U.Room = New.Room AND U.Enabled IS TRUE;
    
    SELECT R.Beds INTO Beds
    FROM Room R
    WHERE R.Num = New.Room;
    
    IF  NEW.Room <> OLD.Room AND numUserInRoom >= Beds AND New.Room IS NOT NULL THEN
    	SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La stanza è al completo';
    END IF;

END
//
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `UserSetting`
--

DROP TABLE IF EXISTS `UserSetting`;
CREATE TABLE IF NOT EXISTS `UserSetting` (
  `User` bigint NOT NULL,
  `Language` varchar(10) NOT NULL,
  `DateFormat` varchar(10) NOT NULL,
  `TimeFormat` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `Absence`
--
ALTER TABLE `Absence`
  ADD CONSTRAINT `Absence_ibfk_1` FOREIGN KEY (`User`) REFERENCES `User` (`ChatID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `AccountType`
--
ALTER TABLE `AccountType`
  ADD CONSTRAINT `AccountType_ibfk_1` FOREIGN KEY (`Permission`) REFERENCES `Permission` (`Name`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `accounttype_ibfk_2` FOREIGN KEY (`Notification`) REFERENCES `Notification` (`Name`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `Execution`
--
ALTER TABLE `Execution`
  ADD CONSTRAINT `executiom_ibfk_1` FOREIGN KEY (`Squad`) REFERENCES `Squad` (`Name`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `executiom_ibfk_2` FOREIGN KEY (`TurnType`) REFERENCES `TurnType` (`Name`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `Guest`
--
ALTER TABLE `Guest`
  ADD CONSTRAINT `Guest_ibfk_1` FOREIGN KEY (`User`) REFERENCES `User` (`ChatID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `Member`
--
ALTER TABLE `Member`
  ADD CONSTRAINT `Member_ibfk_1` FOREIGN KEY (`User`) REFERENCES `User` (`ChatID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `Member_ibfk_2` FOREIGN KEY (`Squad`) REFERENCES `Squad` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `Quote`
--
ALTER TABLE `Quote`
  ADD CONSTRAINT `Quote_ibfk_1` FOREIGN KEY (`Author`) REFERENCES `User` (`ChatID`) ON DELETE SET NULL ON UPDATE SET NULL;

--
-- Constraints for table `User`
--
ALTER TABLE `User`
  ADD CONSTRAINT `User_ibfk_1` FOREIGN KEY (`AccountType`) REFERENCES `AccountType` (`Name`),
  ADD CONSTRAINT `User_ibfk_2` FOREIGN KEY (`Room`) REFERENCES `Room` (`Num`) ON DELETE SET NULL ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
