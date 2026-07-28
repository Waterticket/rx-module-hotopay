<?php

namespace Rhymix\Modules\Hotopay\Notify;

use HotopayModel;
use Rhymix\Modules\Notify\Triggers\TriggerInterface;
use Rhymix\Modules\Notify\Triggers\DispatchesToNotify;

/**
 * Hoto Pay 결제 모듈
 *
 * 정기결제 자동 갱신 트리거. hotopay 모듈이 자체적으로 발동하는 커스텀 트리거명: hotopay.renewSubscription
 * 위치: hotopay.cron.php::renewSubscriptions() (cron이 esti_billing_date가 지난 구독을 찾아 순회하며
 * before/after 두 시점 모두 호출 - 이 트리거는 after만 다룬다). billing_status는 그 시점까지 실제로
 * 관측되는 값이 DONE(갱신 성공)/FAILED_RENEW(PG 갱신 실패)/FAILED_RENEW_TRIGGER(before 트리거에서
 * 취소) 3가지뿐이다 - 재고 소진(OUT_OF_STOCK)은 이 트리거 호출 전에 continue하므로 여기 나타나지
 * 않는다. $trigger_obj에 product_name이 없어 extractVariables()에서 HotopayModel::getProduct()로
 * 보강한다.
 */
class SubscriptionRenewAfterTrigger implements TriggerInterface
{
	use DispatchesToNotify;

	public static function getCode(): string
	{
		return 'hotopay.renewSubscription';
	}

	public static function getName(): string
	{
		return '정기결제 갱신';
	}

	public static function getPosition(): string
	{
		return 'after';
	}

	public static function getVariables(): array
	{
		return [
			['code' => 'subscription_srl', 'label' => '구독 번호', 'type' => 'number'],
			['code' => 'purchase_srl', 'label' => '결제 번호', 'type' => 'number'],
			['code' => 'member_srl', 'label' => '구독자 회원 번호', 'type' => 'number', 'member_lookup' => 'srl'],
			['code' => 'product_srl', 'label' => '상품 번호', 'type' => 'number'],
			['code' => 'product_name', 'label' => '상품명', 'type' => 'string'],
			['code' => 'option_srl', 'label' => '옵션 번호', 'type' => 'number'],
			['code' => 'option_name', 'label' => '옵션명', 'type' => 'string'],
			['code' => 'quantity', 'label' => '수량', 'type' => 'number'],
			['code' => 'price', 'label' => '결제 금액', 'type' => 'number'],
			['code' => 'pg', 'label' => 'PG사', 'type' => 'string'],
			[
				'code' => 'billing_status', 'label' => '갱신 결과', 'type' => 'string', 'value_ui' => 'select',
				'options' => [
					['value' => 'DONE', 'label' => '성공(DONE)'],
					['value' => 'FAILED_RENEW', 'label' => 'PG 갱신 실패(FAILED_RENEW)'],
					['value' => 'FAILED_RENEW_TRIGGER', 'label' => '갱신 전 검증 실패(FAILED_RENEW_TRIGGER)'],
				],
			],
			['code' => 'esti_billing_date', 'label' => '다음 결제 예정일 (성공 시에만 갱신됨)', 'type' => 'string'],
		];
	}

	public function extractVariables($trigger_obj): array
	{
		$product_srl = (int)($trigger_obj->product_srl ?? 0);
		$product = $product_srl ? HotopayModel::getProduct($product_srl) : null;
		$option_srl = (int)($trigger_obj->option_srl ?? 0);
		$option = $option_srl ? HotopayModel::getOption($option_srl) : null;

		return [
			'subscription_srl' => (int)($trigger_obj->subscription_srl ?? 0),
			'purchase_srl' => (int)($trigger_obj->purchase_srl ?? 0),
			'member_srl' => (int)($trigger_obj->member_srl ?? 0),
			'product_srl' => $product_srl,
			'product_name' => (string)($product->product_name ?? ''),
			'option_srl' => $option_srl,
			'option_name' => (string)($option->title ?? ''),
			'quantity' => (int)($trigger_obj->quantity ?? 0),
			'price' => (int)($trigger_obj->price ?? 0),
			'pg' => (string)($trigger_obj->pg ?? ''),
			'billing_status' => (string)($trigger_obj->billing_status ?? ''),
			'esti_billing_date' => (string)($trigger_obj->esti_billing_date ?? ''),
		];
	}
}
