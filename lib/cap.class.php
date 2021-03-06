<?php
/*
*  Copyright (c) 2016  Niklas Spanring   <n.spanring@backbone.co.at>
*  Copyright (c) 2016  Guido Schratzer   <guido.schratzer@backbone.co.at>
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

/**
*  \file       cap.class.php
*  \ingroup    build
*  \brief      File of class with CAP 1.2 builder
*  \standards  from http://docs.oasis-open.org/emergency/cap/v1.2/CAP-v1.2-os.html
*
*/

class CapProcessor{
	private $debug = false;
	var $mime; // CAP / XML
	var $output_destination; // output/
	var $output_content;
	var $error;

	var $capCreate;
	var $capRead;
	var $capSoap;

	var $alert = array();
	private $alertIndex = 0;

	function __construct($output = "", $cap_mime = "xml"){
		$this->mime = $cap_mime;
		$this->output_destination = $output;

		$this->capCreate = new CapCreate($this);
		$this->capRead = new CapRead($this);
	}

	// returns the alert handler for the given index
	function getAlert($index = 0){
		return $this->alert[$index];
	}

	// sets/adds an alert block
	function setAlert($index = false){
		if($index === false) return $this->alert[$this->alertIndex++] = new alertBlock();
		else return $this->alert[$index] = new alertBlock();
	}

	// adds an alert block [relink: setAlert()]
	function addAlert($index = false){
		return $this->setAlert($index);
	}

	// converts cap object to a xml string
	function buildCap($index = false){
		return $this->capCreate->buildCap($index);
	}

	// saves a xml string cap to a file
	function saveCap($name = '',$index = false){
		return $this->capCreate->saveCap($name, $index);
	}

	// reads a xml file, array or string and put it into the class
	function readCap($destination){
		return $this->capRead->readCap($destination);
	}

	// returns the readed cap xml array of the index
	function getCapXmlArray($index = 0){
		return $this->capRead->cap_xml_content[$index];
	}

	// This function is only for the Cap Php Library Desgined!
	function makeCapOfPost($post){
		$this->debug = true;

		$alert = $this->addAlert();

		$alert->setIdentifier($post['identifier']);
		$alert->setSender($post['sender']);

		if(empty($post['sent']['plus'])) $post['sent']['plus'] = "+";
		$alert->setSent(date("Y-m-d\TH:i:s" , strtotime($post['sent']['date']." ".$post['sent']['time'] )).$post['sent']['plus'].date("H:i",strtotime($post['sent']['UTC'])));
		$alert->setStatus($post['status']);
		$alert->setMsgType($post['msgType']);
		$alert->setSource($post['source']);
		$alert->setScope($post['scope']);
		$alert->setRestriction($post['restriction']);
		$alert->setAddresses($post['addresses']);
		if(!is_array($post['code'])){
			$alert->setCode($post['code']);
		}else{
			foreach($post['code'] as $key => $code_val){
				$alert->setCode($code_val);
			}
		}
		$alert->setNote($post['note']);
		$alert->setReferences($post['references']);
		$alert->setIncidents($post['incidents']);

		// if category is present enter the info block
		if($post['category']){
			$info_arr[] = $alert->addInfo(); // add first info block in a array

			foreach ($post['language'] as $key => $value) {
				if( $value == "") unset($post['language'][$key]);
			}
			$post['language'] = array_values($post['language']);
			// foreach language specific data
			if(array_unique($post['language']) > 0)
			foreach(array_unique($post['language']) as $lkey => $lang)
			{
				// check if the value is not a dummy
				if(!empty($lang) && $lang != "")
				{
					// if we have more than 1 language we have to produce more info blocks
					if($lkey >= 1 && count($post['language']) > 1){
						$info_arr[$lkey] = $alert->addInfo(); // write new info block in the array
					}

					// the $lkey is spezifing language specific data
					$info_arr[$lkey]->setLanguage($lang);
					$info_arr[$lkey]->setEvent($post['event'][$lang]);
					$info_arr[$lkey]->setHeadline($post['headline'][$lang]);
					$info_arr[$lkey]->setDescription($post['description'][$lang]);
					$info_arr[$lkey]->setInstruction($post['instruction'][$lang]);
				}
			}

			// all other data is not language specific so we put it in all info blocks
			foreach($info_arr as $ikey => $info)
			{
				$info->setCategory($post['category']);
				$info->setResponseType($post['responseType']);
				$info->setUrgency($post['urgency']);
				$info->setSeverity($post['severity']);
				$info->setCertainty($post['certainty']);
				$info->setAudience($post['audience']);

				if(! empty($post['eventCode']['valueName'][0]))
				foreach($post['eventCode']['valueName'] as $key => $eventCode)
				{
					if(!empty($post['eventCode']['valueName'][$key]))
					{
						$info->setEventCode($post['eventCode']['valueName'][$key], $post['eventCode']['value'][$key]);
					}
				}

				// key: date | time | plus | UTC => 2017-02-03 | 20:00:02 | + | 01:00
				$info->setEffective(date("Y-m-d\TH:i:s" , strtotime($post['effective']['date']." ".$post['effective']['time'] )).$post['effective']['plus'].date("H:i",strtotime($post['effective']['UTC'])));
				$info->setOnset(date("Y-m-d\TH:i:s" , strtotime($post['onset']['date']." ".$post['onset']['time'] )).$post['onset']['plus'].date("H:i",strtotime($post['onset']['UTC'])));
				$info->setExpires(date("Y-m-d\TH:i:s" , strtotime($post['expires']['date']." ".$post['expires']['time'] )).$post['expires']['plus'].date("H:i",strtotime($post['expires']['UTC'])));

				$info->setSenderName($post['senderName']);
				$info->setWeb($post['web']);
				$info->setContact($post['contact']);

				if(! empty($post['parameter']['valueName'][0]))
				foreach($post['parameter']['valueName'] as $key => $parameter)
				{
					if(!empty($post['parameter']['valueName'][$key]))
					{
						$info->setParameter($post['parameter']['valueName'][$key], $post['parameter']['value'][$key]);
					}
				}

				// look if area zone is used
				if(!empty($post['areaDesc']) || !empty($post['polygon'])  || !empty($post['circle']) || !empty($post['geocode']))
				{
					$area = $info->addArea();

					$area->setAreaDesc($post['areaDesc']);
					$area->setPolygon($post['polygon']);
					$area->setCircle($post['circle']);

					if(strpos($post['geocode']['value'][0], "<|>") === false){
						if(! empty($post['geocode']['valueName'][0]))
						foreach($post['geocode']['valueName'] as $key => $geocode)
						{
							if(!empty($post['geocode']['valueName'][$key]))
							{
								$area->setGeocode($post['geocode']['valueName'][$key], $post['geocode']['value'][$key]);
							}
						}
					}else{ // if the geocode contains "<|>" we calculate it this way
						if(! empty($post['geocode']['value'][0]))
						foreach($post['geocode']['value'] as $key => $geocode)
						{
							$geo_arr = explode("<|>", $post['geocode']['value'][$key]);
							if(!empty($geo_arr[0]) && !empty($geo_arr[1]))
							{
								$area->setGeocode($geo_arr[1], $geo_arr[0]);
							}
						}
					}
				}
			}
		}

		//$this->debug($this);
		return true;
	}

	// fills the Class with test values
	function makeTestCAP($debug = true){
		$this->debug = $debug;

		$alert = $this->addAlert();

		$alert->setIdentifier("test.123456789.123");
		$alert->setSender("test.at");
		$alert->setSent(date("d.m.Y H:i:s P"));
		$alert->setStatus("Test");
		$alert->setMsgType("Ack");
		$alert->setSource("cap.class.php makeTestCAP()");
		$alert->setScope("Private");
		//$alert->setRestriction("none");
		$alert->setAddresses("Teststreet 12");

		$alert->setCode("TESTFLAG1");
		$alert->setCode("TESTFLAG3");
		$alert->setCode("TESTFLAG2", 1); // Change second code entery from 3 to 2

		$alert->setNote("This was generated by the makeTestCAP function of the CAP Class");
		$alert->setReferences("test.at,test.123456789.122,".date("Y-m-d\TH:i:sP", strtotime('now - 1 hour')));
		$alert->setIncidents("test.123456789.121 test.123456789.120");

		$info = $alert->addInfo();

		$info->setLanguage("english");

		$info->setCategory("Other");
		$info->setCategory("Met");
		$info->setCategory("Rescue", 0); // Change Other to Rescue

		$info->setEvent("TEST GENERATED MESSAGE");

		$info->setResponseType("Assess");
		$info->setResponseType("None");

		$info->setUrgency("Immediate");
		$info->setSeverity("Minor");
		$info->setCertainty("Observed");
		$info->setAudience("To all affected people");

		$info->setEventCode("SAME", "CEM");
		$info->setEventCode("SYSTEM", "ACK");

		$info->setEffective(date("d.m.Y H:i:s P"));
		$info->setOnset("now");
		$info->setExpires(date("d.m.Y H:i:s")." + 1 day + 1 hour  + 24 min");

		$info->setSenderName("CAP PHP Library");
		$info->setHeadline("GENERATED TEST MESSAGE FROM CAP CLASS!");
		$info->setDescription("THIS IS A GENERATED TEST MESSAGE FROM THE CAP CLASS!");
		$info->setInstruction("CHECK IF SYSTEM IS RESPONDING");
		$info->setWeb("http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].";");
		$info->setContact("testmail@address.com");
		$info->setParameter("SYSTEMID", "123");
		$info->setParameter("STATUS", "OFF");
		$info->setParameter("STATUS", "ON", 1); // Change STATUS to ON
		$info->setParameter("TESTING", "TRUE");

		$resource = $info->addResource();

		$resource->setResourceDesc("GOOGLE TEST PIC");
		$resource->setMimeType("image/png");
		$resource->setSize("105121");
		$resource->setUri("https://www.google.at/images/nav_logo242.png");
		//$resource->derefUri("105121");
		$resource->setDigest("a94a8fe5ccb19ba61c4c0873d391e987982fbbd3");

		$area = $info->addArea();

		$area->setAreaDesc("TEST LOCATION");
		$area->setPolygon("43.00121,32.00121 43.00131,32.00141 43.00151,32.00161 43.00171,32.00181 43.00121,32.00121");
		$area->setPolygon("42.00181,33.00131 42.00151,33.00141 42.00121,33.00171 42.00131,33.00141 42.00181,33.00131");
		$area->setCircle("43.00121,32.00121 15");
		$area->setCircle("42.00181,33.00131 15");
		$area->setGeocode("FIPS", "FL0124");
		$area->setGeocode("FIPS", "FL0125");
		$area->setAltitude("1000");
		$area->setCeiling("1500");

		$area = $info->addArea();

		$area->setAreaDesc("TEST LOCATION 2");
		$area->setPolygon("53.00121,42.00121 53.00131,42.00141 53.00151,42.00161 53.00171,42.00181 53.00121,42.00121");
		$area->setPolygon("52.00181,43.00131 52.00151,43.00141 52.00121,43.00171 52.00131,43.00141 52.00181,43.00131");
		$area->setCircle("53.00121,42.00121 15");
		$area->setCircle("52.00181,43.00131 15");
		$area->setGeocode("FIPS", "FL0134");
		$area->setGeocode("FIPS", "FL0135");
		$area->setAltitude("1500");
		$area->setCeiling("2000");

		$this->debug($this);
	}

	/*
	* Function to Debug cap.class.php
	*
	* @return array    $this   All content of the Class
	*/
	function debug($debug_val){
		if($this->debug == true)
		{
			print '<pre>';
			print_r($debug_val);
			print '</pre>';
			exit;
		}
	}
}


/**
*  Get and Set <alert> CAP values
*  The container for all component parts of the alert message (REQUIRED)
*/
class alertBlock{
	protected $required = array('identifier', 'sender', 'sent', 'status', 'msgType', 'scope');
	protected $subclass = array('info'=>'infoBlock');

	// WMO Organisation ID green -> Your Country ISO Code -> Your File Date/Time "YYMMDDHHMMSS" -> your warning ID (CHAR max Len: 20 / special characters are not allowed only a-Z 0-9 and "_") -> 2.49.0.3.0.AT.150112080000.52550478
	var     $identifier;
	// link to Homepage Guaranteed by assigner to be unique globally
	var     $sender;
	// <yyyy>-<MM>-<dd>T<HH>:<mm>:<ss>+<hour>:<min><P> Offset to UTC (e.g. CET: +01:00; CEST: +02:00) -> 2014-07-15T06:03:02+01:00
	var     $sent;
	// Actual / Test / Exercise / System / Test / Draft
	var     $status;
	// Alert / Update / Cancel / Ack / Error
	var     $msgType;
	var     $source;
	// Public / Restricted / Private
	var     $scope;
	var     $restriction;
	var     $addresses;
	var     $code = array();
	private $codeIndex = 0;
	var     $note;
	// web / identifier / sent [from the older Cap (only fore Update and Cancel)] -> http://www.zamg.ac.at/warnsys/public/aus_all.html,2.49.0.3.0.AT.150115080000.52550477,2015-01-08T10:05:02+01:00
	var     $references;
	var     $incidents;
	var     $info = array();
	private $infoIndex = 0;

	// The identifier of the alert message (REQUIRED)
	function getIdentifier(){ return $this->identifier; }
	function setIdentifier($val){ return $this->identifier = (string) $val;}

	// The identifier of the sender of the alert message (REQUIRED)
	function getSender(){ return $this->sender; }
	function setSender($val){ return $this->sender = (string) $val; }

	// The time and date of the origination of the alert message (REQUIRED)
	function getSent(){ return $this->sent; }
	function setSent($val){
		return $this->sent = date("Y-m-d\TH:i:sP", strtotime($val));
	}

	// The code denoting the appropriate handling of the alert message (REQUIRED)
	function getStatus(){ return $this->status; }
	function setStatus($val){
		switch($val){
			case 'Actual':
			case 'Exercise':
			case 'System':
			case 'Test':
			case 'Draft':
			return $this->status = $val;
			break;
			default:
			return false;
			break;
		}
	}

	// The code denoting the nature of the alert message (REQUIRED)
	function getMsgType(){ return $this->msgType; }
	function setMsgType($val){
		switch($val){
			case 'Alert':
			case 'Update':
			case 'Cancel':
			case 'Ack':
			case 'Error':
			return $this->msgType = $val;
			break;
			default:
			return false;
			break;
		}
	}

	// The text identifying the source of the alert message (OPTIONAL)
	function getSource(){ return $this->source; }
	function setSource($val){ return $this->source = (string) $val; }

	// The code denoting the intended distribution of the alert message (REQUIRED)
	function getScope(){ return $this->scope; }
	function setScope($val){
		switch($val){
			case 'Public':
			case 'Restricted':
			case 'Private':
			return $this->scope = $val;
			break;
			default:
			return false;
			break;
		}
	}

	// The text describing the rule for limiting distribution of the restricted alert message (CONDITIONAL)
	function getRestriction(){ return $this->restriction; }
	function setRestriction($val){ return $this->restriction = (string) $val; }

	// The group listing of intended recipients of the alert message (CONDITIONAL)
	function getAddresses(){ return $this->addresses; }
	function setAddresses($val){ return $this->addresses = (string) $val; }

	// The code denoting the special handling of the alert message (OPTIONAL)
	function getCode(){ return $this->code; }
	function setCode($val, $index = false){
		if($index === false) return $this->code[$this->codeIndex++] = (string) $val; // ADD
		else return $this->code[$index] = (string) $val; // CHANGE
	}

	// The text describing the purpose or significance of the alert message (OPTIONAL)
	function getNote(){ return $this->note; }
	function setNote($val){ return $this->note = (string) $val; }

	// The group listing identifying earlier message(s) referenced by the alert message (OPTIONAL)
	function getReferences(){ return $this->references; }
	function setReferences($val){ return $this->references = (string) $val; }

	// The group listing naming the referent incident(s) of the alert message (OPTIONAL)
	function getIncidents(){ return $this->incidents; }
	function setIncidents($val){ return $this->incidents = (string) $val; }

	function getInfo($index = 0){
		return $this->info[$index];
	}

	function setInfo($index = false){
		if($index === false) return $this->info[$this->infoIndex++] = new infoBlock();
		else return $this->info[$index] = new infoBlock();
	}

	function addInfo($index = false){
		return $this->setInfo($index);
	}
}
/**
*  Get and Set <info> CAP values
*  The container for all component parts of the info sub-element of the alert message (OPTIONAL)
*/
class infoBlock{
	protected $required = array('category', 'event', 'urgency', 'severity', 'certainty');
	protected $subclass = array('eventCode'=>'parameterBlock', 'parameter'=>'parameterBlock', 'resource'=>'resourceBlock', 'area' => 'areaBlock');

	// language-COUNTRY Format RFC 3066 Specification: de-DE -> German
	var     $language = "en-GB";
	// Geo / Met / Safety / Security / Rescue / Fire / Health / Env / Transport / Infra / CBRNE / Other
	var     $category = array();
	private $categoryIndex = 0;
	// The text denoting the type of the subject event of the alert message
	var     $event;
	// Shelter / Evacuate / Prepare / Execute / Avoid / Monitor / Assess / AllClear / None
	var     $responseType = array();
	private $responseTypeIndex=0;
	// Immediate / Expected / Future / Past
	var     $urgency;
	// Extreme / Severe / Moderate / Minor / Unknown
	var     $severity;
	// Observed / Likely / Possible/ Unlikely / Unknown
	var     $certainty;
	// The text describing the intended audience of the alert message
	var     $audience;
	// <eventCode>  <valueName>valueName</valueName>  <value>value</value></eventCode>
	var     $eventCode = array();
	private $eventCodeIndex=0;
	// The effective time(oid)                                                      / Form: <yyyy>-<MM>-T<HH>:<mm>:<ss>+<hour>:<min> Offset to UTC
	var     $effective;
	// The expected time of the beginning of the subject event of the alert message / Form: <yyyy>-<MM>-T<HH>:<mm>:<ss>+<hour>:<min> Offset to UTC -> 2015-01-08T10:05:02+01:00
	var     $onset;
	// The expiry time of the information of the alert message                      / Form: <yyyy>-<MM>-T<HH>:<mm>:<ss>+<hour>:<min> Offset to UTC -> 2015-01-08T15:00:13+01:00
	var     $expires;
	// The text naming the originator of the alert message  (The human-readable name of the agency or authority issuing this alert.) -> ZAMG Österreich
	var     $senderName;
	// The text headline of the alert message
	var     $headline;
	// The text describing the subject event of the alert message
	var     $description;
	// The text describing the recommended action to be taken by recipients of the alert message
	var     $instruction;
	// The identifier of the hyperlink associating additional information with the alert message
	var     $web;
	// The text describing the contact for follow-up and confirmation of the alert message
	var     $contact;
	// A system-specific additional parameter associated with the alert message (as example meteoalarm.eu using it as specific warnings identifier) <parameter>  <valueName>valueName</valueName>  <value>value</value></parameter>
	var     $parameter = array();
	private $parameterIndex=0;
	var     $resource = array();
	private $resourceIndex=0;
	var     $area = array();
	private $areaIndex=0;

	// The code denoting the language of the info sub-element of the alert message (OPTIONAL)
	function getLanguage(){ return $this->language; }
	function setLanguage($val){ return $this->language = (string) $val; }

	// The code denoting the category of the subject event of the alert message (REQUIRED)
	function getCategory(){ return $this->category; }
	function setCategory($val, $index = false){
		switch($val){
			case 'Geo':
			case 'Met':
			case 'Safety':
			case 'Security':
			case 'Rescue':
			case 'Fire':
			case 'Health':
			case 'Env':
			case 'Transport':
			case 'Infra':
			case 'CBRNE':
			case 'Other':
			if($index === false) return $this->category[$this->categoryIndex++] = $val;
			else return $this->category[$index] = $val;
			break;
			default:
			return false;
			break;
		}
	}

	// The text denoting the type of the subject event of the alert message (REQUIRED)
	function getEvent(){ return $this->event; }
	function setEvent($val){ return $this->event = (string) $val; }

	// The code denoting the type of action recommended for the target audience (OPTIONAL)
	function getResponseType(){ return $this->responseType; }
	function setResponseType($val, $index = false){
		switch($val){
			case 'Shelter':
			case 'Evacuate':
			case 'Prepare':
			case 'Execute':
			case 'Avoid':
			case 'Monitor':
			case 'Assess':
			case 'AllClear':
			case 'None':
			if($index === false) return $this->responseType[$this->responseTypeIndex++] = $val;
			else return $this->responseType[$index] = $val;
			break;
			default:
			return false;
			break;
		}
	}

	// The code denoting the urgency of the subject event of the alert message (REQUIRED)
	function getUrgency(){ return $this->urgency; }
	function setUrgency($val){
		switch($val){
			case 'Immediate':
			case 'Expected':
			case 'Future':
			case 'Past':
			case 'Unknown':
			return $this->urgency = $val;
			break;
			default:
			return false;
			break;
		}
	}

	// The code denoting the severity of the subject event of the alert message (REQUIRED)
	function getSeverity(){ return $this->severity; }
	function setSeverity($val){
		switch($val){
			case 'Extreme':
			case 'Severe':
			case 'Moderate':
			case 'Minor':
			case 'Unknown':
			return $this->severity = $val;
			break;
			default:
			return false;
			break;
		}
	}

	// The code denoting the certainty of the subject event of the alert message (REQUIRED)
	function getCertainty(){ return $this->certainty; }
	function setCertainty($val){
		switch($val){
			case 'Observed':
			case 'Likely':
			case 'Possible':
			case 'Unlikely':
			case 'Unknown':
			return $this->certainty = $val;
			break;
			default:
			return false;
			break;
		}
	}

	// The text describing the intended audience of the alert message (OPTIONAL)
	function getAudience(){ return $this->audience; }
	function setAudience($val){ return $this->audience = (string) $val; }

	// A system-specific code identifying the event type of the alert message (OPTIONAL)
	function getEventCode(){ return $this->eventCode; }
	function setEventCode($valn, $val, $index = false){
		if($index === false) return $this->eventCode[$this->eventCodeIndex++] = new parameterBlock($valn, $val);
		else return $this->eventCode[$index] = new parameterBlock($valn, $val);
	}

	// The effective time of the information of the alert message (OPTIONAL)
	function getEffective(){ return $this->effective; }
	function setEffective($val){
		return $this->effective = date("Y-m-d\TH:i:sP", strtotime($val));
	}

	// The expected time of the beginning of the subject event of the alert message (OPTIONAL)
	function getOnset(){ return $this->onset; }
	function setOnset($val){
		return $this->onset = date("Y-m-d\TH:i:sP", strtotime($val));
	}

	// The expiry time of the information of the alert message (OPTIONAL)
	function getExpires(){ return $this->expires; }
	function setExpires($val){
		return $this->expires = date("Y-m-d\TH:i:sP", strtotime($val));
	}

	// The text naming the originator of the alert message (OPTIONAL)
	function getSenderName(){ return $this->senderName; }
	function setSenderName($val){ return $this->senderName = (string) $val; }

	// The text headline of the alert message (OPTIONAL)
	function getHeadline(){ return $this->headline; }
	function setHeadline($val){ return $this->headline = (string) $val; }

	// The text describing the subject event of the alert message (OPTIONAL)
	function getDescription(){ return $this->description; }
	function setDescription($val){ return $this->description = (string) $val; }

	// The text describing the recommended action to be taken by recipients of the alert message (OPTIONAL)
	function getInstruction(){ return $this->instruction; }
	function setInstruction($val){ return $this->instruction = (string) $val; }

	// The identifier of the hyperlink associating additional information with the alert message (OPTIONAL)
	function getWeb(){ return $this->web; }
	function setWeb($val){ return $this->web = (string) $val; }

	// The text describing the contact for follow-up and confirmation of the alert message (OPTIONAL)
	function getContact(){ return $this->contact; }
	function setContact($val){ return $this->contact = (string) $val; }

	// A system-specific additional parameter associated with the alert message (OPTIONAL)
	function getParameter(){ return $this->parameter; }
	function setParameter($valn, $val, $index = false){
		if($index === false) return $this->parameter[$this->parameterIndex++] = new parameterBlock($valn, $val);
		else return $this->parameter[$index] = new parameterBlock($valn, $val);
	}

	function getResource($index = 0){
		return $this->resource[$index];
	}

	function setResource($index = false){
		if($index === false) return $this->resource[$this->resourceIndex++] = new resourceBlock();
		else return $this->resource[$index] = new resourceBlock();
	}
	function addResource($index = false){
		return $this->setResource($index);
	}

	function getArea($index = 0){
		return $this->area[$index];
	}

	function setArea($index = false){
		if($index === false) return $this->area[$this->areaIndex++] = new areaBlock();
		else return $this->area[$index] = new areaBlock();
	}
	function addArea($index = false){
		return $this->setArea($index);
	}
}
/**
*  Get and Set <resource> CAP values
*  The container for all component parts of the resource sub-element of the info sub-element of the alert element (OPTIONAL)
*/
class resourceBlock{
	protected $required = array('resourceDesc', 'mimeType');
	var $resourceDesc;
	var $mimeType;
	var $size;
	var $uri;
	var $derefUri;
	var $digest;

	// The text describing the type and content of the resource file (REQUIRED)
	function getResourceDesc(){ return $this->resourceDesc; }
	function setResourceDesc($val){ return $this->resourceDesc = (string) $val; }

	// The identifier of the MIME content type and sub-type describing the resource file (REQUIRED)
	function getMimeType(){ return $this->mimeType; }
	function setMimeType($val){ return $this->mimeType = (string) $val; }

	// The integer indicating the size of the resource file (OPTIONAL)
	function getSize(){ return $this->size; }
	function setSize($val){ return $this->size = (string) $val; }

	// The identifier of the hyperlink for the resource file (OPTIONAL)
	function getUri(){ return $this->uri; }
	function setUri($val){ return $this->uri = (string) $val; }

	// The base-64 encoded data content of the resource file (CONDITIONAL)
	function getDerefUri(){ return $this->derefUri; }
	function setDerefUri($val){ return $this->derefUri = (string) $val; }

	// The code representing the digital digest (“hash”) computed from the resource file (OPTIONAL)
	function getDigest(){ return $this->digest; }
	function setDigest($val){ return $this->digest = (string) $val; }

}
/**
*  Get and Set <area> CAP values
*  The container for all component parts of the area sub-element of the info sub-element of the alert message (OPTIONAL)
*/
class areaBlock{
	protected $required = array('areaDesc');
	protected $subclass = array('geocode'=>'parameterBlock');
	// A text description of the affected area. -> Niederösterreich
	var     $areaDesc;
	// The paired values of points defining a polygon that delineates the affected area of the alert message
	var     $polygon = array();
	private $polygonIndex = 0;
	// The paired values of a point and radius delineating the affected area of the alert message
	var     $circle = array();
	private $circleIndex = 0;
	// <geocode><valueName>valueName</valueName>  <value>value</value></geocode> -> valueName: NUTS2 value: AT12
	var     $geocode = array();
	private $geocodeIndex = 0;
	var     $altitude;
	var     $ceiling;

	// The container for all component parts of the area sub-element of the info sub-element of the alert message (OPTIONAL)
	function getAreaDesc(){ return $this->areaDesc; }
	function setAreaDesc($val){ return $this->areaDesc = (string) $val; }

	// The text describing the affected area of the alert message (REQUIRED)
	function getPolygon(){ return $this->polygon; }
	function setPolygon($val, $index = false){
		if($index === false) return $this->polygon[$this->polygonIndex++] = (string) $val;
		else return $this->polygon[$index] = (string) $val;
	}

	// The paired values of a point and radius delineating the affected area of the alert message (OPTIONAL)
	function getCircle(){ return $this->circle; }
	function setCircle($val, $index = false){
		if($index === false) return $this->circle[$this->circleIndex++] = (string) $val;
		else return $this->circle[$index] = (string) $val;
	}

	// The geographic code delineating the affected area of the alert message (OPTIONAL)
	function getGeocode(){ return $this->geocode; }
	function setGeocode( $valn, $val, $index = false){
		if($index === false) return $this->geocode[$this->geocodeIndex++] = new parameterBlock($valn, $val);
		else return $this->geocode[$index] = new parameterBlock($valn, $val);
	}

	// The specific or minimum altitude of the affected area of the alert message (OPTIONAL)
	function getAltitude(){ return $this->altitude; }
	function setAltitude($val){ return $this->altitude = (string) $val; }

	// The maximum altitude of the affected area of the alert message (CONDITIONAL)
	function getCeiling(){ return $this->ceiling; }
	function setCeiling($val){ return $this->ceiling = (string) $val; }
}

class parameterBlock {
	protected $required = array('valueName','value');
	var $valueName;
	var $value;

	function __construct($vn="", $v=""){
		$this->setValueName($vn);
		$this->setValue($v);
	}

	function setValueName($str = ""){
		$this->valueName = (string) $str;
	}

	function setValue($str = ""){
		$this->value = (string) $str;
	}
}

/*********************************
*      CAP XML Create Class      *
*********************************/

class CapCreate{
	var $processor;

	function __construct(CapProcessor $processorClass){
		$this->processor = $processorClass;
	}

	/**
	* build CAP 1.2 content XML
	*
	* @return  None
	*/
	function buildCap($index = 0)
	{
		$alert = $this->processor->getAlert($index);
		$xml = new XmlProcessor(/*ver*/'1.0',/*encoding*/'utf-8',array('standalone'=>'yes'));
			$xml->tag_open('alert',array('xmlns' => 'urn:oasis:names:tc:emergency:cap:1.2'));

			$xml->tag_simple('identifier', $alert->identifier);
			$xml->tag_simple('sender', $alert->sender);
			$xml->tag_simple('sent', $alert->sent);
			$xml->tag_simple('status', $alert->status);
			$xml->tag_simple('msgType', $alert->msgType);
			$xml->tag_simple('source', $alert->source);
			$xml->tag_simple('scope', $alert->scope);
			$xml->tag_simple('restriction', $alert->restriction);
			$xml->tag_simple('addresses', $alert->addresses);

			if(!empty($alert->code))
			foreach($alert->code as $key => $val){
				$xml->tag_simple('code', $val);
			}

			$xml->tag_simple('note', $alert->note);
			$xml->tag_simple('references', $alert->references);
			$xml->tag_simple('incidents', $alert->incidents);

			if(!empty($alert->info))
			foreach($alert->info as $key => $info){
				$xml->tag_open('info');

				$xml->tag_simple('language', $info->language);
				if(!empty($info->category))
				foreach($info->category as $key => $val){
					$xml->tag_simple('category', $val);
				}

				$xml->tag_simple('event', $info->event);

				if(!empty($info->responseType))
				foreach($info->responseType as $key => $val){
					$xml->tag_simple('responseType', $val);
				}

				$xml->tag_simple('urgency', $info->urgency);
				$xml->tag_simple('severity', $info->severity);
				$xml->tag_simple('certainty', $info->certainty);
				$xml->tag_simple('audience', $info->audience);

				if(! empty($info->eventCode))
				foreach($info->eventCode as $key => $eventCode){
					if(!empty($eventCode->valueName)) {
						$xml->tag_open('eventCode');
						$xml->tag_simple('valueName', $eventCode->valueName);
						$xml->tag_simple('value', $eventCode->value);
						$xml->tag_close('eventCode');
					}
				}

				$xml->tag_simple('effective', $info->effective);
				$xml->tag_simple('onset', $info->onset);
				$xml->tag_simple('expires', $info->expires);

				$xml->tag_simple('senderName', $info->senderName);

				$xml->tag_simple('headline', $info->headline);
				$xml->tag_simple('description', $info->description);
				$xml->tag_simple('instruction', $info->instruction);

				$xml->tag_simple('web', $info->web);
				$xml->tag_simple('contact', $info->contact);

				if(! empty($info->parameter))
				foreach($info->parameter as $key => $parameter)
				{
					if(!empty($parameter->valueName)) {
						$xml->tag_open('parameter');
						$xml->tag_simple('valueName', $parameter->valueName);
						$xml->tag_simple('value', $parameter->value);
						$xml->tag_close('parameter');
					}
				} // foreach parameter

				if(!empty($info->resource))
				foreach ($info->resource as $key => $resource) {

					$xml->tag_open('resource');

					$xml->tag_simple('resourceDesc', $resource->resourceDesc);
					$xml->tag_simple('mimeType', $resource->mimeType);
					$xml->tag_simple('size', $resource->size);
					$xml->tag_simple('uri', $resource->uri);
					$xml->tag_simple('derefUri', $resource->derefUri);
					$xml->tag_simple('digest', $resource->digest);

					$xml->tag_close('resource');
				}

				if(!empty($info->area))
				foreach ($info->area as $key => $area) {
					$xml->tag_open('area');

					$xml->tag_simple('areaDesc', $area->areaDesc);

					if(!empty($area->polygon))
					foreach($area->polygon as $key => $val){
						$xml->tag_simple('polygon', $val);
					}

					if(!empty($area->circle))
					foreach($area->circle as $key => $val){
						$xml->tag_simple('circle', $val);
					}

					if(! empty($area->geocode))
					foreach($area->geocode as $key => $geocode) {
						if(!empty($geocode->valueName)) {
							$xml->tag_open('geocode');
							$xml->tag_simple('valueName', $geocode->valueName);
							$xml->tag_simple('value', $geocode->value);
							$xml->tag_close('geocode');
						}
					} // foreach geocode

					$xml->tag_simple('altitude', $area->altitude);
					$xml->tag_simple('ceiling', $area->ceiling);

					$xml->tag_close('area');
				}

				$xml->tag_close('info');
			}

			$xml->tag_close('alert');

			$this->processor->output_content = $xml->output();
			return $this->processor->output_content;
		}


		/**
		* Create File
		*
		* @return  path of the New CAP 1.2
		*/
		function saveCap($name = '', $index = 0)
		{
			$alert = $this->processor->getAlert($index);
			if($alert->identifier != "")
			{
				if($this->processor->output_destination == "")
				{
					try{
						if($name == '') $name = $alert->identifier;
						if(substr($name,-4,5) == '.xml') $end_type = ""; else $end_type = ".xml";
						$capfile = fopen($name.$end_type, "w");
						if($capfile === false) 
						{
							$this->processor->error = "Unable to open file! ".$name.$end_type;
							return -1;
						}
						fwrite($capfile, $this->processor->output_content);
						fclose($capfile);
	
						// convert in UTF-8
							
						$data = file_get_contents($name.$end_type);

						if (!preg_match('!!u', $data)){ //this is not utf-8
							$data = mb_convert_encoding($data, 'UTF-8', 'OLD-ENCODING');
						}

						file_put_contents($name.$end_type, $data, LOCK_EX);
						return $name.$end_type;
					} catch (Exception $e) 
					{
						$this->processor->error = "Exception raised: " . $e->getMessage();
						return -1;
					}
				}
				else
				{
					$filefullpath = $this->processor->output_destination.'/'.$name.$end_type;
									
					if($name == '') $name = $alert->identifier;
					if(substr($name,-4,5) == '.xml') $end_type = ""; else $end_type = ".xml";
					$capfile = fopen($filefullpath, "w");
					if($capfile === false) return "Unable to open file! ".$filefullpath;
					fwrite($capfile, $this->processor->output_content);
					fclose($capfile);

					chmod($filefullpath, 0664);  // octal; correct value of mode
					chgrp($filefullpath, filegroup($this->processor->output_destination));

					$data = file_get_contents($filefullpath);

					// convert in UTF-8
					if (!preg_match('!!u', $data)){ //this is not utf-8
						$data = mb_convert_encoding($data, 'UTF-8', 'OLD-ENCODING');
					}

					file_put_contents($filefullpath, $data, LOCK_EX);
					return $this->processor->output_destination.'/'.$name.$end_type;
				}
			}
			else
			{
				return -1;
			}
		}
	}

	/*********************************
	*      CAP XML Read Class      *
	*********************************/

	class CapRead{

		var $processor;
		var $process_class = array();
		var $cap_xml_content = array();
		var $cap_xml_content_index = 0;
		var $is_start = true;
		var $read_count = 0;

		function __construct(CapProcessor $processorClass){
			$this->processor = $processorClass;
		}

		function readCap($xml){
			$this->is_start = true;
			$this->process_class = array();
			if(@is_file($xml)) { // @silent because its posible that this is no path!
				// load XML file into object
				$xml_processed = simplexml_load_file($xml);
			} elseif(is_array($xml) || is_object($xml)) {
				// load xml object
				$xml_processed = $xml;
			} elseif(is_string($xml)) {
				// load xml string
				$xml_processed = simplexml_load_string($xml);
			}  else {
				// xml can not be parsed
				$xml_processed = false;
			}
			if($xml_processed){
				// parse xml object
				$this->cap_xml_content[$this->cap_xml_content_index++] = $xml_processed;
				$this->readCapArray($xml_processed);
			}
		}

		function readCapArray($xml_object,$spec_class='', $class='')
		{
			if($spec_class && method_exists($class, 'add'.ucfirst($spec_class))){
				$process_class_{$spec_class} = $class->{'add'.ucfirst($spec_class)}();
			}
			if($this->is_start)
			{
				$spec_class = 'alert';
				$process_class_{$spec_class} = $this->processor->addAlert();
				$this->is_start = false;
			}
			foreach($xml_object as $key => $val){

				if(!empty($val->valueName) && method_exists($process_class_{$spec_class}, 'set'.ucfirst($key))) {
					$process_class_{$spec_class}->{'set'.ucfirst($key)}($val->valueName, $val->value);

				} else if(class_exists($key.'Block')){
					$this->readCapArray($val,$key, $process_class_{$spec_class});

				} else if(method_exists($process_class_{$spec_class}, 'set'.ucfirst($key))) {
					$process_class_{$spec_class}->{'set'.ucfirst($key)}($val);
				}
			}
		}
	}

	/*********************************
	*      CAP XML write Class      *
	*********************************/

	class XmlProcessor{

		var $file;
		var $break = "\r\n";
		var $tabspace = "\t";
		var $lt = '<';
		var $gt = '>';

		var $tab = 0;
		var $tab_tree=array();

		/**
		* initialize XML
		*
		* @param   string  $version            The version of the XML
		* @param   string  $encoding           The encoding of the XML
		* @param   string  $options            The options of the XML
		* @return  None
		**/
		function __construct($version, $encoding, $options=array()){

			$this->addrow(('<'.'?xml version="'.$version.'" encoding="'.$encoding.'"'.$this->aToT($options).' ?'.'>'));
		}

		/**
		* make a tag in the XML file
		*
		* @param   string  $tag                    The name of the XML Tag
		* @param   string  $value              The value of the XML Tag
		* @param   array       $options            The options of the XML Tag
		* @param   int         $trimtext           Trim or not Trim XML Tag value
		* @return  None
		**/
		function tag_simple($tag,$value='',$options=array(),$trimtext=false)
		{
			if ($trimtext == 1) $value = trim($value);
			if($value == '' and empty($options)) return '';

			$row = ($this->lt.$tag.$this->aToT($options));
			if(trim($value) != ''){
				$row.= ($this->gt);
				$row.= htmlspecialchars(($value), ENT_QUOTES, "UTF-8");
				$row.= ($this->lt.'/'.$tag.$this->gt);
			}else{
				if($tag == 'summary'){
					$row.= ($this->gt);
					$row.= 'No Summary';
					$row.= ($this->lt.'/'.$tag.$this->gt);
				}else{
					$row.= ('/'.$this->gt);
				}
			}
			$this->addrow($row);
		}

		/**
		* make a open tag in the XML file
		*
		* @param   string  $tag                    The name of the XML Tag
		* @param   array       $options            The options of the XML Tag
		* @return  None
		**/
		function tag_open($tag,$options=array() ){
			$row =( $this->lt.$tag.$this->aToT($options).$this->gt );
			$this->addrow($row);
			$this->tab++;
			array_push($this->tab_tree,$tag);
		}

		/**
		* close a open tag in the XML file
		*
		* @param   string  $tag                    The name of the XML Tag
		* @return  None
		**/
		function tag_close($tag){
			$c=0;
			if($this->tab > 0)
			do{
				$ltag = array_pop($this->tab_tree);
				$this->tab--;
				$row = ($this->lt.'/'.$ltag.$this->gt);
				$this->addrow($row);
				$c++;
			}while(($ltag != $tag || ( is_int($tag) && $c < $tag )) && $this->tab > 0  );

		}

		/**
		* Make a blank line in the XML file
		*
		* @return  None
		**/
		function add_emptyrow(){
			$this->addrow('');
		}

		/**
		* Make a <![CDATA[]]> Tag in the XML File
		*
		* @param   string  $value                  The name of the CDATA Tag
		* @return  None
		**/
		function cdata($value){
			return '<![CDATA['.$value.']]>';
		}

		/**
		*
		**/
		function tab(){
			if($this->tab == 0) return '';
			return implode('',array_fill(0,$this->tab,$this->tabspace));
		}

		/**
		* Add a row with content in the XML File
		*
		* @param   string  $content                    The Content wich should be added
		* @return  None
		**/
		function addrow($content){
			$this->file[] = $this->tab().$content.$this->break;
		}

		/**
		* make the option style
		*
		* @param   array   $options                    The options wich should be added
		* @return  None
		**/
		function aToT($options){
			if(!is_array($options)) return '';
			$ret = '';
			foreach($options as $key => $value){
				$ret .= ' '.$key.'="'.$value.'"';
			}
			return $ret;
		}

		/**
		* Add a Comment
		*
		* @param   string  $comment                    the comment
		* @return  None
		**/
		function addComment($comment){
			$this->addrow(('<!--'.$comment.'-->'));
		}

		/**
		* Output The File
		*
		* @return  $this->file     All content of the File
		**/
		function output(){
			return (implode('',$this->file));
		}
	}
	?>
