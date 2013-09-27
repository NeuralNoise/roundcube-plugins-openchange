/**
 * OpenChange Roundcube Calendar
 *
 * Table for saving Openchange calendars
 * colors into the database.
 *
 * @version @package_version@
 * @author Miguel Julián
 * @licence GNU AGPL
 * @copyright (c) 2013 Zentyal SL
 *
 **/

CREATE TABLE `colors` (
  `color_asig_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `calendar_id` varchar(50) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `color` varchar(8) NOT NULL,
  PRIMARY KEY(`color_asig_id`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

