<?php

class RSS {

	private $_title = "RSS Feed";
	private $_description = "";
	private $_link = "";
	private $_ttl = 1800;
	public $items = array();
	

	function __construct($title = "RSS Feed", $description = "", $link = "") {
		$this->_title = $title;
		$this->_description = $description;
		$this->_link = $link;
	}

	public function display() {
		@header("Content-Type: application/rss+xml");
		echo($this->fetch());
	}
	
	public function fetch() {
		$out ='<'.'?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0">
<channel>
        <title>'.self::XMLval($this->_title).'</title>
        <description>'.self::XMLval($this->_description).'</description>
        <link>'.self::XMLval($this->_link).'</link>
        <lastBuildDate>'.date("r").'</lastBuildDate>
        <pubDate>'.date("r").'</pubDate>
        <ttl>'.($this->_ttl+0).'</ttl>'.PHP_EOL;
		foreach ($this->items as $row) {
			$out .= $row->render();
		}
		$out .= '</channel></rss>';
		return $out;
	}
	
	protected static function XMLval($txt) {
		return str_replace(array("&","<",">"),array("&amp;","&lt;","&gt;"),$txt);
	}
	
	public function addItem($item) {
		$this->items[] = $item;
	}
}

class RSSItem {
	private $_title = "";
	private $_description = "";
	private $_link = "";
	private $_pubdate = -1;
	private $_guid = "";
	
	function __construct($title = null, $description = null, $link = null) {
		if ($title != null) $this->_title = $title;
		if ($description != null) $this->_description = $description;
		if ($link != null) $this->_link = $link;
	}
	
	public function setPubDate($value) {
		$this->_pubdate(strtotime($value));
	}
	
	public function setGUID($value) {
		$this->_guid = $value;
	}
	
	public function getGUID() {
		if ($this->_guid == "") return md5($this->_title."|".$this->_pubdate).md5($this->_link)."@rss.".$_SERVER["HTTP_HOST"];
		return $this->_guid;
	}
	
	public function render() {
		$out = '<item>
                <title>'.self::XMLval($this->_title).'</title>
                <description>'.self::XMLval($this->_description).'</description>
                <link>'.self::XMLval($this->_link).'</link>
                <guid>'.self::XMLval($this->getGUID()).'</guid>';
		if ($this->_pubdate == -1) $out .= '<pubDate>'.date("r").'</pubDate>'; else $out .= '<pubDate>'.date("r", $this->_pubdate).'</pubDate>';
        $out .= PHP_EOL.'</item>'.PHP_EOL;
		return $out;
	}
	
	protected static function XMLval($txt) {
		return str_replace(array("&","<",">"),array("&amp;","&lt;","&gt;"),$txt);
	}

}



?>