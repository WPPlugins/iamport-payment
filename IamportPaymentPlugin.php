<?php
/*
Plugin Name: 아임포트 결제버튼 생성 플러그인
Plugin URI: http://www.iamport.kr
Description: 원하는 위치에 자유자재로 결제버튼을 생성하실 수 있는 아임포트 플러그인입니다. 국내 PG사의 다양한 결제수단을 이용하실 수 있습니다. ( 신용카드 / 실시간계좌이체 / 가상계좌 / 휴대폰소액결제 - 에스크로포함 )
Version: 0.43
Author: SIOT
Author URI: http://www.siot.do
*/
function get_page_by_slug($slug) {
	$args = array(
		'name'        => $slug,
		'post_type'   => 'page',
		'post_status' => 'publish',
		'numberposts' => 1
	);
	return get_posts($args);
}

function create_history_page() {
	$slug = 'iamport_history';
	
	$history_page = get_page_by_slug($slug);
	if( empty($history_page) ) {
		$page_data = array(
			'post_status'		=> 'publish',
			'post_type'			=> 'page',
			'post_author'		=> 1,
			'post_name'			=> $slug,
			'post_title'		=> '결제내역 - 아임포트',
			'post_content'		=> '[iamport_history_page]',
			'post_parent'		=> 0,
			'comment_status'	=> 'closed'
		);

		$page_id = wp_insert_post( $page_data );
	}
}

function create_thankyou_page() {
	$slug = 'iamport_thankyou';
	
	$thankyou_page = get_page_by_slug($slug);
	if( empty($thankyou_page) ) {
		$page_data = array(
			'post_status'		=> 'publish',
			'post_type'			=> 'page',
			'post_author'		=> 1,
			'post_name'			=> $slug,
			'post_title'		=> '결제완료 - 아임포트',
			'post_content'		=> '[iamport_thankyou_page]',
			'post_parent'		=> 0,
			'comment_status'	=> 'closed'
		);

		$page_id = wp_insert_post( $page_data );
	}
}

function add_endpoints() {
	add_rewrite_endpoint( 'iamport-order-view', EP_PAGES );
	add_rewrite_endpoint( 'iamport-order-received', EP_PERMALINK | EP_PAGES );
	flush_rewrite_rules();
}

function iamport_activated() {
	create_history_page();
	create_thankyou_page();
	add_endpoints();
}
register_activation_hook( __FILE__, 'iamport_activated' );

if ( !class_exists('IamportPaymentPlugin') ) {
	require_once(dirname(__FILE__).'/lib/iamport.php');
	require_once(dirname(__FILE__).'/model/iamport-order.php');
	require_once(dirname(__FILE__).'/shortcode.php');
	require_once(dirname(__FILE__).'/shortcode-default.php');

	class IamportPaymentPlugin {
		
		private $user_code;
		private $api_key;
		private $api_secret;
		private $shortcode;
		private $internal_shortcode;

		public function __construct() {
			if( is_admin() ) {
				add_action('admin_menu', array( $this, 'iamport_admin_menu') );
			}

			add_action('init', array($this, 'init') );
			add_action('wp_enqueue_scripts', array($this, 'iamport_script_enqueue'), 99);
			add_filter('query_vars', array($this, 'add_query_vars'), 0);

			add_action( 'wp_ajax_get_order_uid', array($this, 'ajax_get_order_uid') );
			add_action( 'wp_ajax_nopriv_get_order_uid', array($this, 'ajax_get_order_uid') );

			add_action( 'add_meta_boxes', array($this, 'iamport_order_metabox') );
			add_action( 'save_post', array($this, 'save_iamport_order_metabox') );
		}

		public function add_query_vars($vars) {
			$vars[] = 'iamport-order-view';
			$vars[] = 'iamport-order-received';

			return $vars;
		}

		public function init() {
			$this->user_code = get_option('iamport_user_code');
			$this->api_key = get_option('iamport_rest_key');
			$this->api_secret = get_option('iamport_rest_secret');

			$this->shortcode = new IamportShortcode($this->user_code, $this->api_key, $this->api_secret);
			$this->internal_shortcode = new IamportInternalShortcode($this->user_code, $this->api_key, $this->api_secret);
			$this->create_iamport_post_type();
		}

		public function ajax_get_order_uid() {
			$order_title 	= $_POST['order_title'];
			$pay_method 	= $_POST['pay_method'];
			$buyer_name 	= $_POST['buyer_name'];
			$buyer_email 	= $_POST['buyer_email'];
			$buyer_tel 		= $_POST['buyer_tel'];
			$order_amount 	= $_POST['order_amount'];

			$order_data = array(
				'post_status'		=> 'publish',
				'post_type'			=> 'iamport_payment',
				'post_name'			=> $slug,
				'post_title'		=> $order_title,
				'post_parent'		=> 0,
				'comment_status'	=> 'closed'
			);

			$order_id = wp_insert_post( $order_data );
			
			$order_uid = $this->get_order_uid();
			add_post_meta( $order_id, 'order_uid', $order_uid, true);
			add_post_meta( $order_id, 'pay_method', $pay_method, true);
			add_post_meta( $order_id, 'buyer_name', $buyer_name, true);
			add_post_meta( $order_id, 'buyer_email', $buyer_email, true);
			add_post_meta( $order_id, 'buyer_tel', $buyer_tel, true);
			add_post_meta( $order_id, 'order_amount', $order_amount, true);
			add_post_meta( $order_id, 'order_status', 'ready', true);

			$thankyou_url = '';
			$thankyou_page = get_page_by_slug('iamport_thankyou');
			if ( !empty($thankyou_page) )	$thankyou_url = get_page_link($thankyou_page[0]->ID) . '/iamport-order-received/' . $order_uid;

			wp_send_json(array('order_id'=>$order_id, 'order_uid'=>$order_uid, 'thankyou_url'=>$thankyou_url));
		}

		private function get_order_uid() {
			return uniqid(date('mdis_'));
		}

		private function create_iamport_post_type() {
			register_post_type( 'iamport_payment',
				array(
					'labels' => array(
						'name' => '아임포트 결제목록',
						'singular_name' => '아임포트 결제목록'
					),
					'menu_icon' => plugin_dir_url( __FILE__ ) . 'img/iamport.jpg',
					'show_ui' => true,
					'show_in_nav_menus' => false,
					'show_in_admin_bar' => true,
					'public' => true,
					'has_archive' => false,
					'rewrite' => array('slug' => 'iamport_payment'),
					'map_meta_cap' => true,
					'capabilities' => array(
						'edit_post' => true,
						'create_posts' => false
					)
				)
			);

			remove_post_type_support( 'iamport_payment', 'editor' );

			add_filter( 'manage_iamport_payment_posts_columns', array($this, 'iamport_payment_columns') );
			add_action( 'manage_iamport_payment_posts_custom_column' , array($this, 'iamport_payment_custom_columns'), 10, 2 );
		}

		public function iamport_admin_menu() {
			//add_menu_page( '아임포트 결제설정', '아임포트 결제설정', 'administrator', 'iamport', array($this, 'iamport_admin_page'), plugin_dir_url( __FILE__ ) . 'img/iamport.jpg' );
			add_submenu_page('edit.php?post_type=iamport_payment', '아임포트 설정', '아임포트 설정', 'administrator', 'iamport-config', array($this, 'iamport_admin_page'));
			add_submenu_page('edit.php?post_type=iamport_payment', '아임포트 숏코드', '아임포트 숏코드 예시', 'administrator', 'iamport-shortcode', array($this, 'iamport_shortcode_page'));
		}

		public function iamport_script_enqueue($hook) {
			wp_deregister_script( 'iamport-payment-sdk' );
			wp_register_script( 'iamport-payment-sdk', 'https://service.iamport.kr/js/iamport.payment.js', array( 'jquery', 'jquery-ui-dialog' ) );
			wp_enqueue_script( 'iamport-payment-sdk' );

			wp_enqueue_style('wp-jquery-ui-dialog');
			wp_enqueue_style('admin');
		}

		public function iamport_admin_page() {
			ob_start();
			?>
			<div class="wrap">
				<h2>아임포트 결제설정 페이지</h2>
				<p>
					<h3>아임포트 결제정보 설정</h3>
					<form method="post" action="">
						<table class="form-table">
							<tbody>
								<tr valign="top">
									<th scope="row" style="width:160px;"><label for="iamport_user_code">[아임포트] 가맹점 식별코드</label></th>
									<td>
										<input class="regular-text" name="iamport_user_code" type="text" id="iamport_user_code" value="<?=$this->user_code?>" /><br>
										<a target="_blank" href="https://admin.iamport.kr">https://admin.iamport.kr</a> 에서 회원가입 후, "시스템설정" > "내정보"에서 확인하실 수 있습니다.
									</td>
								</tr>
								<tr valign="top">
									<th scope="row" style="width:160px;"><label for="iamport_rest_key">[아임포트] REST API 키</label></th>
									<td>
										<input class="regular-text" name="iamport_rest_key" type="text" id="iamport_rest_key" value="<?=$this->api_key?>" /><br>
										<a target="_blank" href="https://admin.iamport.kr">https://admin.iamport.kr</a> 에서 회원가입 후, "시스템설정" > "내정보"에서 확인하실 수 있습니다.
									</td>
								</tr>
								<tr valign="top">
									<th scope="row" style="width:160px;"><label for="iamport_rest_secret">[아임포트] REST API Secret</label></th>
									<td>
										<input class="regular-text" name="iamport_rest_secret" type="text" id="iamport_rest_secret" value="<?=$this->api_secret?>" /><br>
										<a target="_blank" href="https://admin.iamport.kr">https://admin.iamport.kr</a> 에서 회원가입 후, "시스템설정" > "내정보"에서 확인하실 수 있습니다.
									</td>
								</tr>
							</tbody>
						</table>
						
						<?php wp_nonce_field('iamport-options', 'iamport-settings'); ?>
						<input type="hidden" name="action" value="update_iamport_settings" />
						<input class="button-primary" type="submit" name="iamport-options" value="저장하기" />
					</form>
				</p>
			</div>
			<?php 
			ob_end_flush();
		}

		public function iamport_shortcode_page() {
			ob_start();
			?>
			<style type="text/css">
			.shortcode-box {
				padding:10px;
				border: 1px solid #cfcfcf;
				font-size:1.2em;
			}
			</style>
			<div class="wrap">
				<h2>아임포트 숏코드 예시</h2>
				<h3>[iamport_payment_button] 숏코드를 추가하면, "아임포트 결제버튼"이 생성됩니다</h3>
				<p class="shortcode-box">
					[iamport_payment_button title="참가권 결제" description="아래 정보를 기입 후 결제진행해주세요." name="양평캠프 1박2일권" amount="1000" pay_method_list="card,trans,vbank,phone"] 1,000원 결제하기 [/iamport_payment_button]<br><br>
					<img src="<?=plugin_dir_url( __FILE__ )?>img/payment-button.png" style="width:140px"><br>
					'1,000원 결제하기' 문구는 텍스트 외에 이미지 또는 HTML이 사용될 수도 있습니다.
				</p>
				<h3>"아임포트 결제버튼"을 클릭하면 결제정보 입력 팝업이 나타납니다</h3>
				<p class="shortcode-box">
					사용자가 원하는 결제수단을 선택하고, 개인 정보를 입력한 후 "결제하기" 버튼을 누르면 PG사의 결제창이 호출되어 결제가 진행됩니다.<br><br>
					<img src="<?=plugin_dir_url( __FILE__ )?>img/payment-popup.png" style="width:300px">
				</p>
				<h3>지원되는 속성</h3>
				<p class="shortcode-box">
					<strong>1. title : </strong> 팝업 상단의 타이틀(위 캡쳐에서는 팝업 가장 위의 '결제하기' 문구)을 변경합니다. 기본값은 '결제하기'입니다.<br>
					<strong>2. description : </strong> 팝업 상단의 설명문구(위 캡쳐에서는 '아래의 정보를 입력 후 결제버튼을 클릭해주세요')를 변경합니다. 기본값은 '아래의 정보를 입력 후 결제버튼을 클릭해주세요'입니다.<br>
					<strong>3. pay_method_list : </strong> 사용자가 선택할 수 있는 결제수단의 종류를 나열합니다. card(신용카드), trans(실시간계좌이체), vbank(가상계좌), phone(휴대폰소액결제)를 의미하며 pay_method_list="card,vbank"와 같이 제공하려는 항목을 콤마(,)로 열거하면 됩니다.<br>
					<strong>4. field_list : </strong> 사용자가 입력해야하는 개인 정보의 종류를 나열합니다. email(이메일주소), name(이름), phone(전화번호)를 의미하여 field_list="name,email,phone"과 같이 항목을 콤마(,)로 열거하면 됩니다. 열거된 순서대로 화면에 표기됩니다<i>(단, PG사에 따라 3가지 정보가 반드시 입력되어야 결제가 진행되는 경우도 있습니다)</i><br>
					<strong>5. name : </strong> 해당 결제에 대한 주문명입니다. 예시) 1박2일 참가권 결제<br>
					<strong>6. amount : </strong> 결제받으실 금액<br>
					<strong>7. style : </strong> 숏코드가 생성하는 "결제하기"버튼에 적용될 HTML style속성을 지정합니다. 기본값은 'display:inline-block;padding:6px 12px;color:#fff;background-color:#2c3e50'입니다.<i>(단, 아래의 class속성이 적용되면, style속성은 무시되며, 기본값도 적용되지 않습니다.)</i><br>
					<strong>8. class : </strong> 숏코드가 생성하는 "결제하기"버튼에 적용될 css class속성을 지정합니다. class속성이 설정되면 결제버튼에 style속성은 적용되지 않습니다.
				</p>
			</div>
			<?php 
			ob_end_flush();
		}

		public function iamport_payment_columns($columns) {
			$columns['title'] 				= '주문명';
			$columns['order_status'] 		= '주문상태';
			$columns['order_paid_amount']	= '요청금액<br>결제금액';
			$columns['order_uid'] 			= '주문번호';
			$columns['pay_method'] 			= '결제수단';
			$columns['buyer_name'] 			= '결제자 이름';
			$columns['buyer_email'] 		= '결제자 Email';
			$columns['buyer_tel'] 			= '결제자 전화번호';
			$columns['paid_date'] 			= '결제시각';

			unset($columns['date']);

			return $columns;
		}

		public function iamport_payment_custom_columns( $column, $post_id ) {
			$iamport_order = IamportOrder::find_by_id($post_id);
			if ( $iamport_order == null )	return;

			switch ( $column ) {
				case 'order_status':
				echo $iamport_order->get_order_status();
				break;

				case 'order_uid':
				echo $iamport_order->get_order_uid();
				break;

				case 'pay_method':
				echo $iamport_order->get_pay_method();
				break;

				case 'order_paid_amount' :
				echo number_format($iamport_order->get_order_amount()) . ' 원<br><b>' . number_format($iamport_order->get_paid_amount()) . ' 원</b>';
				break;

				case 'buyer_name':
				echo $iamport_order->get_buyer_name();
				break;

				case 'buyer_email':
				echo $iamport_order->get_buyer_email();
				break;

				case 'buyer_tel':
				echo $iamport_order->get_buyer_tel();
				break;

				case 'paid_date':
				echo $iamport_order->get_paid_date();
				break;
			}
		}

		public function iamport_order_metabox() {
			remove_meta_box( 'submitdiv', 'iamport_payment', 'side' );

			add_meta_box( 'iamport-order-info', '아임포트 결제상세정보', array($this, 'iamport_order_metabox_callback'), 'iamport_payment', 'normal' );
			add_meta_box( 'iamport-order-action', '결제상태변경', array($this, 'iamport_order_action_metabox_callback'), 'iamport_payment', 'side', 'high' );
			add_meta_box( 'iamport-order-fail-history', '결제히스토리', array($this, 'iamport_order_history_metabox_callback'), 'iamport_payment', 'side', 'low');
		}

		public function iamport_order_metabox_callback($post) {
			$iamport_order = new IamportOrder($post);
			echo $this->internal_shortcode->get_order_view( $iamport_order->get_order_uid() );
		}

		public function iamport_order_action_metabox_callback($post) {
			wp_nonce_field( 'iamport_metabox_nonce', 'iamport_metabox_nonce' );

			$iamport_order = new IamportOrder($post);
			$order_status = $iamport_order->get_order_status(true);
			ob_start();
			?>
				<style type="text/css">
				#iamport-order-action .inside {padding: 0}
				</style>
				<div id="minor-publishing">
					<div class="misc-pub-section">
						<label for="iamport-order-status-meta">결제상태 변경 :</label>
						<select id="iamport-order-status-meta" name="new_iamport_order_status">
							<option value="ready" <?=$order_status=='ready'?'selected':''?>>미결제</option>
							<option value="paid" <?=$order_status=='paid'?'selected':''?>>결제완료</option>
							<option value="cancelled" <?=$order_status=='cancelled'?'selected':''?>>결제취소 및 환불처리</option>
						</select>
					</div>
				</div>
				
				<div id="major-publishing-actions">
					<div id="delete-action"><?php
                        if ( current_user_can( 'delete_post', $post->ID ) ) {

                            if ( !EMPTY_TRASH_DAYS ) {
                                    $delete_text = '삭제하기';
                            } else {
                                    $delete_text = '휴지통으로 이동';
                            }
                            ?><a class="submitdelete deletion" href="<?php echo esc_url( get_delete_post_link( $post->ID ) ); ?>"><?php echo $delete_text; ?></a><?php
                        }
                    ?></div>
                    <div id="publishing-action">
                    	<input type="submit" class="button save_order button-primary tips" name="save" value="변경하기" />
                    </div>
                    <div class="clear"></div>
				</div>
	            </li>
			</ul>
			
			<?php 
			$html = ob_get_clean();

			echo $html;
		}

		public function iamport_order_history_metabox_callback($post) {
			$iamport_order = new IamportOrder($post);
			$history = $iamport_order->get_failed_history();

			echo '<div id="minor-publishing">';
			foreach($history as $h) {
				echo '<div class="misc-pub-section">[' . $h['date'] . '] ' . $h['reason'] . '</div>';
			}
			echo '</div>';
		}

		public function save_iamport_order_metabox($post_id) {
			$iamport_order = IamportOrder::find_by_id($post_id);

			if ( empty($iamport_order) )															return;
			if ( !isset( $_POST['iamport_metabox_nonce'] ) )										return;
			if ( !wp_verify_nonce( $_POST['iamport_metabox_nonce'], 'iamport_metabox_nonce' ) )		return;
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )									return;

			if ( isset( $_POST['post_type'] ) && 'iamport_payment' == $_POST['post_type'] ) {
				if ( !current_user_can('administrator') )		return;
			} else {
				return;
			}

			if ( !isset($_POST['new_iamport_order_status']) )	return;

			$new_iamport_order_status = sanitize_text_field($_POST['new_iamport_order_status']);
			if ( $new_iamport_order_status == 'cancelled' ) {
				$iamport = new Iamport($this->api_key, $this->api_secret);
				$iamport_result = $iamport->cancel(array(
					'merchant_uid' => $iamport_order->get_order_uid(),
					'amount' => $iamport_order->get_paid_amount(),
					'reason' => '워드프레스 관리자 결제취소'
				));

				$iamport_order->add_failed_history(date('Y-m-d H:i:s'), $iamport_result->error['message']);

				if ( !$iamport_result->success )	return; //결제실패가 이루어지지 못했으므로 상태업데이트 해주면 안됨
			}

			$iamport_order->set_order_status($new_iamport_order_status);
		}

	}

	new IamportPaymentPlugin($user_code, $api_key, $api_secret);
}


//iamport 결제설정 정보 저장
if (isset($_POST['action']) && $_POST['action'] == "update_iamport_settings"){
	require_once(ABSPATH .'wp-includes/pluggable.php');
	if (wp_verify_nonce($_POST['iamport-settings'],'iamport-options')){
		update_option('iamport_user_code',$_POST['iamport_user_code']);
		update_option('iamport_rest_key',$_POST['iamport_rest_key']);
		update_option('iamport_rest_secret',$_POST['iamport_rest_secret']);
	}else{ ?>
		<div class="error">update failed</div><?php
	}
}