<?php
// Load WordPress
define('WP_USE_THEMES', false);
require('../wp-load.php');

$user_id = $_GET["id"];
$action = $_GET["action"];
$auth = $_GET["auth"];

if($auth != "iloveyogalol") {
  die();
}

// Load MemberPress
require_once '../wp-content/plugins/memberpress/memberpress.php';

$mepr_user = new MeprUser( $user_id );

$plans = array();

$plans[] = 808; // annual
$plans[] = 807; // monthly
$plans[] = 14638; // annual pro
$plans[] = 2329; // mtb switch
$plans[] = 12875; // 'pro' plan
$plans[] = 11781; // 'oxygen addict' plan

function checkSubscriber($user_id) {
  global $mepr_user;
  global $plans;

  $is_subscriber = false;

  foreach($plans as $plan) {
    if($mepr_user->is_active_on_membership( $plan )) {
      $is_subscriber = true;
      break;
    }
  }

  return $is_subscriber;
}

switch($action) {
  case "check":
    $is_subscriber = checkSubscriber($user_id);

    die(json_encode(array("success" => true, "subscriber" => $is_subscriber)));
  break;
  case "create":
    $productId = intval($_GET["productId"]);
    $price = $_GET["price"];
    $periodType = $_GET["period"];

    if($periodType != "months" && $periodType != "years") {
      die(json_encode(array("error" => "Invalid period ID")));
    }

    if(!in_array($productId, $plans)) {
      die(json_encode(array("error" => "Invalid plan ID")));
    }

    if(checkSubscriber($user_id)) {
      die(json_encode(array("error" => "Already subscriber")));
    }

    $sub = new MeprSubscription();
    $sub->user_id = $user_id;
    $sub->product_id = $productId;
    $sub->price = $price;
    $sub->total = $price;
    $sub->period = 1;
    $sub->period_type = $periodType;
    $sub->status = MeprSubscription::$active_str;
    $sub_id = $sub->store();

    $txn = new MeprTransaction();
    $txn->amount = $price;
    $txn->total = $price;
    $txn->user_id = $user_id;
    $txn->product_id = $productId;
    $txn->status = MeprTransaction::$complete_str;
    $txn->txn_type = MeprTransaction::$payment_str;
    $txn->gateway = 'manual';
    $txn->expires_at = gmdate('Y-m-d 23:59:59', (time() + ($periodType == 'years' ? MeprUtils::years(1) : MeprUtils::months(1)) ));
    $txn->subscription_id = $sub_id;
    $txn->store();

    die(json_encode(array("success" => checkSubscriber($user_id))));
  break;
}

?>