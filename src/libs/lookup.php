<?php

  /**
   * Class LookupData
   */
 class LookupData {

 function __construct() {}

    /**
     * This function create a database connection
     * @return object database connection
     */
    public static function getLookupValue($tablename, $fieldname, $comparefieldname,$id) {
	   // $container = $app->getContainer();
		$conn = PDOConnection::getConnection();
		try{
		$sql = "SELECT  ".$fieldname."  FROM  ".$tablename." where ".$comparefieldname."=".$id;
  		$stmt = $conn->prepare($sql);
  		$stmt->execute();
  		$maxid = $stmt->fetchAll();
		if( empty($maxid[0][$fieldname])){
		return "999999";
		}else{
		return $maxid[0][$fieldname];
		}
		}
		catch (PDOException $e) {
        $this['logger']->error("DataBase Error.<br/>" . $e->getMessage());
      } catch (Exception $e) {
        $this['logger']->error("General Error.<br/>" . $e->getMessage());
      } finally {
        // Destroy the database connection
        $conn = null;
      }
    }
  }
  

?>
