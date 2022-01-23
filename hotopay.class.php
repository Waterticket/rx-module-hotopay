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
		// array('document.updateDocument', 'after', 'controller', 'triggerAfterUpdateDocument'),
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
			if(!isset(self::$_config_cache->shop_name)) self::$_config_cache->shop_name = 'HotoPay'; // 쇼핑몰 이름
			
			if(!isset(self::$_config_cache->toss_enabled)) self::$_config_cache->toss_enabled = 'N'; // 토스 활성화
			if(!isset(self::$_config_cache->paypal_enabled)) self::$_config_cache->paypal_enabled = 'N'; // 페이팔 활성화
			if(!isset(self::$_config_cache->kakaopay_enabled)) self::$_config_cache->kakaopay_enabled = 'N'; // 카카오페이 활성화
			if(!isset(self::$_config_cache->n_account_enabled)) self::$_config_cache->n_account_enabled = 'N'; // 무통장입금 활성화
			
			if(!isset(self::$_config_cache->toss_payments_list)) self::$_config_cache->toss_payments_list = array(1,2); // 토스 결제 방식 목록

			if(!isset(self::$_config_cache->toss_payments_client_key)) self::$_config_cache->toss_payments_client_key = ''; // 토스 클라이언트 키
			if(!isset(self::$_config_cache->toss_payments_secret_key)) self::$_config_cache->toss_payments_secret_key = ''; // 토스 시크릿 키
			if(!isset(self::$_config_cache->paypal_client_key)) self::$_config_cache->paypal_client_key = ''; // 페이팔 클라이언트 키
			if(!isset(self::$_config_cache->paypal_secret_key)) self::$_config_cache->paypal_secret_key = ''; // 페이팔 시크릿 키
			if(!isset(self::$_config_cache->kakaopay_admin_key)) self::$_config_cache->kakaopay_admin_key = ''; // 카카오페이 어드민 키
			if(!isset(self::$_config_cache->kakaopay_cid_key)) self::$_config_cache->kakaopay_cid_key = ''; // 카카오페이 가맹점 코드
			if(!isset(self::$_config_cache->kakaopay_cid_secret_key)) self::$_config_cache->kakaopay_cid_secret_key = ''; // 카카오페이 가맹점 코드 인증키

			if(!isset(self::$_config_cache->n_account_string)) self::$_config_cache->n_account_string = ''; // 무통장 입금 계좌
			if(!isset(self::$_config_cache->purchase_term_url)) self::$_config_cache->purchase_term_url = ''; // 결제 약관 URL
			
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
}
