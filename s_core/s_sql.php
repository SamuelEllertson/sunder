<?php


class SQL{
	public $conn;
	public $Array;
	public $stmt;
	public $result;

    //                                   INFORMATION
    // =======================================================================================
    /*

    Create Connection with: $sql = new SQL;

    To add a query, go to the query section, copy/paste the sample query, change the name, args, and query.

    Use autoVoid() when you dont need return values (e.g Update query): $sql->q_sample1($param1, $param2)->f_autoVoid();

    autoReturn() returns 2d array of values: $result = $sql->q_sample2($param1, $param2)->f_autoReturn();

    autoDisplay() echos an html table from the result of a query. $sql->q_sample1($param1, $param2)->f_autoDisplay(true, "myTableClass");


    */
	//									CORE FUNCTIONS
	//=========================================================================================

	public function __construct( ) {
		try{
			require_once(dirname(__FILE__) . "/s_info.php");
			$info = getDBInfo();

			$str = "mysql:host=".$info['host'].";dbname=".$info['dbname'];
        	$this->conn = new PDO($str, $info['user'], $info['pass']);

			unset($info, $str);
		} catch(Exception $e){
			echo "Error connecting to database";
			exit;
		}
    }

	public function f_getNumRows(){
		return count($this->result);
	}

	public function f_getNumCols(){
		return count($this->result[0]) / 2;
	}

	public function f_prepare(){
		try{
			$this->stmt = $this->conn->prepare($this->Array[0]);
			return $this;
		} catch(Exception $e){
			echo "Failed to Prepare Statement";
			exit;
		}
	}

	public function f_bind(){
		try{
			for($i = 1; $i < sizeof($this->Array); $i++){
				$this->stmt->bindValue($i, $this->Array[$i]);
			}
			return $this;
		} catch(Exception $e){
			echo "Failed to Bind Variables";
			exit;
		}
	}

	public function f_execute(){
		try{
			$this->stmt->execute();
			return $this;
		} catch(Exception $e){
			echo "Failed to Execute Query";
			exit;
		}
	}

	public function f_getResult(){
		try{
			$this->result = $this->stmt->fetchAll();
			return $this->result;
		} catch(Exception $e){
			echo "Failed to Fetch Results";
			exit;
		}
	}

	public function f_autoVoid(){
		$this->f_prepare()->f_bind()->f_execute();
		return $this;
	}

	public function f_autoReturn(){
		return $this->f_prepare()->f_bind()->f_execute()->f_getResult();
	}

	public function f_autoDisplay($title = true, $class = "queryOutput"){
		$this->f_autoReturn();

		if($this->result[0] != NULL){
			$keys = array_keys($this->result[0]);
		}

		echo "<table class='".$class."'>";
		if($title and $this->result[0] != NULL){
			echo "<tr>";
			for($i = 0; $i < count($keys); $i += 2) {
				echo "<td>".noHTML($keys[$i])."</td>";
			}
			echo "</tr>";
		}

		for($row = 0; $row < $this->f_getNumRows(); $row++){
			echo "<tr>";
			for($col = 0; $col < $this->f_getNumCols(); $col++){
				echo "<td>".noHTML($this->result[$row][$col])."</td>";
			}
			echo "</tr>";
		}
		echo "</table>";
	}


	//                                    QUERIES
	//=======================================================================================

    //args are executed in order (e.g: `col_1` = $arg1 WHERE `col_2` = $arg2)
    //$this->array is always set with $query as first element, with any arguments passed in order you want executed
	public function q_sample1($arg1, $arg2){
		$query = "UPDATE `Table` SET `col_1` = ? WHERE `col_2` = ?;";

		$this->Array = array($query, $arg1, $arg2);
		return $this;
	}

    public function q_sample2($arg1, $arg2){
        $query = "SELECT `Value` FROM `Table` WHERE `col_1` = ? AND `col_2` = ?;";

        $this->Array = array($query, $arg1, $arg2);
        return $this;
    }

}

//xss sanitation for autoDisplay()
if(!function_exists(noHTML)){
    function noHTML($input, $encoding = 'UTF-8'){
        return htmlentities($input, ENT_QUOTES | ENT_HTML5, $encoding, false);
    }
}

?>