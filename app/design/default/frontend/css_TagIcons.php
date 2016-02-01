<?php
@header("Content-Type: text/css; Charset: UTF-8");

$expires = 3600;
@header("Pragma: public");
@header("Cache-Control: maxage=".$expires);
@header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');

$a = fcache::read(__FILE__,3600);
if ($a != null) die($a);

$db = new SQL(0);
$rows = $db->cmdrows(0, 'SELECT tag,icon_URL FROM tag_details WHERE icon_URL != ""');
$out = "";
foreach ($rows as $row) {
	$out .= '.tags a.tag.'.niceClass($row["tag"]).' { background: #F3F6F6 url(\''.$row["icon_URL"].'\') no-repeat left center; background-size: 16px 16px; padding-left: 18px; }'.PHP_EOL;
}
$out .= '/***cached at '.date("d.m.Y H:i:s").' ***/'.PHP_EOL;
fcache::write(__FILE__, $out);
echo($out);
exit(1);