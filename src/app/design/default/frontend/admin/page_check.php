<?php
if (isset($_POST["act"]) && $_POST["act"] == "doSQL") {
	$db = new SQL(0);
	$db->cmd(0, $_POST["cmd"], true);
}

PageEngine::html("html_head", array("title" => _e("Administration Upgrade Datenbankvergleich")));
PageEngine::html("header", array("searchquery" => (isset($_GET["query"])?$_GET["query"]:"")));

$db = new SQL(0);

?>
	<div id="Content" class="content-wrapper PageUserprofile">
		<article>
		
			<h1><?=_e("Verzeichnisprüfung"); ?></h1>
			<ul>
<?php
	if (!is_dir($_ENV["basepath"]."/app/cache")) {
		echo('<li style="color:#800000;">Verzeichnis app/cache fehlt!</li>');
	} elseif (@chmod ($_ENV["basepath"]."/app/cache", 0755)) {
		echo('<li style="color:#008000;">Verzeichnis app/cache Ok!</li>');
	} else {
		echo('<li style="color:#800000;">Verzeichnis app/cache ist nicht beschreibbar.</li>');
	}
?>			
</ul>
	<h1 class="search-result-summary"><?=_e("Datenbankprüfung"); ?></h1>

<?php
$doc = new DOMDocument();
$doc->load($_ENV["basepath"]."/app/code/install.mysql.xml");

foreach($doc->getElementsByTagName("table") as $child) {
	
	$CreateSQL1 = trim($child->textContent);
	$TableName = $child->attributes->item(0)->value;
	try {
	$row = $db->cmdrow(0, 'SHOW CREATE TABLE `'.$TableName.'`');
	} catch(Exception $ex) {
		$row[1] = "";
	}
	$CreateSQL2 = trim($row[1].";");
	
	$CreateSQL1b = preg_replace("`AUTO_INCREMENT=([0-9]+)`","AUTO_INCREMENT=???", $CreateSQL1);
	$CreateSQL2b = preg_replace("`AUTO_INCREMENT=([0-9]+)`","AUTO_INCREMENT=???", $CreateSQL2);
	
	$CreateSQL1c = str_replace(array(chr(10),chr(32),chr(13)), "", $CreateSQL1b);
	$CreateSQL2c = str_replace(array(chr(10),chr(32),chr(13)), "", $CreateSQL2b);
	
	
	
	echo('<h4 style="margin-bottom:0px; padding-bottom:0px;">'.html($TableName).'</h4>');
	if ($CreateSQL1c == $CreateSQL2c) {
		echo('<p style="color: green; margin-top: 0px;">korrekt</p>');
	} else {
		$CreateSQL1 = preg_replace("@ AUTO\_INCREMENT\=[0-9]+@", "", $CreateSQL1);
		$CreateSQL2 = preg_replace("@ AUTO\_INCREMENT\=[0-9]+@", "", $CreateSQL2);
		$out = diff_line_text($CreateSQL2, $CreateSQL1);
		echo('<table><tr style="vertical-align: top; font-family: Courier; font-size:12px;"><td width="50%">'.$out[0].'</td><td width="50%">'.$out[1].'</td></tr></table>');
		if ($CreateSQL2 == ";") echo('<form method="POST"><INPUT type="hidden" name="act" value="doSQL"/><INPUT type="hidden" name="cmd" value="'.html($CreateSQL1).'"/><button type="submit">'._e("create table").'</button></form>');
	}
	


}

function diff_line_text($text1, $text2) {
	$out = array("","");
	$g1 = explode(chr(13),str_replace(array(chr(10).chr(13),chr(13).chr(10),chr(10)), array(chr(13),chr(13),chr(13)), $text1));
	$g2 = explode(chr(13),str_replace(array(chr(10).chr(13),chr(13).chr(10),chr(10)), array(chr(13),chr(13),chr(13)), $text2));
	for ($i = 0; $i < count($g1); $i++) $g1[$i] = trim($g1[$i]);
	for ($i = 0; $i < count($g2); $i++) $g2[$i] = trim($g2[$i]);
	while (count($g1)+count($g2)>0) {
		if (!isset($g1[0])) {
			$out[1] .= '<span style="color: green;">'.htmlentities($g2[0],3,"UTF-8").'</span><br/>';
			array_shift($g2);
			continue;
		}
		if (!isset($g2[0])) {
			$out[0] .= '<span style="color: red;">'.htmlentities($g1[0],3,"UTF-8").'</span><br/>';
			array_shift($g1);
			continue;
		}
		if ($g1[0] == $g2[0]) { //Die Zeilen sind gleich
			$out[0] .= '<span style="color: grey;">'.htmlentities($g1[0],3,"UTF-8").'</span><br/>';
			$out[1] .= '<span style="color: grey;">'.htmlentities($g2[0],3,"UTF-8").'</span><br/>';
			array_shift($g1); array_shift($g2);
			continue;
		}
		if (!in_array($g1[0],$g2)) {
			$out[0] .= '<span style="color: red;">'.htmlentities($g1[0],3,"UTF-8").'</span><br/>';
			array_shift($g1);
			continue;
		}
		if (!in_array($g2[0],$g1)) {
			$out[1] .= '<span style="color: green;">'.htmlentities($g2[0],3,"UTF-8").'</span><br/>';
			array_shift($g2);
			continue;
		}
	}
	
	
	return $out;
}


?>


			
			
		</article>
	
	
	
	</div>
	
	
	
	
<?php
PageEngine::html("footer");
?>
	
</body>
</html>
<?php
exit(1);
?>