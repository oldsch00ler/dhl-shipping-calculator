<?php
/**
 * Plugin Name: DHL Shipping Calculator
 * Description: Plugin for calculating shipping costs via DHL API.
 * Version: 1.0.0
 * Author: Lukáš Zuzaňák
 * Author URI: https://zuzanak.eu
 * Plugin URI: https://zuzanak.eu/plugins/dhl-shipping-calculator
 * Text Domain: dhl-shipping-calculator
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.2
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * 
 * Usage:
 * In new wordpress page use shortcode [dhl_shipping_form] to display the form.
 *
 * API Documentation:
 * For more detailed information about the API, please read:
 * https://developer.dhl.com/api-reference/dhl-express-mydhl-api
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 3, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

 if (!defined('ABSPATH')) {
    exit;
}

function load_dhl_shipping_textdomain() {
    $plugin_language = get_option('dhl_shipping_language', 'auto');
    
    if ($plugin_language !== 'auto') {
        $locale = $plugin_language;
        load_textdomain('dhl-shipping-calculator', 
            plugin_dir_path(__FILE__) . 'languages/' . $locale . '.mo');
    } else {
        load_plugin_textdomain('dhl-shipping-calculator', 
            false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
}
add_action('plugins_loaded', 'load_dhl_shipping_textdomain');

class DHLShippingCalculator {
    private $option_name = 'dhl_api_key';
    private $user_option_name = 'dhl_api_user';

    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'create_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_shortcode('dhl_shipping_form', [$this, 'render_shipping_form']);
        add_action('wp_ajax_dhl_calculate_shipping', [$this, 'calculate_shipping']);
        add_action('wp_ajax_nopriv_dhl_calculate_shipping', [$this, 'calculate_shipping']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function create_settings_page() {
        add_options_page('DHL Shipping', 'DHL Shipping', 'manage_options', 'dhl-shipping', [$this, 'settings_page_html']);
    }

    public function register_settings() {
        register_setting('dhl_shipping_options', 'dhl_use_production');
        register_setting('dhl_shipping_options', $this->option_name);
        register_setting('dhl_shipping_options', $this->user_option_name);
        register_setting('dhl_shipping_options', 'dhl_shipping_language');
        add_settings_section('dhl_section', __('Nastavení DHL API', 'dhl-shipping-calculator'), null, 'dhl_shipping_options');
        add_settings_field(
            'dhl_api_key_field',
            __('DHL API Key', 'dhl-shipping-calculator'),
            function() {
                echo '<input type="text" name="'.$this->option_name.'" value="'.esc_attr(get_option($this->option_name)).'" class="regular-text">';
            },
            'dhl_shipping_options',
            'dhl_section'
        );
        add_settings_field(
            'dhl_api_user_field',
            __('DHL API User', 'dhl-shipping-calculator'),
            function() {
                echo '<input type="text" name="'.$this->user_option_name.'" value="'.esc_attr(get_option($this->user_option_name)).'" class="regular-text">';
            },
            'dhl_shipping_options',
            'dhl_section'
        );
    }

    public function settings_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- Informační panel -->
            <div class="card" style="max-width: 800px; margin-bottom: 20px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; padding: 15px;">
                <h2><?php _e('O pluginu DHL Shipping Calculator', 'dhl-shipping-calculator'); ?></h2>
                <p><?php _e('Plugin pro výpočet ceny přepravného přes DHL API.', 'dhl-shipping-calculator'); ?></p>
                <p><strong><?php _e('Autor:', 'dhl-shipping-calculator'); ?></strong> <a href="https://zuzanak.eu" target="_blank">Lukáš Zuzaňák</a></p>
                <p><strong><?php _e('Verze:', 'dhl-shipping-calculator'); ?></strong> 1.0.0</p>
                
                <h3><?php _e('API Dokumentace:', 'dhl-shipping-calculator'); ?></h3>
                <p><?php _e('Pro podrobnější informace o API si prosím přečtěte:', 'dhl-shipping-calculator'); ?> 
                   <a href="https://developer.dhl.com/api-reference/dhl-express-mydhl-api" target="_blank">
                      https://developer.dhl.com/api-reference/dhl-express-mydhl-api
                   </a>
                </p>
                
                <h3><?php _e('Endpointy:', 'dhl-shipping-calculator'); ?></h3>
                <ul>
                    <li><?php _e('Testovací endpoint:', 'dhl-shipping-calculator'); ?> <code>https://express.api.dhl.com/mydhlapi/test/rates</code></li>
                    <li><?php _e('Produkční endpoint:', 'dhl-shipping-calculator'); ?> <code>https://express.api.dhl.com/mydhlapi/rates</code></li>
                </ul>
            </div>
            
            <!-- Formulář nastavení -->
            <form action="options.php" method="post">
                <?php
                settings_fields('dhl_shipping_options');
                do_settings_sections('dhl_shipping_options');
                ?>
                <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('API Endpoint', 'dhl-shipping-calculator'); ?></th>
                    <td>
                        <label>
                            <input type="radio" name="dhl_use_production" value="0" <?php checked(get_option('dhl_use_production', '0'), '0'); ?>>
                            <?php _e('Testovací endpoint (https://express.api.dhl.com/mydhlapi/test/rates)', 'dhl-shipping-calculator'); ?>
                            </label>
                            <br>
                        <label>
                            <input type="radio" name="dhl_use_production" value="1" <?php checked(get_option('dhl_use_production', '0'), '1'); ?>>
                            <?php _e('Produkční endpoint (https://express.api.dhl.com/mydhlapi/rates)', 'dhl-shipping-calculator'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Jazyk pluginu', 'dhl-shipping-calculator'); ?></th>
                        <td>
                            <select name="dhl_shipping_language">
                                <option value="auto" <?php selected(get_option('dhl_shipping_language', 'auto'), 'auto'); ?>><?php esc_html_e('Automaticky (podle webu)', 'dhl-shipping-calculator'); ?></option>
                                <option value="cs_CZ" <?php selected(get_option('dhl_shipping_language', 'auto'), 'cs_CZ'); ?>><?php esc_html_e('Čeština', 'dhl-shipping-calculator'); ?></option>
                                <option value="en" <?php selected(get_option('dhl_shipping_language', 'auto'), 'en'); ?>><?php esc_html_e('Angličtina', 'dhl-shipping-calculator'); ?></option>
                            </select>
                        </td>
                </tr>
                </table>
                <?php submit_button(__('Uložit nastavení', 'dhl-shipping-calculator')); ?>
            </form>
        </div>
        <?php
    }

    public function render_shipping_form() {
        ob_start();
        ?>
        <form id="dhl-shipping-form" class="container mt-4">
            <h3><?php _e('Údaje o odesilateli', 'dhl-shipping-calculator'); ?></h3>
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label"><?php _e('Odesílací PSČ:', 'dhl-shipping-calculator'); ?></label>
                    <input type="text" name="shipper_postal_code" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php _e('Odesílací město:', 'dhl-shipping-calculator'); ?></label>
                    <input type="text" name="shipper_city" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php _e('Odesílací země (kód):', 'dhl-shipping-calculator'); ?></label>
                    <input type="text" name="shipper_country_code" class="form-control" placeholder="<?php _e('např. CZ', 'dhl-shipping-calculator'); ?>" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label"><?php _e('Adresní řádek 1:', 'dhl-shipping-calculator'); ?></label>
                    <input type="text" name="shipper_address_line1" class="form-control" required>
                </div>
            </div>
    
            <h3 class="mt-3"><?php _e('Údaje o příjemci', 'dhl-shipping-calculator'); ?></h3>
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label"><?php _e('Cílové PSČ:', 'dhl-shipping-calculator'); ?></label>
                    <input type="text" name="receiver_postal_code" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php _e('Cílové město:', 'dhl-shipping-calculator'); ?></label>
                    <input type="text" name="receiver_city" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php _e('Cílová země (kód):', 'dhl-shipping-calculator'); ?></label>
                    <input type="text" name="receiver_country_code" class="form-control" placeholder="<?php _e('např. DE', 'dhl-shipping-calculator'); ?>" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label"><?php _e('Adresní řádek 1:', 'dhl-shipping-calculator'); ?></label>
                    <input type="text" name="receiver_address" class="form-control" required>
                </div>
            </div>
    
            <h3 class="mt-3"><?php _e('Údaje o zásilce', 'dhl-shipping-calculator'); ?></h3>
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label"><?php _e('Hmotnost (kg):', 'dhl-shipping-calculator'); ?></label>
                    <input type="number" name="weight" step="0.1" min="0.1" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?php _e('Délka (cm):', 'dhl-shipping-calculator'); ?></label>
                    <input type="number" name="length" step="0.1" min="0.1" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?php _e('Šířka (cm):', 'dhl-shipping-calculator'); ?></label>
                    <input type="number" name="width" step="0.1" min="0.1" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?php _e('Výška (cm):', 'dhl-shipping-calculator'); ?></label>
                    <input type="number" name="height" step="0.1" min="0.1" class="form-control" required>
                </div>
            </div>
    
            <h3 class="mt-3"><?php _e('Doplňkové údaje', 'dhl-shipping-calculator'); ?></h3>
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label"><?php _e('Deklarovaná hodnota:', 'dhl-shipping-calculator'); ?></label>
                    <input type="number" name="declared_value" step="0.01" min="1" class="form-control" value="100" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php _e('Měna:', 'dhl-shipping-calculator'); ?></label>
                    <select name="currency" class="form-control" required>
                        <option value="CZK">CZK</option>
                        <option value="EUR">EUR</option>
                        <option value="USD">USD</option>
                        <option value="GBP">GBP</option>
                    </select>
                </div>
                <div class="col-md-12 mt-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="customs_declarable" id="customs_declarable" checked>
                        <label class="form-check-label" for="customs_declarable">
                            <?php _e('Zásilka podléhá celnímu řízení', 'dhl-shipping-calculator'); ?>
                        </label>
                    </div>
                </div>
                <div class="col-md-12 mt-1">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="next_business_day" id="next_business_day" checked>
                        <label class="form-check-label" for="next_business_day">
                            <?php _e('Doručení následující pracovní den', 'dhl-shipping-calculator'); ?>
                        </label>
                    </div>
                </div>
            </div>
    
            <button type="submit" class="btn btn-primary mt-3"><?php _e('Spočítat přepravné', 'dhl-shipping-calculator'); ?></button>
        </form>
        <div id="dhl-shipping-result" class="mt-3"></div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('dhl-shipping-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Zobrazit informaci o načítání
                    document.getElementById('dhl-shipping-result').innerHTML = '<p><?php _e('Načítám data z DHL API...', 'dhl-shipping-calculator'); ?></p>';
                    
                    const form = this;
                    const formData = new FormData(form);
                    formData.append('action', 'dhl_calculate_shipping');
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: new URLSearchParams(formData)
                    })
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('dhl-shipping-result').innerHTML = data;
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('dhl-shipping-result').innerHTML = '<p><?php _e('Nastala chyba při komunikaci s API.', 'dhl-shipping-calculator'); ?></p>';
                    });
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }

    public function calculate_shipping() {
        $api_key = get_option($this->option_name);
        $api_user = get_option($this->user_option_name);
        $auth = base64_encode($api_user . ':' . $api_key);

        // Vytvořit Message-Reference správné délky (28-36 znaků)
        $message_reference = 'WP_DHL_PLUGIN_' . uniqid() . '_REF';
        // Ujistíme se, že délka je mezi 28-36 znaky
        if (strlen($message_reference) < 28) {
            $message_reference .= str_repeat('X', 28 - strlen($message_reference));
        } elseif (strlen($message_reference) > 36) {
            $message_reference = substr($message_reference, 0, 36);
        }

        // Nastavte datum a čas plánované přepravy (aktuální datum + 1 den)
        $planned_date = date('Y-m-d', strtotime('+1 day')) . 'T13:00:00GMT+00:00';
        // Získání dat z formuláře
        $shipper_postal_code = isset($_POST['shipper_postal_code']) ? sanitize_text_field($_POST['shipper_postal_code']) : '15000';
        $shipper_city = isset($_POST['shipper_city']) ? sanitize_text_field($_POST['shipper_city']) : 'Prague';
        $shipper_country_code = isset($_POST['shipper_country_code']) ? sanitize_text_field($_POST['shipper_country_code']) : 'CZ';
        $shipper_address_line1 = isset($_POST['shipper_address_line1']) ? sanitize_text_field($_POST['shipper_address_line1']) : '';

        $receiver_postal_code = isset($_POST['receiver_postal_code']) ? sanitize_text_field($_POST['receiver_postal_code']) : '0010';
        $receiver_city = isset($_POST['receiver_city']) ? sanitize_text_field($_POST['receiver_city']) : 'Oslo';
        $receiver_country_code = isset($_POST['receiver_country_code']) ? sanitize_text_field($_POST['receiver_country_code']) : 'NO';
        $receiver_address = isset($_POST['receiver_address']) ? sanitize_text_field($_POST['receiver_address']) : '';

        $package_weight = isset($_POST['weight']) ? floatval($_POST['weight']) : 10.5;
        $package_length = isset($_POST['length']) ? floatval($_POST['length']) : 25;
        $package_width = isset($_POST['width']) ? floatval($_POST['width']) : 35;
        $package_height = isset($_POST['height']) ? floatval($_POST['height']) : 15;

        $declared_value = isset($_POST['declared_value']) ? floatval($_POST['declared_value']) : 1000;
        $currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : 'CZK';

        // Příprava dat pro DHL API podle screenu
        $data = [
            "customerDetails" => [
                "shipperDetails" => [
                    "postalCode" => $shipper_postal_code,
                    "cityName" => $shipper_city,
                    "countryCode" => $shipper_country_code,
                    "addressLine1" => $shipper_address_line1
                ],
                "receiverDetails" => [
                    "postalCode" => $receiver_postal_code,
                    "cityName" => $receiver_city,
                    "countryCode" => $receiver_country_code,
                    "addressLine1" => $receiver_address
                ]
            ],
            "accounts" => [
                [
                    "number" => "304230190",
                    "typeCode" => "shipper"
                ]
            ],
            "plannedShippingDateAndTime" => $planned_date,
            "unitOfMeasurement" => "metric",
            "isCustomsDeclarable" => true,
            "nextBusinessDay" => true,
            "monetaryAmount" => [
                [
                    "typeCode" => "declaredValue",
                    "value" => $declared_value,
                    "currency" => $currency
                ]
            ],
            "packages" => [
                [
                    "weight" => $package_weight,
                    "dimensions" => [
                        "length" => $package_length,
                        "width" => $package_width,
                        "height" => $package_height
                    ]
                ]
            ]
        ];

        // API Endpoint
        $use_production = get_option('dhl_use_production', '0');
        $api_url = $use_production == '1' 
            ? 'https://express.api.dhl.com/mydhlapi/rates'  // Produkční
            : 'https://express.api.dhl.com/mydhlapi/test/rates'; // Testovací

        // Volání API
        $response = wp_remote_post($api_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $auth,
                'Message-Reference' => $message_reference,
                'Message-Reference-Date' => date('Y-m-d\TH:i:s\Z'),
                'Plugin-Name' => 'DHL_Shipping_Calculator',
                'Plugin-Version' => '1.0.0'
            ],
            'body' => json_encode($data),
        ]);

        // Předpokládáme, že $response obsahuje odpověď z API
        if (!is_wp_error($response)) {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code == 200) {
            $result = json_decode($body, true);
            
            if (isset($result['products']) && count($result['products']) > 0) {
                echo '<div class="dhl-rates-results mt-4">';
                echo '<h3 class="mb-3">Dostupné přepravní služby DHL</h3>';
                
                // Responzivní tabulka s Bootstrap 5
                echo '<div class="table-responsive">';
                echo '<table class="table table-striped table-hover table-bordered align-middle">';
                echo '<thead class="table-dark">';
                echo '<tr class="text-center">';
                echo '<th scope="col">Služba</th>';
                echo '<th scope="col">Základní cena (CZK)</th>';
                echo '<th scope="col">Příplatky (CZK)</th>';
                echo '<th scope="col">Celková cena (CZK)</th>';
                echo '<th scope="col">Celková cena (EUR)</th>';
                echo '<th scope="col">Datum doručení</th>';
                echo '<th scope="col">Doba přepravy (dny)</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                foreach ($result['products'] as $product) {
                    echo '<tr>';
                    echo '<td class="fw-bold">' . esc_html($product['productName']) . '</td>';
                
                    // Základní cena v CZK
                    $base_price_czk = 'N/A';
                    // Příplatky v CZK
                    $surcharges_czk = 0;
                
                    // Procházíme detailní rozpis cen pro CZK
                    if (isset($product['detailedPriceBreakdown'])) {
                        foreach ($product['detailedPriceBreakdown'] as $priceBreakdown) {
                            if ($priceBreakdown['priceCurrency'] == 'CZK') {
                                if (isset($priceBreakdown['breakdown']) && is_array($priceBreakdown['breakdown'])) {
                                    // První položka je obvykle základní cena služby
                                    if (isset($priceBreakdown['breakdown'][0])) {
                                        $base_price_czk = number_format($priceBreakdown['breakdown'][0]['price'], 2, ',', ' ') . ' CZK';
                                    }
                        
                                    // Další položky jsou příplatky
                                    for ($i = 1; $i < count($priceBreakdown['breakdown']); $i++) {
                                        $surcharges_czk += $priceBreakdown['breakdown'][$i]['price'];
                                    }
                                }
                                break;
                            }
                        }
                    }
                
                    echo '<td class="text-end">' . $base_price_czk . '</td>';
                    echo '<td class="text-end">' . number_format($surcharges_czk, 2, ',', ' ') . ' CZK</td>';
                
                    // Celková cena v CZK
                    $total_czk = 'N/A';
                    foreach ($product['totalPrice'] as $price) {
                        if ($price['priceCurrency'] == 'CZK') {
                            $total_czk = number_format($price['price'], 2, ',', ' ') . ' CZK';
                            break;
                        }
                    }
                    echo '<td class="text-end fw-bold">' . $total_czk . '</td>';
                
                    // Celková cena v EUR
                    $total_eur = 'N/A';
                    foreach ($product['totalPrice'] as $price) {
                        if ($price['priceCurrency'] == 'EUR') {
                            $total_eur = number_format($price['price'], 2, ',', ' ') . ' EUR';
                            break;
                        }
                    }
                    echo '<td class="text-end">' . $total_eur . '</td>';
                
                    // Datum doručení
                    $delivery_date = 'N/A';
                    if (isset($product['deliveryCapabilities']['estimatedDeliveryDateAndTime'])) {
                        $date_time = new DateTime($product['deliveryCapabilities']['estimatedDeliveryDateAndTime']);
                        $delivery_date = $date_time->format('d.m.Y');
                    }
                    echo '<td class="text-center">' . $delivery_date . '</td>';
                
                    // Doba přepravy
                    $transit_days = isset($product['deliveryCapabilities']['totalTransitDays']) ? 
                        $product['deliveryCapabilities']['totalTransitDays'] : 'N/A';
                    echo '<td class="text-center">' . $transit_days . '</td>';
                
                    echo '</tr>';
                }
                
                echo '</tbody>';
                echo '</table>';
                echo '</div>'; // End of table-responsive
                
                // Poznámka s využitím Bootstrap 5 karet
                echo '<div class="card mt-3 border-info">';
                echo '<div class="card-body p-3">';
                echo '<h5 class="card-title"><i class="bi bi-info-circle-fill text-info me-2"></i>Informace o cenách</h5>';
                echo '<p class="card-text mb-0">Příplatky zahrnují palivový příplatek a další dodatečné poplatky. Všechny ceny jsou uvedeny bez DPH.</p>';
                echo '</div>';
                echo '</div>';
                
                echo '</div>'; // End of dhl-rates-results
            } else {
                echo '<div class="alert alert-warning" role="alert">';
                echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
                echo 'Nenalezeny žádné přepravní možnosti. Zkuste změnit parametry zásilky.';
                echo '</div>';
            }
            } else {
            echo '<div class="alert alert-danger" role="alert">';
            echo '<i class="bi bi-x-circle-fill me-2"></i>';
            echo 'Chyba API: ' . $status_code;
            echo '</div>';
            echo '<pre class="bg-light p-3 mt-3 rounded"><code>' . esc_html($body) . '</code></pre>';
            }
        } else {
            echo '<div class="alert alert-danger" role="alert">';
            echo '<i class="bi bi-x-circle-fill me-2"></i>';
            echo 'Chyba při komunikaci s API: ' . $response->get_error_message();
            echo '</div>';
        }
    }
}

new DHLShippingCalculator();
