<?php 
/*
 *  Copyright (c) 2015  Guido Schratzer   <guido.schratzer@backbone.co.at>
 *  Copyright (c) 2015  Niklas Spanring   <n.spanring@backbone.co.at>
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
 *	\file      	index.php
 *  \ingroup   	main
 */
 
/**
 * Front end of the Cap-php-library
 */
	error_reporting(E_ERROR);
	
	require_once 'class/cap.form.class.php';
	require_once 'lib/cap.create.class.php';
	require_once 'lib/cap.write.class.php';
	require_once 'lib/cap.convert.class.php';
	require_once 'class/translate.class.php';
	
	$langs = new Translate();
	if(file_exists('conf/conf.php'))
	{
		include 'conf/conf.php';
	
		if(! empty($_GET['lang'])) $conf->user->lang = $_GET['lang'];
		$langs->setDefaultLang($conf->user->lang);		
		$langs->load("main");	
	}
	else
	{
		$conf->user->lang = 'en_US';
		$langs->setDefaultLang($conf->user->lang);		
		$langs->load("main");	
	}
	
	$conf->meteoalarm = 1;
	if($conf->meteoalarm == 1)
	{			
		if(file_exists('lib/cap.meteoalarm.webservices.Area.php'))
		{
			include 'lib/cap.meteoalarm.webservices.Area.php';		
			if($_GET['web_test'] == 1) die(print_r($AreaCodesArray));
			$AreaCodesArray = $AreaCodesArray['document']['AreaInfo'];
		}
		if(file_exists('lib/cap.meteoalarm.webservices.Parameter.php'))
		{
			include 'lib/cap.meteoalarm.webservices.Parameter.php';		
			if($_GET['web_test'] == 2) die(print_r($ParameterArray));
			$ParameterArray = $ParameterArray['document']['AreaInfo'];
		}
		
		if(is_array($AreaCodesArray) && is_array($AreaCodesArray)) $conf->webservice_aktive = 1;
	}
	if(!file_exists('conf/conf.php'))
	{
		$cap = new CAP_Form();			
		print $cap->install();
	}
	elseif($_GET['conv'] == 1)
	{
		if(! empty($_POST['location']) || ! empty($_FILES["uploadfile"]["name"]))
		{
			require_once 'lib/cap.read.class.php';
			// Get TEST Cap
			if(! empty($_FILES["uploadfile"]["name"]))
			{
				$location = $_FILES["uploadfile"]["tmp_name"];
			}
			else
			{
				$location = $conf->cap->output.'/'.urldecode($_POST['location']);
			}
			
			$alert = new alert($location);
			$cap = $alert->output();
			
			// Convert
			$converter = new Convert_CAP_Class();		
			$capconvertet = $converter->convert($cap, $_POST['stdconverter'],	$_POST['areaconverter'], $_POST['inputconverter'], $_POST['outputconverter'], $conf->cap->output);

			$form = new CAP_Form();
			print $form->CapView($capconvertet, $cap[identifier].'.conv'); // Cap Preview +
		}
		else
		{
			$form = new CAP_Form();
			print $form->ListCap();
		}
	}
	elseif($_GET['read'] == 1)
	{
		require_once 'lib/cap.read.class.php';
		if(! empty($_POST['upload']))
		{
			if(! empty($_FILES["uploadfile"]["name"]))
			{
				// Get TEST Cap
				if(! empty($_FILES["uploadfile"]["name"]))
				{
					$location = $_FILES["uploadfile"]["tmp_name"];
				}
			}
			
			$alert = new alert($location);
			$cap = $alert->output();
			
			$cap_m = new CAP_Class($_POST);
			$cap_m->buildCap_from_read($cap);
			
			$cap_m->identifier = $_FILES["uploadfile"]["name"];
			$cap_m->destination = $conf->cap->output;
			$path = $cap_m->createFile();
			
			header('Location: '.$_SERVER['PHP_SELF'].'#read');
		}
		
		if(! empty($_FILES["uploadfile"]["name"]))
		{
			// Get TEST Cap
			if(! empty($_FILES["uploadfile"]["name"]))
			{
				$location = $_FILES["uploadfile"]["tmp_name"];
			}
			else
			{
				$location = $conf->cap->output.'/'.urldecode($_POST['location']);
			}
		}
		else
		{
			$location = $conf->cap->output.'/'.urldecode($_POST['location']);
		}
		
		$alert = new alert($location);
		$cap = $alert->output();
		//die(print_r($cap)); // DEBUG
		if(! empty($cap['msg_format']))
		{
			print $cap['msg_format'];
			exit;
		}
		
			$form = new CAP_Form($cap);

			print $form->Form();
	}
	elseif(empty($_POST['action']) && $_GET['webservice'] != 1 && empty($_GET['web_test']))
	{
		// Build Cap Creator form
		if(! empty($_GET['delete']))
		{			
			unlink($conf->cap->output.'/'.$_GET['delete']);		
			header('Location: '.$_SERVER['PHP_SELF'].'#read');
		}
		
			$form = new CAP_Form();

			print $form->Form();
	}
	elseif($_POST['action'] == "create" && $_GET['conf'] != 1)
	{
		$form = new CAP_Form();
		$_POST = $form->MakeIdentifier($_POST);
		
		$cap = new CAP_Class($_POST);
		
		if(!empty($_GET['cap']))
		{
			// Used for the Cap preview
			$cap->buildCap();
			print $cap->cap;
		}
		else
		{
			// Used to build the cap and save it at $cap->destination
			$cap->buildCap();
			$cap->destination = $conf->cap->output;
			if($conf->cap->save == 1)	$path = $cap->createFile();
			
			$conf->identifier->ID_ID++;
			$form->WriteConf();
			
			print $form->CapView($cap->cap, $_POST[identifier]); // Cap Preview +
		}
	}
	elseif($_GET['webservice'] == 1)
	{
		// start webservices
			$form = new CAP_Form();

			print $form->Webservice($_POST[filename]);
	}
	elseif($_GET['conf'] == "1")
	{
		$form = new CAP_Form();		
		$form->PostToConf($_POST['conf']);		
		$form->WriteConf();
		return true;
	}
	elseif($_GET['web_test'] == "1")
	{
		
	}
	
	/**
   * encrypt and decrypt function for passwords
   *     
   * @return	string
   */
	function encrypt_decrypt($action, $string, $key) 
	{
		global $conf;
		
		$output = false;
	
		$encrypt_method = "AES-256-CBC";
		$secret_key = ($key?$key:'NjZvdDZtQ3ZSdVVUMXFMdnBnWGt2Zz09');
		$secret_iv = ($conf->webservice->securitykey ? $conf->webservice->securitykey : 'WebTagServices#hash');
	
		// hash
		$key = hash('sha256', $secret_key);
		
		// iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
		$iv = substr(hash('sha256', $secret_iv), 0, 16);
	
		if( $action == 1 ) {
			$output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
			$output = base64_encode($output);
		}
		else if( $action == 2 ){
			$output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
		}
	
		return $output;
	}
	
?>