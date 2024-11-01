<?php

/*
  Copyright 2010 urakanji (email : urakanji+wordpress@gmail.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

  Plugin Name: Urakanji Wiki Converter
  Plugin URI: http://wordpress.org/extend/plugins/urakanji-wiki-converter/
  Description: Urakanji-Wiki-Converter be able to use wiki markup in your post.
  Version: 0.2.1
  Author: urakanji (japan)
  Author URI: http://urakanji.com
 */
interface functionHook {

	const SFH_MAGICKWORD = 0;
	const SFH_FUNCTION = 1;
	const SFH_DUMMYCODE = 2;
	const SFH_INCLUDEPAGE = 4;

	static function register(&$parser);
}

require 'urakanji-converter.php';
require 'extensions/ParserFunctions.php';
require 'extensions/MagicWord.php';
require 'extensions/DummyCode.php';
if (!defined('BR')) {
	define('BR', "\n", true);
}

/**
 * 
 */
class urakanji_wiki_converter extends urakanji_converter implements functionHook {

	/**
	 * 重複呼び出しを防止するためのスタック
	 * @var type 
	 */
	private $includes = array();

	/**
	 * 処理中のページのプロパティ
	 * @var type 
	 */
	private $pagedata;

	/**
	 * 呼び出されたページの中に含まれるnowiki等、
	 * 途中で処理されるのを防がなければならない部分を
	 * 呼び出し元に送る為のスタック
	 * @var type 
	 */
	private $removecode = array();

	/**
	 * parserFunctionで処理されるマジックワードの登録用スタック
	 * @var type 
	 */
	private $magicwords = array();

	/**
	 * 
	 */
	function __construct() {
		$this->allow_tags = true;
		$this->encode_numericentity_cancel = true;
		$this->no_paragram = false;
		$this->wikitype = self::MEDIAWIKI;
		MagicWord::register($this);
		ParserFunctions::register($this);
		DummyCode::register($this);
	}

	/**
	 * interfaceで指定されている
	 * 未使用
	 * @param type $parser
	 */
	public static function register(&$parser) {
		
	}

	/**
	 * マジックワード等の機能を登録する
	 * @param type $id
	 * @param type $callback
	 * @param type $flags
	 * @return type
	 */
	public function setFunctionHook($id, $callback, $flags = self::SFH_MAGICKWORD) {
		if (self::SFH_MAGICKWORD == $flags) {
			$id = strtoupper($id);
		} elseif (self::SFH_FUNCTION == $flags) {
			$id = '#' . $id;
		} elseif (self::SFH_DUMMYCODE == $flags) {
			
		} else {
			return;
		}
		if (is_callable($callback)) {
			$this->magicwords[$id] = array('callback' => $callback, 'flags' => $flags);
		}
	}

	/**
	 * 起動時の処理群
	 * @param type $str
	 * @return type
	 */
	public function primary($str) {
		$this->pagedata = get_post(null, 'ARRAY_A');
		$this->includes[] = $this->pagedata['ID'];
		$this->setstr($str);
		$this->removeNoWiKi();
		$this->scriptcatch();
		$this->delcomment();
		$this->deleteOnlyIncludeTag();
		$this->noInclude();
		$this->templateValue('');
		$this->parserFunction();
		$this->releaseCode();
		$this->LineConvert();
		$this->includeindex();
		$this->WikiLink();
		$this->SimpleHtml();
		$this->releaseCode($this->removecode);
		return $this->getstr();
	}

	/**
	 * 呼び出されたページの処理群
	 * @param type $str
	 * @param type $val
	 * @param type $includes
	 */
	public function slave($str, $val, $includes) {
		$this->includes = $includes;
		$this->setstr($str);
		$this->delcomment();
		$this->onlyInclude();
		$this->includeOnly();
		$this->removeNoWiKi();
		$this->scriptcatch();
		$this->templateValue($val);
		$this->parserFunction();
		$this->recode($this->nowikis);
	}

	/**
	 * 呼び出し元との中継
	 * @param type $obj
	 */
	public function pipe($obj) {
		reset($this->removecode);
		while (list($key, $val) = each($this->removecode)) {
			if (!isset($obj->code[$key])) {
				$obj->removecode[$key] = $val;
			}
		}
	}

	/**
	 * 呼び出されたページを戻す
	 * @return type
	 */
	public function getIncludePage() {
		return $this->getstr();
	}

	/**
	 * 
	 * @param type $key
	 * @param type $str
	 */
	public function setCode($key, $str) {
		$this->code[$key] = $str;
	}

	/**
	 * 
	 * @param type $key
	 * @return type
	 */
	public function get_Code($key) {
		return $this->code[$key];
	}

	/**
	 * 
	 * @param type $key
	 * @param type $str
	 */
	public function setRemovecode($key, $str) {
		$this->removecode[$key] = $str;
		unset($this->code[$key]);
	}

	/**
	 * nowiki指定部分を待避
	 */
	private function removeNoWiKi() {
		$this->recode($this->nowikis);
	}

	/**
	 * このプラグインで処理しない部分を待避する
	 * @param type $remove
	 */
	private function scriptcatch() {
		$keys = $this->getCode('<pass>', '</pass>');
		$this->recode($keys);
	}

	/**
	 * テンプレートとして使用しない部分の削除
	 * テンプレート指定タグも削除
	 */
	private function noInclude() {
		$res = array();
		foreach ($this->getCode('<includeonly>', '</includeonly>') as $k) {
			$res[$k] = '';
		}
		foreach ($this->getCode('<noinclude>', '</noinclude>') as $k) {
			$res[$k] = $this->code[$k];
		}
		$this->releaseCode($res);
	}

	/**
	 * テンプレート指定タグを削除
	 */
	private function deleteOnlyIncludeTag() {
		$res = array();
		foreach ($this->getCode('<onlyinclude>', '</onlyinclude>') as $k) {
			$res[$k] = $this->code[$k];
		}
		$this->releaseCode($res);
	}

	/**
	 * テンプレート指定部分以外を削除
	 */
	private function onlyInclude() {
		$str = '';
		foreach ($this->getCode('<onlyinclude>', '</onlyinclude>') as $k) {
			$str .= $this->code[$k];
		}
		if ('' !== $str) {
			$this->setstr($str);
		}
	}

	/**
	 * テンプレートに含めない部分を削除
	 * テンプレートのみ使用する部分の指定タグも削除
	 */
	private function includeOnly() {
		$res = array();
		foreach ($this->getCode('<noinclude>', '</noinclude>') as $k) {
			$res[$k] = '';
		}
		foreach ($this->getCode('<includeonly>', '</includeonly>') as $k) {
			$res[$k] = $this->code[$k];
		}
		$this->releaseCode($res);
	}

	/**
	 * 
	 * @param type $keys
	 */
	private function recode($keys) {
		reset($keys);
		while ($key = array_shift($keys)) {
			if (isset($this->code[$key])) {
				$this->removecode[$key] = $this->code[$key];
				unset($this->code[$key]);
			}
		}
	}

	/**
	 * 文中に埋め込まれた代替文字を置き換え
	 * @param type $val
	 */
	private function templateValue($val) {
		$val = $this->valueParser($val);
		$res = array();
		$nocode = array();
		// {{{～}}}が無くなるまで再帰的に処理
		while (TRUE) {
			$key = $this->getCode('{{{', '}}}');
			if (empty($key)) {
				break;
			}
			reset($key);
			$return = array();
			while ($codekey = array_shift($key)) {
				$str = $this->code[$codekey];
				if ('|' == $str) {
					$res[$codekey] = '';
					continue;
				}
				list($valuekey, $s) = $this->explode('|', $str, 2);
				$valuekey = trim($valuekey);
				if (isset($val[$valuekey])) {
					if (FALSE === strpos($s, '{{{')) {
						$res[$codekey] = $val[$valuekey];
					} else {
						$res[$codekey] = $val[$valuekey] . '{{{|';
					}
				} elseif (NULL === $s) {
					$nocode[$codekey] = '{{{' . $str . '}}}';
				} else {
					$res[$codekey] = $s;
				}
			}
			$this->releaseCode($res);
		}
		$this->releaseCode($nocode);
	}

	/**
	 * 文中に埋め込まれたマジックワードを処理
	 */
	private function parserFunction() {
		$keys = $this->getCode('{{', '}}');
		$magicwords = array_keys($this->magicwords);
		reset($keys);
		$code = array();
		while ($key = array_shift($keys)) {
			$str = trim($this->code[$key]);
			list($k, $v) = $this->explode(':', $str, 2);
			$k = trim($k);
			if (in_array($k, $magicwords)) {
				$code[$this->magicwords[$k]['flags']][$key] = array(
					'callback' => $this->magicwords[$k]['callback'],
					'key' => $key,
					'value' => $v,
				);
			} else {
				list($id, $v) = $this->explode('|', $str, 2);
				$code[self::SFH_INCLUDEPAGE][$key] = array(
					'method' => is_numeric($id) ? 'includePageID' : 'includePageName',
					'key' => $key,
					'value' => $v
				);
			}
		}
		reset($code);
		while ($type = array_shift($code)) {
			$res = array();
			reset($type);
			while ($do = array_shift($type)) {
				if (isset($do['method'])) {
					$this->code[$do['key']] = $res[$do['key']] = $this->$do['method']($do['key'], $this->explode('|', $do['value']));
				} else {
					if (is_array($do['callback'])) {
						$this->code[$do['key']] = $res[$do['key']] = $do['callback'][0]::$do['callback'][1]($this, $do['key'], $this->explode('|', $do['value']));
					} else {
						$this->code[$do['key']] = $res[$do['key']] = $do['callback']($this, $do['key'], $this->explode('|', $do['value']));
					}
				}
			}
			$this->releaseCode($res);
		}
	}

	/**
	 * 
	 * @param type $key
	 * @param type $m
	 * @return type
	 */
	private function includePageID($key, $m) {
		list($id, $val) = $this->explode('|', $this->code[$key], 2);
		if (NULL === $this->checkStatus($id)) {
			return '{{' . $this->code[$key] . '}}';
		}
		return $this->includePage($id, $key, $val);
	}

	/**
	 * 
	 * @param type $key
	 * @param type $m
	 * @return type
	 */
	private function includePageName($key, $m) {
		list($pagename, $val) = $this->explode('|', $this->code[$key], 2);
		$id = $this->existPostID($pagename);
		if (NULL === $id) {
			return '{{' . $this->code[$key] . '}}';
		}
		if (NULL === $this->checkStatus($id)) {
			return '{{' . $this->code[$key] . '}}';
		}
		return $this->includePage($id, $key, $val);
	}

	/**
	 * 
	 * @param type $id
	 * @param type $return
	 * @param type $arrow
	 * @return null
	 */
	private function checkStatus($id, $return = 'id', $arrow = array(
		'publish', /* 公開済み */
		'private', /* 非公開 */
		'draft', /* 下書き */
		'pending', /* 承認待ち */
	)) {
		$status = get_post_status($id);
		if (in_array($status, $arrow)) {
			if ('id' == $return) {
				return $id;
			} else {
				return $status;
			}
		} else {
			return NULL;
		}
	}

	/**
	 * 
	 * @param type $id
	 * @param type $key
	 * @param type $val
	 * @return type
	 */
	private function includePage($id, $key, $val) {
		// 二重呼び出しを禁止 無限ループ対策
		if (in_array($id, $this->includes)) {
			list($res, ) = $this->explode('|', $this->code[$key], 2);
			return $res;
		}
		$post = get_post($id, 'ARRAY_A');
		$txt = $post['post_content'];
		if (is_int(strpos($txt, '__WIKI__'))) {
			$includes = $this->includes;
			$includes[] = $id;
			$obj = new urakanji_wiki_converter();
			$obj->slave($txt, $val, $includes);
			$obj->pipe($this);
			return $obj->getIncludePage();
		} else {
			return $txt;
		}
	}

	/**
	 * タイトルからIDを検索する
	 * @global type $wpdb
	 * @param type $str
	 * @return null
	 */
	private function existPostID($str) {
		global $wpdb;
		$str = trim($str);
		$raw = $wpdb->get_row($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_name LIKE %s OR post_title LIKE %s LIMIT 1", rawurlencode($str), $str), 'ARRAY_A');
		if (isset($raw['ID'])) {
			return $raw['ID'];
		} else {
			return NULL;
		}
	}

	/**
	 * 
	 */
	private function WikiLink() {
		foreach ($this->getCode('[[', ']]') as $k) {
			if (isset($this->code[$k])) {
				$this->code[$k] = $this->internalLink($this->code[$k]);
			}
		}
		foreach ($this->getCode('[', ']') as $k) {
			if (isset($this->code[$k])) {
				$this->code[$k] = $this->www_link($this->code[$k]);
			}
		}
	}

	/**
	 * wordpress内でのリンク
	 * [[指定ページ#アンカー|アンカーテキスト]]
	 * 
	 * @param type $val
	 * @return type
	 */
	private function internalLink($val) {
		list($id, $str) = $this->explode('|', $val, 2);
		if (empty($id)) {
			return '[[' . $val . ']]';
		}
		list($id, $anc) = $this->explode('#', $id, 2);
		if (empty($id)) {
			$id = $this->pagedata['ID'];
		} elseif (!is_numeric($id)) {
			$id = $this->existPostID($id);
		}

		if (NULL === $id) {
			return '[[' . $val . ']]';
		}

		$url = $txt = '';
		$option = array();
		if ($id != $this->pagedata['ID']) {
			$status = $this->checkStatus($id, 'status');
			if ('publish' == $status) {
				$url = get_permalink($id);
			} else {
				return '[[' . $val . ']]';
			}
		}
		if (NULL === $str and NULL === $anc) {
			$txt = get_the_title($id);
		} elseif (NULL !== $str and NULL === $anc) {
			$txt = $str;
			$option['title'] = get_the_title($id);
		} elseif (NULL === $str and NULL !== $anc) {
			$txt = get_the_title($id) . '#' . $anc;
			$url .= '#' . $this->anchor_link($anc);
		} else {
			$txt = $str;
			$option['title'] = get_the_title($id) . '#' . $anc;
			$url .= '#' . $this->anchor_link($anc);
		}
		return $this->build_link($url, $txt, $option);
	}

	/**
	 * 
	 * @param String $val
	 * @return Array
	 */
	private function valueParser($val) {
		if (NULL === $val) {
			return array();
		}
		$res = array($val);
		$arr = $this->explode('|', $val);
		reset($arr);
		while ($str = array_shift($arr)) {
			list($k, $v) = $this->explode('=', $str, 2);
			if (NULL === $v) {
				$res[] = $k;
			} else {
				$res[$k] = $v;
			}
		}
		return $res;
	}

	/**
	 * 文字列分割
	 * 分割文字が含まれていない場合はでもエラーを出さない
	 * 指定分割数がある場合は、残りを代替文字で埋める
	 * @param String $delimiter
	 * @param String $string
	 * @param String $limit
	 * @param String $replacement
	 * @return Array
	 */
	private function explode($delimiter, $string, $limit = NULL, $replacement = NULL) {
		if (FALSE === strpos($string, $delimiter)) {
			$res = array($string);
			if (1 < $limit) {
				for ($i = 1; $i < $limit; $i++) {
					$res[] = $replacement;
				}
			}
			return $res;
		} else {
			if (NULL === $limit) {
				return explode($delimiter, $string);
			} else {
				return explode($delimiter, $string, $limit);
			}
		}
	}

}

add_filter('the_content', 'Urakanji_Wiki_Converter', 1);

function Urakanji_Wiki_Converter($txt) {
	if (is_int(strpos($txt, '__WIKI__'))) {
#		remove_filter('the_content', 'wpautop');
		$obj = new urakanji_wiki_converter;
		return $obj->primary($txt);
	} else {
		return $txt;
	}
}
