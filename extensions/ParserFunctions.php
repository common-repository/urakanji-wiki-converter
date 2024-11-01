<?php
class ParserFunctions implements functionHook{
	public static function register(&$parser) {
		foreach (get_class_methods(__CLASS__) as $class){
			if(preg_match('/^do([0-9a-z]+)$/i', $class, $m)){
				$parser->setFunctionHook(strtolower($m[1]), array(__CLASS__, $class), self::SFH_FUNCTION);
			}
		}
	}
	/**
	 * 条件分岐
	 * {{#if: <もし文> | <ならば文> | <さもなくば文> }}
	 * {{#if: <もし文> | <ならば文> }}
	 * @param type $parser
	 * @param type $key
	 * @param type $val
	 * @return string
	 */
	public static function doIf(&$parser, $key, $val){
		if(strlen(trim($val[0]))){
			return $val[1];
		}elseif (isset($val[2])) {
			return $val[2];
		}else{
			return '';
		}
    }
	/**
	 * 比較
	 * {{#ifeq: <文字列1> | <文字列2> | <等しいときに返す文> | <等しくないときに返す文> }}
	 */
	/**
	 * {{#switch: <基準値>
	 * | <値1> = <返す文1>
	 * | <値2> = <返す文2>
	 * | ...
	 * | <値n> = <返す文n>
	 * | <その他の時に返す文>
	 * }}
	 */
	/**
	 * ページの有無
	 * {{#ifexist: < ページ名> | <あるときに返す文> | <ないときに返す文> }}
	 */
	/**
	 * 計算結果による条件分岐
	 * {{#ifexpr: <数式> | <1の場合に返す文> | <0の場合に返す文> }}
	 */
	/**
	 * 数式
	 * {{#expr: <数式> }}
	 */
	/**
	 * {{#time: <書式> }}
	 * {{#time: <書式> | <日付/時間> }}
	 */
}
