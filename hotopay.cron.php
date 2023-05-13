<?php

// Check Security
require "../../common/scripts/common.php";

$oDB = DB::getInstance();
$stmt = $oDB->query('SELECT domain FROM domains WHERE is_default_domain = "Y" LIMIT 1');
$domain = $stmt->fetchAll();
$_SERVER['HTTP_HOST'] = $domain[0]->domain;

class HotopayCronJob extends Hotopay{
    private $config;
    private $oDB;

    public function __construct()
    {
        parent::__construct();
        $this->config = $this->getConfig();
        $this->oDB = DB::getInstance();
    }

    public function run()
    {
        echo "====== Hotopay Cron ======" . PHP_EOL;
        $this->printLog("Start Time: " . date("Y-m-d H:i:s"));
        $this->printLog("Shop Name: " . $this->config->shop_name);
        $this->printLog("Domain: " . $_SERVER['HTTP_HOST']);
        $start_time = microtime();

        $this->cancelExpiredPurchases();
        $this->renewSubscriptions();

        $end_time = microtime();
        $this->printLog("Cron job finished");
        $this->printLog("End Time: " . date("Y-m-d H:i:s"));
        $this->printLog("Execute Duration: " . ($end_time - $start_time) . "s");
        echo "==========================" . PHP_EOL . PHP_EOL;
    }

    private function printLog($message)
    {
        echo "[".date("Y-m-d H:i:s")."] " . $message . PHP_EOL;
    }

    private function cancelExpiredPurchases()
    {
        $updateExpiredStatus = HotopayModel::updateExpiredPurchaseStatus();
        if (!$updateExpiredStatus->toBool())
        {
            $this->printLog("Error: Failed to cancel expired purchases; " . $updateExpiredStatus->message);
        }
        else
        {
            $this->printLog("Success: Change old purchases status to EXPIRED");
        }
    }

    private function renewSubscriptions()
    {
        $stmt = $this->oDB->query(
            "SELECT subscription.*, billing_key.pg AS pg, billing_key.key AS billing_key, billing_key.payment_type AS payment_type,
                    product.product_name AS product_name, product.product_status AS product_status, product.product_buyer_group AS buyer_group,
                    option.title AS option_name, option.stock AS option_stock, option.billing_infinity_stock AS option_billing_infinity_stock, option.price * subscription.quantity AS original_price,
                    subscription.last_billing_date + INTERVAL subscription.period day AS next_billing_date
             FROM hotopay_subscription AS subscription
             LEFT JOIN hotopay_billing_key AS billing_key ON subscription.billing_key_idx = billing_key.key_idx
             LEFT JOIN hotopay_product AS product ON subscription.product_srl = product.product_srl
             LEFT JOIN hotopay_product_option AS option ON subscription.option_srl = option.option_srl
             WHERE subscription.last_billing_date + INTERVAL subscription.period day <= CURRENT_TIMESTAMP AND subscription.status = 'ACTIVE'"
        );
        $subscriptions = $stmt->fetchAll();
        
        if (!empty($subscriptions))
        {
            $validator = new HotopayLicenseValidator();
            $isLicenseValid = $validator->validate($this->config->hotopay_license_key);
            if (!$isLicenseValid)
            {
                return $this->printLog("Error: Skip renewing subscriptions due to the Hotopay license key");
            }
        }
        else
        {
            return $this->printLog("Skip: No subscriptions to renew");
        }
            
        foreach ($subscriptions as $subscription)
        {
            $subscription->item_name = sprintf("%s (%s)", $subscription->product_name, $subscription->option_name);
            $subscription->hotopay_pay_method = $subscription->payment_type;
            if ($subscription->pg == 'payple')
            {
                $subscription->hotopay_pay_method = 'paypl_'.$subscription->payment_type;
            }

            echo PHP_EOL;
            $this->printLog("Billing Subscription: #" . $subscription->subscription_srl);
            $this->printLog("MemberSrl: #" . $subscription->member_srl);
            $this->printLog("Title: " . $subscription->item_name);
            $this->printLog("Renewal Date: " . $subscription->next_billing_date);

            $optionValid = $this->checkOptionStock($subscription);
            if (!$optionValid)
            {
                $this->printLog("Error: Out of stock; Cancel Renewing");
                $this->printLog("Update Subscription Status: OUT_OF_STOCK");
                $this->changeSubscriptionStatus($subscription->subscription_srl, 'OUT_OF_STOCK');

                $purchase_srl = $this->addPurchase('OUT_OF_STOCK', $subscription);
                $this->printLog("Add Purchase: #" . $purchase_srl);
                continue;
            }

            echo "Renewing Subscription..." . PHP_EOL;
            $this->minusOptionStock($subscription, 1);
        }
    }

    private function changeSubscriptionStatus($subscription_srl, $status)
    {
        $stmt = $this->oDB->prepare("UPDATE hotopay_subscription SET status = :status WHERE subscription_srl = :subscription_srl");
        $stmt->bindValue(":status", $status);
        $stmt->bindValue(":subscription_srl", $subscription_srl);
        $stmt->execute();
    }

    private function addPurchase($status, $subscription): int
    {
        $this->oDB->begin();
        $purchase_srl = getNextSequence();
        $extra_vars = serialize(new stdClass());

        $stmt = $this->oDB->prepare("INSERT INTO hotopay_purchase (purchase_srl, member_srl, title, products, pay_method, product_purchase_price, product_original_price, pay_status, is_billing, extra_vars, regdate)
                 VALUES (:purchase_srl, :member_srl, :title, :products, :pay_method, :product_purchase_price, :product_original_price, :pay_status, :is_billing, :extra_vars, :regdate)");
        $stmt->bindValue(":purchase_srl", $purchase_srl);
        $stmt->bindValue(":member_srl", $subscription->member_srl);
        $stmt->bindValue(":title", $subscription->item_name);
        $stmt->bindValue(":products", '');
        $stmt->bindValue(":pay_method", $subscription->hotopay_pay_method);
        $stmt->bindValue(":product_purchase_price", $subscription->price);
        $stmt->bindValue(":product_original_price", $subscription->original_price);
        $stmt->bindValue(":pay_status", $status);
        $stmt->bindValue(":is_billing", 'Y');
        $stmt->bindValue(":extra_vars", $extra_vars);
        $stmt->bindValue(":regdate", time());
        $stmt->execute();

        $item_srl = getNextSequence();
        $stmt = $this->oDB->prepare("INSERT INTO hotopay_purchase_item (item_srl, purchase_srl, product_srl, option_srl, option_name, purchase_price, original_price, quantity, extra_vars, regdate)
            VALUES (:item_srl, :purchase_srl, :product_srl, :option_srl, :option_name, :purchase_price, :original_price, :quantity, :extra_vars, :regdate)");
        $stmt->bindValue(":item_srl", $item_srl);
        $stmt->bindValue(":purchase_srl", $purchase_srl);
        $stmt->bindValue(":product_srl", $subscription->product_srl);
        $stmt->bindValue(":option_srl", $subscription->option_srl);
        $stmt->bindValue(":option_name", $subscription->option_name);
        $stmt->bindValue(":purchase_price", $subscription->price);
        $stmt->bindValue(":original_price", $subscription->original_price);
        $stmt->bindValue(":quantity", $subscription->quantity);
        $stmt->bindValue(":extra_vars", $extra_vars);
        $stmt->bindValue(":regdate", time());
        $stmt->execute();
        $this->oDB->commit();

        return $purchase_srl;
    }

    private function checkOptionStock(object $subscription): bool
    {
        if ($subscription->option_billing_infinity_stock == 'Y')
        {
            $this->printLog("Option Stock: infinity");
            return true;
        }
        else
        {
            $this->printLog("Option Stock: " . $subscription->option_stock);

            return ($subscription->option_stock > 0);
        }
    }

    private function minusOptionStock(object $subscription, int $quantity = 1): bool
    {
        if ($subscription->option_billing_infinity_stock != 'Y')
        {
            $stmt = $this->oDB->prepare("UPDATE hotopay_product_option SET stock = stock - :quantity WHERE option_srl = :option_srl");
            $stmt->bindValue(":quantity", $quantity);
            $stmt->bindValue(":option_srl", $subscription->option_srl);
            $stmt->execute();
        }

        return true;
    }
}

$oHotopayCronjob = new HotopayCronJob();
$oHotopayCronjob->run();


// foreach ($result as $row)
// {
//     $stmt = $oDB->prepare("UPDATE hotopay_subscription SET last_billing_date = CURRENT_TIMESTAMP WHERE id = :id");
//     $stmt->bindValue(":id", $row['id']);
//     $stmt->execute();

//     $stmt = $oDB->prepare("INSERT INTO hotopay_purchase (product_id, member_srl, payment_method, payment_amount, payment_status, payment_date, payment_ipaddress, payment_extra_vars) VALUES (:product_id, :member_srl, :payment_method, :payment_amount, :payment_status, :payment_date, :payment_ipaddress, :payment_extra_vars)");
//     $stmt->bindValue(":product_id", $row['product_id']);
//     $stmt->bindValue(":member_srl", $row['member_srl']);
//     $stmt->bindValue(":payment_method", $row['payment_method']);
//     $stmt->bindValue(":payment_amount", $row['payment_amount']);
//     $stmt->bindValue(":payment_status", "paid");
//     $stmt->bindValue(":payment_date", date("Y-m-d H:i:s"));
//     $stmt->bindValue(":payment_ipaddress", $row['payment_ipaddress']);
//     $stmt->bindValue(":payment_extra_vars", $row['payment_extra_vars']);
//     $stmt->execute();
// }