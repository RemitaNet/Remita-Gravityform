<?php
/**
 * Plugin Name: Remita Gravity Forms Add-On
 * Description: Remita Payment Gateway Integration for Gravity Forms
 * Version: 0.1.0
 * Author: Remita
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('REMITA_GF_PLUGIN_FILE', __FILE__);
define('REMITA_GF_PLUGIN_DIR', plugin_dir_path(__FILE__));

if (!function_exists('remita_gf_require_sdk')) {
    function remita_gf_require_sdk(): void
    {
        $candidates = [
            REMITA_GF_PLUGIN_DIR . 'vendor/payment-engine-sdk/index.php',
            REMITA_GF_PLUGIN_DIR . '../../../developer-tools/server-side-sdks/php/src/index.php',
            REMITA_GF_PLUGIN_DIR . '../../developer-tools/server-side-sdks/php/src/index.php',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                require_once $candidate;
                return;
            }
        }

        throw new RuntimeException('Payment Engine PHP SDK bootstrap file could not be found.');
    }
}

add_action('gform_loaded', static function () {
    if (!method_exists('GFForms', 'include_payment_addon_framework')) {
        return;
    }

    remita_gf_require_sdk();
    require_once REMITA_GF_PLUGIN_DIR . 'src/Support/AmountNormalizer.php';
    require_once REMITA_GF_PLUGIN_DIR . 'src/Support/PaymentIdentifier.php';
    require_once REMITA_GF_PLUGIN_DIR . 'src/Support/PaymentStatusMapper.php';
    require_once REMITA_GF_PLUGIN_DIR . 'src/Support/PaymentUpdateService.php';
    require_once REMITA_GF_PLUGIN_DIR . 'src/class-remita-gf-payment-addon.php';

    GFAddOn::register('Remita_GF_Payment_AddOn');
}, 5);

add_filter('gform_currencies', static function ($currencies) {
    $currencies['NGN'] = [
        'name'               => __('Nigerian Naira', 'remita-gravity-forms'),
        'symbol_left'        => '₦',
        'symbol_right'       => '',
        'symbol_padding'     => ' ',
        'thousand_separator' => ',',
        'decimal_separator'  => '.',
        'decimals'           => 2,
    ];
    return $currencies;
});
