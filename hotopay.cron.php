<?php

// 해당 파일을 임의로 수정하거나, 외부 API를 통해 원격 호출하는 것을 "강력하게" 권장하지 않습니다.
// 보안상 취약점에 노출될 수 있습니다.

// It is "strongly" discouraged to modify these files arbitrarily, or to make remote calls to them via external APIs.
// You may be exposed to security vulnerabilities.

// Check Security
require "../../common/scripts/common.php";
set_time_limit(0);

class HotopayCronJob extends Hotopay {
    private $config;
    private $oDB;
    private $oHotopayModel;

    public function __construct()
    {
        parent::__construct();
        $this->config = $this->getConfig();
        $this->oDB = DB::getInstance();
        $this->oHotopayModel = HotopayModel::getInstance();

        $stmt = $this->oDB->query('SELECT domain FROM domains WHERE is_default_domain = "Y" LIMIT 1');
        $domain = $stmt->fetchAll();
        $_SERVER['HTTP_HOST'] = $domain[0]->domain;
        define("RX_BASEURL", "https://".$_SERVER['HTTP_HOST']);
    }

    public function run()
    {
        echo "====== Hotopay Cron ======" . PHP_EOL;
        $this->printLog("Start Time: " . date("Y-m-d H:i:s"));
        $this->printLog("Shop Name: " . $this->config->shop_name);
        $this->printLog("Domain: " . $_SERVER['HTTP_HOST']);
        $start_time = microtime(true);

        $this->updateLastCronRunTime();
        $this->checkLicenseRenewalDate();
        $this->cancelExpiredPurchases();
        $this->updateCurrency();
        $this->renewSubscriptions();
        $this->updateLastCronSuccessTime();
        $this->callHotopayCronAfterTrigger();

        $end_time = microtime(true);
        $this->printLog("Cron job finished");
        $this->printLog("End Time: " . date("Y-m-d H:i:s"));
        $this->printLog("Execute Duration: " . round($end_time - $start_time, 3) . "s");
        echo "==========================" . PHP_EOL . PHP_EOL;
    }

    private function printLog(string $message, ...$args)
    {
        echo sprintf("[".date("Y-m-d H:i:s")."] " . $message, ...$args) . PHP_EOL;
    }

    private function updateLastCronRunTime()
    {
        $this->config->last_cron_execution_time = time();
        $this->setConfig($this->config);
    }

    private function updateLastCronSuccessTime()
    {
        $this->config->last_cron_execution_success_time = time();
        $this->setConfig($this->config);
    }

    private function checkLicenseRenewalDate()
    {
        $validator = new HotopayLicenseValidator();
        $isLicenseValid = $validator->validate($this->config->hotopay_license_key);
        if (!$isLicenseValid)
        {
            $this->printLog("Warning: Your Hotopay license is invalid");
            return;
        }

        $license_info = $validator->validate($this->config->hotopay_license_key, true);
        $expiry_date = round((strtotime($license_info[1]) - time())/86400);

        if ($expiry_date <= 30)
        {
            $this->printLog("Warning: Your Hotopay license will expire in %d days", $expiry_date);
        }
        else
        {
            $this->printLog("Success: Your Hotopay license will expire in %d days", $expiry_date);
            return;
        }

        if ($expiry_date <= 7)
        {
            if ($this->config->hotopay_last_license_expire_alert_date + (3600 * 24) <= time() + 10)
            {
                $this->config->hotopay_last_license_expire_alert_date = time();
                $this->setConfig($this->config);
                $this->printLog("Warning: Send license expire alert");
                $this->sendLicenseExpireAlert($expiry_date);
            }
        }
    }

    private function sendLicenseExpireAlert(int $expiry_date)
    {
        $title = "Hotopay Pro {$expiry_date}일 후 만료 안내";
        $body = "Hotopay Pro 라이선스가 {$expiry_date}일 후 만료됩니다. <br><br>만료 시 Pro 기능을 이용할 수 없게 되니 만료 전에 라이선스를 갱신해주세요. <br><br>갱신 방법은 <a href='https://potatosoft.kr/notice/11343' target='_blank'>홈페이지</a>에서 확인 가능합니다. <br>";

        $oCommController = CommunicationController::getInstance();
        $oCommController->sendMessage(4, 4, $title, $body);
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

    private function updateCurrency()
    {
        try
        {
            $output = $this->oHotopayModel->updateCurrency();
            if ($output === true)
            {
                $this->printLog("Success: Update currency");
            }
            else
            {
                $this->printLog("Warning: Failed to update currency; " . $output->message);
            }
        }
        catch (Exception $e)
        {
            $this->printLog("Warning: Failed to update currency; " . $e->getMessage());
            $this->sendFailedToUpdateCurrencyAlert($e->getMessage());
            return false;
        }

        $one_usd_to_krw = $this->oHotopayModel->changeCurrency('USD', 'KRW', 1);
        if ($one_usd_to_krw === false)
        {
            $this->printLog("Warning: Failed to change currency; 1 USD to KRW");
            return;
        }
        else
        {
            $this->printLog("Test: 1 USD = %.2f KRW", $one_usd_to_krw);
        }

        $one_thousand_krw_to_usd = $this->oHotopayModel->changeCurrency('KRW', 'USD', 1000);
        if ($one_thousand_krw_to_usd === false)
        {
            $this->printLog("Warning: Failed to change currency; 1,000 KRW to USD");
            return;
        }
        else
        {
            $this->printLog("Test: 1,000 KRW = %.2f USD", $one_thousand_krw_to_usd);
        }
    }

    private function sendFailedToUpdateCurrencyAlert($message)
    {
        $title = "Hotopay 환율 갱신 실패 알림";
        $body = "Hotopay Cron 실행 과정에서 환율 정보 갱신을 실패하였습니다. <br><br>환율 정보가 중요하다면 API 통신을 확인해주시길 바라며, 그렇지 않다면 환율 갱신 수단을 \"설정 안함\"으로 변경하는 것을 권장드립니다. <br><br>에러 메시지: {$message} <br>";

        $oCommController = CommunicationController::getInstance();
        $oCommController->sendMessage(4, 4, $title, $body);
    }

    private function renewSubscriptions()
    {
        $stmt = $this->oDB->query(
            "SELECT subscription.*, billing_key.pg AS pg, billing_key.key AS billing_key, billing_key.payment_type AS payment_type,
                    product.product_name AS product_name, product.product_status AS product_status, product.product_buyer_group AS buyer_group,
                    option.title AS option_name, option.stock AS option_stock, option.billing_infinity_stock AS option_billing_infinity_stock, option.price * subscription.quantity AS original_price,
                    member.nick_name AS nick_name, member.email_address AS email_address, member.phone_number AS phone_number
             FROM hotopay_subscription AS subscription
             LEFT JOIN hotopay_billing_key AS billing_key ON subscription.billing_key_idx = billing_key.key_idx
             LEFT JOIN hotopay_product AS product ON subscription.product_srl = product.product_srl
             LEFT JOIN hotopay_product_option AS option ON subscription.option_srl = option.option_srl
             LEFT JOIN member AS member ON subscription.member_srl = member.member_srl
             WHERE subscription.esti_billing_date <= CURRENT_TIMESTAMP AND subscription.status = 'ACTIVE'"
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
            $this->printLog("Start renewing subscription");
            $this->printLog("Billing Subscription: #" . $subscription->subscription_srl);
            $this->printLog("MemberSrl: #" . $subscription->member_srl);
            $this->printLog("Title: " . $subscription->item_name);
            $this->printLog("Estimate Renewal Date: " . $subscription->esti_billing_date);

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

            $trigger_obj = new stdClass();
            $trigger_obj->subscription_srl = $subscription->subscription_srl;
            $trigger_obj->member_srl = $subscription->member_srl;
            $trigger_obj->pg = $subscription->pg;
            $trigger_obj->product_srl = $subscription->product_srl;
            $trigger_obj->option_srl = $subscription->option_srl;
            $trigger_obj->quantity = $subscription->quantity;
            $trigger_obj->price = $subscription->price;
            $trigger_obj->billing_key_idx = $subscription->billing_key_idx;
            $trigger_obj->register_date = $subscription->register_date;
            $trigger_obj->esti_billing_date = $subscription->esti_billing_date;
            $output = ModuleHandler::triggerCall('hotopay.renewSubscription', 'before', $trigger_obj);
            if ($output->toBool() === false)
            {
                $this->printLog("Error: Failed renew subscription due to hotopay.renewSubscription before trigger; " . $output->message);
                $this->printLog("Update Subscription Status: FAILED_RENEW_TRIGGER");
                $this->changeSubscriptionStatus($subscription->subscription_srl, 'FAILED_RENEW_TRIGGER');

                $purchase_srl = $this->addPurchase('FAILED_RENEW_TRIGGER', $subscription);
                $this->printLog("Add Purchase: #" . $purchase_srl);

                $removeGroup = $this->removeMemberGroup($subscription->member_srl, $subscription->buyer_group);
                if (!$removeGroup->toBool())
                {
                    $this->printLog("Error: Failed to remove member group; " . $removeGroup->message);
                }
                $this->printLog("Remove Member Group: " . $subscription->buyer_group);

                $trigger_obj->purchase_srl = $purchase_srl;
                $trigger_obj->billing_status = 'FAILED_RENEW_TRIGGER';
                ModuleHandler::triggerCall('hotopay.renewSubscription', 'after', $trigger_obj);
                continue;
            }

            $this->printLog("Renewing Subscription...");
            $output = $this->requestBilling($subscription);
            if (isset($output->data->PCD_PAYER_ID)) $output->data->PCD_PAYER_ID = '*** secret ***';
            $subscription->pay_data = $output->data;
            $subscription->receipt_url = $output->data->receipt_url;
            if ($output->error != 0 )
            {
                $this->printLog("Error: Failed to renew subscription; " . $output->message);
                $this->printLog("Update Subscription Status: FAILED_RENEW");
                $this->changeSubscriptionStatus($subscription->subscription_srl, 'FAILED_RENEW');

                $purchase_srl = $this->addPurchase('FAILED_RENEW', $subscription);
                $this->printLog("Add Purchase: #" . $purchase_srl);

                $removeGroup = $this->removeMemberGroup($subscription->member_srl, $subscription->buyer_group);
                if (!$removeGroup->toBool())
                {
                    $this->printLog("Error: Failed to remove member group; " . $removeGroup->message);
                }
                $this->printLog("Remove Member Group: " . $subscription->buyer_group);

                $trigger_obj->purchase_srl = $purchase_srl;
                $trigger_obj->billing_status = 'FAILED_RENEW';
                ModuleHandler::triggerCall('hotopay.renewSubscription', 'after', $trigger_obj);
                continue;
            }

            $this->printLog("Successfully renewed");
            $this->minusOptionStock($subscription, 1);
            $this->addPurchase('DONE', $subscription, $output->data->purchase_srl);
            $this->printLog("Add Purchase: #" . $output->data->purchase_srl);
            $next_esti_billing_date = $this->updateSubscriptionEstiBillingDate($subscription);

            $trigger_obj->purchase_srl = $output->data->purchase_srl;
            $trigger_obj->billing_status = 'DONE';
            $trigger_obj->esti_billing_date = $next_esti_billing_date;
            ModuleHandler::triggerCall('hotopay.renewSubscription', 'after', $trigger_obj);
            $this->printLog("End renewing subscription");
        }
    }

    private function changeSubscriptionStatus($subscription_srl, $status)
    {
        $stmt = $this->oDB->prepare("UPDATE hotopay_subscription SET status = :status WHERE subscription_srl = :subscription_srl");
        $stmt->bindValue(":status", $status);
        $stmt->bindValue(":subscription_srl", $subscription_srl);
        $stmt->execute();
    }

    private function addPurchase($status, $subscription, $purchase_srl = -1): int
    {
        $this->oDB->begin();
        if ($purchase_srl < 0)
        {
            $purchase_srl = getNextSequence();
        }

        $extra_vars = serialize($subscription->extra_vars ?? new stdClass());
        $pay_data = json_encode($subscription->pay_data ?? new stdClass());

        $stmt = $this->oDB->prepare("INSERT INTO hotopay_purchase (purchase_srl, member_srl, title, products, pay_method, product_purchase_price, product_original_price, pay_status, pay_data, is_billing, receipt_url, extra_vars, regdate)
                 VALUES (:purchase_srl, :member_srl, :title, :products, :pay_method, :product_purchase_price, :product_original_price, :pay_status, :pay_data, :is_billing, :receipt_url, :extra_vars, :regdate)");
        $stmt->bindValue(":purchase_srl", $purchase_srl);
        $stmt->bindValue(":member_srl", $subscription->member_srl);
        $stmt->bindValue(":title", $subscription->item_name);
        $stmt->bindValue(":products", '');
        $stmt->bindValue(":pay_method", $subscription->hotopay_pay_method);
        $stmt->bindValue(":product_purchase_price", $subscription->price);
        $stmt->bindValue(":product_original_price", $subscription->original_price);
        $stmt->bindValue(":pay_status", $status);
        $stmt->bindValue(":pay_data", $pay_data);
        $stmt->bindValue(":receipt_url", $subscription->receipt_url ?? '');
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

        $this->oHotopayModel->updatePurchaseItemSubscriptionSrl($item_srl, $subscription->subscription_srl);
        $this->oHotopayModel->copyPurchaseExtraInfo($purchase_srl, $subscription->subscription_srl);
        $this->oDB->commit();

        $trigger_obj = new stdClass();
        $trigger_obj->purchase_srl = $purchase_srl;
        $trigger_obj->pay_status = $status;
        $trigger_obj->pay_data = $subscription->pay_data;
        $trigger_obj->pay_pg = $subscription->pg;
        $trigger_obj->amount = $subscription->price;
        ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);

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

            $this->printLog("Minus Option Stock quantity: " . $quantity);
        }
        else
        {
            $this->printLog("Skip Minus Option Stock quantity due to infinity stock");
        }

        return true;
    }

    private function requestBilling(object $subscription): object
    {
        switch($subscription->pg)
        {
            case "toss":
                $oToss = new Toss(true);
                $output = $oToss->requestBilling($subscription);
                $output->data->receipt_url = $output->data->receipt->url ?? "";
                break;

            case "payple":
                $oPayple = new Payple();
                $output = $oPayple->requestBilling($subscription);
                $output->data->receipt_url = $output->data->PCD_PAY_CARDRECEIPT ?? "";
                break;
        }
        return $output;
    }

    private function updateSubscriptionEstiBillingDate(object $subscription): string
    {
        $esti_billing_date = date("Y-m-d H:i:s", strtotime("+" . $subscription->period . " days", strtotime($subscription->esti_billing_date)));
        $last_billing_date = date("Y-m-d H:i:s");
        $stmt = $this->oDB->prepare("UPDATE hotopay_subscription AS subscription SET subscription.esti_billing_date = :esti_billing_date, subscription.last_billing_date = :last_billing_date WHERE subscription.subscription_srl = :subscription_srl");
        $stmt->bindValue(":subscription_srl", $subscription->subscription_srl);
        $stmt->bindValue(":esti_billing_date", $esti_billing_date);
        $stmt->bindValue(":last_billing_date", $last_billing_date);
        $stmt->execute();

        $this->printLog("Update Esti billing date: %s -> %s", $subscription->esti_billing_date, $esti_billing_date);

        return $esti_billing_date;
    }

    private function removeMemberGroup(int $member_srl, int $group_srl): BaseObject
    {
        $args = new stdClass();
        $args->member_srl = $member_srl;
        $args->group_srl = $group_srl;
        $output = executeQuery('member.deleteMemberGroupMember', $args); // 그룹제거

        return $output;
    }

    private function callHotopayCronAfterTrigger()
    {
        $trigger_obj = new stdClass();
        ModuleHandler::triggerCall('hotopay.cron', 'after', $trigger_obj);
    }
}

$oHotopayCronjob = new HotopayCronJob();
$oHotopayCronjob->run();
