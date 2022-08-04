<?php namespace App\Libraries;

class OneSignal
{
    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get app detail
     * @param string $app_id
     * @param string $api_key
     * @return array
     */
    public function app(string $app_id, string $api_key): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://onesignal.com/api/v1/apps/'.$app_id);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic '.$api_key
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $final = curl_exec($ch);
        $response = json_decode($final, true);
        curl_close($ch);
        if (!empty($response['id'])) {
            $result = [
                'event'  => true,
                'detail' => $response
            ];
        } else {
            $result = ['event' => false];
        }
        return $result;
    }

    /**
     * Get notifications history
     * @param string $app_id
     * @param string $api_key
     * @param int $offset
     * @return array
     */
    public function notifications(string $app_id, string $api_key, int $offset = 0): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://onesignal.com/api/v1/notifications?app_id='.$app_id.'&offset='.$offset.'&limit=20');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic '.$api_key
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $final = curl_exec($ch);
        $response = json_decode($final, true);
        curl_close($ch);
        if (!empty($response['total_count'])) {
            $result = [
                'event'         => true,
                'notifications' => $response['notifications'],
                'count'         => $response['total_count']
            ];
        } else {
            $result = ['event' => false, 'response' => $response];
        }
        return $result;
    }

    /**
     * Start push newsletter
     * @param string $body
     * @param string $key
     * @return array
     */
    public function send(string $body, string $key): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic '.$key
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $final = curl_exec($ch);
        $response = json_decode($final, true);
        curl_close($ch);
        if (!empty($response['id'])) {
            if ($response['recipients']) {
                $result = [
                    "event" => true,
                    "id"    => $response['id']
                ];
            } else {
                $result = [
                    "event"   => false,
                    "message" => [
                        'error'  => lang("Message.message_54"),
                    ],
                ];
            }
        } else {
            $result = [
                "event"   => false,
                "message" => [
                    'error'  => lang("Message.message_55"),
                ],
            ];
        }
        return $result;
    }
}