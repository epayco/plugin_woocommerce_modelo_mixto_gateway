<?php
class WC_Gateway_Epayco_gateway extends WC_Payment_Gateway
{
    private $pluginVersion = '4.9.1';
    private $epayco_gateway_feedback;
    private $sandbox;
    private $enable_for_shipping;

    function __construct()
    {
        $this->setup_properties();
        $this->init_form_fields();
        $this->init_settings();
        $this->title               = $this->get_option('title');
        $this->description         = $this->get_option('description');
        $this->epayco_gateway_feedback       = $this->get_option('epayco_gateway_feedback', true);
        $this->epayco_gateway_customerid = $this->get_option('epayco_gateway_customerid');
        $this->epayco_gateway_secretkey = $this->get_option('epayco_gateway_secretkey');
        $this->epayco_gateway_publickey = $this->get_option('epayco_gateway_publickey');
        $this->epayco_gateway_description = $this->get_option('epayco_gateway_description');
        $this->epayco_gateway_testmode = $this->get_option('epayco_gateway_testmode');
        $this->epayco_gateway_lang = $this->get_option('epayco_gateway_lang');
        $this->epayco_gateway_type_checkout = $this->get_option('epayco_gateway_type_checkout');
        $this->epayco_gateway_endorder_state=$this->get_option('epayco_gateway_endorder_state');
 
        // Saving hook
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        // Payment listener/API hook
        add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_ePayco_gateway_response' ) );
        // Status change hook
        add_action('woocommerce_order_status_changed', [$this, 'change_status_action'], 10, 3);

        add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));
        
        add_action('ePayco_gateway_init', array( $this, 'ePayco_gateway_successful_request'));
        $this->init_OpenEpaycoGateway();
    }

    /**
     * Setup general properties for the gateway.
     */
    protected function setup_properties() {
        $this->id                 = 'epayco_gateway';
        $this->icon               = apply_filters('woocommerce_epayco_gateway_icon', plugin_dir_url(__FILE__).'/logo.png');
        $this->method_title       = __('ePayco Checkout Gateway', 'epayco_gateway');
        $this->method_description = __('Acepta tarjetas de creditó, debitó, depositos y transferencias.', 'epayco_gateway');
        $this->has_fields         = false;
        $this->supports           = ['products', 'refunds'];
    }

    protected function init_OpenEpaycoGateway($currency = null)
    {
        $isSandbox = 'yes' === $this->get_option('sandbox');

        if ($this->isWpmlActiveAndConfigure())
        {
            $optionSuffix = '_' . (null !== $currency ? $currency : get_woocommerce_currency());
        } else {
            $optionSuffix = '';
        }
    }

    public function is_valid_for_use()
    {
                return in_array(get_woocommerce_currency(), array('COP', 'USD'));
    }
    
    public function admin_options()
    {
    ?>
                <style>
                    tbody{
                    }
                    .epayco-table tr:not(:first-child) {
                        border-top: 1px solid #ededed;
                    }
                    .epayco-table tr th{
                            padding-left: 15px;
                            text-align: -webkit-right;
                    }
                    .epayco-table input[type="text"]{
                            padding: 8px 13px!important;
                            border-radius: 3px;
                            width: 100%!important;
                    }
                    .epayco-table .description{
                        color: #afaeae;
                    }
                    .epayco-table select{
                            padding: 8px 13px!important;
                            border-radius: 3px;
                            width: 100%!important;
                            height: 37px!important;
                    }
                    .epayco-required::before{
                        content: '* ';
                        font-size: 16px;
                        color: #F00;
                        font-weight: bold;
                    }

                </style>
                <div class="container-fluid">
                    <div class="panel panel-default" style="">
                        <img  src="<?php echo plugin_dir_url(__FILE__).'/logo.png' ?>">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="fa fa-pencil"></i>Configuración <?php _e('ePayco', 'epayco_gateway'); ?></h3>
                        </div>

                        <div style ="color: #31708f; background-color: #d9edf7; border-color: #bce8f1;padding: 10px;border-radius: 5px;">
                            <b>Este modulo le permite aceptar pagos seguros por la plataforma de pagos ePayco</b>
                            <br>Si el cliente decide pagar por ePayco, el estado del pedido cambiara a ePayco Esperando Pago
                            <br>Cuando el pago sea Aceptado o Rechazado ePayco envia una configuracion a la tienda para cambiar el estado del pedido.
                        </div>

                        <div class="panel-body" style="padding: 15px 0;background: #fff;margin-top: 15px;border-radius: 5px;border: 1px solid #dcdcdc;border-top: 1px solid #dcdcdc;">
                                <table class="form-table epayco-table">
                                <?php
                            if ($this->is_valid_for_use()) :
                                $this->generate_settings_html();
                            else :
                            if ( is_admin() && ! defined( 'DOING_AJAX')) {
                                echo '<div class="error"><p><strong>' . __( 'ePayco: Requiere que la moneda sea USD O COP', 'epayco_gateway' ) . '</strong>: ' . sprintf(__('%s', 'woocommerce-mercadopago' ), '<a href="' . admin_url() . 'admin.php?page=wc-settings&tab=general#s2id_woocommerce_currency">' . __( 'Click aquí para configurar!', 'epayco_gateway') . '</a>' ) . '</p></div>';
                                        }
                                    endif;
                                ?>
                                </table>
                        </div>
                    </div>
                </div>
                <?php
            }

    function init_form_fields()
    {
        global $woocommerce_wpml;

        $currencies = [];

        if ($this->isWpmlActiveAndConfigure())
        {
            $currencies = $woocommerce_wpml->multi_currency->get_currency_codes();
        }

        $this->form_fields = array_merge($this->getFormFieldsBasic(), $this->getFormFieldConfig($currencies), $this->getFormFieldInfo());
    }

    /**
     * Check If The Gateway Is Available For Use.
     * Copy from COD module
     *
     * @return bool
     */
    public function is_available()
    {
        if (!is_admin()) {
            $order = null;

            if (!WC()->cart && is_page(wc_get_page_id('checkout')) && 0 < get_query_var('order-pay')) {
                $order_id = absint(get_query_var('order-pay'));
                $order = wc_get_order($order_id);
            }
        }

        return parent::is_available();
    }

    /**
     * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
     *
     * Copy from COD
     *
     * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
     * @return array $canonical_rate_ids    Rate IDs in a canonical format.
     */
    private function get_canonical_order_shipping_item_rate_ids( $order_shipping_items ) {

        $canonical_rate_ids = array();

        foreach ( $order_shipping_items as $order_shipping_item ) {
            $canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
        }

        return $canonical_rate_ids;
    }

    /**
     * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
     *
     * Copy from COD
     *
     * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
     * @return array $canonical_rate_ids  Rate IDs in a canonical format.
     */
    private function get_canonical_package_rate_ids( $chosen_package_rate_ids ) {

        $shipping_packages  = WC()->shipping()->get_packages();
        $canonical_rate_ids = array();

        if ( ! empty( $chosen_package_rate_ids ) && is_array( $chosen_package_rate_ids ) ) {
            foreach ( $chosen_package_rate_ids as $package_key => $chosen_package_rate_id ) {
                if ( ! empty( $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ] ) ) {
                    $chosen_rate          = $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ];
                    $canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
                }
            }
        }

        return $canonical_rate_ids;
    }

    /**
     * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
     *
     * Copy from COD
     *
     * @param array $rate_ids Rate ids to check.
     * @return boolean
     */
    private function get_matching_rates( $rate_ids ) {
        // First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
        return array_unique( array_merge( array_intersect( $this->enable_for_shipping, $rate_ids ), array_intersect( $this->enable_for_shipping, array_unique( array_map( 'wc_get_string_before_colon', $rate_ids ) ) ) ) );
    }

    function process_payment($order_id)
    {

          $order = new WC_Order($order_id);
                $order->reduce_order_stock();
                if (version_compare( WOOCOMMERCE_VERSION, '2.1', '>=')) {
                    return array(
                        'result'    => 'success',
                        'redirect'  => add_query_arg('order-pay', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
                    );
                } else {
                    return array(
                        'result'    => 'success',
                        'redirect'  => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
                    );
                }
    }

        function get_pages($title = false, $indent = true) {
                $wp_pages = get_pages('sort_column=menu_order');
                $page_list = array();
                if ($title) $page_list[] = $title;
                foreach ($wp_pages as $page) {
                    $prefix = '';
                    // show indented child pages?
                    if ($indent) {
                        $has_parent = $page->post_parent;
                        while($has_parent) {
                            $prefix .=  ' - ';
                            $next_page = get_page($has_parent);
                            $has_parent = $next_page->post_parent;
                        }
                    }
                    // add to page list array array
                    $page_list[$page->ID] = $prefix . $page->post_title;
                }
                return $page_list;
            }


     public function receipt_page($order_id)
            {
                global $woocommerce;
                $order = new WC_Order($order_id);
                $descripcionParts = array();
                foreach ($order->get_items() as $product) {
                    $descripcionParts[] = $this->string_sanitize($product['name']);
                }
                $descripcion = implode(' - ', $descripcionParts);
                $currency = strtolower(get_woocommerce_currency());
                $basedCountry = WC()->countries->get_base_country();
                
                $redirect_url =get_site_url() . "/";
                $confirm_url=get_site_url() . "/";
              
                $redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
                $redirect_url = add_query_arg( 'order_id', $order_id, $redirect_url );
                $confirm_url = add_query_arg( 'wc-api', get_class( $this ), $confirm_url );
                $confirm_url = add_query_arg( 'order_id', $order_id, $confirm_url );
                $confirm_url = $redirect_url.'&confirmation=1';
                $name_billing=$order->get_billing_first_name().' '.$order->get_billing_last_name();
                $address_billing=$order->get_billing_address_1();
                $phone_billing=@$order->billing_phone;
                $email_billing=@$order->billing_email;
                $order = new WC_Order($order_id);
                $tax=$order->get_total_tax();
                $tax=round($tax,2);
                if((int)$tax>0){
                    $base_tax=$order->get_total()-$tax;
                }else{
                    $base_tax=$order->get_total();
                    $tax=0;
                }

                $external_type = $this->epayco_gateway_type_checkout;
          		$epayco_gateway_lang = $this->epayco_gateway_lang;
          		if ($epayco_gateway_lang == 'es') {
          			$message = '<span class="animated-points">Cargando métodos de pago</span>
                                    <br>
                                        <small class="epayco-subtitle"> Si no se cargan automáticamente, de clic en el botón "Pagar con ePayco"</small>';
          			$button = plugin_dir_url(__FILE__).'/Boton-color-espanol.png';
          		}else{
          			$message = '<span class="animated-points">Loading payment methods</span>
                                    <br>
                                        <small class="epayco-subtitle">If they are not charged automatically, click the  "Pay with ePayco" button</small>';
                    $button = plugin_dir_url(__FILE__).'/Boton-color-Ingles.png';
          		}
          		$test_mode = $this->epayco_gateway_testmode == "yes" ? "true" : "false";
          	
          	    //Busca si ya se restauro el stock
                if (!EpaycoOrder::ifExist($order_id)) {
                    //si no se restauro el stock restaurarlo inmediatamente
                    EpaycoOrder::create($order_id,1);
                }
                
               echo('
                    <style>
                        .epayco-title{
                            max-width: 900px;
                            display: block;
                            margin:auto;
                            color: #444;
                            font-weight: 700;
                            margin-bottom: 25px;
                        }
                        .loader-container{
                            position: relative;
                            padding: 20px;
                            color: #f0943e;
                        }
                        .epayco-subtitle{
                            font-size: 14px;
                        }
                        .epayco-button-render{
                            transition: all 500ms cubic-bezier(0.000, 0.445, 0.150, 1.025);
                            transform: scale(1.1);
                            box-shadow: 0 0 4px rgba(0,0,0,0);
                        }
                        .epayco-button-render:hover {
                            /*box-shadow: 0 0 4px rgba(0,0,0,.5);*/
                            transform: scale(1.2);
                        }
                        .animated-points::after{
                            content: "";
                            animation-duration: 2s;
                            animation-fill-mode: forwards;
                            animation-iteration-count: infinite;
                            animation-name: animatedPoints;
                            animation-timing-function: linear;
                            position: absolute;
                        }
                        .animated-background {
                            animation-duration: 2s;
                            animation-fill-mode: forwards;
                            animation-iteration-count: infinite;
                            animation-name: placeHolderShimmer;
                            animation-timing-function: linear;
                            color: #f6f7f8;
                            background: linear-gradient(to right, #7b7b7b 8%, #999 18%, #7b7b7b 33%);
                            background-size: 800px 104px;
                            position: relative;
                            background-clip: text;
                            -webkit-background-clip: text;
                            -webkit-text-fill-color: transparent;
                        }
                        
                        .loading::before{
                            -webkit-background-clip: padding-box;
                            background-clip: padding-box;
                            box-sizing: border-box;
                            border-width: 2px;
                            border-color: currentColor currentColor currentColor transparent;
                            position: absolute;
                            margin: auto;
                            top: 0;
                            left: 0;
                            right: 0;
                            bottom: 0;
                            content: " ";
                            display: inline-block;
                            background: center center no-repeat;
                            background-size: cover;
                            border-radius: 50%;
                            border-style: solid;
                            width: 30px;
                            height: 30px;
                            opacity: 1;
                            -webkit-animation: loaderAnimation 1s infinite linear,fadeIn 0.5s ease-in-out;
                            -moz-animation: loaderAnimation 1s infinite linear, fadeIn 0.5s ease-in-out;
                            animation: loaderAnimation 1s infinite linear, fadeIn 0.5s ease-in-out;
                        }
                        @keyframes animatedPoints{
                            33%{
                                content: "."
                            }
                            66%{
                                content: ".."
                            }
                            100%{
                                content: "..."
                            }
                        }
                        @keyframes placeHolderShimmer{
                            0%{
                                background-position: -800px 0
                            }
                            100%{
                                background-position: 800px 0
                            }
                        }
                        @keyframes loaderAnimation{
                            0%{
                                -webkit-transform:rotate(0);
                                transform:rotate(0);
                                animation-timing-function:cubic-bezier(.55,.055,.675,.19)
                            }
                            50%{
                                -webkit-transform:rotate(180deg);
                                transform:rotate(180deg);
                                animation-timing-function:cubic-bezier(.215,.61,.355,1)
                            }
                            100%{
                                -webkit-transform:rotate(360deg);
                                transform:rotate(360deg)
                            }
                        }
                    </style>
                    ');
                echo sprintf('
                        <div class="loader-container">
                            <div class="loading"></div>
                        </div>
                        <p style="text-align: center;" class="epayco-title">
                            '.$message.'
                        </p>
                        <div id="epayco_form" style="text-align: center;">
                        <form>
                        <script
                            src="https://epayco-checkout-testing.s3.amazonaws.com/checkout.preprod.js?version=1639601662446"
                            class="epayco-button"
                            data-epayco-key="%s"
                            data-epayco-test="%s"
                            data-epayco-amount="%s"
                            data-epayco-tax="%s"
                            data-epayco-tax-base="%s"
                            data-epayco-name="%s"
                            data-epayco-description="%s"
                            data-epayco-currency="%s"                         
                            data-epayco-invoice="%s" 
                            data-epayco-country="%s"
                            data-epayco-external="%s"                       
                            data-epayco-response="%s"
                            data-epayco-confirmation="%s"
                            data-epayco-email-billing="%s"
                            data-epayco-name-billing="%s"
                            data-epayco-address-billing="%s"
                            data-epayco-lang="%s"
                            data-epayco-mobilephone-billing="%s"
                            data-epayco-button="'.$button.'"
                            data-epayco-autoclick="true"
                            >
                        </script>
                    </form>
                        </div>       
                ',$this->epayco_gateway_publickey,$test_mode,$order->get_total(),$tax,$base_tax, $descripcion, $descripcion, $currency, $order->get_id(), $basedCountry, $external_type, $redirect_url,$confirm_url,
                    $email_billing,$name_billing,$address_billing,$epayco_gateway_lang,$phone_billing);
                
            }

                public function string_sanitize($string, $force_lowercase = true, $anal = false) {
                $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
                               "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
                               "â€”", "â€“", ",", "<", ".", ">", "/", "?");
                $clean = trim(str_replace($strip, "", strip_tags($string)));
                $clean = preg_replace('/\s+/', "_", $clean);
                $clean = ($anal) ? preg_replace("/[^a-zA-Z0-9]/", "", $clean) : $clean ;
                return $clean;
            }


        function check_ePayco_gateway_response(){
                @ob_clean();
                if ( ! empty( $_REQUEST ) ) {
                    header( 'HTTP/1.1 200 OK' );
                    do_action( "ePayco_gateway_init", $_REQUEST );
                } else {
                    wp_die( __("ePayco Request Failure", 'epayco-gateway-woocommerce') );
                }
        }
        
        public function authSignature($x_ref_payco, $x_transaction_id, $x_amount, $x_currency_code){
                $signature = hash('sha256',
                    trim($this->epayco_gateway_customerid).'^'
                    .trim($this->epayco_gateway_secretkey).'^'
                    .$x_ref_payco.'^'
                    .$x_transaction_id.'^'
                    .$x_amount.'^'
                    .$x_currency_code
                );

                return $signature;
            }

        function ePayco_gateway_successful_request($validationData)
            {
            
                    global $woocommerce;
                    $order_id="";
                    $ref_payco="";
                    $signature="";
              
                    if(isset($_REQUEST['x_signature'])){
                        $order_id = trim(sanitize_text_field($_GET['order_id']));
                        $x_ref_payco = trim(sanitize_text_field($_REQUEST['x_ref_payco']));
                        $x_transaction_id = trim(sanitize_text_field($_REQUEST['x_transaction_id']));
                        $x_amount = trim(sanitize_text_field($_REQUEST['x_amount']));
                        $x_currency_code = trim(sanitize_text_field($_REQUEST['x_currency_code']));
                        $x_signature = trim(sanitize_text_field($_REQUEST['x_signature']));
                        $x_cod_transaction_state=(int)trim(sanitize_text_field($_REQUEST['x_cod_response']));
                        $x_test_request = trim(sanitize_text_field($_REQUEST['x_test_request']));
                        $x_approval_code = trim(sanitize_text_field($_REQUEST['x_approval_code']));
                        $signature = hash('sha256',
                            trim($this->epayco_gateway_customerid).'^'
                            .trim($this->epayco_gateway_secretkey).'^'
                            .$x_ref_payco.'^'
                            .$x_transaction_id.'^'
                            .$x_amount.'^'
                            .$x_currency_code
                        );

                    }else{
                        $order_id_info = sanitize_text_field($_GET['order_id']);
                        $order_id_explode = explode('=',$order_id_info);
                        $order_id_rpl  = str_replace('?ref_payco','',$order_id_explode);
                        $order_id = $order_id_rpl[0];
                        $ref_payco = sanitize_text_field($_GET['ref_payco']);
                        $isConfirmation = sanitize_text_field($_GET['confirmation']) == 1;
                        if(empty($ref_payco)){
                            $ref_payco =$order_id_rpl[1];
                        }
                      
                        if (!$ref_payco) {
                            $explode=explode('=',$order_id);
                            $ref_payco=$explode[1];
                            $explode2 = explode('?', $order_id );
                            $order_id=$explode2[0];
                        }
                        $url = 'https://secure.epayco.io/validation/v1/reference/'.$ref_payco;
                        $response = wp_remote_get(  $url );
                        $body = wp_remote_retrieve_body( $response ); 
                        $jsonData = @json_decode($body, true);
                        $validationData = $jsonData['data'];
                        $x_test_request = trim($validationData['x_test_request']);
                        $x_amount = trim($validationData['x_amount']);
                        $x_approval_code = trim($validationData['x_approval_code']);
                        $x_cod_transaction_state = (int)trim($validationData['x_cod_transaction_state']);
                        $x_ref_payco = (int)trim($validationData['x_ref_payco']);
                        $x_transaction_id = (int)trim($validationData['x_transaction_id']);
                        $x_currency_code = trim($validationData['x_currency_code']);
                        //Validamos la firma
                        if ($order_id!="" && $ref_payco!="") {
                            $signature = hash('sha256',
                                 trim($this->epayco_gateway_customerid).'^'
                                .trim($this->epayco_gateway_secretkey).'^'
                                .trim($validationData['x_ref_payco']).'^'
                                .trim($validationData['x_transaction_id']).'^'
                                .trim($validationData['x_amount']).'^'
                                .trim($validationData['x_currency_code'])
                            );
                        }
                    }
                    
                    $order = new WC_Order($order_id);
                    
                    if(is_null($ref_payco) ){
                        $order = new WC_Order($order_id);
                        $message = 'Pago fallido';
                        $messageClass = 'woocommerce-error';
                        $order->update_status('failed');
                        $order->add_order_note('Pago expirado');
                        $this->restore_order_stock($order->id);
                        $redirect_url = $order->get_checkout_order_received_url(); 
                        wp_redirect($redirect_url);
                        die();
                    }

                    $message = '';
                    $messageClass = '';
                
                    $current_state = $order->get_status();
                    $isTestTransaction = $x_test_request == 'TRUE' ? "yes" : "no";
                    update_option('epaycor_gateway_order_status', $isTestTransaction);
                    $isTestMode = get_option('epaycor_gateway_order_status') == "yes" ? "true" : "false";
                    $isTestPluginMode = $this->epayco_gateway_testmode;
                   
                   if($order->get_total() == $x_amount){
                    if("yes" == $isTestPluginMode){
                        $validation = true;
                    }
                    if("no" == $isTestPluginMode ){
                        if($x_approval_code != "000000" && $x_cod_transaction_state == 1){
                            $validation = true;
                        }else{
                            if($x_cod_transaction_state != 1){
                                $validation = true;
                            }else{
                                $validation = false;
                            }
                        }
                        
                    }
                }else{
                     $validation = false;
                }
                
                if ($order_id != "" && $x_ref_payco != "") {
                    $authSignature = $this->authSignature($x_ref_payco, $x_transaction_id, $x_amount, $x_currency_code);
                }

                if($authSignature == $signature && $validation){

                    switch ($x_cod_transaction_state) {
                        case 1: {
                            if($current_state == "epayco_failed" ||
                            $current_state == "epayco_cancelled" ||
                            $current_state == "failed" ||
                            $current_state == "epayco-cancelled" ||
                            $current_state == "epayco-failed"
                        ){}else{
                             //Busca si ya se descontó el stock
                        if (!EpaycoOrder::ifStockDiscount($order_id)){
                            
                            //se descuenta el stock
                            EpaycoOrder::updateStockDiscount($order_id,1);
                                
                        }

                       if($isTestMode=="true"){
                                $message = 'Pago exitoso Prueba';
                                switch ($this->epayco_gateway_endorder_state ){
                                    case 'epayco-processing':{
                                        $orderStatus ='epayco_processing';
                                    }break;
                                    case 'epayco-completed':{
                                        $orderStatus ='epayco_completed';
                                    }break;
                                    case 'processing':{
                                        $orderStatus ='processing_test';
                                    }break;
                                    case 'completed':{
                                        $orderStatus ='completed_test';
                                    }break;
                                }
                            }else{
                                $message = 'Pago exitoso';
                                $orderStatus = $this->epayco_gateway_endorder_state;
                            }
                            $order->payment_complete($x_ref_payco);
                            $order->update_status($orderStatus);
                            $order->add_order_note($message);
                        }
                        echo "1";
                        } break;
                        case 2: {
                            if($isTestMode=="true"){
                                    if($current_state =="epayco_failed" ||
                                        $current_state =="epayco_cancelled" ||
                                        $current_state =="failed" ||
                                        $current_state == "epayco_processing" ||
                                        $current_state == "epayco_completed" ||
                                        $current_state == "processing_test" ||
                                        $current_state == "completed_test"
                                    ){}else{
                                        $message = 'Pago rechazado Prueba: ' .$x_ref_payco;
                                        $messageClass = 'woocommerce-error';
                                        $order->update_status('epayco_cancelled');
                                        $order->add_order_note($message);
                                        if($current_state !="epayco-cancelled"){
                                            if("yes" == $isTestPluginMode AND $isTestMode == "true"){
                                            $this->restore_order_stock($order->id);
                                            }
                                        }
                                    }
                                }else{
                                    if($current_state =="epayco-failed" ||
                                        $current_state =="epayco-cancelled" ||
                                        $current_state =="failed" ||
                                        $current_state == "epayco-processing" ||
                                        $current_state == "epayco-completed" ||
                                        $current_state == "processing" ||
                                        $current_state == "completed"
                                    ){}else{
                                        $message = 'Pago rechazado' .$x_ref_payco;
                                        $messageClass = 'woocommerce-error';
                                        $order->update_status('epayco-cancelled');
                                        $order->add_order_note('Pago fallido');
                                        if("no" == $isTestPluginMode AND $isTestMode == "false"){
                                        $this->restore_order_stock($order->id);
                                        }
                                    }
                                }
                                echo "2";
                        } break;
                        case 3: {
                            
                            //Busca si ya se restauro el stock y si se configuro reducir el stock en transacciones pendientes
                            if (!EpaycoOrder::ifStockDiscount($order_id) && $this->get_option('epayco_reduce_stock_pending') != 'yes') {
                                //actualizar el stock
                                EpaycoOrder::updateStockDiscount($order_id,1);
                            }

                            if($isTestMode=="true"){
                                $message = 'Pago pendiente de aprobación Prueba';
                                $orderStatus = "epayco_on_hold";
                            }else{
                                $message = 'Pago pendiente de aprobación';
                                $orderStatus = "epayco-on-hold";
                            }
                            $order->update_status($orderStatus);
                            $order->add_order_note($message);
                        } break;
                        case 4: {
                            if($isTestMode=="true"){
                                    if($current_state =="epayco_failed" ||
                                        $current_state =="epayco_cancelled" ||
                                        $current_state =="failed" ||
                                        $current_state == "epayco_processing" ||
                                        $current_state == "epayco_completed" ||
                                        $current_state == "processing_test" ||
                                        $current_state == "completed_test"
                                    ){}else{
                                        $message = 'Pago Fallido Prueba: ' .$x_ref_payco;
                                        $messageClass = 'woocommerce-error';
                                        $order->update_status('epayco_failed');
                                        $order->add_order_note($message);
                                        if($current_state !="epayco-cancelled"){
                                            if("yes" == $isTestPluginMode AND $isTestMode == "true"){
                                            $this->restore_order_stock($order->id);
                                            }
                                        }
                                    }
                                }else{
                                    if($current_state =="epayco-failed" ||
                                        $current_state =="epayco-cancelled" ||
                                        $current_state =="failed" ||
                                        $current_state == "epayco-processing" ||
                                        $current_state == "epayco-completed" ||
                                        $current_state == "processing" ||
                                        $current_state == "completed"
                                    ){}else{
                                        $message = 'Pago Fallido' .$x_ref_payco;
                                        $messageClass = 'woocommerce-error';
                                        $order->update_status('epayco-failed');
                                        $order->add_order_note('Pago fallido');
                                        if("no" == $isTestPluginMode AND $isTestMode == "false"){
                                        $this->restore_order_stock($order->id);
                                        }
                                    }
                                }
                                echo "4";
                        } break;
                        case 6: {
                            $message = 'Pago Reversada' .$x_ref_payco;
                            $messageClass = 'woocommerce-error';
                            $order->update_status('refunded');
                            $order->add_order_note('Pago Reversado');
                            $this->restore_order_stock($order->id);
                        } break;
                        case 10:{
                            if($isTestMode == "true"){
                                    if($current_state =="epayco_failed" ||
                                        $current_state =="epayco_cancelled" ||
                                        $current_state =="failed" ||
                                        $current_state == "epayco_processing" ||
                                        $current_state == "epayco_completed" ||
                                        $current_state == "processing_test" ||
                                        $current_state == "completed_test"
                                    ){}else{
                                        $message = 'Pago Fallido Prueba: ' .$x_ref_payco;
                                        $messageClass = 'woocommerce-error';
                                        $order->update_status('epayco_failed');
                                        $order->add_order_note($message);
                                        if($current_state !="epayco-cancelled"){
                                            if("yes" == $isTestPluginMode AND $isTestMode == "true"){
                                            $this->restore_order_stock($order->id);
                                            }
                                        }
                                    }
                                }else{
                                    
                                    if($current_state =="epayco-failed" ||
                                        $current_state =="epayco-cancelled" ||
                                        $current_state =="failed" ||
                                        $current_state == "epayco-processing" ||
                                        $current_state == "epayco-completed" ||
                                        $current_state == "processing" ||
                                        $current_state == "completed"
                                    ){}else{
                                        $message = 'Pago Fallido' .$x_ref_payco;
                                        $messageClass = 'woocommerce-error';
                                        $order->update_status('epayco-failed');
                                        $order->add_order_note('Pago fallido');
                                        if("no" == $isTestPluginMode AND $isTestMode == "false"){
                                        $this->restore_order_stock($order->id);
                                        }
                                    }
                                }
                                echo "10";
                        } break;
                        case 11:{
                            if($isTestMode == "true"){
                                    if($current_state =="epayco_failed" ||
                                        $current_state =="epayco_cancelled" ||
                                        $current_state =="failed" ||
                                        $current_state == "epayco_processing" ||
                                        $current_state == "epayco_completed" ||
                                        $current_state == "processing_test" ||
                                        $current_state == "completed_test"
                                    ){}else{
                                        $message = 'Pago Cancelado Prueba: ' .$x_ref_payco;
                                        $messageClass = 'woocommerce-error';
                                        $order->update_status('epayco_cancelled');
                                        $order->add_order_note($message);
                                        if($current_state !="epayco-cancelled"){
                                            if("yes" == $isTestPluginMode AND $isTestMode == "true"){
                                            $this->restore_order_stock($order->id);
                                            }
                                        }
                                    }
                                }else{
                                    if($current_state =="epayco-failed" ||
                                        $current_state =="epayco-cancelled" ||
                                        $current_state =="failed" ||
                                        $current_state == "epayco-processing" ||
                                        $current_state == "epayco-completed" ||
                                        $current_state == "processing" ||
                                        $current_state == "completed"
                                    ){}else{
                                        $message = 'Pago Cancelado' .$x_ref_payco;
                                        $messageClass = 'woocommerce-error';
                                        $order->update_status('epayco-cancelled');
                                        $order->add_order_note('Pago Cancelado');
                                        if("no" == $isTestPluginMode AND $isTestMode == "false"){
                                        $this->restore_order_stock($order->id);
                                        }
                                    }
                                }
                                echo "11";
                        } break;
                        default: {
                            if(
                                $current_state == "epayco-processing" ||
                                $current_state == "epayco-completed" ||
                                $current_state == "processing" ||
                                $current_state == "completed"){
                            } else{
                                $message = 'Pago '.$_REQUEST['x_transaction_state'] . $x_ref_payco;
                                $messageClass = 'woocommerce-error';
                                $order->update_status('epayco-failed');
                                $order->add_order_note('Pago fallido o abandonado');
                                $this->restore_order_stock($order->id);
                            }
                        } break;
                    }

                    //validar si la transaccion esta pendiente y pasa a rechazada y ya habia descontado el stock
                    if($current_state == 'on-hold' && ((int)$x_cod_transaction_state == 2 || (int)$x_cod_transaction_state == 4) && EpaycoOrder::ifStockDiscount($order_id)){
                        //si no se restauro el stock restaurarlo inmediatamente
                        $this->restore_order_stock($order_id);
                    };

                } else{
                        
                    if($isTestMode == "true"){
                        if($current_state =="epayco_failed" ||
                            $current_state =="epayco_cancelled" ||
                            $current_state =="epayco-cancelled" ||
                            $current_state =="failed" ||
                            $current_state == "epayco_processing" ||
                            $current_state == "epayco_completed" ||
                            $current_state == "processing_test" ||
                            $current_state == "completed_test"
                        ){
                            if($x_cod_transaction_state == 1){
                                $message = 'Pago exitoso Prueba';
                                switch ($this->epayco_gateway_endorder_state ){
                                        case 'epayco-processing':{
                                            $orderStatus ='epayco_processing';
                                        }break;
                                        case 'epayco-completed':{
                                            $orderStatus ='epayco_completed';
                                        }break;
                                        case 'processing':{
                                            $orderStatus ='processing_test';
                                        }break;
                                        case 'completed':{
                                            $orderStatus ='completed_test';
                                        }break;
                                    }
                                    $order->update_status($orderStatus);
                                    $order->add_order_note($message);
                                
                                }
                        }else{
                            if($x_cod_transaction_state == 1){
                            $message = 'Pago exitoso Prueba';
                            switch ($this->epayco_gateway_endorder_state ){
                                    case 'epayco-processing':{
                                        $orderStatus ='epayco_processing';
                                    }break;
                                    case 'epayco-completed':{
                                        $orderStatus ='epayco_completed';
                                    }break;
                                    case 'processing':{
                                        $orderStatus ='processing_test';
                                    }break;
                                    case 'completed':{
                                        $orderStatus ='completed_test';
                                    }break;
                                }
                                 $order->update_status($orderStatus);
                                $order->add_order_note($message);
                                if($current_state !=  "epayco_on_hold"){
                                    $this->restore_order_stock($order->id);
                                }
                            }else{
                           $order->update_status('epayco_failed');
                            $order->add_order_note('Pago fallido o abandonado');
                            $this->restore_order_stock($order->id);
                            }
                        }
                    }else{
                       if($current_state =="epayco-failed" ||
                            $current_state =="epayco-cancelled" ||
                            $current_state =="failed" ||
                            $current_state == "epayco-processing" ||
                            $current_state == "epayco-completed" ||
                            $current_state == "processing" ||
                            $current_state == "completed"
                        ){}else{
                            if($x_cod_transaction_state == 1){
                            $message = 'Pago exitoso';
                            $orderStatus = $this->epayco_gateway_endorder_state;
                            }else{ 
                            $order->update_status('epayco-failed');
                            $order->add_order_note('Pago fallido o abandonado');
                            $this->restore_order_stock($order->id);
                            } 
                        } 
                    }
                        
                    }      
            
                if (isset($_REQUEST['confirmation'])) {
                    echo $current_state;
                    die();
                        
                }else{
                        
                    $redirect_url = $order->get_checkout_order_received_url(); 
                    wp_redirect($redirect_url);
                    die();
                }
   
}

    public function agafa_dades($url) {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            $timeout = 5;
            $user_agent='Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch,CURLOPT_TIMEOUT,$timeout);
            curl_setopt($ch,CURLOPT_MAXREDIRS,10);
            $data = curl_exec($ch);
            curl_close($ch);
                return $data;
        }else{
                $data =  @file_get_contents($url);
                return $data;
            }
        }


    public function goter(){
        $context = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'protocol_version' => 1.1,
                'timeout' => 10,
                'ignore_errors' => true
            )
        ));
    }
            
            
    /**
    * @param $order_id
    */
    public function restore_order_stock($order_id,$operation = 'increase')
    {
    $order = wc_get_order($order_id);
    if (!get_option('woocommerce_manage_stock') == 'yes' && !sizeof($order->get_items()) > 0) {
            return;
        }
        foreach ($order->get_items() as $item) {
            // Get an instance of corresponding the WC_Product object
            $product = $item->get_product();
            $qty = $item->get_quantity(); // Get the item quantity
            wc_update_product_stock($product, $qty, $operation);
        }
    }


    /**
     * @param $value
     * @return int
     */
    private function toAmount($value)
    {
        return (int)round($value * 100);
    }

    /**
     * @return string
     */
    private function getLanguage()
    {
        return substr(get_locale(), 0, 2);
    }

    /**
     * @param WC_Order $order
     * @return string
     */
    private function getOrderCurrency($order)
    {
        return method_exists($order,'get_currency') ? $order->get_currency() : $order->get_order_currency();
    }

    /**
     * @param WC_Order $order
     */
    private function reduceStock($order)
    {
        function_exists('wc_reduce_stock_levels') ?
            wc_reduce_stock_levels($order->get_id()) : $order->reduce_order_stock();

    }

    /**
     * @param string $notification
     * @return null|string
     */
    private function extractCurrencyFromNotification($notification)
    {
        $notification = json_decode($notification);

        if (is_object($notification) && $notification->order && $notification->order->currencyCode) {
            return $notification->order->currencyCode;
        }
        return null;
    }

    /**
     * @return string
     */
    private function getIP()
    {
        return ($_SERVER['REMOTE_ADDR'] == '::1' || $_SERVER['REMOTE_ADDR'] == '::' ||
            !preg_match('/^((?:25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9]).){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9])$/m',
                $_SERVER['REMOTE_ADDR'])) ? '127.0.0.1' : $_SERVER['REMOTE_ADDR'];
    }

    /** @return bool */
    private function isWpmlActiveAndConfigure() {
        global $woocommerce_wpml;

        return $woocommerce_wpml
            && property_exists($woocommerce_wpml, 'multi_currency')
            && $woocommerce_wpml->multi_currency
            && count($woocommerce_wpml->multi_currency->get_currency_codes()) > 1;
    }

    /**
     * @return array
     */
    private function getFormFieldsBasic()
    {
        return array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'label' => __('Habilitar ePayco Checkout', 'epayco_gateway'),
                'type' => 'checkbox',
                'description' => __('Para obtener las credenciales de configuración, <a href="https://dashboard.epayco.co/login?utm_campaign=epayco&utm_medium=button-header&utm_source=web#registro" target="_blank">Inicie sesión</a>.', 'epayco_gateway'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Title:', 'epayco_gateway'),
                'type' => 'text',
                'description' => __('Corresponde al titulo que el usuario ve durante el checkout.', 'epayco_gateway'),
                'default' => __('ePayco Checkout', 'epayco_gateway'),
                'desc_tip' => true
            )
        );
    }

    /**
     * @return array
     */
    private function getFormFieldInfo()
    {
        return array(
            'description' => array(
                'title' => __('Description:', 'epayco_gateway'),
                'type' => 'text',
                'description' => __('Corresponde a la descripción que verá el usuaro durante el checkout', 'epayco_gateway'),
                'default' => __('Checkout ePayco (Tarjetas de crédito,debito,efectivo)', 'epayco_gateway'),
                'desc_tip' => true
            ),
            'epayco_agregador_feedback' => array(
                'title' => __('Redireccion con data:', 'epayco_gateway'),
                'type' => 'checkbox',
                'description' => __('Automatic collection makes it possible to automatically confirm incoming payments.', 'epayco_gateway'),
                'label' => ' ',
                'default' => 'no',
                'desc_tip' => true
            )
        );
    }

    /**
     * @param array $currencies
     * @return array
     */
    private function getFormFieldConfig($currencies = [])
    {
        if (count($currencies) < 2) {
            $currencies = array('');
        }
        $config = array();

        foreach ($currencies as $code) {
            $idSuffix = ($code ? '_' : '') . $code;
            $namePrefix = $code . ($code ? ' - ' : '');

            $config += array(
                'epayco_gateway_customerid' . $idSuffix => array(
                    'title' => $namePrefix . __('<span class="epayco-required">P_CUST_ID_CLIENTE</span>', 'epayco_gateway'),
                    'type' => 'text',
                    'description' => $namePrefix . __('ID de cliente que lo identifica en ePayco. Lo puede encontrar en su panel de clientes en la opción configuración.', 'epayco_gateway'),
                    'desc_tip' => true
                ),
                'epayco_gateway_secretkey' . $idSuffix => array(
                    'title' => $namePrefix . __('<span class="epayco-required">P_KEY</span>', 'epayco_gateway'),
                    'type' => 'text',
                    'description' => __('LLave para firmar la información enviada y recibida de ePayco. Lo puede encontrar en su panel de clientes en la opción configuración.', 'epayco_gateway'),
                    'desc_tip' => true
                ),
                'epayco_gateway_publickey' . $idSuffix => array(
                    'title' => $namePrefix . __('<span class="epayco-required">PUBLIC_KEY</span>', 'epayco_gateway'),
                    'type' => 'text',
                    'description' => __('LLave para autenticar y consumir los servicios de ePayco, Proporcionado en su panel de clientes en la opción configuración.', 'epayco_gateway'),
                    'desc_tip' => true
                ),
                'epayco_gateway_testmode' . $idSuffix => array(
                    'title' => $namePrefix . __('Sitio en pruebas', 'epayco_gateway'),
                    'type' => 'checkbox',
                    'label' => __('Habilitar el modo de pruebas', 'epayco_gateway'),
                    'description' => __('Habilite para realizar pruebas', 'epayco_gateway'),
                    'default' => 'no',
                    'desc_tip' => true
                ),
                'epayco_gateway_lang' . $idSuffix => array(
                    'title' => $namePrefix . __('Idioma del Checkout', 'epayco_gateway'),
                    'type' => 'select',
                    'css' =>'line-height: inherit',
                    'description' => __('Habilite para realizar pruebas', 'epayco_gateway'),
                      'options' => array('es'=>"Español","en"=>"Inglés"),
                    'desc_tip' => true
                ),
                'epayco_gateway_type_checkout' . $idSuffix => array(
                    'title' => $namePrefix . __('Tipo Checkout', 'epayco_gateway'),
                    'type' => 'select',
                    'css' =>'line-height: inherit',
                    'label' => __('Seleccione un tipo de Checkout:', 'epayco_gateway'),
                    'description' => __('(Onpage Checkout, el usuario al pagar permanece en el sitio) ó (Standart Checkout, el usario al pagar es redireccionado a la pasarela de ePayco)', 'epayco_gateway'),
                    'options' => array('false'=>"Onpage Checkout","true"=>"Standart Checkout"),
                    'desc_tip' => true
                ),
                'epayco_gateway_endorder_state' => array(
                        'title' => __('Estado Final del Pedido', 'epayco_gateway'),
                        'type' => 'select',
                        'css' =>'line-height: inherit',
                        'description' => __('Seleccione el estado del pedido que se aplicará a la hora de aceptar y confirmar el pago de la orden', 'epayco_gateway'),
                        'options' => array(
                            'epayco-processing'=>"ePayco Procesando Pago",
                            "epayco-completed"=>"ePayco Pago Completado",
                            'processing'=>"Procesando",
                            "completed"=>"Completado"
                        ),
                    ),
             
            );
        }
        return $config;
    }
    

    private function getShippingMethods()
    {
        $options    = [];
        $data_store = WC_Data_Store::load( 'shipping-zone' );
        $raw_zones  = $data_store->get_zones();

        foreach ( $raw_zones as $raw_zone ) {
            $zones[] = new WC_Shipping_Zone( $raw_zone );
        }

        $zones[] = new WC_Shipping_Zone( 0 );

        foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

            $options[ $method->get_method_title() ] = array();

            // Translators: %1$s shipping method name.
            $options[ $method->get_method_title() ][ $method->id ] = sprintf( __( 'Any &quot;%1$s&quot; method', 'woocommerce' ), $method->get_method_title() );

            foreach ( $zones as $zone ) {

                $shipping_method_instances = $zone->get_shipping_methods();

                foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

                    if ( $shipping_method_instance->id !== $method->id ) {
                        continue;
                    }

                    $option_id = $shipping_method_instance->get_rate_id();

                    // Translators: %1$s shipping method title, %2$s shipping method id.
                    $option_instance_title = sprintf( __( '%1$s (#%2$s)', 'woocommerce' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

                    // Translators: %1$s zone name, %2$s shipping method instance name.
                    $option_title = sprintf( __( '%1$s &ndash; %2$s', 'woocommerce' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'woocommerce' ), $option_instance_title );

                    $options[ $method->get_method_title() ][ $option_id ] = $option_title;
                }
            }
        }

        return $options;
    }
    
}
