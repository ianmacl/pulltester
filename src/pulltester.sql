--
-- Database: `pulltester`
--

-- --------------------------------------------------------

--
-- Table structure for table `pulls`
--

CREATE TABLE IF NOT EXISTS `pulls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pull_id` int(11) NOT NULL,
  `head` varchar(50) NOT NULL,
  `tests` int(11) NOT NULL,
  `assertions` int(11) NOT NULL,
  `failures` int(11) NOT NULL,
  `errors` int(11) NOT NULL,
  `test_time` float NOT NULL,
  `files` int(11) NOT NULL,
  `loc` int(11) NOT NULL,
  `ncloc` int(11) NOT NULL,
  `classes` int(11) NOT NULL,
  `methods` int(11) NOT NULL,
  `coveredmethods` int(11) NOT NULL,
  `conditionals` int(11) NOT NULL,
  `coveredconditionals` int(11) NOT NULL,
  `statements` int(11) NOT NULL,
  `coveredstatements` int(11) NOT NULL,
  `elements` int(11) NOT NULL,
  `coveredelements` int(11) NOT NULL,
  `checkstyle_errors` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=latin1 ;

