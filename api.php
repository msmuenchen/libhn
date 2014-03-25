<?
$ts_start=microtime(true);
ini_set("zlib.output_compression","on");
header("Content-Type:application/json; charset=utf-8");

require("libhn.php");
require("core.php");
$ret=array();
$log="";
try {
  if(!isset($_GET["action"]) || $_GET["action"]=="")
    throw new APIMissingParameterException("Keine Aktion angegeben");
  $action=$_GET["action"];
  $hn=new HN();
  if(isset($_GET["auth"]))
    $hn->setAuth($_GET["auth"]);
  
  switch($action) {
    case "getnews":
    case "login":
      require("api/$action.php");
    break;
    default:
      throw new APIWrongCallException("Ungültige Aktion angegeben");
  }
  $ret["status"]="ok";
  $ret["message"]=$log;
} catch(Exception $e) {
  $ret["status"]="error";
  $ret["message"]=$e->getMessage();
  $ret["type"]=get_class($e);
}
$ts_end=microtime(true);
$ret["rt"]=$ts_end-$ts_start;
echo pretty_json(json_encode($ret,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP));
