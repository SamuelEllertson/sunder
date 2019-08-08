<?php

class S_BruteForce{

	//                          CONFIGURE
	// ==========================================================

	// array of throttle settings. #failed_attempts => delay/captcha
	private static $defaultSettings = [
			6 => 5, 		//delay in seconds
            10 => 7,
			12 => 'captcha' //captcha
	];

	//database config
	private static $dbConfig = [
		'driver' => "mysql",
		'charset' => "utf8",
		'auto_clear' => true,
		'table_name' => "S_BruteForce"
	];

	//time frame to use when retrieving the number of recent failed logins from database (minutes)
	private static $defaultTimeFrame = 1;


    //                         INFORMATION
    // ===========================================================
    /*

    create a database table with:

    CREATE TABLE IF NOT EXISTS `S_BruteForce` (
      `HitID` int(11) NOT NULL AUTO_INCREMENT,
      `RelevantID` varchar(256) DEFAULT NULL,
      `IPAddress` varchar(45) DEFAULT NULL,
      `Timestamp` datetime NOT NULL,
      `Location` varchar(128) NOT NULL,
      PRIMARY KEY (`HitID`)
    );

    and index all the values


    add a hit with S_BruteForce::addHit($relevantID, $IPAddress, $location)


	check the status with:

	$BFstatus = S_BruteForce::getStatus(2, $_SERVER["REMOTE_ADDR"], "test");
	switch($BFstatus["status"]):
		case "safe":
			echo "safe";
			break;
		case "error":
			echo "error: ".$BFstatus["message"];
			break;
		case "delay":
			echo "remaining delay: ".$BFstatus["message"];
			break;
		case "captcha":
			echo "captcha";
			break;
	endswitch;


    */
	//                         FUNCTIONS
	// ===========================================================



    //Add a hit to the database, can enter a relevantID (userid, etc) / IP address -> only one required, better if both, and location ("login", "adminStuff", etc).
	public static function addHit($relevantID, $IPAddress, $location){

        //clear old hits if specified to
        if(self::$dbConfig["auto_clear"] === true){
            S_BruteForce::clearOldHits();
        }

		$db = S_BruteForce::_databaseConnect();

		try{

			$query = 'INSERT INTO '.self::$dbConfig["table_name"].' SET `RelevantID` = ?, `IPAddress` = ?, timestamp = NOW(), `Location` = ?;';

			$stmt = $db->prepare($query);

			$stmt->execute(array($relevantID, $IPAddress, $location));

			return true;
		} catch(PDOException $ex){
			return false;
		}
	}

    //Get the status (safe, delay, captcha) of a relevantID/IPaddress -> only one required, better if both. Can optionally specify location, options (throttle settings), and timeframe
    public static function getStatus($relevantID, $IPAddress, $location = null, $options = null, $timeframe = null){

        //clear old hits if specified to
        if(self::$dbConfig["auto_clear"] === true){
            S_BruteForce::clearOldHits();
        }

        $db = S_BruteForce::_databaseConnect();

        //set options to use
        $currentOptions = self::$defaultSettings;
        if($options != null){
            $currentOptions = $options;
        }

		//Find smallest option in $currentOptions
		$minHits = PHP_INT_MAX;
		foreach($currentOptions as $key => $val){
			if($key < $minHits){
				$minHits = $key;
			}
		}

		//Find how many hits to trigger captcha
		$maxHitsBeforeCaptcha = PHP_INT_MAX;
		foreach($currentOptions as $key => $val){
			if($val == 'captcha'){
				$maxHitsBeforeCaptcha = $key;
				break;
			}
		}

        //set timeframe to use
        $currentTimeframe = self::$defaultTimeFrame;
        if($timeframe != null){
            $currentTimeframe = $timeframe;
        }

        //setup response array
        $response_array = array(
            'status' => 'safe',
            'message' => null
        );

        $foundHits = 0;

        //if given a relevantID, find all hits and sets foundhits if larger
        if($relevantID != null){

			//setup query and parameters
			$query = "SELECT COUNT(`HitID`) AS 'Hits' FROM `S_BruteForce` WHERE `RelevantID` = :relevantID AND `Timestamp` > DATE_SUB(NOW(), INTERVAL :currentTimeframe MINUTE)";

			$params = array(":relevantID" => $relevantID, ":currentTimeframe" => $currentTimeframe);

			//if given a location, include that in query
			if($location != null){
				$query .= " AND `Location` = :location;";
				$params[":location"] = $location;
			}else {
				$query .= ";";
			}

			//execute query
			try{
				$stmt = $db->prepare($query);

            	$stmt->execute($params);

				$count = $stmt->fetch()["Hits"];
			}catch(PDOException $e){
				$response_array['status'] = 'error';
				$response_array['message'] = $ex->getMessage();
				return $response_array;
			}

			//set $foundhits if $count is larger
            if($count > $foundhits){
                $foundhits = $count;
            }
        }

		//if given an IP address, find all hits and sets foundhits if larger
        if($IPAddress != null){

			//setup query and parameters
			$query = "SELECT COUNT(`HitID`) AS 'Hits' FROM `S_BruteForce` WHERE `IPAddress` = :IPAddress AND `Timestamp` > DATE_SUB(NOW(), INTERVAL :currentTimeframe MINUTE)";

			$params = array(":IPAddress" => $IPAddress, ":currentTimeframe" => $currentTimeframe);

			//if given a location, include that in query
			if($location != null){
				$query .= " AND `Location` = :location;";
				$params[":location"] = $location;
			}else {
				$query .= ";";
			}

            //execute query
			try{
				$stmt = $db->prepare($query);

            	$stmt->execute($params);

				$count = $stmt->fetch()["Hits"];
			}catch(PDOException $e){
				$response_array['status'] = 'error';
				$response_array['message'] = $ex->getMessage();
				return $response_array;
			}

            if($count > $foundhits){
                $foundhits = $count;
            }
        }

		//CAPTCHA RESPONSE
		if($foundhits >= $maxHitsBeforeCaptcha){
			$response_array["status"] = 'captcha';
			$response_array["message"] = 'captcha';
			return $response_array;
		}

		//DELAY RESPONSE
		else if($foundhits >= $minHits){

			//find the required delay as specified in the currentOptions
			$requiredDelay = 0;
			krsort($currentOptions);
			foreach($currentOptions as $attempts => $delay){
				if($foundhits > $attempts && is_numeric($delay)){
					$requiredDelay = $delay;
					break;
				}
			}
			$requiredDelay = new DateInterval("PT0H0M".$requiredDelay."S");

			//setup queries to find the most recent hit
			$query1 = "SELECT MAX(`Timestamp`) AS last_hit FROM ".self::$dbConfig["table_name"]." WHERE `RelevantID` = :RelevantID AND `Timestamp` > DATE_SUB(NOW(), INTERVAL :currentTimeframe MINUTE)";

            $params1 = array(":RelevantID" => $relevantID, ":currentTimeframe" => $currentTimeframe);

			$query2 = "SELECT MAX(`Timestamp`) AS last_hit FROM ".self::$dbConfig["table_name"]." WHERE `IPAddress` = :IPAddress AND `Timestamp` > DATE_SUB(NOW(), INTERVAL :currentTimeframe MINUTE)";

			$params2 = array(":IPAddress" => $IPAddress, ":currentTimeframe" => $currentTimeframe);

			//if given a location, include that in query
			if($location != null){
				$query1 .= " AND `Location` = :location;";
				$query2 .= " AND `Location` = :location;";
				$params1[":location"] = $location;
				$params2[":location"] = $location;
			}else {
				$query1 .= ";";
				$query2 .= ";";
			}

			//execute queries and create dateTimes based on response
			$stmt = $db->prepare($query1);
            $stmt->execute($params1);
            $result = $stmt->fetch()["last_hit"];

            if($result != null){
                $lastHitTime = new DateTime($result);
            }

			$stmt = $db->prepare($query2);
			$stmt->execute($params2);
			$result = $stmt->fetch()["last_hit"];

            if($result != null){
                $temp = new DateTime($result);
                if($temp > $lastHitTime){
                    $lastHitTime = new DateTime($result);
                }
            }

			//has finished delay -> SAFE
            $now = new DateTime;
			if($lastHitTime->add($requiredDelay) < $now){
				$response_array["status"] = 'safe';
				$response_array["message"] = 'safe';
				return $response_array;
			}

			//delay left -> DELAY
			else{
                $remainingDelay = $lastHitTime->getTimeStamp() - $now->getTimeStamp();

				$response_array["status"] = 'delay';
				$response_array["message"] = max($remainingDelay, 1);
				return $response_array;
			}

		}

		//SAFE RESPONSE
		else{
			$response_array["status"] = 'safe';
			$response_array["message"] = 'safe';
			return $response_array;
		}
    }

    //setup and return database connection, this is handled automatically
    private static function _databaseConnect(){
        require_once(dirname(__FILE__) . "/s_info.php");

        $info = getDBInfo();

        //connect to database
        $str = self::$dbConfig['driver'].":host=".$info['host'].";dbname=".$info['dbname']. ";charset=".self::$dbConfig['charset'];

        $db = new PDO($str, $info['user'], $info['pass']);

        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        unset($info, $str);

        //return the db connection object
        return $db;
    }

    //clears outdated hits from the database, this is handled automatically, see $dbConfig
    private static function clearOldHits(){
        try{

            $db = S_BruteForce::_databaseConnect();

            $stmt = $db->query("DELETE FROM ".self::$dbConfig["table_name"]." WHERE `Timestamp` < DATE_SUB(NOW(), INTERVAL ".(self::$defaultTimeFrame * 2)." MINUTE);");
            $stmt->execute();

        } catch(PDOException $e){
            return "PDOException: ".$e->getMessage();
        }
    }
}


?>