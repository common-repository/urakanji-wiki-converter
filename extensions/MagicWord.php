<?php
/**
 * Description of MagicWord
 *
 * @author うらかんじ
 */
class MagicWord implements functionHook{
	public static function register( &$parser ) {
		foreach (get_class_methods(__CLASS__) as $class){
			if(preg_match('/^do([0-9a-z]+)$/i', $class, $m)){
				$parser->setFunctionHook(strtoupper($m[1]), array(__CLASS__, $class), self::SFH_MAGICKWORD);
			}
		}
	}
	public static function doSitename() {
		return get_option( 'blogname' );
	}

	public static function doPagename() {
		return get_the_title();
	}
	
	public static function doSiteurl() {
		return get_option('siteurl');
	}

	public static function doHomeurl() {
		return get_option( 'home' );
	}
	
	public static function doBlogDescription() {
		return get_option( 'blogdescription' );
	}
	
	public static function doRemoteAddr() {
		return $_SERVER['REMOTE_ADDR'];
	}
}
