<?
require_once("libhn.php");

$hn=new HN();
$p1=$hn->getNews();
/*$p2=$hn->getNews($p1["continuation"]);
$p3=$hn->getNews($p2["continuation"]);*/
echo "page 1\n";
print_r($p1);
echo "page 2\n";
print_r($p2);
echo "page 3\n";
print_r($p3);


