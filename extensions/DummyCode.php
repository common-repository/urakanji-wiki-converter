<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DummyCode
 *
 * @author うらかんじ
 */
class DummyCode implements functionHook{
	public static function register(&$parser) {
		$parser->setFunctionHook('!',	array(__CLASS__, '_001'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('!!',	array(__CLASS__, '_002'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('!!!',	array(__CLASS__, '_003'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('!(',	array(__CLASS__, '_004'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('!((',	array(__CLASS__, '_005'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('!)',	array(__CLASS__, '_006'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('!))',	array(__CLASS__, '_007'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('!-',	array(__CLASS__, '_008'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('!-!',	array(__CLASS__, '_009'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('colon',	array(__CLASS__, '_010'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('&',	array(__CLASS__, '_011'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('(',	array(__CLASS__, '_012'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('(!',	array(__CLASS__, '_013'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('((',	array(__CLASS__, '_014'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('(((',	array(__CLASS__, '_015'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook(')',	array(__CLASS__, '_016'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook(')!',	array(__CLASS__, '_017'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('))!',	array(__CLASS__, '_018'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('))',	array(__CLASS__, '_019'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook(')))',	array(__CLASS__, '_020'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('*',	array(__CLASS__, '_021'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('+',	array(__CLASS__, '_022'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('--',	array(__CLASS__, '_023'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('-!',	array(__CLASS__, '_024'), self::SFH_DUMMYCODE);
		$parser->setFunctionHook('=',	array(__CLASS__, '_025'), self::SFH_DUMMYCODE);
#		$parser->setFunctionHook('',	array(__CLASS__, '_026'), self::SFH_DUMMYCODE);
#		$parser->setFunctionHook('',	array(__CLASS__, '_027'), self::SFH_DUMMYCODE);
#		$parser->setFunctionHook('',	array(__CLASS__, '_028'), self::SFH_DUMMYCODE);
#		$parser->setFunctionHook('',	array(__CLASS__, '_029'), self::SFH_DUMMYCODE);
#		$parser->setFunctionHook('',	array(__CLASS__, '_030'), self::SFH_DUMMYCODE);

	}
	public static function _001(&$parser, $key, $arge) {
		return '|';
	}
	public static function _002(&$parser, $key, $arge){
		return '||';
	}
	public static function _003(&$parser, $key, $arge){
		return '</td><td>';
	}
	public static function _004(&$parser, $key, $arge) {
		return '[';
	}
	public static function _005(&$parser, $key, $arge) {
		return '[[';
	}
	public static function _006(&$parser, $key, $arge) {
		return ']';
	}
	public static function _007(&$parser, $key, $arge) {
		return ']]';
	}
	public static function _008(&$parser, $key, $arge) {
		return '|-';
	}
	public static function _009(&$parser, $key, $arge) {
		return "|-\n|";
	}
	public static function _010(&$parser, $key, $arge) {
		$parser->setRemovecode($key, ':');
		return $key;
	}
	public static function _011(&$parser, $key, $arge) {
		return '&';
	}
	public static function _012(&$parser, $key, $arge) {
		return '{';
	}
	public static function _013(&$parser, $key, $arge) {
		return '{|';
	}
	public static function _014(&$parser, $key, $arge) {
		return '{{';
	}
	public static function _015(&$parser, $key, $arge) {
		return '{{{';
	}
	public static function _016(&$parser, $key, $arge) {
		return '}';
	}
	public static function _017(&$parser, $key, $arge) {
		return ']';
	}
	public static function _018(&$parser, $key, $arge) {
		return ']]';
	}
	public static function _019(&$parser, $key, $arge) {
		return '}}';
	}
	public static function _020(&$parser, $key, $arge) {
		return '}}}';
	}
	public static function _021(&$parser, $key, $arge) {
		return '&nbsp;&bull;&#32;';
	}
	public static function _022(&$parser, $key, $arge) {
		return '<sup>+</sup>';
	}
	public static function _023(&$parser, $key, $arge) {
		return '—';
	}
	public static function _024(&$parser, $key, $arge) {
		return '&nbsp;|';
	}
	public static function _025(&$parser, $key, $arge) {
		return '=';
	}
	public static function _026(&$parser, $key, $arge) {
		return '';
	}
	public static function _027(&$parser, $key, $arge) {
		return '';
	}
	public static function _028(&$parser, $key, $arge) {
		return '';
	}
	public static function _029(&$parser, $key, $arge) {
		return '';
	}
	public static function _030(&$parser, $key, $arge) {
		return '';
	}
}

