<?php

class Tag {



	public static function niceClass($tag = "test") {
		if (substr($tag,0,1)+0 > 0) $tag = "_number_".$tag;
		return html(str_replace(array(" ","&"), array("_","_-amp-_"), $tag));
	}




}