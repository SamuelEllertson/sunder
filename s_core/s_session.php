<?php

class S_Session{


	//                          CONFIGURE
	// ==========================================================


	private static $settings = [

	];

	//default time before trying to regenerate session (minutes)
	private static $defaultTimeout = 0.15;

    //how long old sessions are valid for before complete destruction (minutes)
    private static $oldSessionLockout = 0.10;



	//                         INFORMATION
    // ===========================================================
    /*

    call S_Session::startSession(); near the top of your php.

    use S_Session::get() and S_Session::set() to get/set

    refer to main functions below for list of useful functions


    */
	//                         FUNCTIONS
	// ===========================================================



	public static function startSession($regenerated = false){

		ob_start();

		session_start(array(
			'use_strict_mode' => 1,
			'use_only_cookies' => 1,
			'cookie_httponly' => true
		));

		//just created new session
		if(!isset($_SESSION["s_setup"]) || $regenerated){
			$_SESSION["s_setup"]      = true;
			$_SESSION["s_identity"]   = self::generateIdentity();
			$_SESSION["s_obsolete"]   = false;
            $_SESSION["s_expires"]    = null;
			$_SESSION["s_loggedIn"]   = false;
			$_SESSION["s_privilege"]  = 0;
			$_SESSION["s_admin"]      = false;
			$_SESSION["s_timestamp"]  = time();
			$_SESSION["s_username"]   = null;
			$_SESSION["s_userID"]     = null;
			$_SESSION["unique test"]  = rand();
            $_SESSION["session_id"]   = session_id();
		}

		//otherwise, make sure those values exist
		else{
			isset($_SESSION["s_setup"])     ? true : $_SESSION["s_setup"]     = true;

			isset($_SESSION["s_identity"])  ? true : $_SESSION["s_identity"]  = self::generateIdentity();

			isset($_SESSION["s_obsolete"])  ? true : $_SESSION["s_obsolete"]  = false;

            isset($_SESSION["s_expires"])   ? true : $_SESSION["s_expires"]   = null;

			isset($_SESSION["s_loggedIn"])  ? true : $_SESSION["s_loggedIn"]  = false;

			isset($_SESSION["s_privilege"]) ? true : $_SESSION["s_privilege"] = 0;

			isset($_SESSION["s_admin"])     ? true : $_SESSION["s_admin"]     = false;

			isset($_SESSION["s_timestamp"]) ? true : $_SESSION["s_timestamp"] = time();

			isset($_SESSION["s_username"])  ? true : $_SESSION["s_username"]  = null;

			isset($_SESSION["s_userID"])    ? true : $_SESSION["s_userID"]    = null;
		}

        //session is obsolete ->session has already been regenerated. Session is expired -> destroy it
		if(self::isObsolete() && self::get("s_expires") < time()){

			//destroy session
			$_SESSION = array();
			session_destroy();

            //restart session
			session_start();

        }

        //session is obsolete ->session has already been regenerated->send it
        else if(self::get("s_obsolete")){
            session_start();
        }

        //session has not been regenerated yet
        else{

            //session is too old
            if(self::needsRegeneration()){
                self::regenerateSession();
            }

            //session has been tampered with
            else if(self::attackDetected()){

                //destroy session
                $_SESSION = array();
                session_destroy();

                self::regenerateSession();
            }
        }


		ob_flush();
	}


	public static function regenerateSession(){

		//if obsolete -> session already regenerated
		if(self::get("s_obsolete")){
			return;
		}

		//set this session to obsolete
		self::set("s_obsolete", true);
        self::set("s_expires", time() + self::$oldSessionLockout);

		// Create new session without destroying the old one
		session_regenerate_id(false);

		// Grab current session ID and close both sessions to allow other scripts to use them
		$newSession = session_id();
		session_write_close();

		// Set session ID to the new one, and start it back up again
		session_id($newSession);
		self::startSession(true);

		//Ensure new session is not obsolete at start
		self::set("s_obsolete", false);
        self::set("s_expires", null);


	}

	private static function needsRegeneration(){

        //if session is obsolete, it has already been regenerated
		if(self::isObsolete()){
			return false;
		}

		//get timestamp
		$timestamp = self::get("s_timestamp");

		if( ($timestamp + (self::$defaultTimeout * 60)) < time()){
			return true;
		}

		return false;

	}

    private static function isObsolete(){
        return self::get("s_obsolete");
    }

	private static function attackDetected(){
        return !hash_equals(self::get("s_identity"), self::generateIdentity());
	}

    private static function generateIdentity(){
        return sha1($_SERVER["REMOTE_ADDR"] . $_SERVER['HTTP_USER_AGENT']);
    }

    //                    Main Functions
    //=========================================================

	public static function set($property, $value){

		//if a session needs to start
		if(session_status() === 1){
			//create/resume session
			self::startSession();
		}

		//set value
		$_SESSION[$property] = $value;
	}

	public static function get($property){

		//if a session needs to start
		if(session_status() === 1){
			//create/resume session
			self::startSession();
		}

		//set value
		$value = $_SESSION[$property];

		//return value
		return $value;
	}

	public static function getLoggedIn(){
		return self::get("s_loggedIn");
	}

	public static function setLoggedIn($loggedIn){
		self::set("s_loggedIn", $loggedIn);
	}

	public static function getUsername(){
		return self::get("s_username");
	}

	public static function setUsername($username){
		self::set("s_username", $username);
	}

	public static function getUserID(){
		return self::get("s_userID");
	}

	public static function setUserID($userID){
		self::set("s_userID", $userID);
	}

	public static function getPrivilege(){
		return self::get("s_privilege");
	}

	public static function setPrivilege($privilege){
		self::set("s_privilege", $privilege);
	}

	public static function getAdmin(){
		return self::get("s_admin");
	}

	public static function setAdmin($admin){
		self::set("s_admin", $admin);
	}
}


?>