<?php

namespace Rosetta\Helpers;

use Rosetta\Services\Notifications\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\Log;

class CustomHelper
{
    public static function sendWhatsappMessage($recipientNumber, $templateName, $parameters = [])
    {
        $accessToken = env('WHATSAPP_PERMANENT_TOKEN');
        $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');

        $url = "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages";

        // Construct template components only if parameters exist
        $templateComponents = [
            [
                "type" => "body",
                "parameters" => array_map(function ($key, $value) {
                    return [
                        "type" => "text",
                        "parameter_name" => $key,
                        "text" => $value  // Include the key if needed
                    ];
                }, array_keys($parameters), array_values($parameters))
            ]
        ];

        // Construct the API request payload
        $data = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $recipientNumber,
            "type" => "template",
            "template" => [
                "name" => $templateName,
                "language" => ["code" => "en"], // Change this if necessary
            ]
        ];

        // Only include components if parameters exist
        if (!empty($templateComponents)) {
            $data["template"]["components"] = $templateComponents;
        }

        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $response;
        /*$accessToken = env('WHATSAPP_PERMANENT_TOKEN'); // Check or refresh token
        $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');

        $url = "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages";

        $data = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $recipientNumber,
            "type" => "template",
            "template" => [
                "name" => $templateName,
                "language" => ["code" => "en_US"]
            ]
        ];

        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Return response directly, no need to refresh token
        return $response;*/
    }


    public static function sendWhatsappNotification($blanketOrder)
    {
        if (!empty($blanketOrder)) {
            try {
                app(WhatsAppService::class)->orderPlacedCheck($blanketOrder->order_number, $blanketOrder);
            } catch (\Throwable $th) {
                Log::error('Error while processing blanket order Whatsapp notification', [
                    'order_number' => $blanketOrder->order_number,
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
            }
        }
    }

    public static function arrayRemoveDuplicatesRecursive(array $array): array
    {
        // Check if this is an associative array (has string keys or non-sequential numeric keys)
        $isAssociative = !array_is_list($array);

        // For associative arrays, process each value recursively but don't check for duplicates
        // at this level since we want to preserve all key-value pairs
        if ($isAssociative) {
            $result = [];
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $result[$key] = self::arrayRemoveDuplicatesRecursive($value);
                } else {
                    $result[$key] = $value;
                }
            }
            return $result;
        }

        // For indexed arrays (lists), check for duplicate elements
        $seen = [];
        $result = [];

        foreach ($array as $key => $value) {
            // Recursively process array values
            if (is_array($value)) {
                $value = self::arrayRemoveDuplicatesRecursive($value);
            }

            // Create unique signature for comparison
            $signature = serialize($value);

            // Only add if not seen before
            if (!in_array($signature, $seen, true)) {
                $seen[] = $signature;
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
