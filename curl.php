<?
//cURL exception
class CURLException extends Exception {
  function __construct($msg,$c) {
    $msg.=": ".curl_error($c);
    parent::__construct($msg);
  }
}

//cURL server exception
class CURLDownloadException extends Exception {
  public $rc;
  function __construct($rc) {
    $this->rc=$rc;
    parent::__construct("HTTP return code: $rc");
  }
}
class CURL {
  private $c;
  private static $inst=null;
  public function __construct() {
    $this->c=curl_init();
    curl_setopt($this->c,CURLOPT_RETURNTRANSFER,true);
//    curl_setopt($this->c,CURLOPT_FOLLOWLOCATION,true);
    curl_setopt($this->c,CURLOPT_SSL_VERIFYPEER,false);
    curl_setopt($this->c,CURLOPT_SSL_VERIFYHOST,0);
    curl_setopt($this->c,CURLOPT_USERAGENT,"libhn 1.0");
    curl_setopt($this->c,CURLOPT_HEADER,1);
  }
  public static function getInst() {
    if(static::$inst==null)
      static::$inst=new static();
    return static::$inst;
  }
  public static function get($url,$cookie="") {
    $scheme=parse_url($url,PHP_URL_SCHEME);
//TODO THIS IS UGLY
    if($scheme=="") { $scheme="http"; $url="http:".$url; }//protocol-relative URLs default to http
    if(!in_array($scheme,array("http","https","ftp")))
      throw new PermissionDeniedException("Ungültige URL");
    
    $inst=static::getInst();
    $r=curl_setopt($inst->c,CURLOPT_HTTPGET,true);
    if($r===false)
      throw new CURLException("curl_setopt(HTTPGET) failed",$inst->c);
    curl_setopt($inst->c, CURLOPT_URL, $url);
    if($r===false)
      throw new CURLException("curl_setopt(URL) failed",$inst->c);
    if($cookie!="") {
      curl_setopt($inst->c, CURLOPT_HTTPHEADER, array("Cookie: $cookie"));
      if($r===false)
        throw new CURLException("curl_setopt(HTTPHEADER) failed",$inst->c);
    }
    
    $ret=curl_exec($inst->c);
    if($ret===false)
      throw new CURLException("curl_exec failed",$inst->c);
    
    $rc=curl_getinfo($inst->c,CURLINFO_HTTP_CODE);
    if($rc===false)
      throw new CURLException("curl_getinfo(HTTPCODE) failed",$inst->c);
    if($rc!=200)
      throw new CURLDownloadException($rc);
    
    list($header,$body)=explode("\r\n\r\n",$ret,2);
    $header=explode("\r\n",$header);
    $rc=array_shift($header);
    $headers=array();
    foreach($header as $h) {
      list($k,$v)=explode(":",$h,2);
      $k=strtolower(trim($k));
      $v=trim($v);
      if(isset($headers[$k]) && is_array($headers[$k]))
        $headers[$k][]=$v;
      elseif(isset($headers[$k]) && !is_array($headers[$k]))
        $headers[$k]=array($headers[$k],$v);
      else
        $headers[$k]=$v;
    }
    return array("rc"=>$rc,"headers"=>$headers,"header"=>$header,"body"=>$body);
  }
  public static function post($url,$fields,$cookie="",$e_rc=200) {
    $scheme=parse_url($url,PHP_URL_SCHEME);
//TODO THIS IS UGLY
    if($scheme=="") { $scheme="http"; $url="http:".$url; }//protocol-relative URLs default to http
    if(!in_array($scheme,array("http","https")))
      throw new PermissionDeniedException("Ungültige URL");
    
    //prepare data
    foreach($fields as $k=>$v)
      $fields[$k]=urlencode($v);
    $pdata="";
    foreach($fields as $k=>$v)
      $pdata.="$k=$v&";
    $pdata=substr($pdata,0,-1);
    
    $inst=static::getInst();
    $r=curl_setopt($inst->c,CURLOPT_POST,sizeof($fields));
    if($r===false)
      throw new CURLException("curl_setopt(POST) failed",$inst->c);
    curl_setopt($inst->c, CURLOPT_URL, $url);
    if($r===false)
      throw new CURLException("curl_setopt(URL) failed",$inst->c);
    curl_setopt($inst->c, CURLOPT_POSTFIELDS, $pdata);
    if($r===false)
      throw new CURLException("curl_setopt(URL) failed",$inst->c);
    if($cookie!="") {
      curl_setopt($inst->c, CURLOPT_HTTPHEADER, array("Cookie: $cookie"));
      if($r===false)
        throw new CURLException("curl_setopt(HTTPHEADER) failed",$inst->c);
    }
    
    $ret=curl_exec($inst->c);
    if($ret===false)
      throw new CURLException("curl_exec failed",$inst->c);
    
    $rc=curl_getinfo($inst->c,CURLINFO_HTTP_CODE);
    if($rc===false)
      throw new CURLException("curl_getinfo(HTTPCODE) failed",$inst->c);
    if($rc!=$e_rc)
      throw new CURLDownloadException($rc);
    
    list($header,$body)=explode("\r\n\r\n",$ret,2);
    $header=explode("\r\n",$header);
    $rc=array_shift($header);
    $headers=array();
    foreach($header as $h) {
      list($k,$v)=explode(":",$h,2);
      $k=strtolower(trim($k));
      $v=trim($v);
      if(isset($headers[$k]) && is_array($headers[$k]))
        $headers[$k][]=$v;
      elseif(isset($headers[$k]) && !is_array($headers[$k]))
        $headers[$k]=array($headers[$k],$v);
      else
        $headers[$k]=$v;
    }
    return array("rc"=>$rc,"headers"=>$headers,"header"=>$header,"body"=>$body);
  }
}
