=== Plugin Name ===
Contributors: iamport
Donate link: http://www.iamport.kr
Tags: payment, payment-button, iamport, woocommerce, button, pg, gateway
Requires at least: 3.0.1
Tested up to: 4.4
Stable tag: 0.43
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

shortcode를 활용해 아임포트 결제버튼을 어디서든 생성. 신용카드/실시간이체/가상계좌/휴대폰소액결제 가능.

== Description ==

아임포트는 국내 PG서비스들을 표준화하고 있는 결제 서비스입니다. 아임포트 하나면 국내 여러 PG사들의 결제 기능을 표준화된 동일한 방식으로 사용할 수 있게 됩니다. <br>
이 플러그인은 아임포트 서비스를 어디서든 쉽게 이용할 수 있도록 "결제버튼"을 생성해주는 shortcode를 포함하고 있습니다.<br>
우커머스가 설치되어있지 않은 환경에서도 사용하실 수 있습니다.
신용카드 / 실시간계좌이체 / 가상계좌 / 휴대폰소액결제를 지원합니다. <br>
아임포트(https://admin.iamport.kr) 회원가입 후 이용하실 수 있습니다.

http://www.iamport.kr 에서 아임포트 서비스에 대한 보다 상세한 내용을 확인하실 수 있습니다.

데모 페이지 : http://demo.movingcart.kr

*   아임포트 관리자 페이지( https://admin.iamport.kr ) 에서 관리자 회원가입을 합니다.
*   아임포트 플러그인을 다운받아 워드프레스에 설치합니다.
*   아임포트 결제설정 페이지에서 "가맹점 식별코드", "REST API키", "REST API secret"을 플러그인 설정에 저장합니다.


== Installation ==

아임포트 플러그인 설치, https://admin.iamport.kr 에서 관리자 회원가입, 시스템설정 정보저장이 필요합니다.


1. 다운받은 iamport.zip파일을 `/wp-content/plugins/` 디렉토리에 복사합니다.
2. unzip iamport.zip으로 압축 파일을 해제하면 iamport폴더가 생성됩니다.
3. 워드프레스 관리자페이지에서 'Plugins'메뉴를 통해 "아임포트 결제버튼 생성 플러그인" 플러그인을 활성화합니다. 
4. https://admin.iamport.kr 에서 관리자 회원가입 후 시스템설정 페이지의 "가맹점 식별코드", "REST API키", "REST API secret"를 확인합니다.
5. 워드프레스 관리자페이지 좌측에 생성된 "아임포트 결제설정" 페이지에서 해당 정보를 저장합니다.

== Frequently Asked Questions ==
= 서비스 소개 =
http://www.iamport.kr
= 관리자 페이지 =
https://admin.iamport.kr
= 페이스북 =
https://www.facebook.com/iamportservice

= 고객센터 =
070-8658-8870 / iamport@siot.do

== Screenshots ==

1. 아임포트 관리자 로그인 후 "시스템 설정" 페이지에서 "가맹점 식별코드", "REST API키", "REST API secret" 정보를 확인합니다.
2. "아임포트 결제설정" 페이지에서 "가맹점 식별코드", "REST API키", "REST API secret" 정보를 저장합니다.
3. 관리자 페이지에서 결제 정보를 조회하고 관리하실 수도 있습니다.


== Changelog ==
= 0.43
* php short_open_tag 설정이 off인 경우에 오류가 발생하는 버그 수정

= 0.42 =
* require_once 절대경로로 변경

= 0.41 =
* 우커머스용 아임포트 플러그인과 동시에 설치되었을 때 lib/iamport.php 에서 class redeclare충돌나지 않도록 처리
* 스크린샷 추가

= 0.4 =
* 결제 시 사용자 정보를 입력받을 수 있도록 jQuery dialog적용 ( shortcode attribute를 통해 원하는 필드만 지정 가능 )
* 관리자 페이지에서 아임포트 결제내역을 조회할 수 있음(아임포트 결제내역에 대한 custom post type 정의 및 메타데이터 적용)
* 사용자가 자신의 결제내역을 확인할 수 있음(아임포트 결제 내역을 출력하는 shortcode적용)
* 아임포트 shortcode 예시 소개 포함
* 한 페이지에 여러 개의 payment button shortcode가 포함되어있을 때 initialized 오동작 버그 수정

= 0.32 =
* shortcode content영역에 img태그 등 html태그가 직접 사용될 수 있도록 strip_tags함수 제거
* 워드프레스 4.4와 호환되는지 확인함

= 0.31 =
* shortcode 가 html생성하는 방식이 잘못되어 수정
* 결제하기 버튼이 클릭되었을 때 IMP.init()호출하도록 변경

= 0.3 =
* 최초 배포
* http://demo.movingcart.kr 에 적용된 버전


== Arbitrary section ==


== A brief Markdown Example ==

