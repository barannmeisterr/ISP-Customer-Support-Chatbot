<?php
/**
 * Author: Arda Baran
 *
 * Description:
 *
 * This class contains the actions executed by the ISS Chatbot
 * after an appropriate decision-tree path has been selected.
 *
 * The class communicates with internal ISS and OIM APIs to retrieve
 * subscriber information and perform customer-related operations.
 *
 * The main responsibilities of this class include:
 *
 * - Checking the subscriber's PPP session status.
 * - Checking the subscriber's latest connection status.
 * - Checking invoices and outstanding debt.
 * - Retrieving the customer's payment link.
 * - Creating a field-service fault ticket.
 * - Retrieving the customer's current tariff speed.
 * - Retrieving available internet plans.
 * - Sending a plan-change request.
 * - Converting Kbps values to Mbps.
 *
 * API authentication information is provided through BotAuthInfo.
 *
 * Most methods follow a similar workflow:
 *
 * 1. Retrieve authentication information.
 * 2. Determine the API host according to the application environment.
 * 3. Authenticate against the required API.
 * 4. Retrieve the required customer or service information.
 * 5. Validate the API response.
 * 6. Return a standardized result containing a status and data.
 */
namespace App\Services\bot;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class DecisionActions
{
    public function __construct(
	
	    /**
     * Initializes the action service with authentication information.
     *
     * If no active authentication information exists, a new
     * authentication session is started automatically.
     *
     * @param BotAuthInfo $authInfo Authentication information provider.
     */
        public BotAuthInfo $authInfo,
    )
    {
        if (!$this->authInfo->get()) {
            $this->authInfo->start();
        }
    }

   
    public function check_ppp_session(string $subscriberId): array
    {
  
        $auth = $this->authInfo->get();
        $apiKey = $auth['musteri_api_key'];
        $host = app()->environment() === 'local' ? 'nginx' : localIp();
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        $options = [
            'form_params' => [
                'key' => $apiKey,
            ]];
        $request = new Request('POST', sprintf("http://%s/api/iss/api/authenticate", $host), $headers);
        $res = $client->send($request, $options);

        // res: {"status":"success","message":"Authentication Successful","data":{"token":"secret"}}{
        $token = @json_decode($res->getBody()->getContents(), true)['data']['token'];

        if (!$token) {
            
            die;
        }
        $subscriberId = trim((string)$subscriberId);
        $client = new Client();
        $options = [
            'multipart' => [
                [
                    'name' => 'token',
                    'contents' => $token
                ],
                [
                    'name' => 'records',
                    'contents' => '1'
                ],
                [
                    'name' => 'page',
                    'contents' => '1'
                ],
                [
                    'name' => 'find',
                    'contents' => $subscriberId
                ]
            ]];
        $request = new Request('POST', sprintf("http://%s/api/iss/api/find", $host));
        $res = $client->send($request, $options);
        $data = json_decode($res->getBody()->getContents(), true);

        if (
            empty($data['data']) ||
            !isset($data['data']['records']) ||
            !is_array($data['data']['records']) ||
            count($data['data']['records']) === 0
        ) {
            return [
                'status' => 'not_found',
                'data' => null
            ];
        }


        $record = $data['data']['records'][0];


        $pppUser = trim($record['pppoe_k_adi'] ?? '');
        $pppPass = trim($record['pppoe_k_parola'] ?? '');
        $musteriName = trim($record['isim'] ?? '');
        if ($pppUser === '' || $pppPass === '') {
            return [
                'status' => 'offline',
                'data' => [
                    'isim' => $musteriName,
                ],
            ];

        }


        return [
            'status' => 'online',
            'data' => [
                'isim' => $musteriName,
                'username' => $pppUser,
                'password' => $pppPass,
            ]
        ];
    }

  
    public function check_connection_session(string $subscriberId): array
    {
		
		
        $auth = $this->authInfo->get();
        $apiKey = $auth['musteri_api_key'];

        $host = app()->environment() === 'local' ? 'nginx' : localIp();
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        $options = [
            'form_params' => [
                'key' => $apiKey,
            ]];
        $request = new Request('POST', sprintf("http://%s/api/iss/api/authenticate", $host), $headers);
        $res = $client->send($request, $options);

        // res: {"status":"success","message":"Authentication Successful","data":{"token":"secret"}}{
        $token = @json_decode($res->getBody()->getContents(), true)['data']['token'];

        if (!$token) {
            // HATA VER:
            die;
        }
        $subscriberId = trim((string)$subscriberId);
        $client = new Client();
        $options = [
            'multipart' => [
                [
                    'name' => 'token',
                    'contents' => $token
                ],
                [
                    'name' => 'records',
                    'contents' => '1'
                ],
                [
                    'name' => 'page',
                    'contents' => '1'
                ],
                [
                    'name' => 'find',
                    'contents' => $subscriberId
                ]
            ]];
        $request = new Request('POST', sprintf("http://%s/api/iss/api/find", $host));
        $res = $client->send($request, $options);
        $data = json_decode($res->getBody()->getContents(), true);


        if (empty($data['data']['records']) || !is_array($data['data']['records'])) {
            return ['status' => 'user_not_found',
                'data' => null
            ];
        }

        $record = $data['data']['records'][0];
        $musteriName = trim($record['isim'] ?? '');

        $connections = $record['son_baglantilar'] ?? [];

        if (empty($connections)) {
            return [
                'status' => 'user_connection_data_not_found',
                'data' => [
                    'isim' => $musteriName
                ]
            ];
        }


        $lastConn = $connections[0];

        $status = strtolower($lastConn['durum'] ?? '');
        $start = $lastConn['oturum_baslangic'] ?? null;
        $end = $lastConn['oturum_bitis'] ?? null;


        if ($status === 'offline') {
            return [
                'status' => 'offline',
                'data' => [
                    'isim' => $musteriName,
                    'connection_status' => 'Offline',
                    'connection_end' => $end
                ]
            ];
        }


        return [
            'status' => 'online',
            'data' => [
                'isim' => $musteriName,
                'connection_status' => 'Online',
                'connection_start' => $start
            ]
        ];
    }

    public function check_connection_session_for_internet_yok(string $subscriberId): array
    {
        $auth = $this->authInfo->get();
        $apiKey = $auth['musteri_api_key'];
        $host = app()->environment() === 'local' ? 'nginx' : localIp();
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        $options = [
            'form_params' => [
                'key' => $apiKey,
            ]];
        $request = new Request('POST', sprintf("http://%s/api/iss/api/authenticate", $host), $headers);
        $res = $client->send($request, $options);

        // res: {"status":"success","message":"Authentication Successful","data":{"token":"secret"}}{
        $token = @json_decode($res->getBody()->getContents(), true)['data']['token'];

        if (!$token) {
            // HATA VER:
            die;
        }
        $subscriberId = trim((string)$subscriberId);
        $client = new Client();
        $options = [
            'multipart' => [
                [
                    'name' => 'token',
                    'contents' => $token
                ],
                [
                    'name' => 'records',
                    'contents' => '1'
                ],
                [
                    'name' => 'page',
                    'contents' => '1'
                ],
                [
                    'name' => 'find',
                    'contents' => $subscriberId
                ]
            ]];
        $request = new Request('POST', sprintf("http://%s/api/iss/api/find", $host));
        $res = $client->send($request, $options);
        $data = json_decode($res->getBody()->getContents(), true);


        if (empty($data['data']['records']) || !is_array($data['data']['records'])) {
            return ['status' => 'user_not_found',
                'data' => null
            ];
        }

        $record = $data['data']['records'][0];
        $musteriName = trim($record['isim'] ?? '');

        $connections = $record['son_baglantilar'] ?? [];

        if (empty($connections)) {
            return [
                'status' => 'user_connection_data_not_found',
                'data' => [
                    'isim' => $musteriName
                ]
            ];
        }


        $lastConn = $connections[0];

        $status = strtolower($lastConn['durum'] ?? '');
        $start = $lastConn['oturum_baslangic'] ?? null;
        $end = $lastConn['oturum_bitis'] ?? null;


        if ($status === 'offline') {
            return [
                'status' => 'offline',
                'data' => [
                    'isim' => $musteriName,
                    'connection_status' => 'Offline',
                    'connection_end' => $end
                ]
            ];
        }


        return [
            'status' => 'online',
            'data' => [
                'isim' => $musteriName,
                'connection_status' => 'Online',
                'connection_start' => $start
            ]
        ];
    }



    /**
     * Fatura ve Borç Kontrolü
     */
    public function check_payment_session(string $subscriberId): array
    {
        $auth = $this->authInfo->get();
        $apiKey = $auth['musteri_api_key'];
        $host = app()->environment() === 'local' ? 'nginx' : localIp();
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        $options = [
            'form_params' => [
                'key' => $apiKey,
            ]];
        $request = new Request('POST', sprintf("http://%s/api/iss/api/authenticate", $host), $headers);
        $res = $client->send($request, $options);

        // res: {"status":"success","message":"Authentication Successful","data":{"token":"secret"}}{
        $token = @json_decode($res->getBody()->getContents(), true)['data']['token'];

        if (!$token) {
            // HATA VER:
            die;
        }
        $subscriberId = trim((string)$subscriberId);
        $client = new Client();
        $options = [
            'multipart' => [
                [
                    'name' => 'token',
                    'contents' => $token
                ],
                [
                    'name' => 'records',
                    'contents' => '1'
                ],
                [
                    'name' => 'page',
                    'contents' => '1'
                ],
                [
                    'name' => 'find',
                    'contents' => $subscriberId
                ]
            ]];
        $request = new Request('POST', sprintf("http://%s/api/iss/api/find", $host));
        $res = $client->send($request, $options);
        $data = json_decode($res->getBody()->getContents(), true);


        if (empty($data['data']['records']) || !is_array($data['data']['records'])) {
            return ['status' => 'user_not_found',
                'data' => null
            ];
        }

        $record = $data['data']['records'][0];
        $musteriName = trim($record['isim'] ?? '');
        $payments = $record['faturalar'] ?? [];
        $debtAmount = (int)($record['toplam_borc'] ?? 0);
        $paymentLink = trim($record['odeme_link'] ?? '');

        if (empty($payments)) {
            return [
                'status' => 'payment_record_not_found',
                'data' => [
                    'isim' => $musteriName
                ]
            ];
        }

        $faturaNo = null;

        foreach ($payments as $payment) {
            $durum = strtolower($payment['durum'] ?? '');

            if ($durum !== 'ödendi') {

                if ($faturaNo === null) {
                    $faturaNo = $payment['fatura_no'] ?? null;
                }

            }
        }


        if ($debtAmount > 0) {

            return [
                'status' => 'has_unpaid_payment',
                'data' => [
                    'isim' => $musteriName,
                    'faturano' => $faturaNo,
                    'borc' => $debtAmount,
                    'link' => $paymentLink
                ]
            ];
        }


        return [
            'status' => 'has_paid_payment',
            'data' => [
                'isim' => $musteriName,
            ]
        ];
    }

    public function check_payment_link_session(string $subscriberId): array
    {
        $auth = $this->authInfo->get();
        $apiKey = $auth['musteri_api_key'];
        $host = app()->environment() === 'local' ? 'nginx' : localIp();
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        $options = [
            'form_params' => [
                'key' => $apiKey,
            ]];
        $request = new Request('POST', sprintf("http://%s/api/iss/api/authenticate", $host), $headers);
        $res = $client->send($request, $options);

        // res: {"status":"success","message":"Authentication Successful","data":{"token":"secret"}}{
        $token = @json_decode($res->getBody()->getContents(), true)['data']['token'];

        if (!$token) {
            // HATA VER:
            die;
        }
        $subscriberId = trim((string)$subscriberId);
        $client = new Client();
        $options = [
            'multipart' => [
                [
                    'name' => 'token',
                    'contents' => $token
                ],
                [
                    'name' => 'records',
                    'contents' => '1'
                ],
                [
                    'name' => 'page',
                    'contents' => '1'
                ],
                [
                    'name' => 'find',
                    'contents' => $subscriberId
                ]
            ]];
        $request = new Request('POST', sprintf("http://%s/api/iss/api/find", $host));
        $res = $client->send($request, $options);
        $data = json_decode($res->getBody()->getContents(), true);


        if (
            empty($data['data']) ||
            !isset($data['data']['records']) ||
            !is_array($data['data']['records']) ||
            count($data['data']['records']) === 0
        ) {
            return [
                'status' => 'link_unknown_user',
                'data' => null
            ];
        }


        $record = $data['data']['records'][0];
        $musteriName = trim($record['isim'] ?? '');
        $paymentLink = trim($record['odeme_link'] ?? '');

        if ($paymentLink === '' || $paymentLink === null) {
            return [
                'status' => 'link_not_found',
                'data' => [
                    'isim' => $musteriName
                ]
            ];
        }


        return [
            'status' => 'link_found',
            'data' => [
                'isim' => $musteriName,
                'link' => $paymentLink
            ]
        ];
    }

    public function check_saha_ariza_session(string $arizamessage): array
    {
        $auth = $this->authInfo->get();

        $username = $auth['username'];
        $password = $auth['password'];
        $host = app()->environment() === 'local' ? 'nginx' : localIp();
        $client = new Client();
        $loginHeaders = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        $loginOptions = [
            'form_params' => [
                'username' => $username,
                'password' => $password
            ]
        ];

        try {
            $loginRequest = new Request(
                'POST',
                sprintf("http://%s/api/oim/login", $host),
                $loginHeaders
            );

            $loginResponse = $client->send($loginRequest, $loginOptions);
            $loginJson = json_decode($loginResponse->getBody()->getContents(), true);

            if (!($loginJson['success'] ?? false)) {
                return [
                    'status' => 'ticked_not_created',
                    'data' => null
                ];
            }

            $token = $loginJson['data']['token'] ?? null;
            if (!$token) {
                return [
                    'status' => 'ticked_not_created',
                    'data' => null
                ];
            }

        } catch (\Exception $e) {
            return [
                'status' => 'ticked_not_created',
                'data' => null
            ];
        }
        $infoHeaders = [
            'X-HTTP-Authorization' => $token,
            'Accept' => 'application/json'
        ];


        try {
            $customerRequest = new Request(
                'POST',
                sprintf("http://%s/api/oim/ticket_open", $host),
                $infoHeaders
            );
            $options = [
                'form_params' => [
                    'description' => $arizamessage
                ]
            ];
            $customerResponse = $client->send($customerRequest, $options);
            $infoJson = json_decode($customerResponse->getBody()->getContents(), true);

            if (!($infoJson['success'] ?? false)) {
                return [
                    'status' => 'ticked_not_created',
                    'data' => null
                ];
            }
            $record = $infoJson['data']['ticket'] ?? null;

            $arizaCreationTime = trim($record['created_at'] ?? '');
            $arizaDescMessage = trim($record['description'] ?? '');

            if (!$record) {
                return [
                    'status' => 'ticket_not_created',
                    'data' => null
                ];
            }
            return [
                'status' => 'ticked_created',
                'data' => [
                    'time' => $arizaCreationTime,
                    'message' => $arizaDescMessage
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'ticked_not_created',
                'data' => null
            ];
        }

    }

    public function check_tarife_hiz_session(string $subscriberId): array
    {
        $auth = $this->authInfo->get();
        $apiKey = $auth['musteri_api_key'];
        $host = app()->environment() === 'local' ? 'nginx' : localIp();
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        $options = [
            'form_params' => [
                'key' => $apiKey,
            ]];
        $request = new Request('POST', sprintf("http://%s/api/iss/api/authenticate", $host), $headers);
        $res = $client->send($request, $options);

        // res: {"status":"success","message":"Authentication Successful","data":{"token":"secret"}}{
        $token = @json_decode($res->getBody()->getContents(), true)['data']['token'];

        if (!$token) {
            // HATA VER:
            die;
        }
        $subscriberId = trim((string)$subscriberId);
        $client = new Client();
        $options = [
            'multipart' => [
                [
                    'name' => 'token',
                    'contents' => $token
                ],
                [
                    'name' => 'records',
                    'contents' => '1'
                ],
                [
                    'name' => 'page',
                    'contents' => '1'
                ],
                [
                    'name' => 'find',
                    'contents' => $subscriberId
                ]
            ]];
        $request = new Request('POST', sprintf("http://%s/api/iss/api/find", $host));
        $res = $client->send($request, $options);
        $data = json_decode($res->getBody()->getContents(), true);

        if (
            empty($data['data']) ||
            !isset($data['data']['records']) ||
            !is_array($data['data']['records']) ||
            count($data['data']['records']) === 0
        ) {
            return [
                'status' => 'user_not_found',
                'data' => null
            ];
        }


        $record = $data['data']['records'][0];


        $tarifeHizString = trim($record['tarife_hiz'] ?? '');

        $uploadKbps = null;
        $downloadKbps = null;

        if ($tarifeHizString !== '' && str_contains($tarifeHizString, '/')) {
            [$uploadRaw, $downloadRaw] = explode('/', $tarifeHizString, 2);

            $uploadKbps = (int)str_replace('k', '', strtolower(trim($uploadRaw)));
            $downloadKbps = (int)str_replace('k', '', strtolower(trim($downloadRaw)));
        }
        $uploadMbps = $uploadKbps ? round($uploadKbps / 1024, 2) : null;
        $downloadMbps = $downloadKbps ? round($downloadKbps / 1024, 2) : null;

        $musteriName = trim($record['isim'] ?? '');
        if ($tarifeHizString === '') {
            return [
                'status' => 'hiz_not_found',
                'data' => [
                    'isim' => $musteriName,
                ],
            ];

        }


        return [
            'status' => 'hiz_found',
            'data' => [
                'isim' => $musteriName,
                'upload_speed' => $uploadMbps,
                'download_speed' => $downloadMbps,
            ]
        ];
    }

    public function check_available_plans_session(): array
    {
        $auth = $this->authInfo->get();

        $username = $auth['username'];
        $password = $auth['password'];
        $host = app()->environment() === 'local' ? 'nginx' : localIp();

        $client = new Client();


        try {
            $loginRequest = new Request(
                'POST',
                sprintf("http://%s/api/oim/login", $host),
                ['Content-Type' => 'application/x-www-form-urlencoded']
            );

            $loginResponse = $client->send($loginRequest, [
                'form_params' => [
                    'username' => $username,
                    'password' => $password
                ]
            ]);

            $loginJson = json_decode($loginResponse->getBody()->getContents(), true);

            if (!($loginJson['success'] ?? false)) {
                return ['status' => 'error_connection', 'plans' => []];
            }

            $token = $loginJson['data']['token'] ?? null;
            if (!$token) {
                return ['status' => 'error_connection', 'plans' => []];
            }

        } catch (\Exception $e) {
            return ['status' => 'error_connection', 'plans' => []];
        }


        try {
            $plansRequest = new Request(
                'GET',
                sprintf("http://%s/api/oim/plans", $host),
                [
                    'X-HTTP-Authorization' => $token,
                    'Accept' => 'application/json'
                ]
            );

            $plansResponse = $client->send($plansRequest);
            $plansJson = json_decode($plansResponse->getBody()->getContents(), true);


            if (
                !($plansJson['success'] ?? false) ||
                empty($plansJson['data']['plans']) ||
                !is_array($plansJson['data']['plans'])
            ) {
                return ['status' => 'paket_plans_not_found', 'plans' => []];
            }

            $plans = [];

            foreach ($plansJson['data']['plans'] as $plan) {

                $downloadMbps = $this->kbpsToMbps($plan['download']);
                $uploadMbps = $this->kbpsToMbps($plan['upload']);

                $combinedLabel = sprintf(
                    "%s (⬇ %s Mbps / ⬆ %s Mbps) - %s TL",
                    $plan['tarife_adi'],
                    $downloadMbps,
                    $uploadMbps,
                    $plan['fiyat']
                );

                $plans[] = [
                    'id' => (string)$plan['tarife_id'],
                    'label' => $combinedLabel,
                ];
            }


            if (empty($plans)) {
                return ['status' => 'paket_plans_not_found', 'plans' => []];
            }

            return [
                'status' => 'paket_plans_found',
                'plans' => $plans
            ];

        } catch (\Exception $e) {
            return ['status' => 'error_connection', 'plans' => []];
        }
    }


    public function check_plan_change_request_session($tarifeNo): array
    {
        $auth = $this->authInfo->get();

        $username = $auth['username'];
        $password = $auth['password'];
        $host = app()->environment() === 'local' ? 'nginx' : localIp();
        $client = new Client();

        try {

            $loginResponse = $client->post(
                sprintf("http://%s/api/oim/login", $host),
                [
                    'form_params' => [
                        'username' => $username,
                        'password' => $password
                    ]
                ]
            );

            $loginJson = json_decode($loginResponse->getBody()->getContents(), true);

            if (!($loginJson['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => 'OIM giriş başarısız',
                    'data' => null
                ];
            }

            $token = $loginJson['data']['token'] ?? null;
            if (!$token) {
                return [
                    'success' => false,
                    'message' => 'OIM token alınamadı',
                    'data' => null
                ];
            }


            $changeResponse = $client->post(
                sprintf("http://%s/api/oim/requestPlanChange", $host),
                [
                    'headers' => [
                        'X-HTTP-Authorization' => $token,
                        'Accept' => 'application/json'
                    ],
                    'form_params' => [
                        'tarife_id' => $tarifeNo
                    ]
                ]
            );

            $changeJson = json_decode($changeResponse->getBody()->getContents(), true);

            if (!($changeJson['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => $changeJson['message'] ?? 'Paket değişikliği başarısız',
                    'data' => $changeJson
                ];
            }

            return [
                'success' => true,
                'message' => $changeJson['message'] ?? 'Paket değişikliği talebi oluşturuldu',
                'data' => $changeJson
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Sistem hatası: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function kbpsToMbps(string $value): float
    {

        $kbps = (int)str_replace('k', '', strtolower($value));

        return round($kbps / 1024, 2);
    }
}
