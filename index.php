<?php
$expires = 3599;
header("Pragma: public");
header("Cache-Control: maxage=".$expires);
header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
$memcache = new Memcache;
$memcache->connect('localhost', 11211) or die ("Could not connect");

require_once('db.inc.php');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
?>
<html>
<head>
<title>Reaction information</title>
  <link href="style.css" rel="stylesheet" type="text/css"/>
  <link href="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.1/themes/base/jquery-ui.css" rel="stylesheet" type="text/css"/>
  <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
  <script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>


<?php include('/home/web/fuzzwork/htdocs/bootstrap/header.php'); ?>
</head>
<body>
<?php include('/home/web/fuzzwork/htdocs/menu/menubootstrap.php'); ?>
<?php
if (isset($_GET['skill']) and is_numeric($_GET['skill'])) {
    $skill=$_GET['skill'];
    $refine=(50+$skill)/100;
} else {
    $refine=0.5;
}
?>
<div class="container">

<p>Information on Reactions, with some pricing. Alchemy reactions will have the full output priced out</p>
<div class="alert warning">Due to refining changes, all Unrefined materials are being calculated at a 50% return. You need scrap metal processing to increase this, by 1% per level. I /think/ I've got it working right. If you can tell me one way or another, please do.<form action="https://www.fuzzwork.co.uk/reactions/" method="get"><select name="skill"><option>1<option>2<option>3<option>4<option>5</select><input type=submit></form></div>
<table>
<?php

$sql='select invTypes.typeid,typeName,marketGroupID,time from invTypes join industryActivity on invTypes.typeid=industryActivity.typeid where activityid=11 and published=1 order by marketgroupid';

$stmt = $dbh->prepare($sql);

$stmt->execute();

$reactiondata='select materialtypeid,quantity,typename,volume from industryActivityMaterials iam join invTypes it on iam.materialtypeid=it.typeid  where iam.typeid=:typeid and activityid=11';
$reactionstmt=$dbh->prepare($reactiondata);
$productdata='select producttypeid,quantity,typename,volume from industryActivityProducts iap join invTypes it on iap.producttypeid=it.typeid  where iap.typeid=:typeid and activityid=11';
$productstmt=$dbh->prepare($productdata);

while ($row = $stmt->fetchObject()) {
    $cost=0;
    $price=0;
    $volumein=0;
    $volumeout=0;
    $name=$row->typeName;
    echo "<tr><th colspan=4 class='group-".$row->marketGroupID."'>$name</th></tr>";
    $reactionstmt->execute(array(":typeid"=>$row->typeid));
    $productstmt->execute(array(":typeid"=>$row->typeid));
    echo "<tr><td><table border=1><thead><tr><th colspan=4>Input</th><tr>";
    echo "<th>Material</th><th>Quantity</th><th>Volume m3</th><th>Total cost</th></tr></thead>\n";
    while ($reactions = $reactionstmt->fetchObject()) {
        echo "<tr><td>";
        echo $reactions->typename."</td><td class='number'>".$reactions->quantity;
        echo "</td><td class='number'>".($reactions->quantity*$reactions->volume)."</td><td class='number'>";
        $pricedatasell=$memcache->get('forgesell-'.$reactions->materialtypeid);
        $values=explode("|", $pricedatasell);
        $innerprice=$values[0];
        if ($innerprice=="") {
                $innerprice=0;
        }
        $value=$innerprice*$reactions->quantity;
        $cost+=$value;
        $volumein+=$reactions->quantity*$reactions->volume;
        echo number_format($value)."</td></tr>\n";
    }
    echo "<tr><td colspan=2>Overall</td><td class='number'>$volumein</td><td class='number'>".number_format($cost)."</td></tr></table></td>\n";
    echo "<td><table border=1><thead><tr><th colspan=4>Output</th></tr><tr><th>Material</th><th>Quantity</th><th>Volume m3</th><th>Total value</th></tr></thead>\n";
    while ($products = $productstmt->fetchObject()) {
        echo "<tr><td>";
        echo $products->typename."</td><td class='number'>".$products->quantity."</td><td class='number'>".($products->quantity*$products->volume)."</td><td class='number'>";
        $pricedatasell=$memcache->get('forgesell-'.$products->producttypeid);
        $values=explode("|", $pricedatasell);
        $innerprice=$values[0];
        if ($innerprice=="") {
            $innerprice=0;
        }
        $value=$innerprice*$products->quantity;
        $price+=$value;
        $volumeout+=$products->quantity*$products->volume;
        echo number_format($value)."</td></tr>\n";
    }
    echo "<tr><td colspan=2>Overall</td><td class='number'>$volumeout</td><td class='number'>".number_format($price)."</td></tr></table></td>\n";

    echo "<td><table border=1><thead><tr><th colspan=5>Profits</th></tr>";
    echo "<tr><th>Period</th><th>Margin</th><th>Volume in</th><th>Volume out</th><th>Cycles</th></tr></thead>";
    echo "<tr><td>One run</td><td class='number'>".number_format($price-$cost)."</td>";
    echo "<td class='number'>".number_format($volumein)."</td>";
    echo "<td class='number'>".number_format($volumeout)."</td><td>1</td></tr>";
    $cycles=floor(86400/$row->time);
    echo "<tr><td>One day</td><td class='number'>".number_format(($price-$cost)*$cycles)."</td>";
    echo "<td class='number'>".number_format($volumein*$cycles)."</td><td class='number'>".number_format($volumeout*$cycles)."</td>";
    echo "<td>$cycles</td></tr>";
    $cycles=floor((30*86400)/$row->time);
    echo "<tr><td>Thirty days</td><td class='number'>".number_format(($price-$cost)*$cycles)."</td>";
    echo "<td class='number'>".number_format($volumein*$cycles)."</td><td class='number'>".number_format($volumeout*$cycles)."</td>";
    echo "<td>$cycles</td></tr>";
    echo "</table><td class='number'>".number_format(($price-$cost)*$cycles)."</td></tr>";



    echo "</tr>\n";
}
?>
</table>
</div>
<?php include('/home/web/fuzzwork/htdocs/bootstrap/footer.php'); ?>

</body>
</html>
