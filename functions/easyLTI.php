<?php
/* Producer library for LTI using the ims-blti library plus storage of nonces */
/* MBrousseau July 2015 */

//Bring in the IMS Basic LTI Functions
require_once("../ims-blti/blti.php");

//Bring in the LTI and DB info
require_once("easyLTI/config.php");

//connect to LTI | Returns LTI Data or FALSE
function connectLTI()
{
    //Check if the LTI table exists and if it doesn't created it now
    createTable('LTI');

    //Make the LTI connection | the Secret | Store as Session | Redirect after success
    $context = new BLTI($GLOBALS['ltiSecret'], true, false);

    //Valid LTI connection
    if ($context->valid == true) {

        //Check if nonce exists within 90 minute timeline
        if (secureLTI($_REQUEST['oauth_nonce'], $_REQUEST['oauth_timestamp'])) {
            ;
        }

    } //Invalid LTI connection
    else {
        echo "Unable to make a valid LTI connection. Refresh and try again.";
        die;
    }

    //LTI connection made successfully and nonce is OK. Return the LTI object
    return $context;
}

//Check and store timestamp and nonce | Returns TRUE or dies if replay nonce used
function secureLTI($nonce, $timestamp)
{

    //Connect to the DB
    $dbHandle = dbConnect();
    if ($dbHandle != false) {

        //Check to see if the Nonce already exists in the DB
        $stmt = $dbHandle->prepare("SELECT `timestamp` FROM `LTI` WHERE `nonce` = ?");
        $stmt->execute(array($nonce));

        //Nonce exists in DB - No replay for you
        if ($stmt->rowCount() != 0) {
            echo "Error Connecting to DB(2)";
            die;
        } //Insert the nonce and timestamp into the db
        else {
            $stmt = $dbHandle->prepare("INSERT INTO `LTI`(`nonce`, `timestamp`) VALUES (?, ?)");
            $stmt->execute(array($nonce, $timestamp));
        }
    } //Not able to connect to DB
    else {
        echo "Error Connecting to DB(1)";
        die;
    }

    //All's well with the nonce - Return TRUE
    return true;
}

//Connect to the db and return the db handle | Returns DB Handle or FALSE
function dbConnect()
{

    //PDO to the database set in config
    $db = new PDO('mysql:dbname=' . $GLOBALS['dbName'] . ';host=' . $GLOBALS['dbHost'] . ';charset=utf8', $GLOBALS['dbUser'], $GLOBALS['dbPass']);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    //Return the DB Handle
    return $db;
}


//LTI table doesn't exist to store nonces so create it if possible | Returns TRUE on create & FALSE if table already exists
function createTable($tableName){

    //Connect to the information_schema DB
    $dbHandle = dbConnect();

    //See if we can pull 1 record from the table to check it's existence
    try {
        $result = $dbHandle->query("SELECT 1 FROM ".$tableName." LIMIT 1");
    } catch (Exception $e) {

        //Otherwise create the table with specified name
        $stmt = $dbHandle->prepare("CREATE TABLE IF NOT EXISTS `LTI` (
          `nid` int(11) NOT NULL AUTO_INCREMENT,
          `nonce` bigint(11) NOT NULL,
          `timestamp` text NOT NULL,
          PRIMARY KEY (`nid`),
          UNIQUE KEY `nid` (`nid`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=22 ;");
        $stmt->execute();

        //Table created
        return TRUE;
    }
    //No need to create the table as it already exists
    return FALSE;
}



