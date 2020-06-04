/*!40101 SET @OLD_CHARACTER_SET_CLIENT = @@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS = 0 */;
/*!40101 SET @OLD_SQL_MODE = @@SQL_MODE, SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO' */;

create DATABASE IF NOT EXISTS `dz_tb_logs` /*!40100 DEFAULT CHARACTER SET latin1 */;
USE `dz_tb_logs`;

create TABLE IF NOT EXISTS `bank`
(
    `type`     tinyint(1)   DEFAULT NULL,
    `amount`   int(11)      DEFAULT NULL,
    `p_before` int(11)      DEFAULT NULL COMMENT 'player money before',
    `p_after`  int(11)      DEFAULT NULL COMMENT 'player money after',
    `b_before` int(11)      DEFAULT NULL COMMENT 'bank account before',
    `b_after`  int(11)      DEFAULT NULL COMMENT 'bank account after',
    `uid`      varchar(124) DEFAULT NULL,
    `datetime` datetime     DEFAULT NULL,
    UNIQUE KEY `Index 1` (`type`, `amount`, `uid`, `datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = latin1;

create TABLE IF NOT EXISTS `items`
(
    `id`        int(11) NOT NULL AUTO_INCREMENT,
    `classname` varchar(124) DEFAULT NULL,
    `name`      varchar(124) DEFAULT NULL,
    `sold`      int(11)      DEFAULT 0,
    `bought`    int(11)      DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `Index 2` (`classname`)
) ENGINE = InnoDB
  DEFAULT CHARSET = latin1;

create TABLE IF NOT EXISTS `players`
(
    `uid`      varchar(124) DEFAULT NULL,
    `name`     varchar(124) DEFAULT NULL,
    `datetime` datetime     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `Index 1` (`uid`, `name`)
) ENGINE = InnoDB
  DEFAULT CHARSET = latin1;

create TABLE IF NOT EXISTS `trader`
(
    `type`          tinyint(1)   DEFAULT NULL,
    `amount`        int(11)      DEFAULT NULL,
    `player_amount` int(11)      DEFAULT NULL,
    `trader_uid`    int(11)      DEFAULT NULL,
    `item_id`       int(11)      DEFAULT NULL,
    `player_uid`    varchar(124) DEFAULT NULL,
    `datetime`      datetime     DEFAULT NULL,
    UNIQUE KEY `Index 1` (`type`, `datetime`, `player_uid`, `item_id`, `player_amount`)
) ENGINE = InnoDB
  DEFAULT CHARSET = latin1;