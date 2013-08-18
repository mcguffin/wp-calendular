<?php
/**
* @package WPCalendula
* @version 0.1
*/


class CalendularInstaller {
	
	public static function activate() {
		CalendulaCore::register_post_types();
		flush_rewrite_rules();
		// var_dump
	}
	public static function deactivate(){
		flush_rewrite_rules();
	}

}

?>