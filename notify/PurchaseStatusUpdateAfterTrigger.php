<?php

namespace Rhymix\Modules\Hotopay\Notify;

use HotopayModel;
use Rhymix\Modules\Notify\Triggers\TriggerInterface;
use Rhymix\Modules\Notify\Triggers\DispatchesToNotify;

/**
 * Hoto Pay 결제 모듈
 *
 * 결제 상태 변경 트리거. hotopay 모듈이 자체적으로 발동하는 커스텀 트리거명: hotopay.updatePurchaseStatus
 * 위치: hotopay.controller.php(콜백/토스빌링 등 13곳), hotopay.model.php::updateExpiredPurchaseStatus(),
 * hotopay.cron.php::renewSubscriptions() 실패 경로. pay_status로 DONE/FAILED/EXPIRED/REFUNDED/
 * WAITING_FOR_DEPOSIT/OUT_OF_STOCK/FAILED_RENEW 등 결제 생애주기의 모든 상태 전이를 포괄하는
 * 가장 근본적인 트리거다. $trigger_obj에 member_srl/title이 없어 extractVariables()에서
 * HotopayModel::getPurchase()로 보강한다. pay_data는 PG사 원본 응답(JSON)이라 string|number|boolean
 * 제약에 맞지 않고 민감정보가 섞일 수 있어 노출 대상에서 제외한다.
 */
class PurchaseStatusUpdateAfterTrigger implements TriggerInterface
{
	use DispatchesToNotify;

	public static function getCode(): string
	{
		return 'hotopay.updatePurchaseStatus';
	}

	public static function getName(): string
	{
		return '결제 상태 변경';
	}

	public static function getPosition(): string
	{
		return 'after';
	}

	public static function getVariables(): array
	{
		return [
			['code' => 'purchase_srl', 'label' => '결제 번호', 'type' => 'number'],
			['code' => 'pay_status', 'label' => '결제 상태 (DONE/FAILED/EXPIRED/REFUNDED 등)', 'type' => 'string'],
			['code' => 'pay_pg', 'label' => 'PG사', 'type' => 'string'],
			['code' => 'pay_method', 'label' => '결제수단', 'type' => 'string'],
			['code' => 'amount', 'label' => '결제 금액', 'type' => 'number'],
			['code' => 'original_amount', 'label' => '정가', 'type' => 'number'],
			['code' => 'member_srl', 'label' => '구매자 회원 번호', 'type' => 'number', 'member_lookup' => 'srl'],
			['code' => 'title', 'label' => '주문명', 'type' => 'string'],
			['code' => 'product_name', 'label' => '상품명 (여러 개면 콤마 구분)', 'type' => 'string'],
			['code' => 'regdate', 'label' => '결제 신청 일시', 'type' => 'string'],
		];
	}

	public function extractVariables($trigger_obj): array
	{
		$purchase_srl = (int)($trigger_obj->purchase_srl ?? 0);
		$purchase = $purchase_srl ? HotopayModel::getPurchase($purchase_srl) : null;
		$products = $purchase_srl ? HotopayModel::getProductsByPurchaseSrl($purchase_srl) : [];
		$product_names = is_array($products) ? array_column($products, 'product_name') : [];

		return [
			'purchase_srl' => $purchase_srl,
			'pay_status' => (string)($trigger_obj->pay_status ?? ''),
			'pay_pg' => (string)($trigger_obj->pay_pg ?? ''),
			'pay_method' => (string)($purchase->pay_method ?? ''),
			'amount' => (int)($trigger_obj->amount ?? 0),
			'original_amount' => (int)($purchase->product_original_price ?? 0),
			'member_srl' => (int)($purchase->member_srl ?? 0),
			'title' => (string)($purchase->title ?? ''),
			'product_name' => implode(', ', $product_names),
			'regdate' => $purchase && $purchase->regdate ? date('YmdHis', (int)$purchase->regdate) : '',
		];
	}
}
