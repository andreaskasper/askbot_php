<?php
$defaults = array("w" => 150, "h" => 40, "s" => 5, "g" => 0);
$_GET = array_merge($defaults, $_GET);

$abc = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
$code = "";

srand();
for ($i = 0; $i < $_GET["s"]; $i++) {
	$code .= substr($abc, rand(0, strlen($abc)-1), 1);
	}


$im = ImageCreateTrueColor($_GET["w"], $_GET["h"]);
$color["white"] = ImageColorAllocate ($im, 255, 255, 255);
ImageFill($im, 1, 1, $color["white"]);

$font = dirname(dirname(dirname(dirname(dirname(dirname(__FILE__))))))."/skins/default/fonts/CAVEMAN_.TTF";


$black = ImageColorAllocate ($im, 0, 0, 0);


for ($x = $_GET["s"]-1; $x >= 0; $x--) {
	ImageTTFText ($im, 20, rand(-25, 25), floor(($_GET["w"]*0.05)+$x*($_GET["w"]*0.9)/$_GET["s"]), $_GET["h"]-10, $black, $font, substr($code, $x, 1));
	}

$_SESSION["antispam".($_GET["g"]+0)] = $code;

header("Content-Type: image/jpeg");
ImageJpeg($im);
exit(1);
?>