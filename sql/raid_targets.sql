-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               10.0.21-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win64
-- HeidiSQL Version:             12.0.0.6468
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for peq2
CREATE DATABASE IF NOT EXISTS `peq2` /*!40100 DEFAULT CHARACTER SET latin1 */;
USE `peq2`;

-- Dumping structure for table peq2.raid_targets
DROP TABLE IF EXISTS `raid_targets`;
CREATE TABLE IF NOT EXISTS `raid_targets` (
  `level` int(11) DEFAULT NULL,
  `npc` int(11) DEFAULT NULL,
  `killed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Dumping data for table peq2.raid_targets: ~48 rows (approximately)
INSERT INTO `raid_targets` (`level`, `npc`, `killed`) VALUES
	(0, 73057, 1),
	(1, 89154, 0),
	(0, 71012, 0),
	(0, 64001, 0),
	(1, 117073, 0),
	(1, 86014, 0),
	(1, 103056, 0),
	(1, 103080, 0),
	(1, 103055, 0),
	(1, 94009, 0),
	(1, 102112, 0),
	(1, 72003, 0),
	(1, 91093, 0),
	(1, 96089, 0),
	(2, 123115, 0),
	(2, 129003, 0),
	(2, 113118, 0),
	(2, 113215, 0),
	(1, 127001, 0),
	(2, 114106, 0),
	(2, 128041, 0),
	(2, 128045, 0),
	(2, 128053, 0),
	(2, 128043, 0),
	(2, 128042, 0),
	(2, 128044, 0),
	(2, 124040, 0),
	(2, 124031, 0),
	(2, 124038, 0),
	(2, 124035, 0),
	(2, 124036, 0),
	(2, 124030, 0),
	(2, 124039, 0),
	(2, 124034, 0),
	(2, 124105, 0),
	(2, 124020, 0),
	(2, 124104, 0),
	(2, 112025, 0),
	(2, 119112, 0),
	(2, 120084, 0),
	(2, 120005, 0),
	(1, 186107, 0),
	(4, 186111, 0),
	(3, 179037, 0),
	(3, 179180, 0),
	(3, 179157, 0),
	(0, 32040, 1);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
