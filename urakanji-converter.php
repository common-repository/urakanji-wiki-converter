<?php

/*
  Copyright 2010 urakanji (email : urakanji@gmail.com)

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


  supported on PHP 5 or greater

  @Copyright		Copyright 2010, urakanji.
  @Link			http://urakanji.com
  @Version		0.0.2
  @Lastmodified	2010/12
  @License		GNU General Public License

  This script can be convert to HTML format from text written by wiki formality.
  このプログラムは、Wiki構文で書かれたテキストからHTMLを作ることができます。
 */

abstract class Urakanji_Converter {

	const URAKANJIWIKI = 0;
	const MEDIAWIKI = 1;

	// バージョン番号
	private $var = 0;
	//
	protected $wikitype = self::URAKANJIWIKI;
	// 変換するテキスト
	protected $str;
	// 使用を許可するHTMLタグ
	protected $allow_tags = array();
	// 自動的にインデックスを作成する最小値
	protected $autoIndexMin = 4;
	// ページ内インデックス
	protected $index = array('txt' => array(), 'h' => array());
	// paragram処理をしない
	protected $no_paragram = false;
	// blockquote内をparagramで囲うか
	protected $blockquote_paragram = true;
	// 改行文字 '','<br>' 通常は改行しない
	protected $br = false;
	// 
	protected $code = array();
	// preタグの範囲
	protected $pre = array();
	// <nowiki>で待避したデータのキーリスト
	protected $nowikis = array();
	// テキストの整形を中止
	protected $nowiki = false;
	// htmlのタイプ true:xhtml false:html
	protected $xhtml = true;
	// htmlのバージョン番号
	protected $htmlver = 4;
	// 
	protected $intable = false;
	// <strong><em>の正規表現
	protected $strong_em_tag = '/\'{5}([^<>]+)\'{3}/U';
	// <strong>の正規表現
	protected $strong_tag = '/\'{3}([^<>]+)\'{3}/U';
	// <em>の正規表現
	protected $em_tag = '/\'{2}([^<>]+)\'{2}/U';
	// <del>の正規表現
	protected $del_tag = '/-{2}([^<>])-{2}/U';
	// (mail)htmlエンティティを中止する
	protected $encode_numericentity_cancel = false;
	// 外部リンクのnofollowの制御を許可
	protected $nofollow_select = true;
	// 外部リンクをnofollowにするか
	protected $nofollow = false;
	// 外部リンクを開くときの動作
	protected $link_target;
	// 制御マジックワード
	protected $control_magic_words = array(
		'__WIKI__',
		'__NOWIKI__',
		'__BR__',
		'__NOBR__',
		'__TOC__',
		'__NOTOC__',
		'__FOLLOW__',
		'__NOFOLLOW__',
	);

	/*
	 * 	処理する文字列をセット
	 * 	テキスト内の制御文字を取り出す
	 */

	function setstr($str) {
		if (empty($str))
			return;
		// 入力された文字列の改行コードを統一しセットする
		$this->str = str_replace(array("\r\n", "\r"), "\n", $str);
		// preを待避
		foreach($this->getCode('<pre', '</pre>', TRUE) as $key ){
			$this->pre[$key] = $this->code[$key];
			unset($this->code[$key]);
		}
		// nowikiを待避
		$this->nowikis = $this->getCode('<nowiki>', '</nowiki>');
		// 制御文字 NOWIKI または WIKI を調査
		if (is_int(strpos($this->str, '__NOWIKI__'))) {
			$this->nowiki = true;
			$this->str = preg_replace('/__NOWIKI__/', '', $this->str, 1);
		} else
		if (is_int(strpos($this->str, '__WIKI__'))) {
			$this->nowiki = false;
			$this->str = preg_replace('/__WIKI__/', '', $this->str, 1);
		}
		// 改行を<br />に変換するかの制御を取得
		if (is_int(strpos($this->str, '__BR__'))) {
			$this->br = true;
			$this->str = preg_replace('/__BR__/', '', $this->str, 1);
		} else
		if (is_int(strpos($this->str, '__NOBR__'))) {
			$this->br = false;
			$this->str = preg_replace('/__NOBR__/', '', $this->str, 1);
		}
		// NOFOLLOWを制御
		if ($this->nofollow_select) {
			if (is_int(strpos($this->str, '__NOFOLLOW__'))) {
				$this->nofollow = true;
				$this->str = preg_replace('/__NOFOLLOW__/', '', $this->str, 1);
			} else
			if (is_int(strpos($this->str, '__FOLLOW__'))) {
				$this->nofollow = false;
				$this->str = preg_replace('/__FOLLOW__/', '', $this->str, 1);
			}
		}
		// 待避させたnowikiを処理
		$this->nowiki();
	}

	/*
	 * 	処理済みの文字列を取り出す
	 * 	途中で待避させた文字を戻す
	 */

	function getstr() {
		// getCodeで取り出した文字を元の位置に戻す
		$this->releaseCode();
		$this->releaseCode($this->pre);
		return $this->str;
	}

	/*
	 * 	部分的に処理しない文字列 (nowiki)
	 */

	private function nowiki() {
		if ($this->nowiki) { // wiki処理しない場合
			foreach ($this->nowikis as $key) {
				if (isset($this->code[$key])) {
					$this->code[$key] = str_replace(BR, $this->br(), $this->sanitize_html('<nowiki>' . $this->code[$key] . '</nowiki>'));
				}
			}
		} else { // wiki処理する場合
			foreach ($this->nowikis as $key) {
				if (isset($this->code[$key])) {
					$this->code[$key] = htmlspecialchars($this->code[$key]);
				}
			}
		}
	}

	/*
	 * 	行単位で変換を分ける
	 */

	protected function LineConvert() {
		if ($this->nowiki) {
			$this->str = str_replace(BR, $this->br(), $this->sanitize_html($this->str));
			return;
		}
		// 文字列を改行で分割
		$str = explode(BR, $this->str);
		// strを初期化
		$this->str = '';
		// 
		$set = array();
		// タグの初期値を設定
		$tag = 'paragram';
		foreach ($str as $l) {
			if ($l == '') {
				$this->str .= $this->$tag($set);
				$set = array();
				continue;
			}
			if ($l == '----') {
				$this->str .= $this->$tag($set);
				if ($this->xhtml) {
					$this->str .= '<hr />';
				} else {
					$this->str .= '<hr>';
				}
				$set = array();
				continue;
			}
			if (preg_match('/^=.+=$/', $l)) {
				$this->str .= $this->$tag($set);
				$this->str .= $this->head($l);
				$set = array();
				continue;
			}
			// 2文字
			$continue = false;
			switch (substr($l, 0, 2)) {
				case '//':// コメント行 (削除)
					$continue = true;
					break;
				case '{|':// 表
				case '|}':
				case '|-':
				case '|+':
					if ($tag != 'table') {
						$this->str .= $this->$tag($set);
						$set = array();
					}
					$set[] = $l;
					$tag = 'table';
					$continue = true;
					break;
			}
			if ($continue)
				continue;
			// 1文字
			switch (substr($l, 0, 1)) {
				case '*':// リスト
				case '#':
				case ':':
				case ';':
					if ($tag != 'lists') {
						$this->str .= $this->$tag($set);
						$set = array();
					}
					$set[] = $l;
					$tag = 'lists';
					break;
				case '	':
				case ' ':// 
					if ($tag != 'pre') {
						$this->str .= $this->$tag($set);
						$set = array();
					}
					$set[] = $l;
					$tag = 'pre';
					break;
				case '>':
					if ($tag != 'blockquote') {
						$this->str .= $this->$tag($set);
						$set = array();
					}
					$set[] = $l;
					$tag = 'blockquote';
					break;
				case '!':
				case '|':
					if ($tag != 'table') {
						$this->str .= $this->$tag($set);
						$set = array();
					}
					$set[] = $l;
					$tag = 'table';
					break;
				default:
					if ($tag != 'paragram') {
						$this->str .= $this->$tag($set);
						$set = array();
					}
					$set[] = $l;
					$tag = 'paragram';
					break;
			}#switch
		}#foreach
		// 最後の行を処理
		$this->str .= $this->$tag($set);
		// テーブルを閉じる
		$this->str .= $this->table('dump');
	}

	/*
	 * 	見出し ( h1 - h6 )
	 */

	private function head($str) {
		if (empty($str))
			return;
		#	$str = trim( $str );
		// =の長さ
		$head = strspn($str, '=');
		// 文字列を反転
		$rev = strrev($str);
		// 逆の=の長さ
		$tail = strspn($rev, '=');
		// 前後を比べて短いほう(最大６)
		if ($head <= $tail) {
			$h = $head;
		} else {
			$h = $tail;
		}
		if ($h > 6) {
			$h = 6;
		}
		// 取得する文字列の長さ
		$len = strlen($str) - $h * 2;
		// 文字列取得
		$txt = $this->sanitize_html(substr($str, $h, $len), true);
		// アンカーリンク用コード
		$anc = $this->anchor_link($txt, TRUE);
		// 見出し作成
		$res = '<h' . $h . '><a name="' . $anc . '" id="' . $anc . '"></a>' . $txt . '</h' . $h . '>';
		// 
		$txt = preg_replace('/\[{1,2}([^\[\]]+)\]{1,2}/i', '\\1', $txt);
		//
		$txt = ' <a href="#' . $anc . '">' . $txt . '</a>';
		// 目次作成
		switch ($h) {
			case 1 :
				$this->index['txt'][] = '*' . $txt;
				break;
			case 2 :
				$this->index['txt'][] = '**' . $txt;
				break;
			case 3 :
				$this->index['txt'][] = '***' . $txt;
				break;
			case 4 :
				$this->index['txt'][] = '****' . $txt;
				break;
			case 5 :
				$this->index['txt'][] = '******' . $txt;
				break;
			case 6 :
				$this->index['txt'][] = '*******' . $txt;
				break;
		}
		$this->index['h'][] = $h;
		return $res;
	}

	protected function anchor_link($str, $self = FALSE) {
		// 重複対策
		static $arr = array();
		$str = trim($str);
		$res = $key = md5($str);
		if ($this->wikitype == self::MEDIAWIKI) {
			$res = rawurlencode($str);
			$res = str_replace(array('%'), array('.', '_'), $res);
		}
		if ($self) {
			if (isset($arr[$key])) {
				$res .= '_' . ++$arr[$key];
			} else {
				$arr[$key] = 0;
			}
		}
		return $res;
	}

	public function includeindex($val = 4) {
		if (empty($this->index['h']))
			return;
		if ($val < 0)
			return;
		// 目次を挿入
		switch (true) {
			case ( is_int(strpos($this->str, '__TOC__')) ) : // 強制出力
				$this->str = preg_replace('/__TOC__/', $this->buildindex(), $this->str, 1);
				break;
			case ( is_int(strpos($this->str, '__NOTOC__')) ):// 
				$this->str = preg_replace('/__NOTOC__/', '', $this->str, 1);
				break;
			case ( count($this->index) >= $val ):
				$this->str = $this->buildindex() . $this->str;
				break;
			default:
				break;
		}
		$this->index = array('txt' => array(), 'h' => array());
	}

	private function buildindex() {
		// 目次をカウント
		$i = count($this->index['txt']);
		// 最小深度を測定
		$f = min($this->index['h']);
		// 目次を作成 (リスト化) と初期化
		$index = array();
		foreach ($this->index['txt'] as $txt) {
			$index[] = substr($txt, $f - 1);
		}
		return $this->lists($index);
	}

	/*
	 * パラグラム
	 */

	private function paragram($str) {
		if (empty($str))
			return '';
		$str = implode($this->br(), $this->sanitize_html($str));
		if (in_array(trim($str), $this->control_magic_words))
			return $str;
		if ($this->no_paragram)
			return $str;
		if ($this->intable)
			return $str;
		$arr = preg_split('@(</?(address|blockquote|center|dir|div|dl|fieldset|form|h[1-6]|hr|isindex|menu|noframes|ol|p|pre|table|ul).*>)@Ui', $str, -1, PREG_SPLIT_DELIM_CAPTURE);
		$res = '';
		$out = true;
		for ($i = 0; $i < count($arr); $i++) {
			$v = $arr[$i];
			if (empty($v)) {
				
			} elseif (in_array(trim($v), $this->control_magic_words)) {
				$res .= $v;
			} elseif (preg_match('|^<(/)?[^</>]+(/)?>$|U', $v, $m)) {
				$res .= $v;
				if (isset($m[1]) or isset($m[2])) {
					$out = true;
				} else {
					$out = false;
				}
				$i++;
			} else {
				if ($out) {
					$res .= '<p>' . $v . '</p>';
				} else {
					$res .= $v;
				}
			}
		}
		return $res;
	}

	/*
	 * 	改行タグの選択
	 */

	private function br() {
		static $br = false;
		// 2回目以降の呼び出し
		if (is_string($br))
			return $br;
		$br = '';
		if ($this->br) {
			if ($this->xhtml) {
				$br = '<br />';
			} else {
				$br = '<br>';
			}
		}
		return $br;
	}

	/*
	 * 	
	 */

	private function pre($str) {
		if (empty($str))
			return;
		return '<pre>' . implode(BR, $this->sanitize_html($str)) . '</pre>';
	}

	/*
	 * 	blockquoute
	 */

	private function blockquote($str) {
		if (empty($str))
			return;
		// 階層をセット
		$phase = 0;
		$in_p = false; // paragramの中?外?
		$res = ''; // 返値
		$ct = array(); // 閉じタグ
		foreach ($str as $val) {
			// 階層の深さを計測
			$fs = strspn($val, '>');
			// 現在の深さとの差分を計算
			$diff = $fs - $phase;
			// 新しい深さをセット
			$phase = $fs;
			// 制御コード ( > ) を削除
			$val = substr($val, $fs);
			if ($diff == 0) {
				$res .= $this->xhtml == TRUE ? '<br />' : '<br>';
			} else
			if ($diff > 0) { // 深い場合
				if ($this->blockquote_paragram and $in_p) {
					$res .= '</p>';
					$in_p = false;
				}
				for ($i = 0; $i < $diff; $i++) {
					$res .= '<blockquote>';
					$ct[] = '</blockquote>';
				}
			} elseif ($diff < 0) { // 浅い場合
				if ($this->blockquote_paragram) {
					$res .= '</p>';
					$in_p = false;
				}
				for ($i = 0; $i < abs($diff); $i++) {
					$res .= array_shift($ct);
				}
			}
			if ($this->blockquote_paragram and !$in_p and $diff != 0) {
				$res .= '<p>';
				$in_p = true;
			}
			$res .= $this->sanitize_html($val);
		}#foreach
		if ($this->blockquote_paragram and $in_p) {
			$res .= '</p>';
		}
		// 残りの閉じタグをセット
		$res .= implode('', $ct);
		return $res;
	}

	/*
	 * 	リスト
	 */

	private function lists($str) {
		if (empty($str))
			return;
		$res = $et = '';
		$tag =
				array(
					'*' => 'ul',
					'#' => 'ol',
					';' => 'dl',
					':' => 'dl',
		);
		// 閉じタグのスタック
		$ct = array();
		// 階層の深さ
		$phase = 0;
		foreach ($str as $s) {
			$fl = substr($s, 0, 1);
			$fs = strspn($s, $fl);
			if ($fl != ':') {
				$diff = $fs - $phase;
				$phase = $fs;
			} else {
				$diff = 0;
			}
			$val = substr($s, $fs);
			//dlの場合ddを分割
			if ($fl == ';') {
				list($val, $dd) = $this->_split(':', $val, 2);
			}
			if ($diff > 0) {
				for ($i = 0; $i < $diff; $i++) {
					$res .= '<' . $tag[$fl] . '>';
					array_unshift($ct, '</' . $tag[$fl] . '>');
				}
			} elseif ($diff < 0) {
				for ($i = 0; $i < abs($diff); $i++) {
					$res .= array_shift($ct);
				}
			}
			if ($et != $tag[$fl] and $et) {
				$res .= array_shift($ct) . '<' . $tag[$fl] . '>';
				array_unshift($ct, '</' . $tag[$fl] . '>');
			}
			if ($fl == '*' or $fl == '#') {
				$res .= '<li>' . $val . '</li>';
			} elseif ($fl == ';') {
				$res .= '<dt>' . $val . '</dt>';
				if ($dd) {
					$res .= '<dd>' . $dd . '</dd>';
				}
			} elseif ($fl == ':') {
				$res .= '<dd>' . $val . '</dd>';
			}
			$et = $tag[$fl];
		}
		$res .= implode('', $ct);
		return $res;
	}

	/**
	 * 定義リスト用のスプリッタ
	 * 
	 */
	private function _split($spliter, $str, $limit = null) {
		$pre = array('http://', 'https://', 'ftp://');
		$post = array('http%3a//', 'https%3a//', 'ftp%3a//');
		$str = str_replace($pre, $post, $str);
		$arr = explode($spliter, $str);
		foreach ($arr as $key => $val) {
			$arr[$key] = str_replace($post, $pre, $val);
		}
		if (is_int($limit)) {
			$arr = array_pad($arr, $limit, null);
		}
		return $arr;
	}

	/*
	 * 	テーブル処理
	 * 	{|	始端 <table>
	 * 	|+	タイトル <caption>
	 * 	|-	改行 <tr>
	 * 	!	見出しセル <th>
	 * 	|	通常セル <td>
	 * 	|}	終端 </table>
	 */

	private function table($str) {
		if (empty($str))
			return;
		static $stack = array();
		// 残りのスタックを吐き出す
		if ($str == 'dump') {
			if (empty($stack))
				return;
			return '</' . implode('></', $stack) . '>';
		}
		$res = array();
		foreach ($str as $val) {
			// 先頭の制御文字を抜き出す
			preg_match('/^([{|!][|}+-]?)(.*)$/', $val, $m);
			// テキストが無い場合
			if (!isset($m[2])) {
				$m[2] = '';
			}
			//
			switch ($m[1]) {
				case '{|':
					$this->table_start($m[2], $res, $stack);
					break;
				case '|}':
					$this->table_end($m[2], $res, $stack);
					break;
				case '|+':
					$this->table_caption($m[2], $res, $stack);
					break;
				case '|-':
					$this->table_tr($m[2], $res, $stack);
					break;
				case '!':
					$this->table_thtd($m[2], $res, $stack, 'th');
					break;
				case '|':
					$this->table_thtd($m[2], $res, $stack, 'td');
					break;
			}
		}
		return implode('', $res);
	}

	private function table_start($str, &$res, &$stack) {
		// $strを検証
		$str = $this->table_option($str, 'class', 'id', 'title', 'style', 'lang', 'dir', 'xml:lang', 'summary', 'width', 'border', 'frame', 'rules', 'cellspacing', 'cellpadding', 'align', 'bgcolor');
		// 
		$res[] = '<table' . $str . '>';
		array_unshift($stack, 'table');
		$this->intable = true;
	}

	private function table_end($str, &$res, &$stack) {
		$this->table_stack('table', $res, $stack);
	}

	private function table_caption($str, &$res, &$stack) {
		$res[] = '<caption>' . htmlspecialchars($str) . '</caption>';
	}

	private function table_tr($str, &$res, &$stack) {
		$stacktags = array_count_values($stack);
		$this->table_stack('tr', $res, $stack);
		$res[] = '<tr>';
		array_unshift($stack, 'tr');
	}

	private function table_thtd($str, &$res, &$stack, $tag) {
		$stacktags = array_count_values($stack);
		if ((isset($stacktags['tr']) ? $stacktags['tr'] : 0 ) < (isset($stacktags['table']) ? $stacktags['table'] : 0 )) {
			$res[] = '<tr>';
			array_unshift($stack, 'tr');
		}
		$strs = preg_split('/[|!]{2}/', $str);
		foreach ($strs as $val) {
			$this->table_stack($tag, $res, $stack);
			$val = explode('|', $val, 2);
			if (isset($val[1])) {
				$val[0] = $this->table_option($val[0], 'class', 'style', 'id', 'title', 'lang', 'dir', 'xml:lang', 'colspan', 'rowspan', 'align', 'valign', 'char', 'charoff', 'abbr', 'axis', 'headers', 'scope', 'nowrap', 'bgcolor', 'width', 'height');
				$res[] = '<' . $tag . ' ' . $val[0] . '>' . $this->sanitize_html($val[1]);
			} else {
				$res[] = '<' . $tag . '>' . $this->sanitize_html($val[0]);
			}
			array_unshift($stack, $tag);
		}
	}

	private function table_stack($tag, &$res, &$stack) {
		if (in_array($tag, $stack)) {
			if (array_search($tag, $stack) > array_search('table', $stack))
				return;
			foreach ($stack as $s) {
				$t = array_shift($stack);
				$res[] = '</' . $t . '>';
				if ($t == $tag) {
					break;
				}
			}
		}
		$this->intable = !empty($stack);
	}

	private function table_option() {
		static $p = array(
	'<',
	'>',
		);
		static $s = array(
	'',
	'',
		);
		$res = '';
		$args = func_get_args();
		$str = array_shift($args);
		str_replace($p, $s, $str);
		$args = array_map('quotemeta', $args);
		$keys = implode('|', $args);
		preg_match_all("/($keys)=['\"]([^'\"]+)['\"]/i", $str, $match, PREG_SET_ORDER);
		foreach ($match as $m) {
			$res .= ' ' . $m[1] . '="' . htmlspecialchars($m[2]) . '"';
		}
		return $res;
	}

	/**
	 * 指定されたコードを取得しアンカーに置き換える
	 * @param type $preTag コードの始端
	 * @param type $postTag コードの終端
	 * @param type $include
	 * @return array アンカーのリスト
	 */
	protected function getCode($preTag, $postTag, $include = FALSE) {
		// アンカー文字列
		$anchor = '__' . uniqid() . '_';
		//
		$i = 0;
		// 
		$preTagLen = strlen($preTag);
		// 
		$postTagLen = strlen($postTag);
		// 
		$res = array();
		//
		$starts = array();
		while (TRUE) {
			// 始点
			if ($starts) {
				$areaStart = array_pop($starts);
			} else {
				$areaStart = 0;
			}
			$areaStart = stripos($this->str, $preTag, $areaStart);
			if (FALSE === $areaStart) {
				break;
			}
			$nextAreaStart = stripos($this->str, $preTag, $areaStart + 1);
			// 終点
			$areaEnd = stripos($this->str, $postTag, $areaStart);
			if (FALSE === $areaEnd) {
				break;
			}
			// 入れ子になっている場合の処理
			if (is_int($nextAreaStart) and $nextAreaStart < $areaEnd) {
				$starts[] = $areaStart;
				$starts[] = $nextAreaStart;
				continue;
			}
			// 
			$anc = $anchor . ++$i . '__';
			// 始点と終点の間を計算
			$areaLenExcluedTag = $areaEnd - $areaStart - $preTagLen;
			$areaLenIncludeTag = $areaEnd - $areaStart + $postTagLen;
			// 囲まれた文字列を取得
			$substrStart = $include ? $areaStart : $areaStart + $preTagLen;
			$this->code[$anc] = substr($this->str, $substrStart, $include ? $areaLenIncludeTag : $areaLenExcluedTag );
			// 
			$res[] = $anc;
			// 文字列をアンカーに置き換え
			$this->str = substr_replace($this->str, $anc, $areaStart, $areaLenIncludeTag);
		}
		return $res;
	}

	/*
	 * 	getCodeで退避させたテキストを戻す
	 *
	 */

	protected function releaseCode($code = null) {
		if (NULL === $code) {
			$code = $this->code;
		}
		if (empty($code)) {
			return;
		}
		reset($code);
		while (list($k, $v) = each($code)) {
			$code[$k] = strtr($v, $code);
		}
		$this->str = strtr($this->str, $code);
	}

	/*
	 * 	HTMLタグを置き換え ( $entitiyで制御 )
	 * 	$str mix
	 * 	return mix
	 * 	将来的には一部のタグは使えるようにする
	 */

	protected function sanitize_html($str, $allow_tags = true) {
		if ($allow_tags == true) {
			$allow_tags = $this->allow_tags;
		} elseif ($allow_tags == false) {
			$allow_tags = array();
		} elseif (is_array($allow_tags)) {
			if (is_array($this->allow_tags)) {
				$allow_tags = array_intersect($this->allow_tags, $allow_tags);
			} elseif ($this->allow_tags == false) {
				$allow_tags = array();
			}
		}
		return $this->sanitize_html_loop($str, $allow_tags);
	}

	private function sanitize_html_loop($str, $allow_tags) {
		if (is_array($str)) {
			$res = array();
			foreach ($str as $v) {
				$res[] = $this->sanitize_html($v, $allow_tags);
			}
			return $res;
		}
		$arr = preg_split('|(<.+>)|Ui', $str, -1, PREG_SPLIT_DELIM_CAPTURE);
		$res = '';
		foreach ($arr as $val) {
			if (preg_match('|^</?([a-z1-6]+)(.*)/?>$|i', $val, $m)) {
				if ($allow_tags === true or in_array($m[1], $allow_tags)) {
					$res .= $val;
				} else {
					$res .= $this->htmlentitiy($val);
				}
			} else {
				$res .= $this->htmlentitiy($val);
			}
		}
		return $res;
	}

	private function htmlentitiy($str) {
		return str_replace(array('<', '>'), array('&lt;', '&gt;'), $str);
	}

	/*
	 * 	<!-- コメント --> を削除する。
	 * 	子から呼び出すのが原則
	 */

	protected function delcomment() {
		if ($this->nowiki)
			return;
		foreach ($this->getCode('<!--', '-->') as $key) {
			if (isset($this->code[$key]))
				$this->code[$key] = '';
		}
	}

	/**
	 * 	inline要素を処理する
	 * 	子から呼び出すのが原則
	 */
	protected function SimpleHtml() {
		$this->str = preg_replace($this->strong_em_tag, '<strong><em>\\1</em></strong>', $this->str);
		$this->str = preg_replace($this->strong_tag, '<strong>\\1</strong>', $this->str);
		$this->str = preg_replace($this->em_tag, '<em>\\1</em>', $this->str);
		$this->str = preg_replace($this->del_tag, '<del>\\1</del>', $this->str);
	}

	/**
	 * 	外部へのリンク
	 * 	子から呼び出すのが原則
	 */
	protected function www_link($val) {
		if (strpos($val, ' ')) {
			list( $url, $str ) = explode(' ', $val, 2);
		} else {
			$url = $str = $val;
		}
		$p_url = parse_url($url);
		if (isset($p_url['scheme']) && preg_match('/ftp|https?|gopher|news|nntp|telnet|wais|file|prospero/', $p_url['scheme'])) {
			$option = array();
			// [http://example.com/] => <a>http://example.com/</a>
			if (is_null($str)) {
				$str = $url;
			}

			// [http://example.com/ ] => <a>example.com</a>
			if (empty($str)) {
				$str = $p_url['host'];
			}

			/**
			 * urlがドメインで終わっている場合最後の/を補完
			 * [http://example.com] => <a href="http://example.com/">http://example.com</a>
			 */
			if (empty($p_url['path']) and strrpos($url, '/') != strlen($url))
				$url .= '/';
			if ($p_url['host'] != $_SERVER['HTTP_HOST']) {
				$option['class'] = 'outside';
				if ($this->nofollow) {
					$option['rel'] = 'nofollow';
				}
				if (isset($this->link_target)) {
					$option['target'] = $this->link_target;
				}
			} else {
				$option['class'] = 'inside';
			}
			return $this->build_link($url, $str, $option);
		}
		if (is_int(strpos($url, 'mailto:'))) {
			list(, $mailadd) = explode(':', $url, 2);
			// [mailto:urakanji@example.com] => <a>urakanji@example.com</a>
			if (is_null($str)) {
				$str = $mailadd;
			}
			// [mailto:urakanji@example.com ] => <a>urakanji</a>
			if (empty($str)) {
				list($str, ) = explode('@', $mailadd, 2);
			}
			return $this->mailto($mailadd, $str);
		}
		return '[' . $val . ']';
	}

	protected function mailto($url, $str) {
		$url = 'mailto:' . $this->encode_numericentity($url);
		$str = $this->encode_numericentity($str);
		return $this->build_link($url, $str, array('class' => 'mailto'));
	}

	private function encode_numericentity($str) {
		if ($this->encode_numericentity_cancel)
			return $str;
		return str_replace(
						array(
					'-', '/', '0', '1', '2', '3', '4', '5', '6', '7',
					'8', '9', ':', ';', '<', '=', '>', '?', '@', 'A',
					'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K',
					'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U',
					'V', 'W', 'X', 'Y', 'Z', '[', '\\', ']', '^', '_',
					'`', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i',
					'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's',
					't', 'u', 'v', 'w', 'x', 'y', 'z', '{', '|', '.'
						), array(
					'&#45;', '&#47;', '&#48;', '&#49;', '&#50;', '&#51;', '&#52;', '&#53;', '&#54;', '&#55;',
					'&#56;', '&#57;', '&#58;', '&#59;', '&#60;', '&#61;', '&#62;', '&#63;', '&#64;', '&#65;',
					'&#66;', '&#67;', '&#68;', '&#69;', '&#70;', '&#71;', '&#72;', '&#73;', '&#74;', '&#75;',
					'&#76;', '&#77;', '&#78;', '&#79;', '&#80;', '&#81;', '&#82;', '&#83;', '&#84;', '&#85;',
					'&#86;', '&#87;', '&#88;', '&#89;', '&#90;', '&#91;', '&#92;', '&#93;', '&#94;', '&#95;',
					'&#96;', '&#97;', '&#98;', '&#99;', '&#100;', '&#101;', '&#102;', '&#103;', '&#104;', '&#105;',
					'&#106;', '&#107;', '&#108;', '&#109;', '&#110;', '&#111;', '&#112;', '&#113;', '&#114;', '&#115;',
					'&#116;', '&#117;', '&#118;', '&#119;', '&#120;', '&#121;', '&#122;', '&#123;', '&#124;', '&#46;'
						), $str);
	}

	/**
	 * リンクタグ作成
	 */
	protected function build_link($url, $str = '', $option = array()) {
		if (empty($url) and empty($str))
			return '';
		if (empty($url))
			return '<strong>' . $str . '</strong>';
		if (empty($str))
			$str = $url;
		$res = ' href="' . $url . '"';
#		if( isset( $option[''] ) ) $res .= ' ="'.htmlspecialchars( $option[''] ).'"';
		if (isset($option['nofollow']))
			$res .= ' rel="nofollow"';
		if (isset($option['title']))
			$res .= ' title="' . htmlspecialchars($option['title']) . '"';
		if (isset($option['rel']))
			$res .= ' rel="' . htmlspecialchars($option['rel']) . '"';
		if (isset($option['ref']))
			$res .= ' ref="' . htmlspecialchars($option['ref']) . '"';
		if (isset($option['target']))
			$res .= ' target="' . htmlspecialchars($option['target']) . '"';
		if (isset($option['id']))
			$res .= ' id="' . htmlspecialchars($option['id']) . '"';
		if (isset($option['class']))
			$res .= ' class="' . htmlspecialchars($option['class']) . '"';
		if (isset($option['style']))
			$res .= ' style="' . htmlspecialchars($option['style']) . '"';
		if (isset($option['name']))
			$res .= ' name="' . htmlspecialchars($option['name']) . '"';
		return '<a' . $res . '>' . $str . '</a>';
	}

}
