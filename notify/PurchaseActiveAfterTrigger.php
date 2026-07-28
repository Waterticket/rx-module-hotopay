<?php

namespace Rhymix\Modules\Hotopay\Notify;

use HotopayModel;
use Rhymix\Modules\Notify\Triggers\TriggerInterface;
use Rhymix\Modules\Notify\Triggers\DispatchesToNotify;

/**
 * Hoto Pay 결제 모듈
 *
 * 구매 확정(결제 완료) 트리거. hotopay 모듈이 자체적으로 발동하는 커스텀 트리거명: hotopay.activePurchase
 * 위치: hotopay.controller.php::_ActivePurchase() (구매자 그룹 부여·포인트 적립까지 끝난 뒤 발동).
 * hotopay.updatePurchaseStatus(pay_status=DONE)와 같은 결제 건에 대해 발동될 수 있지만, 이 트리거는
 * "실제로 결제가 승인되어 구매가 최종 확정된" 시점만 다루므로 결제수단(콜백/토스빌링/수동승인)에
 * 관계없이 항상 한 번만 발동한다. $trigger_obj에 title/amount가 없어 extractVariables()에서
 * HotopayModel::getPurchase()로 보강한다.
 */
class PurchaseActiveAfterTrigger implements TriggerInterface
{
	use DispatchesToNotify;

	public static function getCode(): string
	{
		return 'hotopay.activePurchase';
	}

	public static function getName(): string
	{
		return '구매 확정(결제 완료)';
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
			['code' => 'amount', 'label' => '결제 금액', 'type' => 'number'],
			['code' => 'pay_method', 'label' => '결제수단', 'type' => 'string'],
			['code' => 'receipt_url', 'label' => '영수증 URL', 'type' => 'string'],
			['code' => 'group_srl_list', 'label' => '부여된 그룹 번호 목록 (콤마 구분)', 'type' => 'string'],
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
			'receipt_url' => (string)($purchase->receipt_url ?? ''),
			'group_srl_list' => implode(',', $trigger_obj->group_srls ?? []),
		];
	}
}
