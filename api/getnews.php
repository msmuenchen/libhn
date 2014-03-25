<?
$cont=(isset($_GET["continuation"]))?$_GET["continuation"]:"";
$ret["news"]=$hn->getNews($cont);
