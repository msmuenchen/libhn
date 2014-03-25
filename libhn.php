<?
require_once("curl.php");
require_once("DOMDocumentCharset.php");
require_once("xmltree_dump_includes.php");

class HN {
  protected $baseURL="https://news.ycombinator.com";
  protected $cookie="";
  
  //get the submissions by a user. For paginations use the returned "continuation" with getNews.
  function getUserSubmittedItems($user) {
    return $this->getNews("submitted?id=$user");
  }
  
  //get the "newest" items, with pagination support
  function getNewestItems($continuation="") {
    //"newest" is same format as "news", only with a different starting name
    if($continuation=="")
      $continuation="newest";
    return $this->getNews($continuation);
  }
  
  //get a "news"-type site from HN
  //"continuation" is used for pagination and its value is supplied as "continuation" key in the returned array
  //return: array of submissions with metadata (JSON objects)
  function getNews($continuation="") {
    //no continuation? start at "news" entry point
    if($continuation=="")
      $content=CURL::get($this->baseURL."/news",$this->cookie);
    else
      $content=CURL::get($this->baseURL."/".$continuation,$this->cookie);
    
    $ret=array();
    $ret["continuation"]="";
    $ret["items"]=array();
    
    //initialize DOM
    $dom=new DOMDocumentCharset();
    $dom->recover = true;
    $dom->strictErrorChecking = false;
    libxml_use_internal_errors(true); //HN outputs stuff like <img ...></img>, which is invalid
    
    //detect the charset, HN doesn't use a meta tag *sigh*
    if(isset($content["headers"]["content-type"]) && preg_match("@charset=(.*)\$@isU",$content["headers"]["content-type"],$hit)==1)
      $dom->loadHTMLCharset($content["body"],$hit[1]);
    else
      $dom->loadHTMLCharset($content["body"]);
    
    $xpath=new DOMXPath($dom);
    //get the node of the content area, fuck is this a fucking stupid xpath expression
    $res=$xpath->evaluate("//html/*/center/table/tr[3]/td/table/tr");
//    xmltree_dump($dom);
    if($res==false || $res->length==0) //lets pray HN never changes its layout
      throw new Exception("xpath fail");
    
    $ctr=0;
    for($i=0;$i<$res->length;$i+=3) {
      $ctr++;
//      echo "item $ctr $i\n";
      $headline=$res->item($i); //the tr containing the submission title/domain
      $bottomline=$res->item($i+1); //the tr containing the submission metadata
      $junk=$res->item($i+2); //HN uses tr's for styling LOL
      
//      echo "headline\n";
//      xmltree_dump($headline);
//      echo "bottomline\n";
//      xmltree_dump($bottomline);
//      echo "junk\n";
//      xmltree_dump($junk);
      
      //the last element, containing the more link, doesn't carry a spacing tr
      if($junk==null) {
        $r2=$xpath->evaluate(".//td/a",$bottomline);
        if($r2==false || $r2->length!=1)
          throw new Exception("no continuation link");
        $l=$r2->item(0)->getAttribute("href");
        if(substr($l,0,1)=="/") //not all continuation links carry an absolute path, but those who do need to be made relative
          $l=substr($l,1);
        $ret["continuation"]=$l;
        break;
      }
      
      $item=array();
      $item["links"]=array();
      
      //split the head line apart
      $r2=$xpath->evaluate(".//td",$headline);
      if($r2==false || $r2->length!=3)
        throw new Exception("headline td xpath fail");
      //position (rank)
      $posNode=$r2->item(0);
      //upvote link
      $voteBlock=$r2->item(1);
      //title + domain
      $titleBlock=$r2->item(2);

//      echo "posnode\n";
//      xmltree_dump($posNode);
//      echo "voteblock\n";
//      xmltree_dump($voteBlock);
//      echo "titleblock\n";
//      xmltree_dump($titleBlock);
      
      //extract upvote link
      $r3=$xpath->evaluate(".//a",$voteBlock);
      if($r3==false)
        throw new Exception("voteblock a xpath fail");
      elseif($r3->length==1) //we can upvote this
        $item["links"]["upvote"]=$r3->item(0)->getAttribute("href");
      else //selfposts by yourself or job-posts cannot be upvoted
        $item["links"]["upvote"]="";
      
      //extract item rank
      $item["position"]=$posNode->textContent;
      
      //extract the link and title
      $r4=$xpath->evaluate(".//a",$titleBlock);
      if($r4==false||$r4->length==0)
        throw new Exception("titleblock a xpath fail");
      $item["links"]["itemlink"]=$r4->item(0)->getAttribute("href");
      if($r4->length==2) { //link 2 = scribd (pdf)?
        $l2=$r4->item(1);
        if(strtolower(trim($l2->textContent))=="scribd")
          $item["links"]["scribd"]=$l2->getAttribute("href");
      }
      //title
      $item["title"]=$r4->item(0)->textContent;
      
      //extract domain
      $r5=$xpath->evaluate(".//span",$titleBlock);
      if($r5==false)
        throw new Exception("titleblock span xpath fail");
      else if($r5->length==1) {
        $d=$r5->item(0)->textContent;
        $d=substr(trim($d),1,-1); //trim the braces
        $item["links"]["domain"]=$d;
      } else //Ask/Show HN, job postings etc. dont carry a domain
        $item["links"]["domain"]="";
      
      //extract karma point score of the submission
      $r6=$xpath->evaluate(".//td[2]/span",$bottomline);
      if($r6==false)
        throw new Exception("bottomblock span xpath fail");
      else if($r6->length==0) {
        //Job postings, these do not carry anything except the timestamp
        //extract the timestamp (or the castrated version HN emits)
        $r7=$xpath->evaluate(".//td[2]",$bottomline);
        if($r7==false||$r7->length!=1)
          throw new Exception("bottomblock timestamp xpath fail");
        $ts=$r7->item(0)->textContent;
        $item["timestamp"]=$ts;
      } else { //"normal" submissions
        $item["score"]=$r6->item(0)->textContent;
        
        //extract author name and link to user page
        $r7=$xpath->evaluate(".//td[2]/a[1]",$bottomline);
        if($r7==false || $r7->length!=1)
          throw new Exception("bottomblock authorlink xpath fail");
        $item["links"]["author"]=$r7->item(0)->getAttribute("href");
        $item["author"]=$r7->item(0)->textContent;
        
        //extract the timestamp (or the castrated version HN emits)
        $r8=$xpath->evaluate(".//td[2]/text()[2]",$bottomline);
        if($r8==false||$r8->length!=1)
          throw new Exception("bottomblock timestamp xpath fail");
        $ts=$r8->item(0)->textContent;
        $ts=trim(substr(trim($ts),0,-1)); //strip the last | of the text node
        $item["timestamp"]=$ts;
        
        //try to get the link to comments/flag
        $r9=$xpath->evaluate(".//td[2]/a[2]",$bottomline);
        if($r9==false||$r9->length!=1)
          throw new Exception("bottomblock commentlink xpath fail");
        $r10=$xpath->evaluate(".//td[2]/a[3]",$bottomline);
        if($r10==false)
          throw new Exception("bottomblock flaglink xpath fail");
        if($r10->length==0) { //no flag right (too low karma), only comment link
          $item["links"]["comments"]=$r9->item(0)->getAttribute("href");
          $item["comments"]=$r9->item(0)->textContent;
        } else {
          $item["links"]["flag"]=$r9->item(0)->getAttribute("href");
          $item["links"]["comments"]=$r10->item(0)->getAttribute("href");
          $item["comments"]=$r10->item(0)->textContent;
        }
      }
      $ret["items"][]=$item;
//      print_r($item);
    }
    return $ret;
  }
  
  //login into HN
  //returns authentication data (in fact, a b64 encoded raw cookie)
  //throws LoginException if user/pass don't work
  public function login($user,$pass) {
    //step 1: get token (fnid)
    $content=CURL::get($this->baseURL."/newslogin");
    $ret=array();
    $dom=new DOMDocumentCharset();
    $dom->recover = true;
    $dom->strictErrorChecking = false;
    libxml_use_internal_errors(true);
    
    if(isset($content["headers"]["content-type"]) && preg_match("@charset=(.*)\$@isU",$content["headers"]["content-type"],$hit)==1)
      $dom->loadHTMLCharset($content["body"],$hit[1]);
    else
      $dom->loadHTMLCharset($content["body"]);
    
    $xpath=new DOMXPath($dom);
    $res=$xpath->evaluate("//form[1]/input[@name='fnid']");
    if($res==false||$res->length!=1)
      throw new Exception("loginform fnid xpath fail");
    $fnid=$res->item(0)->getAttribute("value");
    
    //step 2: do login
    $content=CURL::post($this->baseURL."/y",array("fnid"=>$fnid,"u"=>$user,"p"=>$pass),302);
    if($content["headers"]["location"]!="/") //redir to anything but / is a login failure
      throw new LoginException("wrong login info");
    $cookies=$content["headers"]["set-cookie"];
    $ret["cookie"]="";
    foreach($cookies as $c) {
      list($cs,$junk)=explode(";",$c,2);
      $ret["cookie"].=trim($cs)."; ";
    }
    $ret["cookie"]=base64_encode(substr($ret["cookie"],0,-2));
    return $ret;
  }
  //use the supplied cookie information (that what you got with $ret["cookie"] on login())
  //as context for all subsequent requests
  public function setAuth($cookie) {
    $this->cookie=base64_decode($cookie);
  }
  
}