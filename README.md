# Sunder
Webapp Development Base

Features:

  1) s_sql.php:
    Provides a clean and easy way to connect with your database and perform queries. Simplifies messy query preparation and stray SQL
    strings into an abstracted 1 line command. Ex: $result = $sql->q_myQuery($param1, $param2)->f_autoReturn();
    
  2) s_tokens.php:
    Easily store and validate randomly generated tokens in your database with a single command. 
    
  3) s_bruteForce.php:
    Throttle a users ability to perform an action (e.g attempting to login with bad credentials) based on time. Automatically handles 
    database entries, and is very configurable.
    
  4) s_session.php:
    secure session abstraction that automatically and silently regenerates after a certain amount of time or if it detects
    session tampering
    
  5) s_functions.php:
    provides a couple of useful functions, mainly the ability to more easily include files without worrying about pathing errors, and
    can be configured to require clearance levels to access certain files.
    
  6) s_info.php:
    A place to store database login info for use through the rest of Sunder. Feel free to add other information functions
    based on what you need.
    
  7) s_setup.php:
    defines constants for use by Sunder, including file clearance levels, no includes, and common includes for addfiles()
    

initial setup:

	1) Place s_core file directly inside base directory.
	2) initialize all values in s_info.php
	3) Initialise all values in s_setup.php.
	4) setup s_bruteForceBlock defaults and config.
	5) setup s_tokens.php defaults and config.
