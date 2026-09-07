<?php

declare(strict_types=1);

use PaymentEngine\Sdk\Client\PaymentEngineClient;
use Remita\GravityForms\Support\AmountNormalizer;
use Remita\GravityForms\Support\PaymentIdentifier;
use Remita\GravityForms\Support\PaymentStatusMapper;
use Remita\GravityForms\Support\PaymentUpdateService;

if (!class_exists('GFForms')) {
    die();
}

GFForms::include_payment_addon_framework();

class Remita_GF_Payment_AddOn extends GFPaymentAddOn
{
    protected $_version = '0.1.0';
    protected $_min_gravityforms_version = '2.5';
    protected $_slug = 'remita-gravity-forms';
    protected $_path = 'remita-gravity-forms/remita-gravity-forms.php';
    protected $_full_path = __FILE__;
    protected $_title = 'Remita Checkout Gateway';
    protected $_short_title = 'Remita Checkout';
    protected $_supports_callbacks = true;
    protected $_requires_credit_card = false;

    private static $_instance = null;

    public static function get_instance()
    {
        if (self::$_instance == null) {
            self::$_instance = new Remita_GF_Payment_AddOn();
        }
        return self::$_instance;
    }

    public function init_frontend()
    {
        parent::init_frontend();

        // Register custom callback handler
        add_action('parse_request', [$this, 'handle_callback_request']);
    }

    public function plugin_settings_fields()
    {
        return [
            [
                'title' => 'Remita Checkout API Settings',
                'fields' => [
                    [
                        'name' => 'paymentEngineBaseUrl',
                        'label' => 'Remita Checkout Base URL',
                        'type' => 'text',
                        'class' => 'medium',
                        'default_value' => 'https://api-checkout-qa.systemspecsng.com',
                    ],
                    [
                        'name' => 'paymentEngineSecretKey',
                        'label' => 'Secret Key',
                        'type' => 'text',
                        'class' => 'medium',
                    ],
                    [
                        'name' => 'defaultPhoneNumber',
                        'label' => 'Default Phone Number',
                        'type' => 'text',
                        'class' => 'medium',
                        'default_value' => '08000000000',
                        'tooltip' => 'Used as a fallback if the user does not provide a phone number on the form.',
                    ]
                ]
            ]
        ];
    }

    public function feed_settings_fields()
    {
        return [
            [
                'title' => 'Transaction Settings',
                'fields' => [
                    [
                        'name' => 'feedName',
                        'label' => 'Name',
                        'type' => 'text',
                        'class' => 'medium',
                        'required' => true,
                        'tooltip' => 'Enter a feed name to uniquely identify this setup.',
                    ],
                    [
                        'name' => 'transactionType',
                        'label' => 'Transaction Type',
                        'type' => 'select',
                        'choices' => [
                            ['label' => 'Products and Services', 'value' => 'product'],
                            ['label' => 'Subscription', 'value' => 'subscription'],
                        ],
                    ],
                ]
            ],
            [
                'title' => 'Field Mapping',
                'fields' => [
                    [
                        'name' => 'mappedFirstName',
                        'label' => 'First Name',
                        'type' => 'field_select',
                    ],
                    [
                        'name' => 'mappedLastName',
                        'label' => 'Last Name',
                        'type' => 'field_select',
                    ],
                    [
                        'name' => 'mappedEmail',
                        'label' => 'Email',
                        'type' => 'field_select',
                    ],
                    [
                        'name' => 'mappedPhone',
                        'label' => 'Phone Number',
                        'type' => 'field_select',
                        'tooltip' => 'Select the phone number field from your form. If empty, the default from Remita Settings will be used.',
                    ],
                ]
            ],
            [
                'title' => 'Other Settings',
                'dependency' => [
                    'field' => 'transactionType',
                    'values' => ['subscription', 'product']
                ],
                'fields' => [
                    [
                        'name' => 'paymentAmount',
                        'label' => 'Payment Amount',
                        'type' => 'select',
                        'choices' => [
                            ['label' => 'Form Total', 'value' => 'form_total'],
                        ],
                    ],
                ]
            ]
        ];
    }

    public function supported_billing_intervals()
    {
        return [
            'day' => ['min' => 1, 'max' => 365],
            'week' => ['min' => 1, 'max' => 52],
            'month' => ['min' => 1, 'max' => 12],
            'year' => ['min' => 1, 'max' => 10],
        ];
    }

    public function redirect_url($feed, $submission_data, $form, $entry)
    {
        $baseUrl = $this->get_plugin_setting('paymentEngineBaseUrl');
        $secretKey = $this->get_plugin_setting('paymentEngineSecretKey');

        if (empty($baseUrl) || empty($secretKey)) {
            $this->add_note($entry['id'], 'Remita Error: API settings are incomplete.');
            return false;
        }

        $client = new PaymentEngineClient($baseUrl, $secretKey);
        $amountInKobo = AmountNormalizer::toKobo((float)$submission_data['payment_amount']);
        
        $callbackUrl = site_url('/?callback=remita_payment_engine&entry_id=' . $entry['id']);
        $paymentIdentifier = PaymentIdentifier::build((int) $entry['id']);

        $firstNameFieldId = rgar($feed['meta'], 'mappedFirstName');
        $lastNameFieldId = rgar($feed['meta'], 'mappedLastName');
        $emailFieldId = rgar($feed['meta'], 'mappedEmail');
        $phoneFieldId = rgar($feed['meta'], 'mappedPhone');

        $firstName = !empty($firstNameFieldId) ? rgar($entry, $firstNameFieldId) : rgar($entry, '1.3');
        $lastName = !empty($lastNameFieldId) ? rgar($entry, $lastNameFieldId) : rgar($entry, '1.6');
        $email = !empty($emailFieldId) ? rgar($entry, $emailFieldId) : rgar($entry, '2');
        
        $phone = !empty($phoneFieldId) ? rgar($entry, $phoneFieldId) : '';
        if (empty($phone)) {
            $phone = $this->get_plugin_setting('defaultPhoneNumber') ?: '08000000000';
        }

        $payload = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'phoneNumber' => $phone,
            'paymentIdentifier' => $paymentIdentifier,
            'currency' => 'NGN',
            'narration' => 'Gravity Forms Entry #' . $entry['id'],
            'amount' => $amountInKobo,
            'returnUrl' => $callbackUrl,
        ];

        try {
            $response = $client->redirectCheckout->initiate($payload);
            
            // Save the payment identifier for later verification
            gform_update_meta($entry['id'], 'remita_payment_identifier', $paymentIdentifier);
            
            $paymentLink = (string) ($response['data']['paymentLink'] ?? '');
            if ($paymentLink === '') {
                throw new \RuntimeException('Remita did not return a redirect checkout URL.');
            }
            return $paymentLink;
        } catch (\Throwable $exception) {
            $this->add_note($entry['id'], 'Remita Initialization Error: ' . $exception->getMessage());
            return false;
        }
    }

    public function handle_callback_request($wp)
    {
        if (isset($_GET['callback']) && $_GET['callback'] === 'remita_payment_engine' && isset($_GET['entry_id'])) {
            $entryId = (int) $_GET['entry_id'];
            $entry = GFAPI::get_entry($entryId);

            if (is_wp_error($entry) || empty($entry)) {
                wp_die('Invalid Entry ID', 'Remita Error', ['response' => 400]);
            }

            $paymentIdentifier = sanitize_text_field($_GET['paymentIdentifier'] ?? '');

            if ($paymentIdentifier === '') {
                $uri = $_SERVER['REQUEST_URI'] ?? '';
                if (strpos($uri, '?paymentIdentifier=') !== false) {
                    $parts = explode('?paymentIdentifier=', $uri);
                    $paymentIdentifier = sanitize_text_field(explode('&', $parts[1])[0] ?? '');
                }
            }

            if (empty($paymentIdentifier)) {
                wp_redirect(home_url());
                exit;
            }

            $storedIdentifier = (string) gform_get_meta($entryId, 'remita_payment_identifier');

            if ($storedIdentifier === '' || !hash_equals($storedIdentifier, $paymentIdentifier)) {
                wp_die('Payment identifier mismatch.', 'Remita Error', ['response' => 400]);
            }

            $resolvedEntryId = PaymentIdentifier::extractEntryId($paymentIdentifier);

            if ($resolvedEntryId !== null && $resolvedEntryId !== $entryId) {
                wp_die('Entry mismatch for payment identifier.', 'Remita Error', ['response' => 400]);
            }

            $baseUrl = $this->get_plugin_setting('paymentEngineBaseUrl');
            $secretKey = $this->get_plugin_setting('paymentEngineSecretKey');

            $client = new PaymentEngineClient($baseUrl, $secretKey);
            
            try {
                $response = $client->payments->query($paymentIdentifier);
                $updateService = new PaymentUpdateService();
                $mappedStatus = $updateService->applyQueryResponse(
                    $entry,
                    $response,
                    function () use ($entry, $entryId, $paymentIdentifier): void {
                        $action = [
                            'id' => $paymentIdentifier,
                            'type' => 'complete_payment',
                            'transaction_id' => $paymentIdentifier,
                            'amount' => $entry['payment_amount'],
                            'entry_id' => $entryId,
                        ];

                        $this->complete_payment($entry, $action);
                    },
                    function () use ($entry, $entryId, $paymentIdentifier): void {
                        $action = [
                            'id' => $paymentIdentifier,
                            'type' => 'fail_payment',
                            'transaction_id' => $paymentIdentifier,
                            'entry_id' => $entryId,
                        ];

                        $this->fail_payment($entry, $action);
                    },
                    function (string $note) use ($entryId): void {
                        $this->add_note($entryId, $note);
                    }
                );

                $this->redirect_after_callback($entry, $mappedStatus);
            } catch (\Throwable $exception) {
                wp_die(esc_html($exception->getMessage()), 'Remita Error', ['response' => 500]);
            }
        }
    }

    private function redirect_after_callback(array $entry, string $mappedStatus): void
    {
        if ($mappedStatus === PaymentStatusMapper::STATUS_SUCCESS) {
            $form = GFAPI::get_form($entry['form_id']);
            $confirmations = $form['confirmations'] ?? [];
            $confirmation = reset($confirmations);

            if (!empty($confirmation)) {
                if (($confirmation['type'] ?? '') === 'redirect' && !empty($confirmation['url'])) {
                    wp_redirect($confirmation['url']);
                    exit;
                }

                if (($confirmation['type'] ?? '') === 'page' && !empty($confirmation['pageId'])) {
                    wp_redirect(get_permalink($confirmation['pageId']));
                    exit;
                }
            }
        }

        $sourceUrl = !empty($entry['source_url']) ? $entry['source_url'] : home_url();
        $redirectUrl = add_query_arg(
            [
                'payment_status' => $mappedStatus,
                'entry' => (int) $entry['id'],
            ],
            $sourceUrl
        );

        wp_redirect($redirectUrl);
        exit;
    }
}
