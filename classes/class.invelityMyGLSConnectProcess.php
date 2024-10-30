<?php

class InvelityMyGLSConnectProcess
{

    public $successful = [];
    public $unsuccessful = [];
    private $launcher;
    private $options;

    /**
     * Loads plugin textdomain and sets the options attribute from database
     */
    public function __construct(InvelityMyGLSConnect $launecher)
    {

        $this->launcher = $launecher;
        load_plugin_textdomain($this->launcher->getPluginSlug(), false,
            dirname(plugin_basename(__FILE__)) . '/lang/');
        $this->options = get_option('invelity_my_gls_export_options');
        add_action('admin_footer-edit.php', [$this, 'custom_bulk_admin_footer']);
        add_action('load-edit.php', [$this, 'custom_bulk_action']);
        add_action('admin_notices', [$this, 'custom_bulk_admin_notices']);
        add_action('invelity_my_gls_send_tracking_email_to_customer', [$this, 'send_tracking_email_to_customer']);

    }

    /**
     * Adds option to export invoices to orders page bulk select
     */
    function custom_bulk_admin_footer()
    {

        global $post_type;

        if ($post_type == 'shop_order') {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function () {
                    jQuery('<option>').val('my_gls').text('<?php _e('Export MyGLS')?>').appendTo("select[name='action']");
                    jQuery('<option>').val('my_gls').text('<?php _e('Export MyGLS')?>').appendTo("select[name='action2']");
                });
            </script>
            <?php
        }
    }

    /**
     * Sets up action to be taken after export option is selected
     * If export is selected, provides export and refreshes page
     * After refresh, notices are shown
     */
    function custom_bulk_action()
    {

        global $typenow;
        $post_type = $typenow;

        if ($post_type == 'shop_order') {
            $wp_list_table = _get_list_table('WP_Posts_List_Table');  // depending on your resource type this could be WP_Users_List_Table, WP_Comments_List_Table, etc
            $action = $wp_list_table->current_action();

            $allowed_actions = ["my_gls"];
            if (!in_array($action, $allowed_actions)) {
                return;
            }

            // security check
            check_admin_referer('bulk-posts');

            // make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'

            if (isset($_REQUEST['post'])) {
                $post_ids = array_map('intval', $_REQUEST['post']);
            }

            if (empty($post_ids)) {
                return;
            }

            // this is based on wp-admin/edit.php
            $sendback = remove_query_arg(['exported', 'untrashed', 'deleted', 'ids'], wp_get_referer());
            if (!$sendback) {
                $sendback = admin_url("edit.php?post_type=$post_type");
            }

            $pagenum = $wp_list_table->get_pagenum();
            $sendback = add_query_arg('paged', $pagenum, $sendback);

            switch ($action) {
                case 'my_gls':
                    $options = [];
                    $myGLS = new MyGLS($this->options);
                    foreach ($post_ids as $postId) {
                        global $woocommerce;
                        $order = new WC_Order($postId);
                        if ($order->has_shipping_method('local_pickup')) {
                            $this->unsuccessful[] = [
                                'order_id' => $postId,
                                'message' => __('Order has local pickup shipping method',
                                    $this->launcher->getPluginSlug()),
                            ];
                            continue;
                        }


                        $options[] = $myGLS->set_parcel_options($order);
                    }

                    $response = $myGLS->print_label($options);


                    if (!$response['success']) {
                        $this->unsuccessful[] = $response['errors'];
                    } else {
                        $this->successful = [
                            'order_id' => $response['order_id'],
                            'url' => $response['url'],
                        ];

                        if ($this->options['allow_tracking_email'] == 'on') {
                            $processed_orders = explode(',',$response['order_id']);
                            foreach ($processed_orders as $order_id) {
                                $order = new WC_Order($order_id);
                                do_action('invelity_my_gls_send_tracking_email_to_customer', $order);
                            }
                        }
                    }

                    $sendback = remove_query_arg(['exported', 'untrashed', 'deleted', 'ids'], wp_get_referer());
                    if (!$sendback) {
                        $sendback = admin_url("edit.php?post_type=$post_type");
                    }
                    $pagenum = $wp_list_table->get_pagenum();
                    $sendback = add_query_arg('paged', $pagenum, $sendback);

                    $serializedSuccessfulData = serialize($this->successful);
                    $successful = urlencode($serializedSuccessfulData);
                    $serializedUnsuccessfulData = serialize($this->unsuccessful);
                    $unsuccessful = urlencode($serializedUnsuccessfulData);


                    $sendback = add_query_arg([
                        'my-gls-successful' => $successful,
                        'my-gls-unsuccessful' => $unsuccessful,
                    ], $sendback);
                    $sendback = remove_query_arg([
                        'action',
                        'action2',
                        'tags_input',
                        'post_author',
                        'comment_status',
                        'ping_status',
                        '_status',
                        'post',
                        'bulk_edit',
                        'post_view',
                    ], $sendback);
                    wp_redirect($sendback);
                    die();
                default:
                    return;
            }
        }
    }


    public function send_tracking_email_to_customer($order)
    {

        //send email to customer with body and subject
        $tracking_number = get_post_meta($order->get_id(), 'invelity_gls_parcel_number', true);
        $options = $this->options;
        $country = $options['country_version'];
        $url = 'https://gls-group.eu/' . strtoupper($country) . '/' . strtolower(
                $country
            ) . '/sledovanie-zasielok?match=' . $tracking_number;
        $email = $order->get_billing_email();
        $subject = $options['tracking_email_subject_settings'] != '' ? $options['tracking_email_subject_settings'] : __('Vaša objednávka bola odoslaná', 'invelity-mygls-connect');
        //get woocommerce email header  and footer
        $email_header = wc_get_template_html('emails/email-header.php', ['email_heading' => $subject]);
        $body = $options['tracking_email_text_settings'] != '' ? $options['tracking_email_text_settings'] : 'Dobrý deň {name}. Vaša objednávka {order_number} bola odoslaná. Môžete ju sledovať  kliknutím na tento odkaz: {tracking_link}';
        $email_footer = wc_get_template_html('emails/email-footer.php');
        $email_body = $email_header . $body . $email_footer;

        $email_body = str_replace('{tracking_number}', $tracking_number, $email_body);
        $email_body = str_replace('{tracking_url}', $url, $email_body);
        $email_body = str_replace('{order_number}', $order->get_order_number(), $email_body);
        $email_body = str_replace('{name}', $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(), $email_body);
        $email_body = str_replace('{tracking_link}', '<a href="' . $url . '">' . $tracking_number . '</a>', $email_body);
        $email_body = str_replace('{site_title}', get_bloginfo('name'), $email_body);
        $email_body = str_replace('{site_url}', get_bloginfo('url'), $email_body);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        try {
            wp_mail($email, $subject, wpautop($email_body), $headers);
        } catch (Exception $e) {
            echo 'Caught exception: ', $e->getMessage(), "\n";
        }
    }

    function getHash($data)
    {

        $hashBase = '';
        foreach ($data as $key => $value) {
            if ($key != 'services'
                && $key != 'hash'
                && $key != 'timestamp'
                && $key != 'printit'
                && $key != 'printertemplate'
                && $key != 'customlabel'
            ) {
                $hashBase .= $value;
            }
        }

        return sha1($hashBase);
    }

    function isSviatok($date)
    {

        $year = apply_filters('InvelityMyGLSConnectProcessIsSviatokYearFilter', date('Y'));
        $thisyear = $this->getEaster($year);
        $nextyear = $this->getEaster($year + 1); //generates next year for delivering after December in actual year
        $sviatky = array_merge($thisyear, $nextyear);
        $sviatky[] = '2018-10-30';
        $sviatky = apply_filters('InvelityMyGLSConnectProcessIsSviatokFilter', $sviatky);
        if (in_array($date, $sviatky)) {
            return true;
        }

        return false;
    }

    function getEaster($year)
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

    /**
     * Displays the notice
     */
    function custom_bulk_admin_notices()
    {

        global $post_type, $pagenow;

        if ($pagenow == 'edit.php' && $post_type == 'shop_order' && (isset($_REQUEST['my-gls-successful']) || isset($_REQUEST['my-gls-unsuccessful']))) {


            $data_succesful = str_replace('\\"', '"', $_REQUEST['my-gls-successful']);
            $data_unsuccesful = str_replace('\\"', '"', $_REQUEST['my-gls-unsuccessful']);

            $data_succesful = str_replace("\\'", "'", $data_succesful);
            $data_unsuccesful = str_replace("\\'", "'", $data_unsuccesful);

            $successful = unserialize(urldecode($data_succesful));
            $unsuccessful = unserialize(urldecode($data_unsuccesful));

            ?>
            <style>
                .woocommerce-layout__notice-list-hide {
                    display: block;
                }
            </style>
            <?php

            if ($successful) {

                $plugin_root = plugin_dir_url(__FILE__);
                $plugin_root = dirname($plugin_root);
                echo "<div class=\"updated\">";
                $messageContent = sprintf(__('Your merged labels are ready to <a href="%s" target="_blank" download="">download</a>',
                    $this->launcher->getPluginSlug()), $plugin_root . '/labels/' . $successful['url']);
                echo "<p>{$messageContent}</p>";
                echo "</div>";
            }
            if (is_array($unsuccessful) && count($unsuccessful) != 0) {

                echo "<div class=\"error\">";
                foreach ($unsuccessful as $message) {

                    if ($message['order_id'] == 'global') {
                        $messageContent = sprintf(__('Label was not generated. Error: %s',
                            $this->launcher->getPluginSlug()), $message['message']);
                        echo "<p>{$messageContent}</p>";
                    } else {
                        foreach ($message as $value) {
                            if ($value['order_id']) {
                                $messageContent = sprintf(__('Order no. %s Was not generated. Error: %s',
                                    $this->launcher->getPluginSlug()), $value['order_id'], $value['message']);
                            } else {
                                $messageContent = sprintf(__('Order was not generated. Error: %s',
                                    $this->launcher->getPluginSlug()), $value['message']);
                            }
                            echo "<p>{$messageContent}</p>";
                        }

                    }
                }

                echo "</div>";
            }

        }
    }
}