<div class="hidden">
<?php
$webstr = WebCache::get("http://news.google.de/news?hl=de&ned=de&ie=UTF-8&output=rss&scoring=n&q=".urlencode($params["query"]), 86400, array("</rss>","</channel>", "</item>"));
$doc = new DomDocument();
if ($webstr."" != "") {
	$doc->loadXML($webstr);
	foreach ($doc->getElementsByTagName('item') as $row) {
		echo('<p>'.strip_tags($row->getElementsByTagName("description")->item(0)->nodeValue).'</p>');;
	}
}
?></div>

