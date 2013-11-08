<?php
$expires = 3599;
header("Pragma: public");
header("Cache-Control: maxage=".$expires);
header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
$memcache = new Memcache;
$memcache->connect('localhost', 11211) or die ("Could not connect");

require_once('db.inc.php');
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
<div class="container">

<p>Information on Reactions, with some pricing. Alchemy reactions will have the full output priced out</p>

<table>
<?

$sql='select typeid,typeName,marketGroupID from invTypes where marketGroupID in (508,509,1143) order by marketgroupid,typeid asc';

$stmt = $dbh->prepare($sql);
$sql2="select invTypes.typeid typeid,typename,quantity,input,output,volume from invTypeReactions,invTypes,evesupport.reactionmodifier where invTypeReactions.reactiontypeid=? and invTypeReactions.typeid=invTypes.typeid and evesupport.reactionmodifier.reactiontypeid=invTypeReactions.reactiontypeid order by input asc";
$reactiondata= $dbh->prepare($sql2);
$sql3="select typename,invTypeMaterials.materialTypeID typeid,quantity,volume from invTypeMaterials,invTypes where invTypeMaterials.typeid=? and invTypes.typeid=invTypeMaterials.materialTypeID";
$refinedata= $dbh->prepare($sql3);

$polymersql="select invTypes.typeid typeid,typename,quantity,input,volume from invTypeReactions,invTypes where invTypeReactions.reactiontypeid=? and invTypeReactions.typeid=invTypes.typeid order by input asc";
$polymerstmt=$dbh->prepare($polymersql);


$stmt->execute();

while ($row = $stmt->fetchObject()){
    $inputs=array();
    $output=array();
    $cost=0;
    $price=0;
    $volumein=0;
    $volumeout=0;
    $name=$row->typeName;
    if (strpos($name,"Unrefined") === false)
    {
        # not alchemy
        echo "<tr><th colspan=4 class='group-".$row->marketGroupID."'>$name</th></tr>";
        if ($row->marketGroupID==1143)
        {
            $polymerstmt->execute(array($row->typeid));
            while ($reactions = $polymerstmt->fetchObject()){
                $arraykey=$reactions->typeid.'|'.$reactions->typename.'|'.$reactions->volume;
                if ($reactions->input){
                    $inputs[$arraykey]=$reactions->quantity;
                }
                else {
                    $output[$arraykey]=$reactions->quantity;
                }
            }

        } else {
            $reactiondata->execute(array($row->typeid));
            while ($reactions = $reactiondata->fetchObject()){
                $arraykey=$reactions->typeid.'|'.$reactions->typename.'|'.$reactions->volume;
                if ($reactions->input){
                    $inputs[$arraykey]=$reactions->quantity*100;
                }
                else {
                    $output[$arraykey]=$reactions->output;
                }
            }
        }
        echo "<tr><td><table border=1><thead><tr><th colspan=4>Input</th><tr><th>Material</th><th>Quantity</th><th>Volume m3</th><th>Total cost</th></tr></thead>\n";

        foreach ($inputs as $input=>$quantity){
            echo "<tr><td>";
            $matdata=explode("|",$input);
            echo $matdata[1]."</td><td class='number'>".($quantity)."</td><td class='number'>".($quantity*$matdata[2])."</td><td class='number'>";
            $pricedatasell=$memcache->get('forgesell-'.$matdata[0]);
            $values=explode("|",$pricedatasell);
            $innerprice=$values[0];
            if ($innerprice=="")
            {
                $innerprice=0;
            }
            $value=$innerprice*$quantity;
            $cost+=$value;
            $volumein+=$quantity*$matdata[2];
            echo number_format($value)."</td></tr>\n";
        }
        echo "<tr><td colspan=2>Overall</td><td class='number'>$volumein</td><td class='number'>".number_format($cost)."</td></tr></table></td>\n";
        echo "<td><table border=1><thead><tr><th colspan=4>Output</th></tr><tr><th>Material</th><th>Quantity</th><th>Volume m3</th><th>Total value</th></tr></thead>\n";
        foreach ($output as $output=>$quantity){
            echo "<tr><td>";
            $matdata=explode("|",$output);
            echo $matdata[1]."</td><td class='number'>".($quantity)."</td><td class='number'>".($quantity*$matdata[2])."</td><td class='number'>";
            $pricedatasell=$memcache->get('forgesell-'.$matdata[0]);
            $values=explode("|",$pricedatasell);
            $innerprice=$values[0];
            if ($innerprice=="")
            {
                $innerprice=0;
            }
            $value=$innerprice*$quantity;
            $price+=$value;
            $volumeout+=$quantity*$matdata[2];
            echo number_format($value)."</td></tr>\n";
        }
        echo "<tr><td colspan=2>Overall</td><td class='number'>$volumeout</td><td class='number'>".number_format($price)."</td></tr></table></td>\n";

echo "<td><table border=1><thead><tr><th colspan=4>Profits</th></tr><tr><th>Period</th><th>Margin</th><th>Volume in</th><th>Volume out</th></tr></thead><tr><td>One run</td><td class='number'>".number_format($price-$cost)."</td><td class='number'>".number_format($volumein)."</td><td class='number'>".number_format($volumeout)."</td></tr><tr><td>One day</td><td class='number'>".number_format(($price-$cost)*24)."</td><td class='number'>".number_format($volumein*24)."</td><td class='number'>".number_format($volumeout*24)."</td></tr><tr><td>Thirty days</td><td class='number'>".number_format(($price-$cost)*24*30)."</td><td class='number'>".number_format($volumein*24*30)."</td><td class='number'>".number_format($volumeout*24*30)."</td></tr></table><td class='number'>".number_format(($price-$cost)*24*30)."</td></tr>";



        echo "</tr>\n";

    }
    else
    {
    # Alchemy! So we have to refine as well.
        echo "<tr><th colspan=4 class='group-".$row->marketGroupID."'>$name</th></tr>";
        $reactiondata->execute(array($row->typeid));
        while ($reactions = $reactiondata->fetchObject()){
            $arraykey=$reactions->typeid.'|'.$reactions->typename.'|'.$reactions->volume;
            if ($reactions->input){
                $inputs[$arraykey]=$reactions->quantity*100;
            }
            else {
                $output[$arraykey]=$reactions->output;
            }
        }
        echo "<tr><td><table border=1><thead><tr><th colspan=4>Input</th><tr><th>Material</th><th>Quantity</th><th>Volume m3</th><th>Total cost</th></tr></thead>\n";

        foreach ($inputs as $input=>$quantity){
            echo "<tr><td>";
            $matdata=explode("|",$input);
            echo $matdata[1]."</td><td class='number'>".($quantity)."</td><td class='number'>".($quantity*$matdata[2])."</td><td class='number'>";
            $pricedatasell=$memcache->get('forgesell-'.$matdata[0]);
            $values=explode("|",$pricedatasell);
            $innerprice=$values[0];
            if ($innerprice=="")
            {
                $innerprice=0;
            }
            $value=$innerprice*$quantity;
            $cost+=$value;
            $volumein+=$quantity*$matdata[2];
            echo number_format($value)."</td></tr>\n";
        }
        echo "<tr><td colspan=2>Overall</td><td class='number'>$volumein</td><td class='number'>".number_format($cost)."</td></tr></table></td>\n";
        echo "<td><table border=1><thead><tr><th colspan=3>Output, unrefined</th></tr><tr><th>Material</th><th>Quantity</th><th>Total value</th></tr></thead>\n";
        $unrefined=array();
        foreach ($output as $output=>$quantity){
            echo "<tr><td>";
            $matdata=explode("|",$output);
            echo $matdata[1]."</td><td class='number'>".($quantity)."</td><td class='number'>";
            $pricedatasell=$memcache->get('forgesell-'.$matdata[0]);
            $values=explode("|",$pricedatasell);
            $innerprice=$values[0];
            if ($innerprice=="")
            {
                $innerprice=0;
            }
            $value=$innerprice*$quantity;
            $price+=$value;
            echo number_format($value)."</td></tr>\n";
            $unrefined[$matdata[0]]=$quantity;
        }
        $price=0;
        echo "</table><table border=1><thead><tr><th colspan=4>Output, refined</th></tr><tr><th>Material</th><th>Quantity</th><th>Volume m3</th><th>Total value</th></tr></thead>\n";
        foreach ($unrefined as $material=>$quantity){
            $refinedata->execute(array($material)); 
            while ($refining = $refinedata->fetchObject()){
                echo "<tr><td>";
                echo $refining->typename."</td><td class='number'>".($refining->quantity*$quantity)."</td><td class='number'>".($refining->quantity*$quantity*$refining->volume)."</td><td class='number'>";
                $pricedatasell=$memcache->get('forgesell-'.$refining->typeid);
                $values=explode("|",$pricedatasell);
                $innerprice=$values[0];
                if ($innerprice=="")
                {
                    $innerprice=0;
                }
                $value=$innerprice*$quantity*$refining->quantity;
                $volumeout+=$quantity*$refining->quantity*$refining->volume;
                $price+=$value;
                echo number_format($value)."</td></tr>\n";
            }
        } 
        echo "<tr><td colspan=2>Overall</td><td class='number'>$volumeout</td><td class='number'>".number_format($price)."</td></tr></table>\n";






        echo "<td><table border=1><thead><tr><th colspan=4>Profits</th></tr><tr><th>Period</th><th>Margin</th><th>Volume in</th><th>Volume out</th></tr></thead><tr><td>One run</td><td class='number'>".number_format($price-$cost)."</td><td class='number'>".number_format($volumein)."</td><td class='number'>".number_format($volumeout)."</td></tr><tr><td>One day</td><td class='number'>".number_format(($price-$cost)*24)."</td><td class='number'>".number_format($volumein*24)."</td><td class='number'>".number_format($volumeout*24)."</td></tr><tr><td>Thirty days</td><td class='number'>".number_format(($price-$cost)*24*30)."</td><td class='number'>".number_format($volumein*24*30)."</td><td class='number'>".number_format($volumeout*24*30)."</td></tr></table></td><td class='number'>".number_format(($price-$cost)*24*30)."</td></tr>";

    }





}
?>
</table>
</div>
<?php include('/home/web/fuzzwork/htdocs/bootstrap/footer.php'); ?>

</body>
</html>
