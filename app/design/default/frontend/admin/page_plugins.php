<?php
PageEngine::html("html_head", array("title" => _e("Administration Upgrade Datenbankvergleich")));
PageEngine::html("header", array("searchquery" => (isset($_GET["query"])?$_GET["query"]:"")));

$db = new SQL(0);

?>
	<div id="Content" class="content-wrapper PageUserprofile">
		<article>
	<h1 class="summary"><?=_e("Datenbankprüfung"); ?></h1>
<?php
	PageEngine::html("admin/box_navi");
	PageEngine::html("messagebox", array("name" => "save"));
?>

<form method="POST"><INPUT type="hidden" name="action" value="save"/>
			
<fieldset>
	<legend>Auswahl</legend>
	<table class="std01">
<?php

?>
	<tr><td></td><td><button class="blue" type="submit">speichern</button></td></tr>
	</table>
</fieldset>






<style>
fieldset { border: 1px solid #aaa; margin-bottom: 10px; border-radius:10px; box-shadow: 2px 2px 2px 2px #aaa; }
fieldset > legend { font-family: 'Yanone Kaffeesatz',Arial,sans-serif; font-size: 14px; color: #a88; }
table.std01 th { font-size:13px; color: #525252; }
table.std01 td INPUT { border: #CCE6EC 3px solid; height: 25px; padding-left: 5px; width: 395px; font-size: 14px; font-family: Trebuchet MS,"segoe ui",Helvetica,Tahoma,Verdana,MingLiu,PMingLiu,Arial,sans-serif; }
a.datalink img { height: 20px; }
</style>
			
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