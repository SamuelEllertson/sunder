<?php

    require_once(dirname(__FILE__) . "/s_setup.php");

    //includes files without worrying about pathing (e.g instead of "/some/relative/path/myCode.php" you can just specify "myCode.php")
    //takes in array of file names: addFiles(array("code1.php", "functions.php", "includeMe.php"));
    //Note: only works if there are no duplicate filenames anywhere in the project directory. If thats not possible, avoid use of this.
    if(!function_exists("addFiles")){
        function addFiles($arr, $clearance = 0){

    		try{
    			//remove files that are in NO_INCLUDE_LIST of s_setup.php
    			foreach($arr as $key => $desiredFile){
    				foreach(NO_INCLUDE_LIST as $noInclude){
    					if($desiredFile == $noInclude){
    						unset($arr[$key]);
    					}
    				}
    			}

    			//remove files that are do not have clearance. see FILE_CLEARANCES of s_setup.php
    			foreach($arr as $key => $desiredFile){
    				foreach(FILE_CLEARANCES as $clearanceFileName => $clearanceVal){
    					if($desiredFile == $clearanceFileName && $clearance < $clearanceVal){
    						unset($arr[$key]);
    					}
    				}
    			}

    			$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_DIR), RecursiveIteratorIterator::SELF_FIRST);

    			$filenames = array();

    			foreach($objects as $object){
    				$objectName = $object->getFilename();

    				if ($objectName == '.' || $objectName == '..' || $object->isDir()) {
    					continue;
    				} else{
    					$filenames[] = $objectName; //push all filenames into array for testing for dupes
    				}

    				foreach($arr as $desiredFile){
    					if($objectName == $desiredFile){
    						include_once( realpath( $object->getPathname() ) );
    					}
    				}
    			}

    			if(array_has_dupes($filenames)){
    				throw new Exception('Duplicate filenames are not allowed');
    			}
    		} catch (Exception $e){
    			echo 'Caught exception: ' . $e->getMessage() . "\n";

    		}
        }
    }

    if(!function_exists("noHTML")){
        function noHTML($input, $encoding = 'UTF-8'){
            return htmlentities($input, ENT_QUOTES | ENT_HTML5, $encoding, false);
        }
    }

   if(!function_exists("array_has_dupes")){
        function array_has_dupes($array) {
			return count($array) !== count(array_unique($array));
		}
    }





?>