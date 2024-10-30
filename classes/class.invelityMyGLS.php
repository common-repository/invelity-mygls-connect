<?php

use setasign\Fpdi\Rotate;


require_once(__DIR__ . '/../lib/FPDF/fpdf.php');
require_once(__DIR__ . '/../lib/FPDI/src/autoload.php');

class MyGLS
{

    private $order;
    private $options;

    private $url;


    public function __construct($options)
    {

        $this->options = $options;
        $this->set_url();
    }

    private function set_url()
    {

        $this->url = 'https://api.mygls.' . $this->options['country_version'] . '/ParcelService.svc/json/';
    }


    public function print_label($options)
    {

        $optionsJson = json_encode($options);
        $labelCount = count($options);
        $password = "[" . implode(',', unpack('C*', hash('sha512', $this->options['password'], true))) . "]";
        $request = '{"Username":"' . $this->options['email'] . '","Password":' . $password . ',"ParcelList":' . $optionsJson . '}';

        $response = $this->get_response($this->url, 'PrintLabels', $request);


        if (is_wp_error($response)) {
            $status = [
                'success' => false,
                'errors' => [
                    [
                        'order_id' => 'global',
                        'message' => $response->get_error_message(),
                    ],
                ],
            ];
            error_log('MyGLS error: ' . $response->get_error_message());
            return $status;
        }


        $responseArr = json_decode($response);

        if (!$responseArr) {
            return [
                'success' => false,
                'errors' => [
                    [
                        'order_id' => 'global',
                        'message' => 'Check your settings',
                    ],
                ],
            ];

        }

        $errors = [];

        if (isset($responseArr->PrintLabelsErrorList) && count($responseArr->PrintLabelsErrorList)) {
            foreach ($responseArr->PrintLabelsErrorList as $error) {
                $ids = $error->ClientReferenceList;
                $ids = array_map(function ($id) {

                    return str_replace($this->options['clientref'] . ' - ', '', $id);
                }, $ids);

                $errorDescription = $error->ErrorDescription;
                if ($error->ErrorCode == 13) {
                    $errorDescription = $error->ErrorDescription . '. Check if customer info are correctly formatted';
                }

                $errors[] = [
                    'order_id' => implode(', ', $ids),
                    'message' => $errorDescription,
                ];
            }
        }

        if ($response && isset($responseArr->PrintLabelsErrorList) && json_decode($response)->Labels != null) {
            $pdfFile = implode(array_map('chr', json_decode($response)->Labels));
            $ids = [];
            foreach ($responseArr->PrintLabelsInfoList as $label) {
                $parcel_number = $label->ParcelNumber;
                $order_number = $label->ClientReference;
                if ($this->options['clientref'] != '') {
                    $order_number = str_replace($this->options['clientref'] . ' - ', '', $order_number);
                }

                $order = wc_get_order($order_number);
                $ids[] = $order_number;
                if ($order) {
                    $order->update_meta_data('invelity_gls_parcel_number', $parcel_number);
                    $order->save();
                }


            }

            $file_root = __DIR__ . '/../labels/';
            $fileName = 'labels-' . time() . '.pdf';
            $destination = $file_root . $fileName;

            $status = [
                'success' => true,
                'url' => $fileName,
                'order_id' => implode(', ', $ids),
            ];

            header('Content-Type: application/pdf');
            header("Content-Transfer-Encoding: Binary");
            file_put_contents($file_root . $fileName, $pdfFile);


            $pdf = new Rotate();
            $pageCount = $pdf->setSourceFile($destination);

            $myText = 'Programovanie prepojenia od invelity.com';
            $myFont = 'Helvetica';

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tplIdx = $pdf->importPage($pageNo);
                $pdf->AddPage();
                $pdf->useTemplate($tplIdx, null, null, 297, 210, true);
                $pdf->SetFont($myFont, 'I', 9);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->RotatedText(146, 85, $myText, 90);

                if ($labelCount == 1) {
                    $pdf->RotatedText(146, 85, $myText, 90);
                } else if ($labelCount == 2) {
                    $pdf->RotatedText(146, 85, $myText, 90);
                    $pdf->RotatedText(287, 85, $myText, 90);
                } else if ($labelCount == 3) {
                    $pdf->RotatedText(146, 85, $myText, 90);
                    $pdf->RotatedText(287, 85, $myText, 90);
                    $pdf->RotatedText(146, 190, $myText, 90);
                } else if ($labelCount >= 4) {
                    $pdf->RotatedText(146, 85, $myText, 90);
                    $pdf->RotatedText(287, 85, $myText, 90);
                    $pdf->RotatedText(146, 190, $myText, 90);
                    $pdf->RotatedText(287, 190, $myText, 90);
                }
            }

            $pdf->Output($destination, 'F');
            return $status;

        } else {
            if (count($errors) == 0) {
                $errors = [
                    'order_id' => $this->order->get_id(),
                    'message' => 'Service is not available.Try again later',
                ];
            }
            return [
                'success' => false,
                'errors' => $errors,
            ];

        }
    }

    private function get_response($url, $method, $request)
    {

        $response = wp_remote_post($url . $method, [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => $request,
            'method' => 'POST',
            'data_format' => 'body',
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        return $response['body'];
    }

    public function set_parcel_options($order): array
    {

        //date_default_timezone_set("Europe/Budapest");
        //get all parcel lists\
        // https://api.test.mygls.cz/ParcelService.svc/xml/GetParcelList_20190201

        $pickupDate = date('Y-m-d', strtotime('+1 day'));
        while ($this->is_sviatok($pickupDate)) {
            $pickupDate = date('Y-m-d', strtotime('+1 day', strtotime($pickupDate)));
        }

        $weekDay = date('w', strtotime($pickupDate));

        if (($weekDay == 5)) //Check if the day is saturday or not.
        {
            $pickupDate = date('Y-m-d', strtotime('+2 day', strtotime($pickupDate)));
        } else if ($weekDay == 6) {
            $pickupDate = date('Y-m-d', strtotime('+1 day', strtotime($pickupDate)));
        }

        $pickupDate = "/Date(" . (strtotime($pickupDate) * 1000) . ")/";

        $paymentMethod = $order->get_payment_method();
        $total = $order->get_total();

        $this->order = $order;

        $parcelOptions = [
            'OrderID' => $this->filter_order_id($order),
            'ClientNumber' => intval($this->options['sender_id']),
            'ClientReference' => implode(' - ',
                array_filter([$this->options['clientref'], $this->filter_order_id($order)])),
            //shop name & order ID,
            'CODAmount' => $this->is_cod($paymentMethod) ? $total : 0,
            'CODReference' => $this->filter_order_id($order),
            'Content' => $this->filter_custom_note($order),
            'Count' => 1,
            'DeliveryAddress' => [
                'City' => $this->filter_shipping_city($order),
                'ContactEmail' => $this->filter_email($order),
                'ContactName' => $this->filter_name($order),
                'ContactPhone' => $this->filter_phone($order),
                'CountryIsoCode' => $this->filter_shipping_country($order),
                'Name' => $this->filter_name($order),
                'Street' => $this->filter_shipping_address($order),
                "ZipCode" => $this->filter_shipping_postcode($order),
            ],
            'PickupAddress' => [
                'City' => $this->options['sender_city'],
                'ContactEmail' => $this->options['sender_contact_email'],
                'ContactName' => $this->options['sender_contact_name'],
                'ContactPhone' => $this->options['sender_phone'],
                'CountryIsoCode' => strtoupper($this->options['country_version']),
                'Name' => $this->options['sender_name'],
                'Street' => $this->options['sender_address'],
                'ZipCode' => $this->options['sender_zip'],
            ],
            'PickupDate' => $pickupDate,
            'ServiceList' => [],
        ];
        //this not working Invalid service parameter, Service 'PSD'. Check if customer info are correctly formatted return error

        if ($this->get_shipping_method($order) == 'inv_gls_parcel_shop') {
            $parcelOptions['ServiceList'][] = [
                'Code' => "PSD",
                'PSDParameter' => [
                    'StringValue' => $this->get_psd_service($order),
                ],
            ];
        } else {
            if (isset($this->options['fds']) && $this->options['fds'] == 'on') {
                $parcelOptions['ServiceList'][] = [
                    'Code' => 'FDS',
                    'FDSParameter' => [
                        'Value' => $this->filter_email($order),
                    ],
                ];
            }
            if (isset($this->options['fss']) && $this->options['fss'] == 'on') {
                $parcelOptions['ServiceList'][] = [
                    'Code' => 'FSS',
                    'FSSParameter' => [
                        'Value' => $this->filter_phone($order),
                    ],
                ];
            }
            if (isset($this->options['sm2']) && $this->options['sm2'] == 'on') {
                $parcelOptions['ServiceList'][] = [
                    'Code' => 'SM2',
                    'SM2Parameter' => [
                        'StringValue' => $this->filter_phone($order),
                    ],
                ];
            }
        }


        return $parcelOptions;
    }

    private function is_sviatok($date): bool
    {

        $year = apply_filters('InvelityMyGLSConnectProcessis_sviatokYearFilter', date('Y'));
        $thisyear = $this->get_easter($year);
        $nextyear = $this->get_easter($year + 1); //generates next year for delivering after December in actual year
        $sviatky = [];
        $sviatky = array_merge($thisyear, $nextyear);
        $sviatky[] = '2018-10-30';
        $sviatky = apply_filters('InvelityMyGLSConnectProcessis_sviatokFilter', $sviatky);
        if (in_array($date, $sviatky)) {
            return true;
        }

        return false;
    }

    private function get_easter($year): array
    { //Generates holidays. Default: Slovakia
        $sviatky = [];
        $s = [
            '01-01',
            '01-06',
            '',
            '',
            '05-01',
            '05-08',
            '07-05',
            '08-29',
            '09-01',
            '09-15',
            '11-01',
            '11-17',
            '12-24',
            '12-25',
            '12-26',
        ];
        $easter = date('m-d', easter_date($year));
        $sdate = strtotime($year . '-' . $easter);
        $s[2] = date('m-d', strtotime('-2 days', $sdate)); //Firday
        $s[3] = date('m-d', strtotime('+1 day', $sdate)); //Monday
        foreach ($s as $day) {
            $sviatky[] = $year . '-' . $day;
        }

        return $sviatky;
    }

    private function filter_order_id($order)
    {

        return $order->get_id();
    }

    public function is_cod($payment_method): bool
    {

        $cod = ['dobierka', 'dobirka', 'dobírka', 'cash on delivery', 'cod'];

        if (in_array(strtolower($payment_method), $cod)) {
            return true;
        }

        return false;
    }

    private function filter_custom_note($order)
    {

        $type = $this->options['custom_note'];

        if (!$type) {
            return $order->get_id();
        }
        switch ($type) {
            case    'order_number':
                return $order->get_order_number();
            case    'wc_note':
                return $order->get_customer_note();
            default:
                $function = 'get_' . $type;
                $function = str_replace('%', '', $function);
                if (method_exists($order, $function)) {
                    return $order->$function();
                } else {
                    return '';
                }
        }
    }

    private function filter_shipping_city($order)
    {

        if (!defined('WC_VERSION')) {
            return false;
        }

        return $order->get_shipping_city();
    }

    private function filter_email($order)
    {

        if (!defined('WC_VERSION')) {
            return false;
        }

        return $order->get_billing_email();
    }

    private function filter_name($order)
    {


        return $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
    }

    private function filter_phone($order)
    {


        return $order->get_billing_phone();
    }

    private function filter_shipping_country($order)
    {

        return $order->get_shipping_country();
    }

    private function filter_shipping_address($order)
    {

        $shipping_address = $order->get_shipping_address_1();
        if ($order->get_shipping_address_2() && !empty($order->get_shipping_address_2())) {
            $shipping_address .= ' ' . $order->get_shipping_address_2();
        }

        return $shipping_address;
    }

    private function filter_shipping_postcode($order)
    {


        return $order->get_shipping_postcode();
    }

    private function get_shipping_method($order)
    {

        $shippingMethods = $order->get_shipping_methods();
        foreach ($shippingMethods as $shippingMethod) {
            return $shippingMethod->get_method_id();
        }

        return false;
    }

    private function get_psd_service($order)
    {

        return $order->get_meta('inv_gls_picked_shop_id', true) ?? '';

    }


}
