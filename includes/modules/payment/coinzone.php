<?php
require_once dirname(__FILE__) . '/coinzone/coinzone_api.php';

class Coinzone
{
    /**
     * Constructor class, sets the settings.
     */
    function __construct()
    {
        $this->code = 'coinzone';
        $this->version = '1.0.0';
        $this->title = MODULE_PAYMENT_COINZONE_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_COINZONE_TEXT_DESCRIPTION;
        $this->sort_order = 3;
        $this->enabled = true;
        $this->defaultCurrency = 'EUR';
        $this->tmpOrders = true;
        $this->tmpStatus = '';
        $this->form_action_url = '';

        $this->logFile = DIR_FS_CATALOG . 'logfiles/coinzone.log';
        $this->currencies = array('EUR');
        $this->countries = array('DE');
    }

    /**
     * Settings update. Not used in this module.
     *
     * @return boolean
     */
    function update_status()
    {
        return false;
    }

    /**
     * Javascript code. Not used in this module.
     *
     * @return boolean
     */
    function javascript_validation()
    {
        return false;
    }

    /**
     * Sets information for checkout payment selection page.
     *
     * @return array with payment module information
     */
    function selection()
    {
        global $order;
        return array('id' => $this->code, 'module' => $this->title, 'description' => 'Bitcoin Payment');
    }

    /**
     * Actions before confirmation. Not used in this module.
     *
     * @return boolean
     */
    function pre_confirmation_check()
    {
        return false;
    }

    /**
     * Payment method confirmation. Not used in this module.
     *
     * @return boolean
     */
    function confirmation()
    {
        return false;
    }

    /**
     * Module start via button. Not used in this module.
     *
     * @return boolean
     */
    function process_button()
    {
        return false;
    }

    /**
     * Before process. Not used in this module.
     *
     * @return boolean
     */
    function before_process()
    {
        return false;
    }

    /**
     * Payment process between final confirmation and success page.
     */
    function payment_action()
    {
        global $order, $insert_id;

        $sqlClientCode = xtc_db_query("SELECT configuration_value
                                           FROM " . TABLE_CONFIGURATION . "
                                          WHERE configuration_key = 'MODULE_PAYMENT_COINZONE_CLIENT_CODE'");
        $sqlApiKey = xtc_db_query("SELECT configuration_value
                                           FROM " . TABLE_CONFIGURATION . "
                                          WHERE configuration_key = 'MODULE_PAYMENT_COINZONE_API_KEY'");

        $clientCode = xtc_db_fetch_array($sqlClientCode)['configuration_value'];
        $apiKey = xtc_db_fetch_array($sqlApiKey)['configuration_value'];

        $schema = isset($_SERVER['HTTPS']) ? "https://" : "http://";
        $notifUrl = $schema . $_SERVER['HTTP_HOST'] . '/callback/coinzone/coinzone_callback.php';

        /* create payload array */
        $payload = array(
            'amount' => $order->info['total'],
            'currency' => $order->info['currency'],
            'merchantReference' => $insert_id,
            'email' => $order->customer['email_address'],
            'notificationUrl' => $notifUrl
        );
        $coinzone = new CoinzoneApi($clientCode, $apiKey);
        $response = $coinzone->callApi('transaction', $payload);

        if ($response->status->code == 201) {
            $sql_data_history = array(
                'orders_id' => $insert_id,
                'orders_status_id' => 0,
                'date_added' => 'now()',
                'customer_notified' => 1,
                'comments' => ''
            );
            xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_history);

            xtc_redirect($response->response->url);
        } else {
            xtc_redirect(xtc_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=coinzone&' . session_name() . '=' . session_id(), 'SSL'));
        }
    }

    /**
     * After process. Not used in this module.
     */
    function after_process()
    {
        return false;
    }

    /**
     * Extracts and returns error.
     *
     * @return array with error information
     */
    function get_error()
    {
        $error = false;
        if (!empty($_GET['payment_error'])) {
            $error = array(
                'title' => MODULE_PAYMENT_COINZONE_TEXT_ERROR,
                'error' => $this->convertISO(MODULE_PAYMENT_COINZONE_TEXT_PAYMENT_ERROR)
            );
        }

        return $error;
    }

    /**
     * Error output. Not used in this module.
     *
     * @return boolean
     */
    function output_error()
    {
        return false;
    }

    /**
     * Checks if Coinzone payment module is installed.
     *
     * @return integer
     */
    function check()
    {
        if (!isset($this->check)) {
            $check_query = xtc_db_query("SELECT configuration_value
                                           FROM " . TABLE_CONFIGURATION . "
                                          WHERE configuration_key = 'MODULE_PAYMENT_COINZONE_STATUS'");
            $this->check = xtc_db_num_rows($check_query);
        }
        return $this->check;
    }

    /**
     * Install sql queries.
     */
    function install()
    {
        xtc_db_query(
            "INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added)
            VALUES
            ('MODULE_PAYMENT_COINZONE_STATUS', 'False', '6', '1', 'xtc_cfg_select_option(array(\'True\', \'False\'), ', now())");

        xtc_db_query(
            "INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_key, configuration_value, configuration_group_id, sort_order, date_added)
            VALUES
            ('MODULE_PAYMENT_COINZONE_CLIENT_CODE', '', '6', '2', now()),
            ('MODULE_PAYMENT_COINZONE_API_KEY', '', '6', '3', now())");
    }

    /**
     * Uninstall sql queries.
     */
    function remove()
    {
        xtc_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('MODULE_PAYMENT_COINZONE_STATUS', 'MODULE_PAYMENT_COINZONE_API_KEY', 'MODULE_PAYMENT_COINZONE_CLIENT_CODE')");
    }

    /**
     * All necessary configuration attributes for the payment module.
     *
     * @return array with configuration attributes
     */
    function keys()
    {
        return array(
            'MODULE_PAYMENT_COINZONE_STATUS',
            'MODULE_PAYMENT_COINZONE_CLIENT_CODE',
            'MODULE_PAYMENT_COINZONE_API_KEY',
        );
    }

    /**
     * Coverts text to iso-8859-15 encoding.
     *
     * @param string $text utf-8 text
     * @return string ISO-8859-15 text
     */
    function convertISO($text)
    {
        return mb_convert_encoding($text, 'iso-8859-15', 'utf-8');
    }

    /**
     * Logs errors into Coinzone log file.
     *
     * @param string $message error message
     */
    function czLog($message)
    {
        $time = date("[Y-m-d H:i:s] ");
        error_log($time . $message . "\r\r", 3, $this->logFile);
    }

    /**
     * Get version of current gambio installation
     *
     * @return string
     */
    function getGambioVersion()
    {
        include(DIR_FS_CATALOG . 'release_info.php');
        return $gx_version;
    }
}
