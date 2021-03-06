<?php

namespace DoubleThreeDigital\SimpleCommerce\Http\Controllers;

use DoubleThreeDigital\SimpleCommerce\Contracts\CartRepository;
use DoubleThreeDigital\SimpleCommerce\Events\PostCheckout;
use DoubleThreeDigital\SimpleCommerce\Events\PreCheckout;
use DoubleThreeDigital\SimpleCommerce\Events\StockRunningLow;
use DoubleThreeDigital\SimpleCommerce\Events\StockRunOut;
use DoubleThreeDigital\SimpleCommerce\Exceptions\CustomerNotFound;
use DoubleThreeDigital\SimpleCommerce\Exceptions\NoGatewayProvided;
use DoubleThreeDigital\SimpleCommerce\Facades\Customer;
use DoubleThreeDigital\SimpleCommerce\Facades\Gateway;
use DoubleThreeDigital\SimpleCommerce\Facades\Product;
use DoubleThreeDigital\SimpleCommerce\Http\Requests\Checkout\StoreRequest;
use DoubleThreeDigital\SimpleCommerce\SessionCart;
use Illuminate\Support\Arr;

class CheckoutController extends BaseActionController
{
    use SessionCart;

    public CartRepository $cart;
    public StoreRequest $request;
    public $excludedKeys = ['_token', '_params', '_redirect'];

    public function store(StoreRequest $request)
    {
        $this->cart = $this->getSessionCart();
        $this->request = $request;

        $this
            ->preCheckout()
            ->handleValidation()
            ->handleCustomerDetails()
            ->handlePayment()
            ->handleCoupon()
            ->handleStock()
            ->handleRemainingData()
            ->postCheckout();

        return $this->withSuccess($request, [
            'message' => __('simple-commerce.messages.checkout_complete'),
            'cart'    => $this->cart->toResource(),
        ]);
    }

    protected function preCheckout()
    {
        event(new PreCheckout($this->cart->data));

        return $this;
    }

    protected function handleValidation()
    {
        $checkoutValidationRules = [
            'name' => ['sometimes', 'string'],
            'email' => ['sometimes', 'email'],
        ];

        $gatewayValidationRules = $this->request->has('gateway') ?
            Gateway::use($this->request->get('gateway'))->purchaseRules() :
            [];

        $this->request->validate(array_merge(
            $checkoutValidationRules,
            $gatewayValidationRules
        ));

        return $this;
    }

    protected function handleCustomerDetails()
    {
        $customerData = $this->request->has('customer') ? $this->request->get('customer') : [];

        if ($this->request->has('name') && $this->request->has('email')) {
            $customerData['name'] = $this->request->get('name');
            $customerData['email'] = $this->request->get('email');

            $this->excludedKeys[] = 'name';
            $this->excludedKeys[] = 'email';
        }

        if (isset($customerData['email'])) {
            try {
                $customer = Customer::findByEmail($customerData['email']);
            } catch (CustomerNotFound $e) {
                $customer = Customer::make()
                    ->site($this->guessSiteFromRequest())
                    ->data([
                        'name'  => isset($customerData['name']) ? $customerData['name'] : '',
                        'email' => $customerData['email'],
                    ])
                    ->save();
            }

            $customer->update($customerData);

            $this->cart->update([
                'customer' => $customer->id,
            ]);
        }

        $this->excludedKeys[] = 'customer';

        return $this;
    }

    protected function handlePayment()
    {
        if (! $this->request->has('gateway') && $this->cart->toArray()['is_paid'] === false && $this->cart->data['grand_total'] !== 0) {
            throw new NoGatewayProvided(__('simple-commerce::gateways.no_gateway_provided'));
        }

        $purchase = Gateway::use($this->request->gateway)->purchase($this->request, $this->cart->entry());

        $this->excludedKeys[] = 'gateway';

        foreach (Gateway::use($this->request->gateway)->purchaseRules() as $key => $rule) {
            $this->excludedKeys[] = $key;
        }

        return $this;
    }

    protected function handleCoupon()
    {
        if (isset($this->cart->data['coupon'])) {
            $this->cart->coupon()->redeem();
        }

        return $this;
    }

    protected function handleStock()
    {
        collect($this->cart->data['items'])
            ->each(function ($item) {
                $product = Product::find($item['product']);

                if (isset($product->data['stock'])) {
                    $stock = $product->data['stock'] + $item['quantity'];
                } else {
                    $stock = 1;
                }

                $product->set('stock', $stock);

                if ($stock <= config('simple-commerce.low_stock_threshold')) {
                    event(new StockRunningLow($product, $stock));
                }

                if ($stock <= 0) {
                    event(new StockRunOut($product, $stock));
                }
            });

        return $this;
    }

    protected function handleRemainingData()
    {
        $data = [];

        foreach (Arr::except($this->request->all(), $this->excludedKeys) as $key => $value) {
            if ($value === 'on') {
                $value = true;
            } elseif ($value === 'off') {
                $value = false;
            }

            $data[$key] = $value;
        }

        $this->cart->update($data);

        return $this;
    }

    protected function postCheckout()
    {
        $this->cart->markAsCompleted();
        $this->forgetSessionCart();

        event(new PostCheckout($this->cart->data));

        return $this;
    }
}
