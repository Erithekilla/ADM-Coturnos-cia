<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig; // Import da classe
use MercadoPago\Client\Preference\PreferenceClient;

class PaymentController extends Controller
{
    public function __construct()
    {
        // Define o Access Token globalmente para as chamadas deste controller
        MercadoPagoConfig::setAccessToken(config('MP_ACCESS_TOKEN'));
    }

    public function createPayment()
    {
        // A sua lógica de criação de pagamento aqui...

        
    }
}
