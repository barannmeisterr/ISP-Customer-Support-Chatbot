<?php
/**
 * Author: Arda Baran
 *
 * Description:
 *
 * This class retrieves customer information from the OIM (Online
 * İşlem Merkezi) system and uses that information to personalize
 * the chatbot responses.
 *
 * The class first authenticates against the OIM API using the
 * credentials stored in BotAuthInfo. After successful authentication,
 * it retrieves the authenticated customer's information.
 *
 * The retrieved customer information can then be used by the chatbot
 * to address the customer by name and provide a more personalized
 * conversation experience.
 *
 * Main responsibilities:
 *
 * 1) Authenticate with the OIM API.
 *
 * 2) Retrieve customer information from OIM.
 *
 * 3) Extract the customer's name from the returned information.
 *
 * 4) Generate a personalized greeting for the chatbot.
 */
namespace App\Services\bot;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;


class GetCustomerInfoFromOIM
{
    public function __construct(
        public BotAuthInfo $authInfo,
    )
    {
        if (!$this->authInfo->get()) {
            $this->authInfo->start();
        }
    }

    public function oimLoginAndCustomerInfo(string $username, string $password): ?array
    {

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
                return null;
            }

            $token = $loginJson['data']['token'] ?? null;
            if (!$token) return null;

        } catch (\Exception $e) {
            return null;
        }
        $infoHeaders = [
            'X-HTTP-Authorization' => $token,
            'Accept' => 'application/json'
        ];

        try {
            $customerRequest = new Request(
                'GET',
                sprintf("http://%s/api/oim/customer_information", $host),
                $infoHeaders
            );

            $customerResponse = $client->send($customerRequest);
            $infoJson = json_decode($customerResponse->getBody()->getContents(), true);

            if (!($infoJson['success'] ?? false)) {
                return null;
            }

            return $infoJson['data']['customer'] ?? null;

        } catch (\Exception $e) {
            return null;
        }
    }

    public function makeTheBotPersonalized(): ?string
    {
        $auth = $this->authInfo->get();

        if (!$auth || !isset($auth['username'], $auth['password'])) {
            return null;
        }

        $customer = $this->oimLoginAndCustomerInfo($auth['username'], $auth['password']);

        if (!$customer || !is_array($customer)) {
            return null;
        }

        $fullName = trim($customer['isim'] ?? '');
        if ($fullName === '') {
            return null;
        }

        $parts = explode(' ', $fullName);


        $name = mb_convert_case($parts[0], MB_CASE_TITLE, "UTF-8");

        return "Değerli müşterimiz " . $name . ", ";
    }


}

