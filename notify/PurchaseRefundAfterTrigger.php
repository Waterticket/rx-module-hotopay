<?php

namespace Rhymix\Modules\Hotopay\Notify;

use HotopayModel;
use Rhymix\Modules\Notify\Triggers\TriggerInterface;
use Rhymix\Modules\Notify\Triggers\DispatchesToNotify;

/**
 * Hoto Pay 결제 모듈
 *
 * 환불 처리 트리거. hotopay 모듈이 자체적으로 발동하는 커스텀 트리거명: hotopay.refundPurchase
 * 위치: hotopay.controller.php::_RefundProcess() (PG 환불 요청, 그룹 회수, 포인트 회수까지
 * 모두 끝난 뒤 발동). $trigger_obj에 title/amount가 없어 extractVariables()에서
 * HotopayModel::getPurchase()로 보강한다. 이 시점엔 이미 pay_status가 REFUNDED로 갱신돼 있으므로
 * amount는 환불 시점의 결제 금액(=환불 금액)과 동일하다.
 */
class PurchaseRefundAfterTrigger implements TriggerInterface
{
	use DispatchesToNotify;

	public static function getCode(): string
	{
		return 'hotopay.refundPurchase';
	}

	public static function getName(): string
	{
		return '환불 처리';
	}

	public static function getPosition(): string
	{
		return 'after';
	}

	public static function getVariables(): array
	{
		return [
			['code' => 'purchase_srl', 'label' => '결제 번호', 'type' => 'number'],
			['code' => 'member_srl', 'label' => '구매자 회원 번호', 'type' => 'number', 'member_lookup' => 'srl'],
			['code' => 'title', 'label' => '주문명', 'type' => 'string'],
			['code' => 'product_name', 'label' => '상품명 (여러 개면 콤마 구분)', 'type' => 'string'],
			['code' => 'amount', 'label' => '환불 금액', 'type' => 'number'],
			['code' => 'pay_method', 'label' => '결제수단', 'type' => 'string'],
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
			'member_srl' => (int)($trigger_obj->member_srl ?? 0),
			'title' => (string)($purchase->title ?? ''),
			'product_name' => implode(', ', $product_names),
			'amount' => (int)($purchase->product_purchase_price ?? 0),
			'pay_method' => (string)($purchase->pay_method ?? ''),
		];
	}
}
