<?php

namespace Bizfly;

/**
 * BizPay Lib
 * Version 1.8
 * Cập nhật verify data
 */
class Billing
{
    static $_RSP_CODE_COMPLETED = 0;
    static $_RSP_CODE_NOT_COMPLETED = 99;
    static $_RSP_CODE_DUPLICATE_ORDER = 8;
    static $_RSP_CODE_NOT_EXISTED_ORDER = 7;
    static $_RSP_CODE_NULL_PAYGATE = 6;
    static $_RSP_CODE_INVALID_TOKEN = 4;
    static $_RSP_CODE_NULL_TOKEN = 9;
    static $_RSP_CODE_NULL_TRANSACTION_ID = 10;

    protected $_DOMAIN_URL = 'https://pay.bizfly.vn/';
    protected $_API_URL = 'https://pay.bizfly.vn/payment/pay';
    protected $_PROJECT_TOKEN = '';
    protected $_CLIENT_ID = '';
    protected $_MODE = 'production';

    public function __construct()
    {
        $this->_PROJECT_TOKEN = config('bizfly-billing.project_token');
        $this->_CLIENT_ID = config('bizfly-billing.client_id');
        if ($this->_MODE == 'sandbox') {
            $this->_DOMAIN_URL = 'https://pay.todo.vn/';
            $this->_API_URL = 'https://pay.todo.vn/payment/pay';
        }
    }

    public function setSandboxMode()
    {
        $this->_MODE = 'sandbox';
    }

    /**
     * Function buildUrlIframe() -> get url bizfly payment iframe
     * @param $orderInfo
     * Example: $orderInfo = [Z
     * (required)  "order_id" => "25151-24125415",
     * (required)  "order_value" => "50000",
     * (required)  "project_token" => "ypBrkdtM407veJuj1BVGKVmo7x8WsEL5",
     * (required)  "redirect_url" => "https://merchant.com/confirm",
     * (required)  "email" => "yoyo@gmail.com",
     * (optional)  "fullname" => "phương việt",
     * (optional)  "tel" => 0962305259
     * ]
     * @return object
     * Example: $object = [
     * 'success' => true,
     * 'message' => 'Get iframe url success!',
     * 'url' => ' https://pay.todo.vn/payment?params=9574%3A1571280962%3A4cf2ff8303e4cad0167213b75ab7a17c7d5e47c5d3099f63071b460a4398dd42%3Ahttp%25253A%25252F%25252Fagency.local%25252Fdemo%25252Forder%25252Fcallback%3A0%3A0%3Anguyenvietphuong2108%40gmail.com%3A60%3A0a33d8d5c390a901ae3493a888e0dabf&amp;client_pay_gate=2048&amp;voucher_code=&amp;tel=0&amp;iframe=1'
     * ]
     * @author by phương việt
     */
    public function buildUrlIframe($orderInfo)
    {
        $orderInfo = $this->_initOrder($orderInfo);
        if (isset($orderInfo['success']) && !$orderInfo['success']) {
            return json_encode([
                'success' => false,
                'message' => 'Payment failed',
                'data' => [
                    'redirect' => $orderInfo['redirect_url']
                ]
            ]);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_API_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderInfo));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
        ));
        $result = curl_exec($ch);

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_error($ch)) {
            return false;
        }
        if ($status != 200) {
            curl_close($ch);
            return false;
        }
        // close curl
        curl_close($ch);
        return $result;
    }

    /**
     * Init order info present
     * @param $orderInfo
     * @return array
     */
    protected function _initOrder($orderInfo)
    {
        if (!isset($orderInfo['order_id']) || !isset($orderInfo['order_value']) || !isset($orderInfo['redirect_url'])) {
            if (isset($orderInfo['redirect_url'])) {
                return [
                    'success' => false,
                    'message' => 'Param invalid',
                    'data' => [
                        'redirect' => $orderInfo['redirect_url']
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Param invalid',
                    'data' => []
                ];
            }
        }

        // checking project token
        if (empty($this->_PROJECT_TOKEN)) {
            return [
                'success' => false,
                'message' => 'Project token not found',
                'data' => []
            ];
        }

        $orderInfo['project_token'] = $this->_PROJECT_TOKEN;

        $orderInfo['secret_key'] = $this->_makeSecret($orderInfo);
        $orderInfo['redirect_url'] = urlencode($orderInfo['redirect_url']);

        return $orderInfo;
    }

    /**
     * make secret key function
     * @param $orderInfo array
     * @return string
     */
    private function _makeSecret($orderInfo)
    {
        return hash_hmac('SHA256', md5($orderInfo['order_id'] . $orderInfo['order_value'] . $orderInfo['project_token'] . (isset($orderInfo['recharge']) ? $orderInfo['recharge'] : 0)), md5($this->_PROJECT_TOKEN . '@vcpay'));
    }

    /**
     * Function buildUrlCheckout() --> create order information and redirect to bizfly billing index
     * @param $orderInfo
     * Example: $orderInfo = [
     * ------------------------------------------
     * Required for each order
     * (required)  "order_id" => "25151-24125415",
     * (required)  "order_value" => "50000",
     * (required)  "project_token" => "ypBrkdtM407veJuj1BVGKVmo7x8WsEL5",
     * (required)  "redirect_url" => "https://merchant.com/confirm",
     * (required)  "email" => "yoyo@gmail.com",
     * ------------------------------------------
     * Yes or no with every order
     * (optional)  "fullname" => "phương việt",
     * (optional)  "tel" => 0962305259,
     * ------------------------------------------
     * Get url payment gateway corresponding to the value in the field payment_method
     * (optional)  "ignore" => 'index' (Add this field if you want to get the link at the payment gateway corresponding to the value in the field payment_method)
     * (optional)  "payment_method" => 'wepay', (wepay/wepay_visa/truemoney/vimomo/vnpay/viettelpay)
     * ]
     * @return object
     * Example: $object = [
     * "success": true
     * "message": "Get payment url success!"
     * "data": {
     * "redirect": "http://wallet.local/payment/pay?order_id=1542433-1571366039&order_value=55000&redirect_url=http%253A%252F%252Fagency.local%252Fdemo%252Forder%252Fcallback&recha..."
     * }
     * ]
     * @author by phương việt
     */
    public function buildUrlCheckout($orderInfo)
    {
        $orderInfo = $this->_initOrder($orderInfo);

        if (isset($orderInfo['success']) && !$orderInfo['success']) {
            return json_encode([
                'success' => false,
                'message' => 'Payment failed',
                'data' => [
                    'redirect' => $orderInfo['redirect_url']
                ]
            ]);
        }

        $queryString = http_build_query($orderInfo);
        if (!empty($queryString)) {
            return json_encode([
                'success' => true,
                'message' => 'Get payment url success!',
                'data' => [
                    'redirect' => $this->_API_URL . "?" . $queryString
                ]
            ]);
        } else {
            return json_encode([
                'success' => false,
                'message' => 'Error building order.',
                'data' => [
                    'redirect' => $orderInfo['redirect_url']
                ]
            ]);
        }
    }

    /**
     * Verify data callback
     * @param $message
     * @return bool
     */
    public function verifyUrlCallback(&$message = '')
    {
        if (isset($_REQUEST['error']) && isset($_REQUEST['message'])) {
            $message = $_REQUEST['message'];
            return false;
        }
        if (!isset($_REQUEST['order_id']) || !isset($_REQUEST['created_order_date']) || !isset($_REQUEST['secure_hash'])) {
            $message = 'Thông tin đơn hàng trả về không hợp lệ';
            return false;
        }
        if ($this->_generalKeyOrder(
                $_REQUEST['order_id'],
                $_REQUEST['created_order_date'],
                $_REQUEST['total_payment'],
                $_REQUEST['order_id_client'],
                $_REQUEST['vid'],
                $_REQUEST['recharge']
            ) === $_REQUEST['secure_hash']) {
            if (isset($_REQUEST['recharge']) && $_REQUEST['recharge']) {
                $message = 'Tài khoản VietID: ' . $_REQUEST['vid'] . ' đã được cộng tiền.';
            } else {
                $message = 'Thông tin thanh toán được chấp nhận.';
            }
            $orderInfo = $this->getInfoOrder($_REQUEST['order_id_client']);

            if (!$this->safetyInfomation($_REQUEST, (array)$orderInfo, $message)) {
                return false;
            }
            if (!$this->verifyExtraData($_REQUEST, (array)$orderInfo, $message)) {
                return false;
            }
            if (!$this->verifySpecialData($message)) {
                return false;
            }
            return true;
        }

        $message = 'Thông tin đơn hàng lỗi';
        return false;
    }

    /**
     * Verify Special Data
     * @param $REQUEST
     * @param $orderInfo
     * @param string $message
     * @return bool
     */
    public function verifySpecialData(&$message = '')
    {
        if (!isset($_REQUEST['specialData']) || empty($_REQUEST['specialData'])) {
            $message = 'Thiếu thông tin special data để thực hiện verify';
            return false;
        }
        $secureHashSpecial = json_decode($_REQUEST['specialData'])->secure_hash_special;
        $inputData = array();

        foreach (json_decode($_REQUEST['specialData']) as $key => $value) {
            $inputData[$key] = $value;
        }
        unset($inputData['secure_hash_special']);

        ksort($inputData);
        $i = 0;
        $stringData = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $stringData = $stringData . '&' . $key . "=" . $value;
            } else {
                $stringData = $stringData . $key . "=" . $value;
                $i = 1;
            }
        }
        $secureHash = hash_hmac('sha256', md5($stringData), $this->_PROJECT_TOKEN);

        if ($secureHashSpecial === $secureHash) {
            return true;
        } else {
            $message = 'Thông tin special data không đáng tin cậy';
            return false;
        }
    }

    /**
     * Verify Extra Data
     * @param $REQUEST
     * @param $orderInfo
     * @param string $message
     * @return bool
     */
    public function verifyExtraData($REQUEST, $orderInfo, &$message = '')
    {
        if (!isset($_REQUEST['extraData']) || empty($_REQUEST['extraData'])) {
            $message = 'Thiếu thông tin extra data để thực hiện verify';
            return false;
        }
        $REQUEST['extraData'] = json_decode($REQUEST['extraData']);
        if ((string)hash_hmac('SHA256', md5(
                json_encode(($REQUEST['extraData'])->coupons_discount) .
                json_encode(($REQUEST['extraData'])->mybizfly_discount) .
                $REQUEST['extraData']->payment_gate_discount .
                $REQUEST['extraData']->payment_gate_fees
            ), md5($REQUEST['total_payment'] . '@#!$o9iEC29LjDvB1WI')) !== $REQUEST['extraData']->secure_hash_extra) {
            $message = 'Dữ liệu extra data không đáng tin cậy';
            return false;
        }
        $validExtraData = array_diff_assoc([
            'coupons_discount' => json_encode($orderInfo['order_info']->extraData->coupons_discount),
            'mybizfly_discount' => json_encode($orderInfo['order_info']->extraData->mybizfly_discount),
            'payment_gate_discount' => $orderInfo['order_info']->extraData->payment_gate_discount,
            'payment_gate_fees' => $orderInfo['order_info']->extraData->payment_gate_fees
        ], [
            'coupons_discount' => json_encode($REQUEST['extraData']->coupons_discount),
            'mybizfly_discount' => json_encode($REQUEST['extraData']->mybizfly_discount),
            'payment_gate_discount' => $REQUEST['extraData']->payment_gate_discount,
            'payment_gate_fees' => $REQUEST['extraData']->payment_gate_fees
        ]);

        if (count($validExtraData) > 0) {
            $message = 'Dữ liệu extra data không đáng tin cậy';
            return false;
        } else {
            return true;
        }
    }

    /**
     * verify url callback ignore index
     * @param $message
     * @return bool
     */
    public function verifyUrlPaymentGate(&$message = '')
    {
        if (!isset($_GET['RspCode']) || !isset($_GET['hashKey']) || !isset($_GET['link'])) {
            $message = 'Thông tin đơn hàng trả về không hợp lệ !';
            return false;
        }
        if (md5($_GET['link'] . $this->_PROJECT_TOKEN) == $_GET['hashKey']) {
            $message = 'Lấy link cổng thanh toán thành công!';
            return true;
        } else {
            $message = 'Lấy link cổng thanh toán thất bại';
            return true;
        }
    }

    /**
     * check safety information
     * @param $message
     * @return bool
     */
    public function safetyInfomation($REQUEST, $orderInfo, &$message = '')
    {
        if ($orderInfo['RspCode'] === 0) {
            $check = array_diff_assoc([
                'paygate' => $orderInfo['order_info']->paygate,
                'total_payment' => $orderInfo['order_info']->total_payment,
                'status' => $orderInfo['order_info']->status,
                'order_id' => $orderInfo['order_info']->order_id,
                'order_id_client' => $orderInfo['order_info']->order_id_client,
                'vid' => $orderInfo['order_info']->vid,
                'recharge' => $orderInfo['order_info']->recharge,
                'created_order_date' => $orderInfo['order_info']->created_order_date
            ], [
                'paygate' => $REQUEST['gate'],
                'total_payment' => $REQUEST['total_payment'],
                'status' => $REQUEST['status'],
                'order_id' => $REQUEST['order_id'],
                'order_id_client' => $REQUEST['order_id_client'],
                'vid' => $REQUEST['vid'],
                'recharge' => $REQUEST['recharge'],
                'created_order_date' => $REQUEST['created_order_date']
            ]);

            if (count($check) > 0) {
                $message = 'Cảnh báo! Thông tin có sự thay đổi bất thường';
                return false;
            } else {
                return true;
            }
        } else {
            $message = 'Đơn hàng thanh toán thất bại';
            return false;
        }
    }

    /**
     * @param $orderId
     * @param $created_order_date
     * @param $totalPayment
     * @param $order_id_client
     * @param $vid
     * @param $total_payment_discount
     * @param $recharge
     * @return string
     */
    protected function _generalKeyOrder($orderId, $created_order_date, $totalPayment, $order_id_client, $vid, $recharge)
    {
        // new algorithm
        return hash_hmac('SHA256', md5(
            $orderId .
            $created_order_date .
            $totalPayment .
            $order_id_client .
            $vid .
            $recharge
        ), md5($totalPayment . '@paybizfly'));
    }

    /**
     * Get transaction information
     * @param $order_client_id
     * @return object
     */
    public function getInfoOrder($order_client_id)
    {
        $time = time();
        $auth = md5($order_client_id . $time . $this->_PROJECT_TOKEN);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_DOMAIN_URL . 'api/order/info');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('order_client_id' => $order_client_id)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            "Auth: $auth",
            "Time: $time"
        ));
        $result = curl_exec($ch);

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_error($ch)) {
            return false;
        }
        if ($status != 200) {
            curl_close($ch);
            return false;
        }
        // close curl
        curl_close($ch);
        return json_decode($result);
    }

    /**
     * verify url callback
     * @param $message
     * @return bool
     */
    public function verifyPostUrlCallback(&$message = '')
    {
        if (isset($_POST['error']) && isset($_POST['message'])) {
            $message = $_POST['message'];
            return false;
        }

        if (!isset($_POST['order_id']) || !isset($_POST['created_order_date']) || !isset($_POST['secure_hash'])) {
            $message = 'Thông tin đơn hàng trả về không hợp lệ';
            return false;
        }

        if ($this->_generalKeyOrder(
                $_POST['order_id'],
                $_POST['created_order_date'],
                $_POST['total_payment'],
                $_POST['order_id_client'],
                $_POST['vid'],
                $_POST['recharge']
            ) === $_POST['secure_hash']) {
            if (isset($_POST['recharge']) && $_POST['recharge']) {
                $message = 'Tài khoản VietID: ' . $_POST['vid'] . ' đã được cộng tiền.';
            } else {
                $message = 'Thông tin thanh toán được chấp nhận.';
            }

            return true;
        }

        $message = 'Thông tin đơn hàng lỗi';
        return false;
    }

    /**
     * Recheck order
     * @param $orderInfo
     * @return array
     */
    public function checkOrder($order_client_id)
    {
        $time = time();
        $auth = md5($order_client_id . $time . $this->_PROJECT_TOKEN);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_DOMAIN_URL . 'api/order/check');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('order_id_client' => $order_client_id)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            "Auth: $auth",
            "Time: $time"
        ));
        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_error($ch)) {
            return false;
        }
        if ($status != 200) {
            curl_close($ch);
            return false;
        }
        // close curl
        curl_close($ch);
        return $result;
    }

    /**
     * Function getListOrder() -> get list order with client
     * @param $start_date , $end_date, $current_pointer, $limit, $sort
     * Example:
     * (optional)  $start_date = '2020-10-07 10:00:00' (YYYY-MM-DD),
     * (optional)  $end_date = '2020-10-13 10:00:00' (YYYY-MM-DD),
     * (optional)  $current_pointer = 0,
     * (optional)  $limit = 100,
     * (optional)  $sort = 'asc' (asc/des),
     * @return object
     * Example: $object = [
     * "status" => true
     * "msg" => "Lấy dữ liệu thành công !"
     * "list_order" => array:1 [▼
     * 0 => array:10 [▼
     * "order_id" => 13132
     * "order_id_client" => "Event-1578367492-2703"
     * "order_payment_id" => "AT-478816-1578367506"
     * "paygate_id" => 1
     * "total_payment" => 80000
     * "total_payment_discount" => 0
     * "fees" => 0
     * "order_status" => 13
     * "created_order_date" => "2020-01-07 10:25:00"
     * "updated_order_date" => "2020-01-07 10:25:27"
     * ]
     * ]
     * "next" => "https://pay.todo.vn/api/order/get-list-order?client_id=106&start_date=2020-10-07 10:00:00&end_date=2020-10-13 10:00:00&limit=100&sort=asc&current_pointer=13132&access_key=a35319c00607bdf898013ba2853b2966&access_time=1602036798&type=next&create_time=1602036799"
     * "previous" => "https://pay.todo.vn/api/order/get-list-order?client_id=106&start_date=2020-10-07 10:00:00&end_date=2020-10-13 10:00:00&limit=100&sort=asc&current_pointer=13132&access_key=aff58b43b48c554178fd76ed3ac9591c&access_time=1602036798&type=prev&create_time=1602036799"
     * ]
     * @author by phương việt
     */
    public function getListOrder($start_date = '', $end_date = '', $current_pointer = 0, $limit = 100, $sort = 'asc', $type = '')
    {
        $time = time();
        $auth = md5($this->_CLIENT_ID . $current_pointer . $end_date . $limit . $sort . $start_date . $time . $this->_PROJECT_TOKEN . $type);
        $params = [];
        $params['client_id'] = $this->_CLIENT_ID;
        $params['current_pointer'] = $current_pointer;
        $params['start_date'] = $start_date;
        $params['end_date'] = $end_date;
        $params['limit'] = $limit;
        $params['sort'] = $sort;
        $params['type'] = $type;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_DOMAIN_URL . 'api/order/get-list-order');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            "Auth: $auth",
            "Time: $time"
        ));
        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_error($ch)) {
            return false;
        }
        if ($status != 200) {
            curl_close($ch);
            return false;
        }
        // close curl
        curl_close($ch);
        return json_decode($result, 1);
    }
}
