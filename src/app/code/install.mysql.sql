-- phpMyAdmin SQL Dump
-- version 3.5.2.2
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Erstellungszeit: 27. Jun 2013 um 16:58
-- Server Version: 5.5.27
-- PHP-Version: 5.4.7

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Datenbank: `askbot`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `answers`
--
-- Erzeugt am: 27. Jun 2013 um 00:21
-- Aktualisiert am: 27. Jun 2013 um 14:23
--

CREATE TABLE IF NOT EXISTS `answers` (
  `id` bigint(10) NOT NULL AUTO_INCREMENT,
  `txt` text NOT NULL,
  `question` bigint(10) NOT NULL,
  `author` bigint(10) NOT NULL,
  `authorIP` varchar(45) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL COMMENT 'IP-Adresse des Autors',
  `right_answer` tinyint(1) NOT NULL DEFAULT '0',
  `date_created` bigint(10) NOT NULL,
  `date_edited` bigint(10) NOT NULL,
  `isSPAM` tinyint(1) NOT NULL DEFAULT '0',
  `count_votes` bigint(10) NOT NULL,
  `count_votes_facebook` bigint(10) NOT NULL DEFAULT '0',
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `question_user` (`question`,`author`),
  KEY `question` (`question`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `answer_votes`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 27. Jun 2013 um 00:04
--

CREATE TABLE IF NOT EXISTS `answer_votes` (
  `answer` bigint(10) NOT NULL,
  `user` bigint(10) NOT NULL,
  `vote` tinyint(1) NOT NULL,
  PRIMARY KEY (`answer`,`user`),
  KEY `question` (`answer`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `comments`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 25. Jun 2013 um 23:15
--

CREATE TABLE IF NOT EXISTS `comments` (
  `id` bigint(10) NOT NULL AUTO_INCREMENT,
  `question` bigint(10) NOT NULL,
  `answer` bigint(10) DEFAULT NULL,
  `text` varchar(320) NOT NULL,
  `created` bigint(14) NOT NULL,
  `user` bigint(10) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `question` (`question`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Kommentare';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `config`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 27. Jun 2013 um 11:33
--

CREATE TABLE IF NOT EXISTS `config` (
  `key` varchar(20) NOT NULL,
  `data` text NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Daten zur dynamischen Konfiguration';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `contender`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 25. Jun 2013 um 23:15
--

CREATE TABLE IF NOT EXISTS `contender` (
  `id` varchar(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `html` text NOT NULL,
  `author` bigint(10) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Contender sind Textbestandteile, die von Admins und Editoren gepflegt werden.';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `flags`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 25. Jun 2013 um 23:15
--

CREATE TABLE IF NOT EXISTS `flags` (
  `user` bigint(10) NOT NULL,
  `type` varchar(1) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `ref_id` bigint(10) NOT NULL COMMENT 'Fragen, Antwort oder User-ID',
  `reason` int(3) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user`,`type`,`ref_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Melden von Fragen, Antworten und Usern als unangemessen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `karma_log`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 27. Jun 2013 um 14:23
--

CREATE TABLE IF NOT EXISTS `karma_log` (
  `user` bigint(10) NOT NULL,
  `msgid` int(5) NOT NULL,
  `points` int(5) NOT NULL,
  `question` bigint(10) NOT NULL,
  `created` bigint(10) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `user` (`user`,`msgid`,`question`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Logbuch über alle Karma aktivitäten';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mails`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 25. Jun 2013 um 23:15
--

CREATE TABLE IF NOT EXISTS `mails` (
  `id` bigint(10) NOT NULL AUTO_INCREMENT,
  `from_user` bigint(10) NOT NULL,
  `to_user` bigint(10) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `is_deleted_sender` tinyint(1) NOT NULL DEFAULT '0',
  `is_deleted_receipient` tinyint(1) NOT NULL DEFAULT '0',
  `dt_created` bigint(10) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `from_user` (`from_user`,`to_user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Askbot Nachrichtensystem';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `money_transactions`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 25. Jun 2013 um 23:15
--

CREATE TABLE IF NOT EXISTS `money_transactions` (
  `id` bigint(10) NOT NULL,
  `user` bigint(10) NOT NULL,
  `amount` decimal(20,10) NOT NULL,
  `currency` varchar(3) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `txt` varchar(160) NOT NULL,
  `reasonID` int(5) NOT NULL,
  `dt_trans` bigint(14) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Tabelle für Transaktionen mit Geldgütern';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `qatext_versions`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 25. Jun 2013 um 23:15
--

CREATE TABLE IF NOT EXISTS `qatext_versions` (
  `id` bigint(10) NOT NULL AUTO_INCREMENT,
  `keyid` bigint(10) NOT NULL,
  `type` varchar(1) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `title` varchar(200) NOT NULL,
  `text` text NOT NULL,
  `user` bigint(10) NOT NULL,
  `dt_created` bigint(10) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Versions von Texten um das editieren einfacher zu machen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `questions`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 27. Jun 2013 um 14:23
--

CREATE TABLE IF NOT EXISTS `questions` (
  `id` bigint(10) NOT NULL AUTO_INCREMENT,
  `website` int(2) NOT NULL DEFAULT '0',
  `title` varchar(200) NOT NULL,
  `question` text NOT NULL,
  `author` bigint(10) NOT NULL,
  `tags` varchar(255) NOT NULL COMMENT 'Kommagetrennte Tags',
  `type` enum('question','tip') NOT NULL DEFAULT 'question',
  `is_closed` tinyint(1) NOT NULL DEFAULT '0',
  `is_anonymous` tinyint(1) NOT NULL DEFAULT '0',
  `is_answered` tinyint(1) NOT NULL DEFAULT '0',
  `is_bounty` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Gibt es für diese Auktion eine Belohnung',
  `date_created` bigint(14) NOT NULL,
  `date_edited` bigint(14) NOT NULL,
  `date_action` bigint(14) NOT NULL DEFAULT '0' COMMENT 'Zeitstempel der letzten Aktion bei dieser Frage',
  `user_action` bigint(10) NOT NULL DEFAULT '0' COMMENT 'User der die Aktion ausgeführt hat',
  `count_votes` bigint(10) NOT NULL,
  `count_votes_facebook` bigint(10) NOT NULL DEFAULT '0',
  `count_answers` bigint(10) NOT NULL,
  `count_views` bigint(10) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `creator` (`author`),
  KEY `is_closed` (`is_closed`),
  FULLTEXT KEY `FULLTEXT_tqt` (`title`,`question`,`tags`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='Die aktuellen Fragen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `question_bounty`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 25. Jun 2013 um 23:15
--

CREATE TABLE IF NOT EXISTS `question_bounty` (
  `question` int(10) NOT NULL,
  `user` int(10) NOT NULL,
  `amount` decimal(20,10) NOT NULL,
  `currency` varchar(3) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `dt_created` bigint(14) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `question` (`question`),
  KEY `user` (`user`),
  KEY `currency` (`currency`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Bounty aussetzungen auf eine Frage';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `question_tags`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 26. Jun 2013 um 15:32
--

CREATE TABLE IF NOT EXISTS `question_tags` (
  `question` bigint(10) NOT NULL,
  `tag` varchar(50) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `question` (`question`,`tag`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `question_views`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 26. Jun 2013 um 23:59
--

CREATE TABLE IF NOT EXISTS `question_views` (
  `question` bigint(10) NOT NULL,
  `IP` varchar(20) COLLATE latin1_general_ci NOT NULL,
  `day` int(8) NOT NULL,
  PRIMARY KEY (`question`,`IP`,`day`),
  KEY `question` (`question`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci COMMENT='Zählt die Views pro Seite';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `question_votes`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 25. Jun 2013 um 23:15
--

CREATE TABLE IF NOT EXISTS `question_votes` (
  `question` bigint(10) NOT NULL,
  `user` bigint(10) NOT NULL,
  `vote` tinyint(1) NOT NULL,
  PRIMARY KEY (`question`,`user`),
  KEY `question` (`question`),
  KEY `user` (`user`),
  KEY `vote` (`vote`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tag_details`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 25. Jun 2013 um 23:15
--

CREATE TABLE IF NOT EXISTS `tag_details` (
  `tag` varchar(50) NOT NULL,
  `short_desc` varchar(160) NOT NULL,
  `long_desc` text NOT NULL COMMENT 'Wiki Eintrag in BBCode',
  `icon_URL` varchar(200) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `dt_created` bigint(10) NOT NULL,
  `dt_edited` bigint(10) NOT NULL,
  `count_views` bigint(10) NOT NULL,
  `author` bigint(10) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tag`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Informationen zu den Tags';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tag_synonyms`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 25. Jun 2013 um 23:15
--

CREATE TABLE IF NOT EXISTS `tag_synonyms` (
  `id` bigint(10) NOT NULL AUTO_INCREMENT,
  `tag1` varchar(50) NOT NULL,
  `tag2` varchar(50) NOT NULL,
  `explaination` varchar(200) NOT NULL COMMENT 'Erklärung der Verbindung der beiden Begriffe',
  `count_votes` int(5) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag-tag` (`tag1`,`tag2`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Synonyme für einen Tag';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tag_synonym_votes`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 25. Jun 2013 um 23:15
--

CREATE TABLE IF NOT EXISTS `tag_synonym_votes` (
  `tagid` bigint(10) NOT NULL,
  `user` bigint(10) NOT NULL,
  `vote` tinyint(1) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tagid`,`user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Voting für die Tag-Synonyme, ob es auch stimmt';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tag_views`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 25. Jun 2013 um 23:15
--

CREATE TABLE IF NOT EXISTS `tag_views` (
  `tag` varchar(50) NOT NULL,
  `IP` varchar(20) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `day` bigint(8) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tag`,`IP`,`day`),
  KEY `tag` (`tag`),
  KEY `IP` (`IP`),
  KEY `day` (`day`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Views des Tags loggen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_action`
--
-- Erzeugt am: 27. Jun 2013 um 09:43
--

CREATE TABLE IF NOT EXISTS `user_action` (
  `user` bigint(10) NOT NULL,
  `last_action` bigint(10) NOT NULL,
  `last_writeaction` bigint(10) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user`)
) ENGINE=MEMORY DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci COMMENT='Zur Anzeige des Online-Status';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_badges`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 27. Jun 2013 um 14:43
--

CREATE TABLE IF NOT EXISTS `user_badges` (
  `keyID` varchar(30) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `user` bigint(10) NOT NULL,
  `badge` bigint(10) NOT NULL,
  `medal` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Art der Medaille',
  `dt_received` bigint(10) NOT NULL,
  `question` bigint(10) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`keyID`),
  KEY `user` (`user`),
  KEY `badge` (`badge`),
  KEY `medal` (`medal`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Alle Abzeichen, die ein User erhalten hat';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_list`
--
-- Erzeugt am: 27. Jun 2013 um 11:50
-- Aktualisiert am: 27. Jun 2013 um 14:43
--

CREATE TABLE IF NOT EXISTS `user_list` (
  `id` bigint(10) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email_standard` varchar(100) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `prename` varchar(50) NOT NULL,
  `familyname` varchar(50) NOT NULL,
  `location` varchar(100) NOT NULL,
  `country` varchar(3) NOT NULL COMMENT 'nach ISO 3166 ALPHA-3',
  `show_country` tinyint(1) NOT NULL DEFAULT '1',
  `language` varchar(5) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL COMMENT 'ISO 3166-2',
  `website` varchar(200) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `biography` text NOT NULL,
  `birthday` varchar(10) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT '' COMMENT 'Format: Y-m-d',
  `SkypeID` varchar(50) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL COMMENT 'Skype Adresse für Rückfragen im Profil',
  `GooglePlus` varchar(200) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL COMMENT 'URL zum GooglePlus-Profil',
  `FlattrUID` varchar(20) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL COMMENT 'Flattr UserID für Spenden',
  `PayPal_email` varchar(50) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL COMMENT 'PayPal Adresse für Spenden',
  `register_code` varchar(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `dt_registered` bigint(10) NOT NULL DEFAULT '0',
  `karma` bigint(10) NOT NULL,
  `award_gold` bigint(10) NOT NULL,
  `award_silver` bigint(10) NOT NULL,
  `award_bronce` bigint(10) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='Liste aller User';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_login`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 26. Jun 2013 um 15:28
--

CREATE TABLE IF NOT EXISTS `user_login` (
  `username` varchar(300) NOT NULL,
  `pwd` varchar(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `provider` varchar(20) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `user` bigint(10) NOT NULL,
  `is_standard` tinyint(1) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`username`,`provider`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Logindaten für viele Provider';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_notification`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 25. Jun 2013 um 23:15
--

CREATE TABLE IF NOT EXISTS `user_notification` (
  `user` bigint(10) NOT NULL,
  `msgid` bigint(10) NOT NULL DEFAULT '0',
  `ref_question` bigint(10) NOT NULL DEFAULT '0',
  `ref_answer` bigint(10) NOT NULL DEFAULT '0',
  `ref_user` bigint(10) NOT NULL,
  `created` bigint(14) NOT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `user` (`user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Benachrichtigungen für die User';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_profile_views`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 25. Jun 2013 um 23:15
--

CREATE TABLE IF NOT EXISTS `user_profile_views` (
  `user` bigint(10) NOT NULL,
  `IP` varchar(20) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `date_visited` bigint(8) NOT NULL DEFAULT '0',
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user`,`IP`,`date_visited`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Besucher eines Userprofils';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_rights`
--
-- Erzeugt am: 25. Jun 2013 um 23:15
-- Aktualisiert am: 25. Jun 2013 um 23:15
--

CREATE TABLE IF NOT EXISTS `user_rights` (
  `user` bigint(10) NOT NULL,
  `right` varchar(20) COLLATE latin1_general_ci NOT NULL,
  `date_start` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `date_end` datetime NOT NULL DEFAULT '2999-12-31 23:59:59',
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user`,`right`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci COMMENT='Extra Rechte der User';
