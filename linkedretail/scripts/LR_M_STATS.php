<?php
require_once('../../app/Mage.php'); //Path to Magento
umask(0);
Mage::app();
 
// if ( $_POST ) {
// //get time range - remove the extra space if there's any
// $timefrom=$_POST["timefrom"];
// $timeto=$_POST["timeto"];
// $key=$_POST["password"];

// //password check
// if ( $key!=="coyote") {
// header("Location:https://linkedretail.com/");
// exit();
// }
//}
function get_string_between($string, $start, $end){
    $string = " ".$string;
    $ini = strpos($string,$start);
    if ($ini == 0) return "";
    $ini += strlen($start);
    $len = strpos($string,$end,$ini) - $ini;
    return substr($string,$ini,$len);
}
$url=$_SERVER['REQUEST_URI'];
$hash=get_string_between($url, "?", "&");
if ($hash=="FVmDHJq0JFEH") {
$timerange = substr($url, strpos($url, "&") + 1); 
$timeto = substr($timerange, strpos($timerange, "*") + 1);
$timefrom = get_string_between($url, "&", "*"); 
}
else {
header("Location:http://linkedretail.com");
exit();
}
?>

<?php
// $magentotimezone=date("m/d/Y h:i:s a", Mage::getModel('core/date')->timestamp(time()))
// echo "Magento System time:".$magentotimezone; 
// $timezone = date_default_timezone_get();
// echo "The current server timezone is: " . $timezone;
$dateFormat = 'Y-m-d H:i:s';
$timefrom = date($dateFormat, strtotime($timefrom));
$timeto = date($dateFormat, strtotime($timeto));
//adjusting
$timeto = date($dateFormat, strtotime('+23 hours 59 minutes 59 seconds',strtotime($timeto)));

//get order total number
$orderTotals_completed = Mage::getModel('sales/order')->getCollection()->addAttributeToSelect("*")
    ->addAttributeToFilter('status', array('eq' => Mage_Sales_Model_Order::STATE_COMPLETE))
    ->addAttributeToFilter('created_at', array(
        'from' => $timefrom,
        'to' => $timeto,))
    ->addAttributeToSelect('grand_total')
    ->getColumnValues('grand_total')
;
$orderTotals_processing = Mage::getModel('sales/order')->getCollection()->addAttributeToSelect("*")
    ->addAttributeToFilter('status', array('eq' => Mage_Sales_Model_Order::STATE_PROCESSING))
    ->addAttributeToFilter('created_at', array(
        'from' => $timefrom,
        'to' => $timeto,))
    ->addAttributeToSelect('grand_total')
    ->getColumnValues('grand_total')
;


//get order total amount
$OrdersAmount_completed = Mage::getModel('sales/order')->getCollection()->addAttributeToSelect("*")
    ->addAttributeToFilter('status',array('eq' => Mage_Sales_Model_Order::STATE_COMPLETE))
    ->addAttributeToFilter('created_at', array(
        'from' => $timefrom,
        'to' => $timeto,))
      ->getSize()
    ;
// foreach ($OrdersAmount_completed as $order_completed)
// {
//     $num_completed++;
// }

$OrdersAmount_processing = Mage::getModel('sales/order')->getCollection()->addAttributeToSelect("*")
    ->addAttributeToFilter('status', array('eq' => Mage_Sales_Model_Order::STATE_PROCESSING))
    ->addAttributeToFilter('created_at', array(
        'from' => $timefrom,
        'to' =>  $timeto,))
      ->getSize()
    ;

// foreach ($OrdersAmount_processing as $order_processing)
// {
//     $num_processing++;
// }

//total # of orders
//$OrdersAmount=$num_completed+$num_processing;
$OrdersAmount=$OrdersAmount_completed+$OrdersAmount_processing;

//total $ of orders
$totalSum_completed = array_sum($orderTotals_completed);
$totalSum_processing = array_sum($orderTotals_processing);
$totalSum=$totalSum_completed+$totalSum_processing;
$totalSum2 = Mage::helper('core')->currency($totalSum, true, false);//display currency


echo 
"Website Name: ".Mage::getBaseUrl()."<br>".
"Date Range:".$timefrom." to ".$timeto."<br>".
//"Total Completed Orders Amount:".$num_completed."<br>".
//"Total Processing Orders Amount:".$num_processing."<br>".
"Total Orders Amount:".$OrdersAmount."<br>".
"Total $ of Orders: ".$totalSum2."<br>";

//get product data
$productModel = Mage::getModel('catalog/product');
$collection = $productModel->getCollection();

//two types of attribute sets: "None" and "NONE"
$attrSetName1='None';

$attributeSetId_None1 = Mage::getModel('eav/entity_attribute_set')
    ->load($attrSetName1, 'attribute_set_name')
    ->getAttributeSetId();

// $attrSetName2='NONE';
// $attributeSetId_NONE2 = Mage::getModel('eav/entity_attribute_set')
//     ->load($attrSetName2, 'attribute_set_name')
//     ->getAttributeSetId();


//1) Total Products
$NumProducts = $collection->getSize();

//2) Configurable Products

$TotalConfigurableProduct= $collection->addAttributeToFilter('type_id', array('eq' => 'configurable'))
//->getSize()
;
foreach ($TotalConfigurableProduct as $total_config)
{
    $num_config++;
}

//3) Total Productw within attribute set none
//if ($attributeSetId_None1||$attributeSetId_None1){
$num_none=0;
// if ($attributeSetId_None1){
// $Total_NONE1= $collection->addAttributeToFilter('attribute_set_id',$attributeSetId_None1)
// //->getSize()
//;

$Total_NONE1 = Mage::getModel('catalog/product')
                    ->getCollection()
                    ->addAttributeToSelect('*')
                    ->addFieldToFilter('attribute_set_id', $attributeSetId_None1);

// else {
// $Total_NONE2= $collection->addAttributeToFilter('attribute_set_id',$attributeSetId_NONE2)
// ->getSize();
// $num_none=$Total_NONE2;
// }
foreach ($Total_NONE1 as $none1)
{
    $num_none++;
}

//}
// foreach ($Total_NONE2 as $none2)
// {
//     $num_none2++;
// }
// $num_none=$num_none1+$num_none2;
//}


$num_config_none=$num_config+$num_none;
echo "Total Amount of Products:".$NumProducts."<br>"."Total # of Configurable Products:".$num_config."<br>"."Total # of None Products:".$num_none."<br>"."Total # of Configurable and None products:".$num_config_none."<br>"."<br>"."<br>";

?>