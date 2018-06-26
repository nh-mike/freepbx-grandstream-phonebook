<?php
	//Some people wish to generate a PBX user list and some wish to create a phonebook so we will give them both options
	$tbl_users = true;
	$tbl_visualphonebook = true;

	//Some people wish to echo straight to stdout and others may wish to write to a web cache or TFTP directory. Ensure you have write permissions
	$print = true;
	$write = true;
	$writepath = "/tftpboot/phonebook.xml";

	require_once("/etc/freepbx.conf");
	$mysqli = new mysqli($amp_conf['AMPDBHOST'], $amp_conf['AMPDBUSER'], $amp_conf['AMPDBPASS'], $amp_conf['AMPDBNAME']);

	if ($mysqli->connect_errno) {
	    trigger_error("Connect failed: %s\n", $mysqli->connect_error);
	    exit();
	}

	function DBQuery($query){
		global $mysqli;
		if (!$sqlResult = $mysqli->query($mysqli, $query);) {
			trigger_error('DB query failed: ' . $mysqli->error . "\nquery: " . $query);
			return false;
		} else {
			$all_rows = array();
			while ($row = $sqlResult->fetch_assoc()) {
				$all_rows[] = $row;
			}
			return $all_rows;
		}
	}
	
	function formatXML($xml){
		$dom = new DOMDocument;
		$dom->preserveWhiteSpace = FALSE;
		$dom->loadXML($xml);
		$dom->formatOutput = TRUE;
		return $dom->saveXml();
	}

	function httpAuthenticate(){
		header('WWW-Authenticate: Basic realm="My Realm"');
		header('HTTP/1.0 401 Unauthorized');
		$print = false;
	}
	
	if ( $print ) {
		if (!isset($_SERVER['PHP_AUTH_USER'])) {
			httpAuthenticate();
		} else {
			$PHP_AUTH_USER = $mysqli->real_escape_string($mysqli, $_SERVER['PHP_AUTH_USER']);
			$userPasswordLookupResult = DBQuery("select * from sip where id='$PHP_AUTH_USER' and keyword='secret'");
			if (!$userPasswordLookupResult || !$userPasswordLookupResult[0]['data'] == $_SERVER['PHP_AUTH_PW']) {
				httpAuthenticate();
			}
		}
	}

	header('Content-type: application/xml');
	$xml_obj = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><AddressBook />');

	if ( $tbl_users) {
		foreach (DBQuery("select * from users") as $x){
			$name = explode(" ", $x['name']);
			$Contact = $xml_obj->addChild('Contact');
			$FirstName = $Contact->addChild('FirstName', $name[0]);
			if ($name[1]){
				$LastName = $Contact->addChild('LastName', $name[1]);
			}
			$Phone = $Contact->addChild('Phone');
			$phonenumber = $Phone->addChild('phonenumber', $x['extension']);
			$accountindex = $Phone->addChild('accountindex', 1);
		}
	}

	if ( $tbl_visualphonebook ) {
		foreach (DBQuery("select * from visual_phonebook") as $x){
			$Contact = $xml_obj->addChild('Contact');
			if ( $x['firstname'] ) {
				$FirstName = $Contact->addChild('FirstName', $fname);
			}
			if ( $x['lastname'] ) {
				$LastName = $Contact->addChild('LastName', $lname);
			}
			if ( $x['phone1'] ) {
				$Phone1 = $Contact->addChild('Phone type');
				$Phone1->addAttribute("type", "Work");
				$phonenumber = $Phone1->addChild('phonenumber', $x['phone1']);
				$accountindex = $Phone1->addChild('accountindex', 0);
			}
			if ( $x['phone2'] ) {
				$Phone2 = $Contact->addChild('Phone');
				$Phone2->addAttribute("type", "Cell");
				$phonenumber = $Phone2->addChild('phonenumber', $x['phone2']);
				$accountindex = $Phone2->addChild('accountindex', 0);
			}
			if ( $x['company'] ) {
				$xml_obj->addChild('Company', $x['company']);
			}
			$Group = $xml_obj->addChild('Groups')
			$Group->addChild('groupid', 2);
		}
	}

	$xmldata = $xml_obj->asXML();

	if ( $print ) {
		print formatXML($xmldata);
	}

	if ( $write ) {
		if ( !file_put_contents ( $writepath, $xmldata, LOCK_EX ) ) {
			trigger_error("Unable to write file $writepath");
		}
	}
