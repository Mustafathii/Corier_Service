<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

class WhatsAppController extends Controller
{
    public function sendMessage(Request $request)
    {
        // إعداد WhatsApp API
        $whatsapp = new WhatsAppCloudApi([
            'from_phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        ]);

        try {
            // إرسال الرسالة
            $response = $whatsapp->sendTextMessage('201234567890', 'مرحباً من Laravel! 👋');

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال الرسالة بنجاح!',
                'response' => $response
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في إرسال الرسالة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // تجربة سريعة من البراوزر
public function test()
{
    // طبع الـ token عشان نتأكد إنه صحيح
    $token = env('WHATSAPP_ACCESS_TOKEN');
    echo "Token: " . substr($token, 0, 20) . "...<br>";

    $whatsapp = new WhatsAppCloudApi([
        'from_phone_number_id' => '674083952457074',
        'access_token' => $token,
    ]);

    try {
        $response = $whatsapp->sendTextMessage('+201017172266', 'رسالة تجريبية من الفتول');
        return "Full Response: " . print_r($response, true);
    } catch (\Exception $e) {
        return "خطأ: " . $e->getMessage();
    }
}

  public function testDirect()
    {
        $url = "https://graph.facebook.com/v18.0/674083952457074/messages";
        $token = "EAAR4gpHn0KgBO5hjOETFIZB8QiRN4sZBDjo5nbM2fIFQlpupO9sAs6qPFqCctIUhUhd8fpOf5PdoFlHPfUCP3IJVot3qzYK3t3dXVp3vwhxxk2J0OC8iZASaZCy6ZCnvoPfi6zx13h2aP6cRVJj5mTa4X0jAevIxRUkmDWqaQ0lCwoCl1YBN1i8lmZAK7GstuxnYEUZCoQ9t4sjBTZA35bDuBvcC1wJtjjXGaZAVZBaSf7fHjO8IgZD";

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => '201017172266',
            'text' => ['body' => 'انا فتول ، غير قادر علي الوصول اليك علي الهاتف يرجي الاتصال بي  ، قد لا اتمكن من توصيل طلبك اليوم ، سيتم تأجيله في حالة عدم الرد '],
        ];

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        return "Direct Response: " . $response;
    }

//     public function testTemplate()
// {
//     $url = "https://graph.facebook.com/v18.0/674083952457074/messages";
//     $token = env('WHATSAPP_ACCESS_TOKEN');

//     $data = [
//         'messaging_product' => 'whatsapp',
//         'to' => '201017172266',
//         'type' => 'template',
//         'template' => [
//             'name' => 'hello_world',
//             'language' => ['code' => 'en_US']
//         ]
//     ];

//     $headers = [
//         'Authorization: Bearer ' . $token,
//         'Content-Type: application/json'
//     ];

//     $ch = curl_init();
//     curl_setopt($ch, CURLOPT_URL, $url);
//     curl_setopt($ch, CURLOPT_POST, 1);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
//     curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

//     $response = curl_exec($ch);
//     curl_close($ch);

//     return "Template Response: " . $response;
// }


}
