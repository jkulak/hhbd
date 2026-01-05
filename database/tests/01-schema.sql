/*!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.8-MariaDB, for debian-linux-gnu (aarch64)
--
-- Host: localhost    Database: hhbd
-- ------------------------------------------------------
-- Server version	10.11.8-MariaDB-ubu2204

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `hhbd`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `hhbd` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `hhbd`;

--
-- Table structure for table `album_artist_lookup`
--

DROP TABLE IF EXISTS `album_artist_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `album_artist_lookup` (
  `albumid` int(11) NOT NULL DEFAULT 0,
  `artistid` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 999,
  KEY `i_album_artist_lookup_albumid_artistid` (`albumid`,`artistid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `album_lookup`
--

DROP TABLE IF EXISTS `album_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `album_lookup` (
  `songid` int(11) NOT NULL DEFAULT 0,
  `albumid` int(11) NOT NULL DEFAULT 0,
  `track` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 999,
  KEY `i_album_lookup_songid` (`songid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `album_prices`
--

DROP TABLE IF EXISTS `album_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `album_prices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link` tinytext NOT NULL,
  `albumid` int(11) NOT NULL DEFAULT 0,
  `shopid` int(11) NOT NULL DEFAULT 0,
  `hits` mediumint(9) NOT NULL DEFAULT 0,
  `added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `addedby` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=949 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `album_promomixes`
--

DROP TABLE IF EXISTS `album_promomixes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `album_promomixes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `promomix` tinytext NOT NULL,
  `size` tinytext NOT NULL,
  `hits` smallint(6) NOT NULL DEFAULT 0,
  `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `addedby` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `album_ratings`
--

DROP TABLE IF EXISTS `album_ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `album_ratings` (
  `albumid` smallint(6) NOT NULL DEFAULT 0,
  `rating` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `album_reviews`
--

DROP TABLE IF EXISTS `album_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `album_reviews` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `albumid` int(11) DEFAULT NULL,
  `title` text DEFAULT NULL,
  `review` text DEFAULT NULL,
  `addedby` int(11) DEFAULT NULL,
  `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actionby` int(11) DEFAULT NULL,
  `status` tinyint(4) DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `albums`
--

DROP TABLE IF EXISTS `albums`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `albums` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `legal` enum('y','n') NOT NULL DEFAULT 'y',
  `title` tinytext DEFAULT NULL,
  `labelid` int(11) DEFAULT NULL,
  `year` date DEFAULT NULL,
  `premier` tinytext NOT NULL,
  `media_mc` tinyint(1) DEFAULT NULL,
  `catalog_mc` tinytext DEFAULT NULL,
  `media_cd` tinyint(1) DEFAULT NULL,
  `catalog_cd` tinytext DEFAULT NULL,
  `media_lp` tinyint(1) DEFAULT NULL,
  `catalog_lp` tinytext DEFAULT NULL,
  `epfor` int(11) DEFAULT NULL,
  `singiel` tinyint(1) DEFAULT NULL,
  `addedby` int(11) DEFAULT NULL,
  `added` datetime DEFAULT NULL,
  `updatedby` int(11) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `viewed` int(11) NOT NULL DEFAULT 0,
  `cover` tinytext DEFAULT NULL,
  `description` text DEFAULT NULL,
  `artistabout` text NOT NULL,
  `notes` text DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 999,
  PRIMARY KEY (`id`),
  KEY `i_albums_id` (`id`),
  KEY `i_albums_viewed` (`viewed`)
) ENGINE=MyISAM AUTO_INCREMENT=973 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `altnames_lookup`
--

DROP TABLE IF EXISTS `altnames_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `altnames_lookup` (
  `artistid` int(11) NOT NULL DEFAULT 0,
  `altname` tinytext DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 999,
  KEY `i_altnames_lookup_artistid` (`artistid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artist_city_lookup`
--

DROP TABLE IF EXISTS `artist_city_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artist_city_lookup` (
  `cityid` int(11) NOT NULL DEFAULT 0,
  `artistid` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 999
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artist_concert_lookup`
--

DROP TABLE IF EXISTS `artist_concert_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artist_concert_lookup` (
  `artistid` int(11) DEFAULT NULL,
  `concertid` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artist_lookup`
--

DROP TABLE IF EXISTS `artist_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artist_lookup` (
  `songid` int(11) NOT NULL DEFAULT 0,
  `artistid` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 999,
  KEY `i_artist_lookup_songid` (`songid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artists`
--

DROP TABLE IF EXISTS `artists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) DEFAULT NULL,
  `realname` tinytext DEFAULT NULL,
  `concertinfo` text DEFAULT NULL,
  `since` date DEFAULT NULL,
  `till` date DEFAULT NULL,
  `type` enum('m','f','b','x') NOT NULL DEFAULT 'x',
  `special` tinyint(4) NOT NULL DEFAULT 0,
  `trivia` text NOT NULL,
  `website` tinytext NOT NULL,
  `addedby` int(11) DEFAULT NULL,
  `added` datetime DEFAULT NULL,
  `updatedby` int(11) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `viewed` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 999,
  `profile` text DEFAULT NULL,
  `hits` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=2311 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artists_everyweek`
--

DROP TABLE IF EXISTS `artists_everyweek`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artists_everyweek` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT 0,
  `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `addedby` smallint(6) NOT NULL DEFAULT 0,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artists_photos`
--

DROP TABLE IF EXISTS `artists_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artists_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `artistid` smallint(6) NOT NULL DEFAULT 0,
  `filename` tinytext NOT NULL,
  `description` text NOT NULL,
  `main` enum('y','n') NOT NULL DEFAULT 'n',
  `source` tinytext NOT NULL,
  `sourceurl` tinytext NOT NULL,
  `addedby` smallint(6) NOT NULL DEFAULT 0,
  `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `band_lookup`
--

DROP TABLE IF EXISTS `band_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `band_lookup` (
  `artistid` int(11) NOT NULL DEFAULT 0,
  `bandid` int(11) NOT NULL DEFAULT 0,
  `insince` date DEFAULT NULL,
  `awaysince` date DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 999
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cities`
--

DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` tinytext DEFAULT NULL,
  `addedby` int(11) DEFAULT NULL,
  `added` datetime DEFAULT NULL,
  `updatedby` int(11) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `viewed` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 999,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `city_artist_lookup`
--

DROP TABLE IF EXISTS `city_artist_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `city_artist_lookup` (
  `cityid` int(11) NOT NULL DEFAULT 0,
  `artistid` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 999
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `city_label_lookup`
--

DROP TABLE IF EXISTS `city_label_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `city_label_lookup` (
  `cityid` int(11) NOT NULL DEFAULT 0,
  `labelid` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 999
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `collection`
--

DROP TABLE IF EXISTS `collection`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `collection` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `albumid` int(11) DEFAULT NULL,
  `userid` int(11) DEFAULT NULL,
  `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM AUTO_INCREMENT=1622 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `feattypes`
--

DROP TABLE IF EXISTS `feattypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feattypes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feattype` tinytext DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 999,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `feature_lookup`
--

DROP TABLE IF EXISTS `feature_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feature_lookup` (
  `songid` int(11) NOT NULL DEFAULT 0,
  `artistid` int(11) NOT NULL DEFAULT 0,
  `feattype` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 999,
  KEY `i_feature_lookup_songid` (`songid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hhb_comments`
--

DROP TABLE IF EXISTS `hhb_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hhb_comments` (
  `com_id` int(11) NOT NULL AUTO_INCREMENT,
  `com_content` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `com_author` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `com_author_ip` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `com_author_id` int(11) NOT NULL,
  `com_object_type` enum('a','n','p','l','s','u') NOT NULL,
  `com_object_id` int(11) DEFAULT NULL,
  `com_added` datetime DEFAULT NULL,
  `com_updated` datetime DEFAULT NULL,
  `com_updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`com_id`),
  KEY `com_object_id_index` (`com_object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=152555 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_polish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hhb_user_lyrics_edit`
--

DROP TABLE IF EXISTS `hhb_user_lyrics_edit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hhb_user_lyrics_edit` (
  `ule_id` int(11) NOT NULL AUTO_INCREMENT,
  `ule_user_id` int(11) DEFAULT NULL,
  `ule_lyrics_id` int(11) DEFAULT NULL,
  `ule_action_type` enum('add','edit','delete') NOT NULL,
  `ule_action_timestamp` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `ule_lyrics` tinytext DEFAULT NULL,
  PRIMARY KEY (`ule_id`),
  KEY `i_hhb_user_lyrics_edit_ule_lyrics_id` (`ule_lyrics_id`)
) ENGINE=MyISAM AUTO_INCREMENT=672 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hhb_users`
--

DROP TABLE IF EXISTS `hhb_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hhb_users` (
  `usr_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usr_email` varchar(100) NOT NULL,
  `usr_password` char(32) NOT NULL,
  `usr_display_name` varchar(30) DEFAULT NULL,
  `usr_first_name` varchar(100) DEFAULT NULL,
  `usr_last_name` varchar(100) DEFAULT NULL,
  `usr_recovery_key` char(32) DEFAULT '',
  `usr_is_admin` enum('yes','no') NOT NULL DEFAULT 'no',
  `usr_added` datetime NOT NULL,
  `usr_updated` datetime NOT NULL,
  `usr_last_login` datetime NOT NULL,
  `usr_login_count` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`usr_id`)
) ENGINE=MyISAM AUTO_INCREMENT=13540 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_polish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `labels`
--

DROP TABLE IF EXISTS `labels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `labels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) DEFAULT NULL,
  `website` tinytext DEFAULT NULL,
  `email` tinytext DEFAULT NULL,
  `addres` tinytext DEFAULT NULL,
  `addedby` int(11) DEFAULT NULL,
  `added` datetime DEFAULT NULL,
  `updatedby` int(11) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `viewed` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 999,
  `profile` text DEFAULT NULL,
  `logo` tinytext NOT NULL,
  `hits` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `music_lookup`
--

DROP TABLE IF EXISTS `music_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `music_lookup` (
  `songid` int(11) NOT NULL DEFAULT 0,
  `artistid` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 999,
  KEY `i_music_lookup_songid` (`songid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `news`
--

DROP TABLE IF EXISTS `news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `news` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `news` text DEFAULT NULL,
  `title` tinytext DEFAULT NULL,
  `expires` datetime DEFAULT NULL,
  `glyph` tinytext NOT NULL,
  `graph` tinytext NOT NULL,
  `viewed` int(11) NOT NULL DEFAULT 0,
  `addedby` int(11) DEFAULT NULL,
  `added` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM AUTO_INCREMENT=1879 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `news_album_lookup`
--

DROP TABLE IF EXISTS `news_album_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `news_album_lookup` (
  `newsid` int(11) NOT NULL DEFAULT 0,
  `albumid` int(11) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `news_artist_lookup`
--

DROP TABLE IF EXISTS `news_artist_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `news_artist_lookup` (
  `newsid` int(11) NOT NULL DEFAULT 0,
  `artistid` int(11) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `news_city_lookup`
--

DROP TABLE IF EXISTS `news_city_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `news_city_lookup` (
  `newsid` int(11) NOT NULL DEFAULT 0,
  `cityid` int(11) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `news_concert_lookup`
--

DROP TABLE IF EXISTS `news_concert_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `news_concert_lookup` (
  `newsid` int(11) NOT NULL DEFAULT 0,
  `concertid` int(11) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `news_label_lookup`
--

DROP TABLE IF EXISTS `news_label_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `news_label_lookup` (
  `newsid` int(11) NOT NULL DEFAULT 0,
  `labelid` int(11) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ratings`
--

DROP TABLE IF EXISTS `ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ratings` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `albumid` int(11) DEFAULT NULL,
  `userid` int(11) DEFAULT NULL,
  `rate` int(11) DEFAULT NULL,
  `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM AUTO_INCREMENT=1672 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ratings_avg`
--

DROP TABLE IF EXISTS `ratings_avg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ratings_avg` (
  `albumid` smallint(6) NOT NULL DEFAULT 0,
  `rating` float NOT NULL DEFAULT 0,
  PRIMARY KEY (`albumid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `remix_lookup`
--

DROP TABLE IF EXISTS `remix_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `remix_lookup` (
  `songid` int(11) NOT NULL DEFAULT 0,
  `artistid` int(11) NOT NULL DEFAULT 0,
  `name` tinytext DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 999
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `scratch_lookup`
--

DROP TABLE IF EXISTS `scratch_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scratch_lookup` (
  `songid` int(11) NOT NULL DEFAULT 0,
  `artistid` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 999,
  KEY `i_scratch_lookup_songid` (`songid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `searches`
--

DROP TABLE IF EXISTS `searches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `searches` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `searchstring` text DEFAULT NULL,
  `userid` int(11) DEFAULT NULL,
  `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM AUTO_INCREMENT=147675 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `song_samples`
--

DROP TABLE IF EXISTS `song_samples`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `song_samples` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `songid` int(11) DEFAULT NULL,
  `sample` text DEFAULT NULL,
  `addedby` int(11) DEFAULT NULL,
  `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `songs`
--

DROP TABLE IF EXISTS `songs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `songs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` tinytext DEFAULT NULL,
  `length` int(11) DEFAULT NULL,
  `bpm` float DEFAULT NULL,
  `acapella` tinyint(1) DEFAULT NULL,
  `radio` tinyint(1) DEFAULT NULL,
  `instrumental` tinyint(1) DEFAULT NULL,
  `addedby` int(11) DEFAULT NULL,
  `added` datetime DEFAULT NULL,
  `updatedby` int(11) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `viewed` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 999,
  `description` text DEFAULT NULL,
  `lyrics` text CHARACTER SET utf8mb3 COLLATE utf8mb3_polish_ci NOT NULL,
  `youtube_url` varchar(255) DEFAULT NULL,
  `youtube_url_flag` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'number of ''wrong video'' flags',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=12817 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `submision_errors`
--

DROP TABLE IF EXISTS `submision_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submision_errors` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `addedby` varchar(50) NOT NULL DEFAULT '0',
  `site` tinytext NOT NULL,
  `sid` tinytext NOT NULL,
  `message` mediumtext NOT NULL,
  `status` enum('w','r','a') NOT NULL DEFAULT 'w',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `submision_recommendations`
--

DROP TABLE IF EXISTS `submision_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submision_recommendations` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `email` tinytext NOT NULL,
  `signature` tinytext NOT NULL,
  `link` tinytext NOT NULL,
  `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(16) DEFAULT NULL,
  `pass` varchar(32) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `www` varchar(50) DEFAULT NULL,
  `place` varchar(50) DEFAULT NULL,
  `timesloggedin` int(11) DEFAULT 0,
  `lastlogin` datetime DEFAULT NULL,
  `added` datetime DEFAULT NULL,
  `about` text DEFAULT NULL,
  `status` int(11) DEFAULT 0,
  `gender` int(11) DEFAULT 0,
  `woje` int(11) DEFAULT 0,
  `birthyear` tinyint(4) NOT NULL DEFAULT 0,
  `allow_wishlist` enum('y','n') NOT NULL DEFAULT 'y',
  `allow_collection` enum('y','n') NOT NULL DEFAULT 'y',
  `viewed` mediumint(9) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM AUTO_INCREMENT=1052 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_activations`
--

DROP TABLE IF EXISTS `users_activations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_activations` (
  `userid` int(11) DEFAULT NULL,
  `activationstring` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_admins`
--

DROP TABLE IF EXISTS `users_admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_admins` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(16) DEFAULT NULL,
  `pass` varchar(32) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `timesloggedin` int(11) DEFAULT 0,
  `lastlogin` datetime DEFAULT NULL,
  `added` datetime DEFAULT NULL,
  `news_priv` enum('y','n') NOT NULL DEFAULT 'n',
  `conc_priv` enum('y','n') NOT NULL DEFAULT 'n',
  `week_priv` enum('y','n') NOT NULL DEFAULT 'n',
  `lala_priv` enum('y','n') NOT NULL DEFAULT 'n',
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wishlist`
--

DROP TABLE IF EXISTS `wishlist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wishlist` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `albumid` int(11) DEFAULT NULL,
  `userid` int(11) DEFAULT NULL,
  `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM AUTO_INCREMENT=340 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'hhbd'
--

--
-- Dumping routines for database 'hhbd'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-04 14:37:34
