<?php


class S_Tokens{

	//                          CONFIGURE
	// ==========================================================

	//database config
	private static $dbConfig = [
		'driver' => "mysql",
		'charset' => "utf8",
		'table_name' => "S_Tokens"
	];


	private static $settings = [
		'tokenLength' => 48, //max 255
		'hashCost' => 12
	];

	//default time before token expires (minutes)
	private static $defaultTimeout = 60;

	//                         INFORMATION
    // ===========================================================
    /*

    create a database table with:

    CREATE TABLE IF NOT EXISTS `S_Tokens` (
    `TokenID` INT(15) NOT NULL AUTO_INCREMENT ,
    `RelevantID` VARCHAR(256) NULL ,
    `IPAddress` VARCHAR(45) NULL ,
    `SessionID` VARCHAR(127) NULL ,
    `Token` VARCHAR(256) NOT NULL ,
    `Type` VARCHAR(128) NOT NULL ,
    `Timestamp` DATETIME NOT NULL ,
    `Hashed` BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (`TokenID`)
	) ENGINE = InnoDB;

	and index all values.


    creating Token (see function definition):

    S_Tokens::createToken($relevantID, $IPAddress, $sessionID, $type, $hash = true, $unique = true);


    verifying Token (see function definition):

    S_Tokens::verifyToken($relevantID, $IPAddress, $sessionID, $type, $token, $timeout = null, $autoDelete = true, $unique = true);


    Manually delete token (see function definition):

    S_Tokens::deleteToken($relevantID, $IPAddress, $sessionID, $type);

    */
	//                         FUNCTIONS
	// ===========================================================


    //creates and stores new token with up to three selectors: relevantID, IP, sessionID.
    //type defines what the token is for, user defined. Example: "login", "CSRF", "myForm".
    //hash specifies whether the token should be hashed, on by default.
    //unique will ensure that the token is unique for the provided selectors, on by default.
    //returns the token, or false on error
	public static function createToken($relevantID, $IPAddress, $sessionID, $type, $hash = true, $unique = true){

		//if token needs to be unique, delete all tokens that match selectors, on by default
		if($unique === true){
			self::deleteToken($relevantID, $IPAddress, $sessionID, $type);
		}

        try{

            //create database connection
            $db = self::_databaseConnect();

            //generate token
            $tokenValue = self::generateToken();

            //hash token if specified, On by default
            $token = $tokenValue;
            if($hash){
                $token = password_hash($token, PASSWORD_BCRYPT, ["cost" => self::$settings["hashCost"]]);
            }

            $queryArray = self::createQuery("insert", $relevantID, $IPAddress, $sessionID, $type, $token, $hash, null, null);

            $stmt = $db->prepare($queryArray["query"]);
            $result = $stmt->execute($queryArray["params"]);

            unset($token, $stmt, $queryArray);

            if($result){
                return $tokenValue;
            } else{
                return false;
            }

        }catch(Exception $e){
            return false;
        }
	}

    //verify a token given up to three selectors: relevantID, IP, sessionID.
    //type defines what the token is for, user defined. Example: "login", "CSRF", "myForm".
    //timeout defines how old a token can be before failing verification in minutes, defaults to class default $defaultTimeout, see configure.
    //autoDelete specifies whether the token should be deleted upon verification. On by default.
    //unique specifies whether or not there must be only one match for the given selectors. On by default.
	///strict specifies whether AND or OR is used in building selector query
	public static function verifyToken($relevantID, $IPAddress, $sessionID, $type, $token, $timeout = null, $autoDelete = true, $unique = true, $strict = true){

        try{

            //create database connection
            $db = self::_databaseConnect();

            //build query
            $queryArray = self::createQuery("select", $relevantID, $IPAddress, $sessionID, $type, null, null, $timeout, $strict);

            //query database
            $stmt = $db->prepare($queryArray["query"]);
            $stmt->execute($queryArray["params"]);
            $result = $stmt->fetchAll();

            //get count
            $count = count($result);

            //if theres no result, return false
            if($count == 0){
                return false;
            }

            //if unique answer is required, and there are multiple rows, return false
            if($unique && $count > 1){
                return false;
            }

            //if token was hashed -> password_verify token
            if($result[0]["Hashed"] == true){
                if(password_verify($token, $result[0]["Token"])){

                    //token verified, delete if autoDelete is set
                    if($autoDelete){
                        self::deleteToken($relevantID, $IPAddress, $sessionID, $type);
                    }

                    return true;
                } else{
                    return false;
                }
            }

            //otherwise, constant time verify token string
            else {
                if(hash_equals($token, $result[0]["Token"])){

                    //token verified, delete if autoDelete is set
                    if($autoDelete){
                        self::deleteToken($relevantID, $IPAddress, $sessionID, $type);
                    }

                    return true;
                } else{
                    return false;
                }
            }

        }catch(Exception $e){
            return false;
        }
	}

    //deletes all tokens for the given selectors, handled automatically by default.
    public static function deleteToken($relevantID, $IPAddress, $sessionID, $type){
        $queryArray = self::createQuery("delete", $relevantID, $IPAddress, $sessionID, $type, null, null, null, null);

        try{
            $db = self::_databaseConnect();

            $stmt = $db->prepare($queryArray["query"]);
            $result = $stmt->execute($queryArray["params"]);
            return (bool)$result;
        }catch(PDOException $e){
            return false;
        }
    }

	//creates the actual token value.
	public static function generateToken(){
		if(function_exists('random_bytes')) {
			return random_bytes(self::$settings['tokenLength']);
		}
		else if(function_exists('mcrypt_create_iv')) {
			return bin2hex(mcrypt_create_iv(self::$settings['tokenLength'], MCRYPT_DEV_URANDOM));
		} else {
			return bin2hex(openssl_random_pseudo_bytes(self::$settings['tokenLength']));
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

    //used to build the required queries.
	public static function createQuery($queryType, $relevantID, $IPAddress, $sessionID, $type, $token, $hashed, $timeout, $strict){

		"SELECT * FROM `S_Tokens` WHERE `RelevantID` = :relevantID AND `IPAddress` = :IPAddress AND `SessionID` = :sessionID AND `Type` = :type AND `Timestamp` > DATE_SUB(NOW(), INTERVAL :currentTimeframe MINUTE)";

		"INSERT INTO `S_Tokens`(`TokenID`, `RelevantID`, `IPAddress`, `SessionID`, `Token`, `Type`, `Timestamp`, `Hashed`) VALUES (null,:relevantID,:IpAddress,:sessionID,:token,:type,NOW(),:hashed)";

		//setup query strings
		$beginSelect = 'SELECT * FROM ' . self::$dbConfig['table_name'] . " WHERE ";
		$beginDelete = 'DELETE FROM '   . self::$dbConfig['table_name'] . " WHERE ";

		//used for adding AND clause properly
		$previousParamExists = false;
		$and = "AND ";
		$or = "OR ";

		$q_relevantID = '`RelevantID` = :relevantID ';
		$q_IPAddress = '`IPAddress` = :IPAddress ';
		$q_sessionID = '`SessionID` = :sessionID ';
		$q_type = '`Type` = :type ';
		$q_timestamp = '`Timestamp` > DATE_SUB(NOW(), INTERVAL :currentTimeframe MINUTE) ';

		$semicolon = ';';

		$query = '';

		$params = array();


		//begin query building
		if($queryType == 'select'){
			$query .= $beginSelect;

			//add optional relevantID clause
			if($relevantID !== null ){
				if($previousParamExists){
					if($strict){
						$query .= $and;
					}else{
						$query .= $or;
					}
				}
				$previousParamExists = true;

				$query .= $q_relevantID;
				$params[':relevantID'] = $relevantID;
			}

			//add add optional IPAddress clause
			if($IPAddress !== null ){
				if($previousParamExists){
					if($strict){
						$query .= $and;
					}else{
						$query .= $or;
					}
				}
				$previousParamExists = true;

				$query .= $q_IPAddress;
				$params[':IPAddress'] = $IPAddress;
			}

			//add optional sessionID clause
			if($sessionID !== null ){
				if($previousParamExists){
					if($strict){
						$query .= $and;
					}else{
						$query .= $or;
					}
				}
				$previousParamExists = true;

				$query .= $q_sessionID;
				$params[':sessionID'] = $sessionID;
			}

			//add optional type clause
			if($type !== null ){
				if($previousParamExists){
					if($strict){
						$query .= $and;
					}else{
						$query .= $or;
					}
				}
				$previousParamExists = true;

				$query .= $q_type;
				$params[':type'] = $type;
			}

			//add optional timeout clause, or use default if not provided
			if($timeout !== null ){
				if($previousParamExists){
					$query .= $and;
				}
				$previousParamExists = true;

				$query .= $q_timestamp;
				$params[':currentTimeframe'] = $timeout;
			}else{
				if($previousParamExists){
					$query .= $and;
				}
				$previousParamExists = true;

				$query .= $q_timestamp;
				$params[':currentTimeframe'] = self::$defaultTimeout;
			}
			$query .= $semicolon;

		}

		//create query for inserting token
		else if($queryType == 'insert'){

			$query = "INSERT INTO " . self::$dbConfig['table_name'] . " (`TokenID`, `RelevantID`, `IPAddress`, `SessionID`, `Token`, `Type`, `Timestamp`, `Hashed`) VALUES (null,:relevantID,:IPAddress,:sessionID,:token,:type,NOW(),:hashed);";

			$params[":relevantID"] = $relevantID;
			$params[":IPAddress"]  = $IPAddress;
			$params[":sessionID"]  = $sessionID;
			$params[":token"] = $token;
			$params[":type"] = $type;
			$params[":hashed"] = (int)$hashed;

		}

		//create query for deleting tokens
		else if($queryType == 'delete'){
			$query .= $beginDelete;

			//add optional relevantID clause
			if($relevantID !== null ){
				if($previousParamExists){
					$query .= $and;
				}
				$previousParamExists = true;

				$query .= $q_relevantID;
				$params[':relevantID'] = $relevantID;
			}

			//add add optional IPAddress clause
			if($IPAddress !== null ){
				if($previousParamExists){
					$query .= $and;
				}
				$previousParamExists = true;

				$query .= $q_IPAddress;
				$params[':IPAddress'] = $IPAddress;
			}

			//add optional sessionID clause
			if($sessionID !== null ){
				if($previousParamExists){
					$query .= $and;
				}
				$previousParamExists = true;

				$query .= $q_sessionID;
				$params[':sessionID'] = $sessionID;
			}

			//add optional type clause
			if($type !== null ){
				if($previousParamExists){
					$query .= $and;
				}
				$previousParamExists = true;

				$query .= $q_type;
				$params[':type'] = $type;
			}

		}

		return array(
			"query" => $query,
			"params" => $params
		);
	}


}





?>