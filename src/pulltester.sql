--
-- Database: `pulltester`
--


CREATE TABLE IF NOT EXISTS `phpCsResults` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pulls_id` int(11) NOT NULL,
  `errors` int(11) NOT NULL,
  `warnings` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=latin1;


CREATE TABLE IF NOT EXISTS `phpunitResults` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pulls_id` int(11) NOT NULL,
  `tests` int(11) NOT NULL,
  `assertions` int(11) NOT NULL,
  `num_failures` int(11) NOT NULL,
  `num_errors` int(11) NOT NULL,
  `failures` int(11) NOT NULL,
  `errors` int(11) NOT NULL,
  `time` float NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=latin1;


CREATE TABLE IF NOT EXISTS `pulls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pull_id` int(11) NOT NULL,
  `head` varchar(50) NOT NULL,
  `base` varchar(50) NOT NULL,
  `mergeable` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=latin1;
