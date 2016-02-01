<?php	include("inc.bodystart.php");function is_phpVersionOk() {	$v = explode(".",phpversion());	$v2 = $v[0] * 10000 + $v[1] * 100 + $v[2];	return ($v2 > 50000);}$goon = true;?><div class="legend">	<a href="index">Überblick</a>	<a class="active" href="install">Installation</a>	<a href="upgrade">Upgrade</a></div><aside><ul>	<li class="completed"><a>Einleitung</a></li>	<li class="active"><a>Voraussetzungen</a></li>	<li><a>Datenbank Einstellungen</a></li>	<li><a>Administrations Details</a></li>	<li><a>Konfiguration</a></li>	<li><a>Erweiterte Einstellungen</a></li>	<li><a>Erstelle Datenbank</a></li>	<li><a>Abschluss</a></li></ul></aside><article>	<h1>Prüfung der Servereinstellungen</h1>	<p>Damit wir auch sehen können dass diese Software auf Ihrem Server läuft, prüfen wir nun die Voraussetzungen. Sollte einer dieser Punkte nicht erfüllt werden, dann korrigieren Sie dies bitte und fahren Sie dann mit der Installation fort.</p>	<fieldset>		<legend>PHP Version und Einstellungen</legend>		<table class="std01">			<tr><th><b>PHP Version >= 5.0.0:</b></th><?phpif (is_phpVersionOk()) echo('<td class="yes">ja</td>');else { echo('<td class="no">mit '.phpversion().' zu niedrig </td>'); $goon = false; }			?></tr>			<tr><th><b>GD-Bibliothek installiert:</b>Diese wird benötigt um Grafiken selbst malen zu können.</th><?phpif (!function_exists("ImageCreateTrueColor")) { echo('<td class="no">ImageCreateTrueColor fehlt</td>'); $goon = false; }if (!function_exists("ImagePng")) echo('<td class="no">ImagePNG fehlt</td>');	else echo('<td class="yes">ja</td>');			?></tr>		</table>	</fieldset>		<fieldset>		<legend>Unterstützte Datenbanken</legend>		<table class="std01"><tr><th><b>Passende Datenbank vorhanden:</b></th><?phpif (!function_exists("mysql_connect") AND !function_exists("sqlite_open")) { echo('<td class="no">Eine der Datenbanktypen muss vorhanden sein</td>'); $goon = false; }else echo('<td class="yes">ja</td>');			?></tr>	<tr><th><b>MySQL-Extension:</b></th><?phpif (!function_exists("mysql_connect")) echo('<td class="reco">Empfohlen, da schneller als SQLite</td>');	else echo('<td class="yes">ja</td>');			?></tr><tr><th><b>SQLite-Extension:</b></th><?phpif (!function_exists("sqlite_open")) echo('<td class="reco">Empfohlen, wenn Datenbankserver weit weg oder kein MySQL</td>');	else echo('<td class="yes">ja</td>');			?></tr>						</table>	</fieldset>		<fieldset>		<legend>Dateien und Verzeichnisse</legend>		<table class="std01"><tr><th><b>app/config.standard.php beschreibbar:</b></th><?phpif (!file_exists($_ENV["basepath"]."/app")) { echo('<td class="no">Verzeichnis existiert gar nicht.</td>'); $goon = false; }if (!is_writeable($_ENV["basepath"]."/app/config.standard.php")) { echo('<td class="no">Die Datei muss erstellt werden können.</td>'); $goon = false; }else echo('<td class="yes">ja</td>');			?></tr><tr><th><b>app/cache beschreibbar:</b>In diesem Verzeichnis liegen cache Daten und müssen dort zwischengelagert werden können.</th><?phpif (!is_writeable($_ENV["basepath"]."/app/cache/")) { echo('<td class="no">Das Verzeichnis ist nicht beschreibbar</td>'); $goon = false; }else echo('<td class="yes">ja</td>');			?></tr>					</table>	</fieldset><?phpif ($goon) {	echo('<form method="POST"><INPUT type="hidden" name="status" value="server_ok"/><button type="submit" class="proceed">Fortfahren<img src="skins/default/images/install/forward.png"/></button></form>');} else {	echo('<form method="POST"><INPUT type="hidden" name="license" value="accept"/><button type="submit" class="proceed">Nochmal testen<img src="skins/default/images/install/test.png"/></button></form>');}?></article><style>fieldset { border: 1px solid #aaa; margin-bottom: 10px; background: #eee; box-shadow: 3px 3px 3px 3px #ccc; border-radius: 10px;  }fieldset legend { font-size: 16px; color: #115098; font-family: "Trebuchet MS", Helvetica, sans-serif; }table.std01 { font-size: 12px; color: } table.std01 th { font-weight: normal; border-right: 1px solid #666; text-align: left; font-size: 10px; max-width: 33%; }table.std01 th b { display: block; font-size: 12px; }table.std01 tr:hover th { font-weight: normal; border-right: 1px solid #000; }table.std01 td.yes { color: #008000; font-weight:bold; background: url('skins/default/images/layout/yes.png') no-repeat left center; padding-left: 18px; background-size:16px 16px; }table.std01 td.no { color: #a00000; font-weight:bold; background: url('skins/default/images/layout/no.png') no-repeat left center; padding-left: 18px; background-size:16px 16px; }table.std01 td.reco { color: #ff8000; font-weight:bold; background: url('skins/default/images/install/recommended.png') no-repeat left center; padding-left: 18px; background-size:16px 16px; }</style><?php	include("inc.bodyende.php");?>