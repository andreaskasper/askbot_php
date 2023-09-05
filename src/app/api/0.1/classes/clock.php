<?php
/**
 * 
 * Alle Methoden zur Synchronisierung mit der Serverzeit und Berechnung von relevanten Zeiteinheiten.
 * @author Andreas (Andreas.Kasper@hastuschon.de)
 *
 */
class API_clock {

	/*
	 * Liefert die aktuelle Zeit in Sekunden zurück.
	 * @return integer Zeit in Sekunden seit 01.01.1970
	 */
	public static function gettime() {
		return time();
	}
	
	/**
	 * 
	 * Liefert die aktuelle Zeit in mehreren Varianten zurück.
	 * @return array(string) Verschiedene Zeitformate.
	 */
	public static function gettimestring() {
		$o["dt"] = date("d.m.Y H:i:s");
		$o["mydt"] = date("Y-m-d H:i:s");
		$o["ISO8601"] = date("c");
		return $o;
	}

	/**
	 * 
	 * Liefert die Anzahl der Tage dieses Monats zurück
	 * @return integer Monatstage
	 */
	public static function daysofmonth() {
		$a = mktime(1,1,1, date("n")+1, 0, date("Y"));
		return date("d", $a)+0;
	}

}