<?php

    //Defines the base directory for the project. If using Project_Base_directory/s_core/s_setup.php format -> no change required.
    define("BASE_DIR", realpath(dirname(__FILE__) . "/../"));

    //these files can NOT be included with addFiles()
    const NO_INCLUDE_LIST = array(
        "s_info.php"
    );

    //these files will be included when calling addFiles(COMMON_INCLUDES).
    const COMMON_INCLUDES = array(
        "s_sql.php",
		"s_bruteForce.php",
		"s_tokens.php",
		"s_session.php"
    );

    //Defines clearance level needed to include file with addFile, default is 0, format: file => clearance
    const FILE_CLEARANCES = array(
        "example.php" => 7 //remove this upon real use
    );

?>