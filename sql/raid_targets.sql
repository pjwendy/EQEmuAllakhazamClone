-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               10.0.21-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win64
-- HeidiSQL Version:             12.5.0.6677
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
CREATE TABLE IF NOT EXISTS `raid_targets` (
  `level` int(11) DEFAULT NULL,
  `npc` int(11) DEFAULT NULL,
  `killed` tinyint(4) DEFAULT NULL,
  `killimage` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DELETE FROM `raid_targets`;

-- Dumping data for table peq2.raid_targets: ~61 rows (approximately)
INSERT INTO `raid_targets` (`level`, `npc`, `killed`, `killimage`) VALUES
	(0, 73057, 1, 'Lady_Vox.png'),
	(1, 89154, 1, 'Trakanon.png'),
	(0, 71012, 1, 'The_Spiroc_lord.png'),
	(0, 64001, 1, 'phinigel_autropos.png'),
	(1, 117073, 1, 'Kelorek_Dar.png'),
	(1, 86014, 1, 'Gorenaire.png'),
	(1, 103056, 1, 'Overking_Bathezid.png'),
	(1, 103080, 1, 'Prince_Selrach_Dizok.png'),
	(1, 103055, 1, 'Queen_Velazul_Dizok.png'),
	(1, 94009, 1, 'Severilous.png'),
	(1, 102112, 1, 'Venril_Sathir.png'),
	(1, 72003, 1, 'Cazic_Thule.png'),
	(1, 91093, 1, 'Talendor.png'),
	(1, 96089, 1, 'Faydedar.png'),
	(2, 123115, 0, NULL),
	(2, 129003, 0, NULL),
	(2, 113118, 0, NULL),
	(2, 113215, 0, NULL),
	(2, 127001, 0, NULL),
	(2, 114106, 0, NULL),
	(2, 128041, 0, NULL),
	(2, 128045, 0, NULL),
	(2, 128053, 0, NULL),
	(2, 128043, 0, NULL),
	(2, 128042, 0, NULL),
	(2, 128044, 0, NULL),
	(2, 124040, 0, NULL),
	(2, 124031, 0, NULL),
	(2, 124038, 0, NULL),
	(2, 124035, 0, NULL),
	(2, 124036, 0, NULL),
	(2, 124030, 0, NULL),
	(2, 124039, 0, NULL),
	(2, 124034, 0, NULL),
	(2, 124105, 1, 'Gozzrem.png'),
	(2, 124020, 1, 'Lendiniara.png'),
	(2, 124104, 1, 'Telkorenar.png'),
	(2, 112025, 0, NULL),
	(2, 119112, 0, NULL),
	(2, 120084, 0, NULL),
	(2, 120005, 1, 'Sontalak.png'),
	(1, 186107, 1, 'Innoruuk.png'),
	(3, 154145, 0, NULL),
	(3, 179180, 0, NULL),
	(3, 163075, 0, NULL),
	(0, 32040, 1, 'Lord_Nagafen.png'),
	(3, 179037, 0, NULL),
	(3, 179032, 0, NULL),
	(3, 113457, 0, NULL),
	(3, 162177, 0, NULL),
	(3, 162076, 0, NULL),
	(3, 206067, 0, NULL),
	(3, 124010, 0, NULL),
	(3, 124071, 0, NULL),
	(3, 124011, 0, NULL),
	(3, 124037, 0, NULL),
	(3, 124105, 0, NULL),
	(3, 124072, 0, NULL),
	(3, 124077, 0, NULL),
	(3, 124076, 0, NULL),
	(3, 124103, 0, NULL),
	(3, 124074, 0, NULL),
	(3, 124017, 0, NULL),
	(3, 124075, 0, NULL),
	(3, 124104, 0, NULL),
	(3, 176002, 0, NULL),
	(3, 176088, 0, NULL),
	(4, 208074, 0, NULL),
	(4, 205091, 0, NULL),
	(4, 206074, 0, NULL),
	(4, 211074, 0, NULL),
	(4, 207001, 0, NULL),
	(4, 212023, 0, NULL),
	(4, 212014, 0, NULL),
	(4, 212063, 0, NULL),
	(4, 212061, 0, NULL),
	(4, 212055, 0, NULL),
	(4, 214026, 0, NULL),
	(4, 221008, 0, NULL),
	(4, 201074, 0, NULL),
	(4, 214083, 0, NULL),
	(4, 159691, 0, NULL),
	(4, 162227, 0, NULL),
	(4, 162206, 0, NULL),
	(4, 162190, 0, NULL),
	(5, 126373, 0, NULL),
	(14, 317109, 0, NULL),
	(3, 124073, 0, NULL);


/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
