#
# Table structure for table 'tx_edmigrate_domain_model_log'
#
CREATE TABLE tx_edmigrate_domain_model_log (

	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,

	version varchar(255) DEFAULT '' NOT NULL,
	start_time int(11) DEFAULT '0' NOT NULL,
	end_time varchar(255) DEFAULT '' NOT NULL,
	namespace varchar(255) DEFAULT '' NOT NULL,

	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	KEY parent (pid),

);
