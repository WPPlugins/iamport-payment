<?php
if ( !class_exists('IamportShortcode') ) {

	class IamportShortcode {

		private $user_code;
		private $api_key;
		private $api_secret;
		private $method_names = array(
			'card' => '신용카드',
			'trans' => '실시간계좌이체',
			'vbank' => '가상계좌',
			'phone' => '휴대폰소액결제'
		);

		public function __construct($user_code, $api_key, $api_secret) {
			$this->user_code = $user_code;
			$this->api_key = $api_key;
			$this->api_secret = $api_secret;

			$this->hook();
		}

		private function hook() {
			add_shortcode( 'iamport_payment_button', array($this, 'hook_payment_box') );
			add_shortcode( 'iamport_payment_result', array($this, 'hook_payment_result') );
		}

		private function getUUID() {
			return uniqid('iamport-dialog-');
		}

		public function hook_payment_box($atts, $content = null) {
			$uuid = $this->getUUID();
			$a = shortcode_atts( array(
				'title' => '결제하기',
				'description' => '아래의 정보를 입력 후 결제버튼을 클릭해주세요',
				'pay_method' => 'card',
				'pay_method_list' => 'card,trans,vbank,phone',
				'field_list' => 'email,name,phone',
				'name' => '아임포트 결제하기',
				'amount' => 1004,
				'style' => 'display:inline-block;padding:6px 12px;color:#fff;background-color:#2c3e50',
				'class' => null,
			), $atts );

			$method_list = array_unique( explode(',', $a['pay_method_list']) );
			$field_list = array_unique( explode(',', $a['field_list']) );

			if ( empty($content) )	$content = '결제하기';

			ob_start();
			?>
			<style type="text/css">
			.iamport-payment-box {max-width: 340px;margin: 0 auto;border: 1px solid #efefef;padding: 10px 18px;}
			.iamport-payment-box label, .iamport-payment-box input, .iamport-payment-box select {display: block;width: 99%}
			.iamport-payment-submit, .iamport-payment-link {display: inline-block;padding: 6px; width: 80%; background-color: #24b294; color: #fff; }
			</style>
			<div class="iamport-payment-box" id="<?=$uuid?>" style="display:none">
				<h5><?=$a['title']?></h5>
				<p><?=$a['description']?></p>
				<p>
					<label>결제수단</label>
					<select name="pay_method">
						<?php foreach($method_list as $m) : $method = trim($m); ?>
							<?php if (isset($this->method_names[ $method ])) : ?>
							<option value="<?=$method?>"><?=$this->method_names[ $method ]?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
				</p>

				<?php foreach($field_list as $f) : $field = trim($f); ?>
					<?php if ($field == 'name') : ?>
					<p>
						<label>결제자 성함</label>
						<input type="text" name="buyer_name">
					</p>
					<?php elseif ($field == 'email') : ?>
					<p>
						<label>결제자 Email</label>
						<input type="email" name="buyer_email">
					</p>
					<?php elseif ($field == 'phone') : ?>
					<p>
						<label>결제자 전화번호</label>
						<input type="tel" name="buyer_tel">
					</p>
					<?php endif; ?>
				<?php endforeach; ?>

				<p class="button-holder" style="text-align:center">
					<a href="#" class="iamport-payment-submit">결제하기</a>
				</p>	
			</div>
			<div class="iamport-result-box" id="iamport-result-box" style="display:none">
				<h5 class="title">결제결과</h5>
				<p class="content"></p>
			</div>
			<a href="#<?=$uuid?>" id="<?=$uuid?>-popup" class="<?=$a['class']?>" style="<?=(empty($a['class']) && !empty($a['style'])) ? $a['style'] : ''?>"><?=$content?></a>

			<script type="text/javascript">
			jQuery(function($) {
				var iamport_dialog = $('#<?=$uuid?>').dialog({autoOpen:false, modal:true, draggable:false, close: function() {
					$(this).find('.iamport-payment-submit').attr('data-progress', null).text('결제하기');
				}}),
					result_dialog = $('#iamport-result-box').dialog({autoOpen:false, modal:true, draggable:false});

				$('#<?=$uuid?>-popup').click(function() {
					iamport_dialog.dialog('open');

					IMP.init('<?=$this->user_code?>');

					return false;
				});

				$('#<?=$uuid?> a.iamport-payment-submit').click(function() {
					var $this = $(this);
					if ( $this.attr('data-progress') == 'true' )	return false;
					
					$this.attr('data-progress', 'true').text('결제 중입니다...');
					var box = $(this).closest('.iamport-payment-box');
					var pay_method 	= box.find('select[name="pay_method"]').val(),
						buyer_name 	= box.find('input[name="buyer_name"]').val() || '',
						buyer_email = box.find('input[name="buyer_email"]').val() || '',
						buyer_tel 	= box.find('input[name="buyer_tel"]').val() || '';

					<?php foreach($field_list as $f) : $field = trim($f); ?>
						<?php if ($field == 'name') : ?>
						if ( !buyer_name ) {
							alert('결제자 성함을 입력해주세요.');
							return false;
						}
						<?php elseif ($field == 'email') : ?>
						if ( !buyer_email ) {
							alert('결제자 Email을 입력해주세요.');
							return false;
						}
						<?php elseif ($field == 'phone') : ?>
						if ( !buyer_tel ) {
							alert('결제자 전화번호를 입력해주세요.');
							return false;
						}
						<?php endif; ?>
					<?php endforeach; ?>

					var order_amount = parseInt('<?=$a["amount"]?>'),
						order_title = '<?=strip_tags($a["name"])?>';
					$.ajax({
						method: "POST",
						url: "<?=admin_url( 'admin-ajax.php' )?>",
						data: {
							action: 'get_order_uid',
							order_title : order_title,
							pay_method : pay_method,
							buyer_name : buyer_name,
							buyer_email : buyer_email,
							buyer_tel : buyer_tel,
							order_amount : order_amount
						}
					}).done(function(rsp) {
						iamport_dialog.dialog('close');

						IMP.request_pay({
							pay_method : pay_method,
							merchant_uid : rsp.order_uid,
							name : order_title,
							amount : order_amount,
							buyer_name : buyer_name,
							buyer_email : buyer_email,
							buyer_tel : buyer_tel,
							m_redirect_url : rsp.thankyou_url
						}, function(callback) {
							if ( callback.success ) {
								//TODO : 다음버전에서 결제완료처리는 ajax로 하기
								result_dialog.find('.title').text('결제완료 처리중');
								result_dialog.find('.content').text('잠시만 기다려주세요. 결제완료 처리중입니다.');
								result_dialog.find('.iamport-payment-link').attr('href', rsp.thankyou_url);
								result_dialog.dialog('open');

								location.href = rsp.thankyou_url;
							} else {
								result_dialog.find('.title').text('결제실패');
								result_dialog.find('.content').html('다음과 같은 사유로 결제에 실패하였습니다.<br>' + callback.error_msg);
								result_dialog.dialog('open');
								//location.href = rsp.thankyou_url;
							}
						});
					});

					return false;
				});
			});
			</script>
			<?php 
			$shortcode_html = ob_get_clean();

			return $shortcode_html;
		}

		public function hook_payment_result($atts, $content = null) {
			$a = shortcode_atts( array(
				
			), $atts );
		}

	}

}