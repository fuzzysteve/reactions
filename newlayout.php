<?php
$expires = 3599;
header("Pragma: public");
header("Cache-Control: maxage=".$expires);
header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
$redis = new Redis;
$redis->connect('127.0.0.1', 6379) or die ("Could not connect");

# Forge sell price. Value is weightedaverage|maxval|minval|stddev|median|volume|numorders|fivepercent
function sellprice($typeid)
{
    global $redis;
    $values=explode("|", (string) $redis->get('10000002|'.$typeid.'|false'));
    $price=$values[7] ?? 0;
    return is_numeric($price) ? $price : 0;
}

require_once('db.inc.php');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
?>
<html>
<head>
<title>Reaction information</title>
  <link href="style.css" rel="stylesheet" type="text/css"/>
  <link href="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.1/themes/base/jquery-ui.css" rel="stylesheet" type="text/css"/>
  <link href="//cdn.datatables.net/1.10.20/css/jquery.dataTables.min.css" rel="stylesheet" type="text/css"/>
  <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
  <script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>
  <script src="//cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js"></script>
  
<script>
$(document).ready( function () {
    $('#reactionsTable').DataTable();
} );

</script>

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
<table id="reactionsTable">
<thead>
<tr><th>Materials</th><th>Profit</th><tr>
</thead>
<tbody>
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
    $reactionstmt->execute(array(":typeid"=>$row->typeid));
    $productstmt->execute(array(":typeid"=>$row->typeid));
    $blocktext="<tr><td><table border=1><thead><tr><th colspan=4>Input</th><tr>";
    $blocktext.="<th>Material</th><th>Quantity</th><th>Volume m3</th><th>Total cost</th></tr></thead>\n";
    while ($reactions = $reactionstmt->fetchObject()) {
        $blocktext.="<tr><td>";
        $blocktext.=$reactions->typename."</td><td class='number'>".$reactions->quantity;
        $blocktext.="</td><td class='number'>".($reactions->quantity*$reactions->volume)."</td><td class='number'>";
        $innerprice=sellprice($reactions->materialtypeid);
        $value=$innerprice*$reactions->quantity;
        $cost+=$value;
        $volumein+=$reactions->quantity*$reactions->volume;
        $blocktext.=number_format($value)."</td></tr>\n";
    }
    $blocktext.="<tr><td colspan=2>Overall</td><td class='number'>$volumein</td><td class='number'>".number_format($cost)."</td></tr></table></td>\n";
    $blocktext.="<td><table border=1><thead><tr><th colspan=4>Output</th></tr><tr><th>Material</th><th>Quantity</th><th>Volume m3</th><th>Total value</th></tr></thead>\n";
    while ($products = $productstmt->fetchObject()) {
        $blocktext.= "<tr><td>";
        $blocktext.=$products->typename."</td><td class='number'>".$products->quantity."</td><td class='number'>".($products->quantity*$products->volume)."</td><td class='number'>";
        $innerprice=sellprice($products->producttypeid);
        $value=$innerprice*$products->quantity;
        $price+=$value;
        $volumeout+=$products->quantity*$products->volume;
        $blocktext.=number_format($value)."</td></tr>\n";
    }
    $blocktext.="<tr><td colspan=2>Overall</td><td class='number'>$volumeout</td><td class='number'>".number_format($price)."</td></tr></table></td>\n";

    $blocktext.="<td><table border=1><thead><tr><th colspan=5>Profits</th></tr>";
    $blocktext.="<tr><th>Period</th><th>Margin</th><th>Volume in</th><th>Volume out</th><th>Cycles</th></tr></thead>";
    $blocktext.="<tr><td>One run</td><td class='number'>".number_format($price-$cost)."</td>";
    $blocktext.="<td class='number'>".number_format($volumein)."</td>";
    $blocktext.="<td class='number'>".number_format($volumeout)."</td><td>1</td></tr>";
    $cycles=floor(86400/$row->time);
    $blocktext.="<tr><td>One day</td><td class='number'>".number_format(($price-$cost)*$cycles)."</td>";
    $blocktext.="<td class='number'>".number_format($volumein*$cycles)."</td><td class='number'>".number_format($volumeout*$cycles)."</td>";
    $blocktext.="<td>$cycles</td></tr>";
    $cycles=floor((30*86400)/$row->time);
    $blocktext.="<tr><td>Thirty days</td><td class='number'>".number_format(($price-$cost)*$cycles)."</td>";
    $blocktext.="<td class='number'>".number_format($volumein*$cycles)."</td><td class='number'>".number_format($volumeout*$cycles)."</td>";
    $blocktext.="<td>$cycles</td></tr>";
    $blocktext.="</table></tr>";



    $blocktext.="</tr>\n";
    echo "<tr><td colspan=4 class='group-".$row->marketGroupID."'>$name</td><td rowspan=2 class='number' valign='middle'>".number_format(($price-$cost)*$cycles)."</td></tr>";
    echo $blocktext;
}
?>
</tbody>
</table>
</div>
<?php include('/home/web/fuzzwork/htdocs/bootstrap/footer.php'); ?>

</body>
</html>
