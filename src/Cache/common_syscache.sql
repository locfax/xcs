/*
Navicat MySQL Data Transfer

Source Server         : localhost
Source Server Version : 50717
Source Host           : localhost:3306
Source Database       : loc_votodo

Target Server Type    : MYSQL
Target Server Version : 50717
File Encoding         : 65001

Date: 2017-04-29 02:10:28
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for `common_syscache`
-- ----------------------------
DROP TABLE IF EXISTS `common_syscache`;
CREATE TABLE `common_syscache` (
  `cname` varchar(32) NOT NULL,
  `ctype` tinyint(3) unsigned NOT NULL,
  `dateline` int(10) unsigned NOT NULL,
  `data` mediumtext NOT NULL,
  PRIMARY KEY (`cname`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='系统缓存';

-- ----------------------------
-- Records of common_syscache
-- ----------------------------
