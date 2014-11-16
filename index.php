<?php

/**
 * Wordpress Rest API using PHP SlimFramework
 *
 * Deze API vangt http berichten op gestuurd vanuit een Arduino en verwerkt deze in een database
 * Verder kan deze API ook deze gegevens doorsturen naar een webapplicatie 
 *
 * @author Leander Molegraaf, Mark Dingemanse en Jesse Labruyere
 * 
 */
error_reporting(E_ALL);
ini_set('display_errors', 'On');

define('WP_USE_THEMES', false);

require 'vendor/autoload.php';
require '../wp-load.php';
require 'Kint/Kint.class.php'; //@FIXME voor het debuggen, map + require verwijderen
require 'Constants.php';
require 'KLogger/KLogger.php'; //@FIXME voor het debuggen, map + require verwijderen

$app = new \Slim\Slim(array(
    'debug' => true,
    'mode' => 'development'
        ));

$app->post('/addMeasurement', 'addMeasurement');
$app->post('/addArduino', 'addArduino');
$app->post('/addLokaal', 'addLokaal');
$app->post('/addGrootheid', 'addGrootheid');

$app->post('/getMeasurements', 'getMeasurements');
$app->post('/getArduinosByWing', 'getArduinosByWing');
$app->post('/getArduinos', 'getArduinos');
$app->post('/getLokalen', 'getLokalen');
$app->post('/getGrootheden', 'getGrootheden');

$app->post('/getGrootheidInformatie', 'getGrootheidInformatie');

$app->post('/removeArduino', 'removeArduino');
$app->post('/removeLokaal', 'removeLokaal');
$app->post('/removeGrootheid', 'removeGrootheid');

$app->setName('Wordpress Rest API');

/**
 *
 * Voeg een nieuwe meting toe. verwacht een array met de volgende opmaak:
 * 'arduinoID' = int
 * 'grootheidID' = int
 * 'value' = int
 */
function addMeasurement() {

    $data['arduinoID'] = \Slim\Slim::getInstance()->request()->post('arduinoID');
    $data['grootheidID'] = \Slim\Slim::getInstance()->request()->post('grootheidID');
    $data['value'] = \Slim\Slim::getInstance()->request()->post('value');

    $log = new KLogger("log.txt", KLogger::DEBUG);
    $log->LogInfo("----------------------------------------------------------------------\n"
                    . "Got measurement: arduinoID =  " . $data['arduinoID'] . ", grootheidId = " . $data['grootheidID'] . " and value = " . $data['value'] . "\n"
                    . "With the following POST array var_export: " . serialize($_POST) . "\n\nAnd the following HTTP request(var_export(__request)):\n " . var_export($_REQUEST)) . "\n"
            . "--------------------------------------------------------------------";


    $req_dump = print_r($_REQUEST, TRUE);
    $fp = fopen('request.log', 'a');
    fwrite($fp, "\n\n" . serialize($_POST));
    fclose($fp);

    //sanitize input
    $safeData = sanitizeInput($data);

    //assemble SQL
    $SQL = "INSERT INTO meting(grootheidID, tijd, value, arduinoID)
			VALUES (" . $safeData['grootheidID'] . ", NOW(), " . $safeData['value'] . ", " . $safeData['arduinoID'] . ")";

    //execute SQL code
    queryDb($SQL);
}

/**
 * Voeg een Arduino toe aan de database, verwacht een array met de volgende opbouw
 * 'naam' = String
 * 'lokaalID' = int
 */
function addArduino() {
    $data['naam'] = \Slim\Slim::getInstance()->request()->post('naam');
    $data['lokaalID'] = \Slim\Slim::getInstance()->request()->post('lokaalID');

    $log = new KLogger("log.txt", KLogger::DEBUG);
    $log->LogInfo("Got new arduino: arduinoName =  " . $data['naam'] . " and lokaalId = " . $data['lokaalId']);

    //sanitize input
    $safeData = sanitizeInput($data);

    //assemble SQL
    $SQL = "INSERT INTO arduino(naam, lokaalID)
			VALUES ('" . $safeData['naam'] . "'," . $safeData['lokaalID'] . ")";

    //execute SQL code
    queryDb($SQL);
}

/**
 * Voeg een lokaal toe aan de database, verwacht een array met de volgende opbouw:
 * 'beschrijving' = String
 * 'vleugel' = String 
 * 'lokaalNR' = String
 */
function addLokaal() {
    $data['beschrijving'] = \Slim\Slim::getInstance()->request()->post('beschrijving');
    $data['vleugel'] = \Slim\Slim::getInstance()->request()->post('vleugel');
    $data['lokaalNR'] = \Slim\Slim::getInstance()->request()->post('lokaalNR');

    $log = new KLogger("log.txt", KLogger::DEBUG);
    $log->LogInfo("Got new lokaal: beschrijving =  " . $data['beschrijving'] . ", vleugel = " . $data['vleugel'] . " and lokaalNR = " . $data['lokaalNR']);

    //sanitize input
    $safeData = sanitizeInput($data);

    //assemble SQL
    $SQL = "INSERT INTO lokaal(beschrijving, vleugel, lokaalNR)
			VALUES ('" . $safeData['beschrijving'] . "','" . $safeData['vleugel'] . "','" . $safeData['lokaalNR'] . "')";

    //execute SQL code
    queryDb($SQL);
}

/**
 * Voeg een grootheid toe aan de database, verwacht een array met de volgende opzet
 * 'type' = String
 * 'info' = String
 */
function addGrootheid() {
    $data['type'] = \Slim\Slim::getInstance()->request()->post('type');
    $data['info'] = \Slim\Slim::getInstance()->request()->post('info');

    $log = new KLogger("log.txt", KLogger::DEBUG);
    $log->LogInfo("Got new grootheid: type =  " . $data['type'] . " and info = " . $data['info']);

    //sanitize input
    $safeData = sanitizeInput($data);

    //assemble SQL
    $SQL = "INSERT INTO grootheid(type, info)
			VALUES ('" . $safeData['type'] . "','" . $safeData['info'] . "')";

    //execute SQL code
    queryDb($SQL);
}

//Functies voor het OPHALEN van informatie

/**
 * Haal alle metingen op die bij een bepaalde arduino horen op een bepaalde datum.
 * verwacht een array met de volgende opmaak:
 * 'arduinoId' = int (meerdere mogelijk, gescheiden door een komma)
 * 'datum' = String (YYYY-MM-DD HH:mm:SS)
 * 'periode' = String (dag, maand of jaar)
 * 'grootheidId' = int
 */
function getMeasurements() {
    $data['arduinoId'] = \Slim\Slim::getInstance()->request()->post('arduinoId');
    $data['datum'] = \Slim\Slim::getInstance()->request()->post('datum');
    $data['periode'] = \Slim\Slim::getInstance()->request()->post('periode');
    $data['grootheidId'] = \Slim\Slim::getInstance()->request()->post('grootheidId');

    //data sanitization
    $sanitizedData = sanitizeInput($data);


    //vervang komma's door OR zodat dit makkelijk gebruikt kan worden in de SQL code
    //$arduinoids = "( arduino.arduinoID = " . implode(" OR arduino.arduinoID = ", explode(",", $sanitizedData['arduinoId'])) . ")";
    $arduinoids = explode(",", $sanitizedData['arduinoId']);

    $log = new KLogger("log.txt", KLogger::DEBUG);
    $log->LogInfo("aanvraag getMeasurements ontvangen. arduinoId= " . $data['arduinoId'] . ", datum= " . $data['datum'] . ",periode= " . $data['periode'] . ", grootheidId= " . $data['grootheidId'] . ",arduino IDs generated: " . $arduinoids);


    // controle welke periode er is geselecteerd 
    // selecteer in geval van dag de volledige datum, en in geval van maand, het jaar en de maand, en anders enkel het jaar
    // zet de datum om naar het juiste formaat met - inplaats van /
    if ($sanitizedData['periode'] == "dag") {

        $pieces = explode("/", $sanitizedData['datum']);
        $datum = $pieces[2] . "-" . $pieces[0] . "-" . $pieces[1];
    } else if ($sanitizedData['periode'] == "maand") {

        $pieces = explode("/", $sanitizedData['datum']);
        $datum = $pieces[2] . "-" . $pieces[0];
    } else {

        $pieces = explode("/", $sanitizedData['datum']);
        $datum = $pieces[2];
    }


    //var_dump($arduinoids);
    //bouw SQL query op
    $SQLArd1 = "SELECT meting.value AS value, unix_timestamp(meting.tijd) AS tijd
							FROM meting, arduino, lokaal, grootheid
							WHERE 	arduino.lokaalID = lokaal.lokaalID AND
									arduino.arduinoID = '" . $arduinoids[0] . "' AND 
									meting.tijd LIKE '$datum%' AND
									meting.arduinoID = arduino.arduinoID AND
									grootheid.grootheidID = '" . $sanitizedData['grootheidId'] . "' AND
									grootheid.grootheidID = meting.grootheidID
							ORDER BY meting.tijd ASC";

    if (isset($arduinoids[1])) {

        $SQLArd2 = "SELECT meting.value AS value, unix_timestamp(meting.tijd) AS tijd
							FROM meting, arduino, lokaal, grootheid
							WHERE 	arduino.lokaalID = lokaal.lokaalID AND
									arduino.arduinoID = '" . $arduinoids[1] . "' AND 
									meting.tijd LIKE '$datum%' AND
									meting.arduinoID = arduino.arduinoID AND
									grootheid.grootheidID = '" . $sanitizedData['grootheidId'] . "' AND
									grootheid.grootheidID = meting.grootheidID
							ORDER BY meting.tijd ASC";

        //execute SQL code
        echo(json_encode(array(queryDbForChart($SQLArd1), queryDbForChart($SQLArd2)), JSON_NUMERIC_CHECK));
        //geef resultaat terug
    } else {
        echo(json_encode(array(queryDbForChart($SQLArd1)), JSON_NUMERIC_CHECK)); //array om te voorkomen dat de JS problemen geeft.
    }
}

/**
 * Haal een lijst van alle arduinos op uit de database, op basis van de vleugels waar ze in zitten
 */
function getArduinosByWing() {

    $log = new KLogger("log.txt", KLogger::DEBUG);
    $log->LogInfo("------------------>getArduinosByWing was called.");


    //haal alle vleugels op
    $SQLgetVleugels = "SELECT DISTINCT (
vleugel
)
FROM  `lokaal` ";
    $vleugels = queryDb($SQLgetVleugels);

    for ($index = 0; $index < count($vleugels); $index++) {
        $SQLgetArduinos = "SELECT * 
FROM arduino, lokaal 
WHERE arduino.`lokaalID` = lokaal.lokaalID
AND lokaal.vleugel = '" . $vleugels[$index]['vleugel'] . "'";
        $result[$vleugels[$index]['vleugel']] = queryDb($SQLgetArduinos);
    }

//geef resultaat terug
    echo(json_encode($result));
}

/**
 * Haal een lijst van alle arduinos op uit de database, op basis van de vleugels waar ze in zitten
 */
function getArduinos() {
    $SQL = "SELECT * FROM arduino";

    //execute SQL code
    $result = queryDb($SQL);

    //geef resultaat terug
    echo(json_encode($result));
}

/**
 * Haal een lijst van alle lokalen op uit de database
 */
function getLokalen() {
    //bouw SQL query op
    $SQL = "SELECT * FROM lokaal";

    //execute SQL code
    $result = queryDb($SQL);

    //geef resultaat terug
    echo(json_encode($result));
}

/**
 * haal een lijst van alle grootheden op uit de database
 */
function getGrootheden() {
    //bouw SQL query op
    $SQL = "SELECT * FROM grootheid";

    //execute SQL code
    $result = queryDb($SQL);

    //geef resultaat terug
    echo(json_encode($result));
}

/**
 * haal de informatie van 1 enkele grootheid op uit de database.
 * Verwacht een array met de volgende opbouw:
 * 'grootheidID' = int
 */
function getGrootheidInformatie() {
    $data['grootheidID'] = \Slim\Slim::getInstance()->request()->post('grootheidID');

    //data sanitization
    $sanitizedData = sanitizeInput($data);

    //bouw SQL query op
    $SQL = "SELECT type, info FROM grootheid WHERE grootheidID = " . $sanitizedData['grootheidID'];

    //execute SQL code
    $result = queryDb($SQL);

    //geef resultaat terug
    echo(json_encode($result));
}

//functies voor het VERWIJDEREN van data

/**
 * Verwijder een arduino uit de database, verwacht een array met de volgende opbouw:
 * 'arduinoID' = int
 */
function removeArduino() {
    $data['arduinoID'] = \Slim\Slim::getInstance()->request()->post('arduinoID');

    //data sanitization
    $sanitizedData = sanitizeInput($data);

    //bouw SQL query op
    $SQL = "DELETE FROM arduino WHERE arduinoID =" . $sanitizedData['arduinoID'];

    //execute SQL code
    queryDb($SQL);
}

/**
 * verwijder een lokaal uit de database, verwacht een array met de volgende opbouw:
 * 'lokaalID' = int
 */
function removeLokaal() {
    $data['lokaalID'] = \Slim\Slim::getInstance()->request()->post('lokaalID');

    //data sanitization
    $sanitizedData = sanitizeInput($data);

    //bouw SQL query op
    $SQL = "DELETE FROM lokaal WHERE lokaalID =" . $sanitizedData['lokaalID'];

    //execute SQL code
    queryDb($SQL);
}

/**
 * verwijder een grootheid uit de database, verwacht een array met de volgende opbouw:
 * 'grootheidID' = int
 */
function removeGrootheid() {
    $data['grootheidID'] = \Slim\Slim::getInstance()->request()->post('grootheidID');

    //data sanitization
    $sanitizedData = sanitizeInput($data);

    //bouw SQL query op
    $SQL = "DELETE FROM grootheid WHERE grootheidID =" . $sanitizedData['grootheidID'];

    //execute SQL code
    queryDb($SQL);
}

//functies die vaker gebruikt worden door de bovenstaande methodes

/**
 * Haal connectie op met de database. Dit is geen klasse en dus zijn er geen beschikbare velden.
 * Enter description here ...
 */
function getConnection() {
    return new mysqli(DBLOCATION, DBUSERNAME, DBPASSWORD, DBTABLE);
}

function queryDbForChart($SQL) {
    //voer query uit
    $SQLresults = getConnection()->query($SQL) or die("executing the SQL DIED with the following message:<br /> " . mysqli_error(getConnection()) . "<br /><br />The following SQL was executed: " . $SQL);




    $result = array();
    $result['cols'] = array(
        array('id' => "", 'label' => 'tijd', 'type' => 'date'),
        array('id' => "", 'label' => 'value', 'type' => 'number', "p" => array("role" => "data")),
    );

    $rows = array();
    while ($nt = $SQLresults->fetch_assoc()) {

        $temp = array();
        $temp[] = array('v' => 'Date(' . $nt['tijd'] . '000)', 'f' => NULL);
        $temp[] = array('v' => $nt['value'], 'f' => NULL);
        $rows[] = array('c' => $temp);
    }

    $result['rows'] = $rows;
    return $result;
}

function queryDb($SQL) {

    //voer query uit
    $SQLresults = getConnection()->query($SQL) or die("executing the SQL DIED with the following message:<br /> " . mysqli_error(getConnection()) . "<br /><br />The following SQL was executed: " . $SQL);

    //als er een querie is uitgevoerd die data terug geeft (SELECT, INSPECT, etc.)
    //is de variabel een mysqli_result en moet er een return array opgesteld worden.
    //anders is het een boolean en wordt die terug gegeven.
    if ($SQLresults instanceof mysqli_result) {
        //loop door resultaten, plaats in array
        $resultArray = null;
        while ($row = $SQLresults->fetch_array(MYSQLI_ASSOC)) {
            $resultArray[count($resultArray)] = $row;
        }

        return $resultArray;
    } else {
        return $SQLresults;
    }
}

/**
 * Stript alle tags en escaped alle karaters van alle strings in de meegeleverde array.
 * 
 * @param Array $inputArray array met input gegevens
 * @return Array $sanitizedArray array met alle veilige gegevens
 */
function sanitizeInput($inputArray) {
    //array met 'schoongemkaakte' waardes
    //TODO alles 1 taal in de comments!
    $sanitizedInput = array();

    //voor elke input in de input array
    foreach ($inputArray as $key => $value) {

        $newValue = "";
        //als deze input een string is, voer dan sanitization uit.
        //andere formaten worden niet schoongemaakt.		
        if (is_string($value) && !date('Y-m-d H:i:s', strtotime($value)) == $value) {
            $newValue += strip_tags($value);
            $newValue += getConnection()->real_escape_string($newValue);

            $sanitizedInput[$key] = $newValue;
        } else {
            //value is geen string, oude waarde wordt letterlijk overgenomen.
            $sanitizedInput[$key] = $value;
        }
    }

    return $sanitizedInput;
}

// run the app
$app->run();
