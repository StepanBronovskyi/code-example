<?php
/**
 * Created by PhpStorm.
 * User: 1
 * Date: 17.08.2020
 * Time: 11:20
 */

namespace ModuleVendor_WezzPostcode\WezzPostcode\Block\Postcode;


class LayoutProcessor extends \Wezz\Postcode\Block\Checkout\LayoutProcessor
{
    /**
     * @var \Wezz\Postcode\Helper\Config
     */
    protected $helperConfig;

    /** @var \Magento\Checkout\Model\Session\Proxy  */
    protected $checkoutSession;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * LayoutProcessor constructor.
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Wezz\Postcode\Helper\Config $helperConfig,
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session\Proxy $checkoutSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->helperConfig = $helperConfig;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($helperConfig, $context, $data);
    }

    /**
     * Check post code method
     *
     * @param $result
     * @return bool
     */
    private function checkPostcode($result)
    {
        if ($this->helperConfig->getEnabled() && isset(
                $result['components']
                ['checkout']
                ['children']
                ['steps']
                ['children']
                ['shipping-step']
                ['children']
                ['shippingAddress']
                ['children']
                ['shipping-address-fieldset']
            )
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param array $result
     * @return array
     */
    public function process($result)
    {
        if (!$this->checkPostcode($result)) {
            return $result;
        }

        $shippingFields = $result['components']['checkout']['children']['steps']['children']
        ['shipping-step']['children']['shippingAddress']['children']
        ['shipping-address-fieldset']['children'];

        $shippingFields['postcode_fieldset'] = $this->getFieldArray('shippingAddress', 'shipping');

        $shippingFields = $this->changeFieldPosition($shippingFields);

        $result['components']['checkout']['children']['steps']['children']
        ['shipping-step']['children']['shippingAddress']['children']
        ['shipping-address-fieldset']['children'] = $shippingFields;

        $result = $this->getBillingFormFields($result);

        return $result;
    }

    /**
     * Get billing form fields method
     *
     * @param $result
     * @return mixed
     */
    public function getBillingFormFields($result)
    {
        $fieldsAreShared = $this->scopeConfig
            ->getValue('checkout/options/display_billing_address_on', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if($fieldsAreShared) {
            if (isset(
                $result['components']['checkout']['children']['steps']['children']
                ['billing-step']['children']['payment']['children']['afterMethods']['children']['billing-address-form']
            )) {
                $billingFields = $result['components']['checkout']['children']['steps']['children']
                ['billing-step']['children']['payment']['children']['afterMethods']['children']['billing-address-form']['children']['form-fields']['children'];

                $billingFields['postcode_fieldset'] = $this->getFieldArray('billingAddressshared', 'billing');
                $billingFields = $this->changeFieldPosition($billingFields);

                $result['components']['checkout']['children']['steps']['children']['billing-step']['children']
                ['payment']['children']['afterMethods']['children']['billing-address-form']['children']['form-fields']['children'] = $billingFields;
            }
        } else {
            if (isset(
                $result['components']['checkout']['children']['steps']['children']
                ['billing-step']['children']['payment']['children']['payments-list']
            )) {
                $paymentForms = $result['components']['checkout']['children']['steps']['children']
                ['billing-step']['children']['payment']['children']
                ['payments-list']['children'];

                foreach ($paymentForms as $paymentMethodForm => $paymentMethodValue) {
                    $paymentMethodCode = str_replace('-form', '', $paymentMethodForm);

                    if (!isset($result['components']['checkout']['children']['steps']['children']['billing-step']
                        ['children']['payment']['children']['payments-list']['children'][$paymentMethodCode . '-form'])) {
                        continue;
                    }

                    $billingFields = $result['components']['checkout']['children']['steps']['children']
                    ['billing-step']['children']['payment']['children']
                    ['payments-list']['children'][$paymentMethodCode . '-form']['children']['form-fields']['children'];

                    $billingFields['postcode_fieldset'] = $this->getFieldArray('billingAddress' . $paymentMethodCode, 'billing');
                    $billingFields = $this->changeFieldPosition($billingFields);


                    $result['components']['checkout']['children']['steps']['children']['billing-step']
                    ['children']['payment']['children']['payments-list']['children'][$paymentMethodCode . '-form']
                    ['children']['form-fields']['children'] = $billingFields;
                }
            }
        }

        return $result;
    }

    /**
     * Method to change address field positions
     *
     * @param $addressFields
     * @return mixed
     */
    public function changeFieldPosition($addressFields)
    {
        $postcodePosition = $addressFields['postcode']['sortOrder'];
        foreach($addressFields as $key => $field) {
            if(isset($field['sortOrder'])) {
                if($field['sortOrder'] >= $postcodePosition) {
                    $addressFields[$key]['sortOrder'] = $field['sortOrder'] + 1;
                }
            }
        }

        if(isset($addressFields['postcode_fieldset'])) {
            $addressFields['postcode_fieldset']['sortOrder'] = $postcodePosition;
        }

        return $addressFields;
    }

    /**
     * Get field array method
     *
     * @param $customScope
     * @param $addressType
     * @return array
     */
    private function getFieldArray($customScope, $addressType)
    {
        return [
            'component' => 'Wezz_Postcode/js/view/postcode',
            'type' => 'group',
            'config' => [
                "customScope" => $customScope,
                "template" => 'Wezz_Postcode/form/fieldset',
                "additionalClasses" => "postcode_fieldset field iosc-whole iosc-start iosc-break",
                "loaderImageHref" => $this->getViewFileUrl('images/loader-1.gif')
            ],
            'sortOrder' => '850',
            'children' => $this->getFields($customScope, $addressType),
            'provider' => 'checkoutProvider',
            'addressType' => $addressType
        ];
    }


    /**
     * Get fields method
     *
     * @param $customScope
     * @param $addressType
     * @return array
     */
    public function getFields($customScope, $addressType)
    {
        $quote = $this->checkoutSession->getQuote();
        if ($customScope == 'shippingAddress') {
            $addressObj = $quote->getShippingAddress();
        } else {
            $addressObj = $quote->getBillingAddress();
        }

        $postcodeFields =
            [
                'postcode_postcode' => [
                    'component' => 'Magento_Ui/js/form/element/abstract',
                    'config' => [
                        "customScope" => $customScope,
                        "template" => 'ui/form/field',
                        "elementTmpl" => 'ui/form/element/input',
                        "additionalClasses" => 'field iosc-half iosc-start iosc-break',
                        'value' => $addressObj->getPostcode()
                    ],
                    'provider' => 'checkoutProvider',
                    'dataScope' => $customScope . '.postcode_postcode',
                    'label' => __('Postcode'),
                    'sortOrder' => '800',
                    'validation' => [
                        'required-entry' => true,
                        'postcode_validation' => true,
                    ],
                ],
                'postcode_housenumber' => [
                    'component' => 'Magento_Ui/js/form/element/abstract',
                    'config' => [
                        "customScope" => $customScope,
                        "template" => 'ui/form/field',
                        "additionalClasses" => 'field iosc-quarter iosc-between',
                        'value' => $addressObj->getStreetLine(2)
                    ],
                    'provider' => 'checkoutProvider',
                    'dataScope' => $customScope . '.postcode_housenumber',
                    'label' => __('Housenumber'),
                    'sortOrder' => '801',
                    'validation' => [
                        'required-entry' => true,
                        'validate-housenumber' => true,
                    ],
                ],
                'postcode_housenumber_addition' => [
                    'component' => 'Magento_Ui/js/form/element/select',
                    'config' => [
                        "customScope" => $customScope,
                        "template" => 'ui/form/field',
                        "elementTmpl" => 'ui/form/element/select',
                        "additionalClasses" => 'field iosc-quarter iosc-end',
                        'value' => $addressObj->getStreetLine(3)
                    ],
                    'provider' => 'checkoutProvider',
                    'dataScope' => $customScope . '.postcode_housenumber_addition',
                    'label' => __('Housenumber'),
                    'sortOrder' => '802',
                    'validation' => [
                        'required-entry' => false,
                    ],
                    'options' => [],
                    'visible' => false,
                ],
                'postcode_housenumber_addition_manual' => [
                    'component' => 'Magento_Ui/js/form/element/abstract',
                    'config' => [
                        "customScope" => $customScope,
                        "template" => 'ui/form/field',
                        "elementTmpl" => 'ui/form/element/input',
                        "additionalClasses" => 'field iosc-quarter iosc-end',
                        'value' => $addressObj->getStreetLine(3)
                    ],
                    'provider' => 'checkoutProvider',
                    'dataScope' => $customScope . '.postcode_housenumber_addition_manual',
                    'label' => __('Housenumber Additional'),
                    'sortOrder' => '803',
                    'validation' => [
                        'required-entry' => false,
                    ],
                    'options' => [],
                    'visible' => false,
                ],
                'postcode_disable' => [
                    'component' => 'Magento_Ui/js/form/element/abstract',
                    'config' => [
                        "customScope" => $customScope,
                        "template" => 'ui/form/field',
                        "elementTmpl" => 'ui/form/element/checkbox',
                        "additionalClasses" => "iosc-whole iosc-start iosc-break manual-postcode",
                    ],
                    'provider' => 'checkoutProvider',
                    'dataScope' => $customScope . '.postcode_disable',
                    'description' => __('Fill out address information manually'),
                    'sortOrder' => '804',
                    'validation' => [
                        'required-entry' => false,
                    ],
                    'addressType' => $addressType
                ]
            ];

        return $postcodeFields;
    }
}