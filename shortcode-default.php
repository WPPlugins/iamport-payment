<?php
if ( !class_exists('IamportInternalShortcode') ) {

	class IamportInternalShortcode {

		private $user_code;
		private $api_key;
		private $api_secret;

		public function __construct($user_code, $api_key, $api_secret) {
			$this->user_code = $user_code;
			$this->api_key = $api_key;
			$this->api_secret = $api_secret;

			$this->hook();
		}

		private function hook() {
			add_shortcode( 'iamport_history_page', array($this, 'hook_history_page') );
			add_shortcode( 'iamport_thankyou_page', array($this, 'hook_thankyou_page') );
		}

		public function hook_history_page($atts, $content = null) {
			global $wp;

			if ( empty($wp->query_vars['iamport-order-view']) ) { //list view
				return $this->get_order_list( get_current_user_id() );
			} else { //each order view
				$order_uid = $wp->query_vars['iamport-order-view'];

				return $this->get_order_view($order_uid);
			}
		}

		public function hook_thankyou_page($atts, $content = null) {
			global $wp;
			if ( empty($wp->query_vars['iamport-order-received']) )	return '파라메터라 누락되었습니다. 관리자에게 문의해주세요.';

			$order_uid = $wp->query_vars['iamport-order-received'];
			$iamport_order = IamportOrder::find_by_order_uid($order_uid);
			if ( empty($iamport_order) )		return '주문정보를 찾을 수 없습니다.';


			$iamport = new Iamport($this->api_key, $this->api_secret);
			$iamport_result = $iamport->findByMerchantUID($order_uid);
			if ( $iamport_result->success ) {
				if ( intval($iamport_result->data->amount) != $iamport_order->get_order_amount() )		return '결제요청금액과 승인된 금액이 다릅니다. 비정상적인 시도입니다.';

				//결제완료처리
				$iamport_order->set_pay_method($iamport_result->data->pay_method);
				$iamport_order->set_order_status($iamport_result->data->status, $iamport_result->data->pay_method);

				if ( $iamport_result->data->status == 'paid' ) {
					$iamport_order->set_paid_amount( $iamport_result->data->amount );
					$iamport_order->set_paid_date( date('Y-m-d H:i:s', $iamport_result->data->paid_at) );
					$iamport_order->set_receipt_url($iamport_result->data->receipt_url);
				} else if ( $iamport_result->data->status == 'failed' ) {
					$iamport_order->add_failed_history( 
						date('Y-m-d H:i:s', $iamport_result->data->failed_at), $iamport_result->data->fail_reason );
				}

				if ( $iamport_result->data->pay_method == 'vbank' ) {
					$iamport_order->set_vbank_info(array(
						'name' => $iamport_result->data->vbank_name,
						'account' => $iamport_result->data->vbank_num,
						'due' => date('Y-m-d H:i:s', $iamport_result->data->vbank_date),
					));
				}

				$history_page = get_page_by_slug('iamport_history');
				if ( !empty($history_page) )	$order_view_url = get_page_link($history_page[0]->ID) . '/iamport-order-view/' . $order_uid;

				ob_start();
				?>

				<?php if ($iamport_result->data->status == 'paid') : ?>
				<h3>결제가 정상적으로 완료되었습니다.</h3>
				<?php elseif ($iamport_result->data->status == 'failed') : ?>
				<h3>결제에 실패하였습니다</h3>
				<p><?=$iamport_result->data->fail_reason?></p>
				<?php else : ?>
				<h3>(결제 전)주문이 접수되었습니다.</h3>
				<?php endif; ?>

				<p><a href="<?=$order_view_url?>">결제정보 확인하러 가기</a></p>
				<script type="text/javascript">
					setTimeout(function() {
						location.href = '<?=$order_view_url?>';
					}, 100);
				</script>

				<?php 
				$shortcode_html = ob_get_clean();

				return $shortcode_html;
			} else {
				ob_start();
				?>

				<h3>결제 정보를 확인할 수 없습니다.</h3>
				<script type="text/javascript">
					setTimeout(function() {
						location.href = '<?=$order_view_url?>';
					}, 100);
				</script>

				<?php
				$html = ob_get_clean();

				return $html;
			}
		}

		public function get_order_list( $user_id ) {
			$args = array(
				'posts_per_page' => 20,
				'post_type' => 'iamport_payment',
				'post_status' => 'any',
				'author' => $user_id,
				'orderby' => 'ID',
				'order' => 'DESC'
			);
			$posts = get_posts( $args );

			$history_page = get_page_by_slug('iamport_history');
			if ( !empty($history_page) )	$history_page_url = get_page_link($history_page[0]->ID);

			ob_start();
			?>
			<style type="text/css">
			.iamport-order-list {width: 100%; border: 1px solid #efefef; padding: 4px;}
			.iamport-order-list th, .iamport-order-list td {padding: 4px;border-bottom: 1px solid #efefef;}
			.iamport-order-list th {font-weight: bold;}
			.iamport-order-list td {}
			.iamport-order-list td a.view-order {display: inline-block; background-color: #efefef; color: #333;padding: 4px 8px;}
			</style>
			<table class="iamport-order-list">
				<thead>
					<tr>
						<th>주문번호</th>
						<th>주문명</th>
						<th>결제수단</th>
						<th>주문일자</th>
						<th>주문상태</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach($posts as $p) : $iamport_order = new IamportOrder($p); ?>
					<tr>
						<td><?=$iamport_order->get_order_uid()?></td>
						<td><?=$p->post_title?></td>
						<td><?=$iamport_order->get_pay_method()?></td>
						<td></td>
						<td></td>
						<td><a target="_blank" class="view-order" href="<?=$history_page_url?>/iamport-order-view/<?=$iamport_order->get_order_uid()?>">보기</a></td>
					</tr>
					<?php endforeach; ?>

					<?php if (empty($posts)) : ?>
					<tr>
						<td colspan="6">결제내역을 찾을 수 없습니다.</td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>
			<?php 
			$html = ob_get_clean();

			return $html;
		}

		public function get_order_view($order_uid) {
			$iamport_order = IamportOrder::find_by_order_uid($order_uid);

			if ( $iamport_order ) {
				ob_start();
				?>
				<style type="text/css">
				.iamport-order-view {width: 100%; border: 1px solid #efefef; padding: 4px;}
				.iamport-order-view th, .iamport-order-view td {padding: 4px 8px;border-bottom: 1px solid #efefef;}
				.iamport-order-view th {font-weight: bold;}
				.iamport-order-view td {}
				.iamport-order-view td a.view-order {display: inline-block; background-color: #efefef; color: #333;padding: 4px 8px;}
				</style>
				<table class="iamport-order-view">
					<tbody>
						<tr>
							<th>주문번호</th>
							<td><?=$iamport_order->get_order_uid()?></td>
						</tr>
						<?php if ($iamport_order->get_order_status(true) == 'paid') : ?>
						<tr>
							<th>매출전표</th>
							<td><a href="<?=$iamport_order->get_receipt_url()?>" target="_blank">매출전표보기</a></td>
						</tr>
						<tr>
							<th>결제일자</th>
							<td><?=$iamport_order->get_paid_date()?></td>
						</tr>
						<tr>
							<th>결제금액</th>
							<td><?=number_format($iamport_order->get_order_amount())?> 원</td>
						</tr>
						<?php endif; ?>
						<tr>
							<th>주문명</th>
							<td><?=$iamport_order->get_order_name()?></td>
						</tr>
						<tr>
							<th>결제수단</th>
							<td><?=$iamport_order->get_pay_method()?></td>
						</tr>
						<tr>
							<th>결제상태</th>
							<td><b><?=$iamport_order->get_order_status()?></b></td>
						</tr>
						<?php if ($iamport_order->get_pay_method(true) == 'vbank') : $vbank_info = $iamport_order->get_vbank_info(); ?>
							<?php if (!empty($vbank_info['name'])) : ?>
							<tr>
								<th>가상계좌 입금은행</th>
								<td><?=$vbank_info['name']?></td>
							</tr>
							<?php endif; ?>
							<?php if (!empty($vbank_info['account'])) : ?>
							<tr>
								<th>가상계좌번호</th>
								<td><?=$vbank_info['account']?></td>
							</tr>
							<?php endif; ?>
							<?php if (!empty($vbank_info['due'])) : ?>
							<tr>
								<th>가상계좌 입금기한</th>
								<td><?=$vbank_info['due']?></td>
							</tr>
							<?php endif; ?>
						<?php endif; ?>
						<?php
							$buyer_name = $iamport_order->get_buyer_name();
							$buyer_email = $iamport_order->get_buyer_email();
							$buyer_tel = $iamport_order->get_buyer_tel();
						?>
						<?php if(!empty($buyer_name)) : ?>
						<tr>
							<th>결제자 이름</th>
							<td><?=$buyer_name?></td>
						</tr>
						<?php endif; ?>
						<?php if(!empty($buyer_email)) : ?>
						<tr>
							<th>결제자 Email</th>
							<td><?=$buyer_email?></td>
						</tr>
						<?php endif; ?>
						<?php if(!empty($buyer_tel)) : ?>
						<tr>
							<th>결제자 전화번호</th>
							<td><?=$buyer_tel?></td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
				<?php 
				$html = ob_get_clean();

				return $html;
			}

			return '주문정보를 찾을 수 없습니다.';
		}

	}
}