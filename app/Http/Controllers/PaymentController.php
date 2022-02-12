<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Stripe\Exception\CardException;
use Stripe\StripeClient;

class PaymentController extends Controller
{
    private $stripe;
    public function __construct()
    {
        $this->stripe = new StripeClient(config('stripe.api_keys.secret_key'));
    }

    public function index()
    {
        return view('payment');
    }

    public function payment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fullName' => 'required',
            'cardNumber' => 'required',
            'month' => 'required',
            'year' => 'required',
            'cvv' => 'required'
        ]);

        if ($validator->fails()) {
            $request->session()->flash('danger', $validator->errors()->first());
            return response()->redirectTo('/');
        }

        $token = $this->createToken($request);
        if (!empty($token['error'])) {
            $request->session()->flash('danger', $token['error']);
            return response()->redirectTo('/');
        }
        if (empty($token['id'])) {
            $request->session()->flash('danger', 'Payment failed.');
            return response()->redirectTo('/');
        }

        $charge = $this->createCharge($token['id'], 2000);
        if (!empty($charge) && $charge['status'] == 'succeeded') {
            $request->session()->flash('success', 'Payment completed.');
        } else {
            $request->session()->flash('danger', 'Payment failed.');
        }
        return response()->redirectTo('/');
    }

    private function createToken($cardData)
    {
        $token = null;
        try {
            $token = $this->stripe->tokens->create([
                'card' => [
                    'number' => $cardData['cardNumber'],
                    'exp_month' => $cardData['month'],
                    'exp_year' => $cardData['year'],
                    'cvc' => $cardData['cvv']
                ]
            ]);
        } catch (CardException $e) {
            $token['error'] = $e->getError()->message;
        } catch (Exception $e) {
            $token['error'] = $e->getMessage();
        }
        return $token;
    }

    private function createCharge($tokenId, $amount)
    {
        $charge = null;
        try {
            $charge = $this->stripe->charges->create([
                'amount' => $amount,
                'currency' => 'usd',
                'source' => $tokenId,
                'description' => 'My first payment'
            ]);
        } catch (Exception $e) {
            $charge['error'] = $e->getMessage();
        }
        return $charge;
    }
}
