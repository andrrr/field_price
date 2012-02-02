<?php

	Class extension_field_price extends Extension {
		public function about(){
			return array('name' => 'Field: Price',
				'version' => '2.0',
				'release-date' => '2012-02-02',
				'author' => array('name' => 'Andrey Lubinov, Giel Berkers',
					'website' => false,
					'email' => 'andrey.lubinov@gmail.com')
			);
		}
		
		public function install() {
			try {
				
				Symphony::Database()->query("CREATE TABLE `tbl_fields_price` (
						`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
						`field_id` int(11) unsigned NOT NULL,
	  					`locale` varchar(11) NOT NULL DEFAULT 'en_US',
						`format` varchar(255) DEFAULT '%i',
						PRIMARY KEY (`id`),
						KEY `field_id` (`field_id`)
					) ENGINE=MyISAM DEFAULT CHARSET=utf8
				");
				
			} catch(Exception $e) {
				return false;
			}			
			return true;
		}
		
		public function uninstall() {
			if(parent::uninstall() == true){
				Symphony::Database()->query('DROP TABLE `tbl_fields_price`');
				return true;
			}			
			return false;
		}
	}

