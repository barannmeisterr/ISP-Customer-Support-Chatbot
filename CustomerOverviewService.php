<?php
/**
 * Author: Arda Baran
 *
 * Description:
 *
 * This service collects and combines customer-related information
 * from OIM and the DecisionActions service.
 *
 * It provides a single overview of the authenticated customer's
 * current status, including:
 *
 * 1) Customer name
 * 2) Subscriber number
 * 3) Current tariff
 * 4) Internet connection status
 * 5) Connection start/end time
 * 6) Outstanding debt amount
 * 7) Payment link
 *
 * The service acts as an aggregation layer between the chatbot
 * and the underlying OIM / ISS API services.
 *
 * Instead of making the chatbot request each piece of customer
 * information separately, this class retrieves the required data
 * and combines it into a single structured response.
 */
namespace App\Services\bot;
class CustomerOverviewService
{

    public function __construct(
        public DecisionActions        $actions,
        public GetCustomerInfoFromOIM $oimClient,
        public BotAuthInfo $authInfo,
    )
    {
        if (!$this->authInfo->get()) {
            $this->authInfo->start();
        }
    }

    public function get(): array
    {
        $auth = $this->authInfo->get();
        $defaultUsername = $auth['username'];
        $defaultPassword = $auth['password'];

        $customer = $this->oimClient->oimLoginAndCustomerInfo($defaultUsername, $defaultPassword);
        if (!$customer || !is_array($customer)) {
            return [];
        }
        $fullName = trim($customer['isim'] ?? '');
        $parts = explode(' ', $fullName);


        $name = mb_convert_case($parts[0], MB_CASE_TITLE, "UTF-8");
        $aboneNo = trim($customer['abone_no'] ?? '');
        $tarife = trim($customer['tarife'] ?? '');
        $connectionData = $this->actions->check_connection_session($aboneNo);
        $paymentData = $this->actions->check_payment_session($aboneNo);
        $paymentLinkData = $this->actions->check_payment_link_session($aboneNo);
        $connectionTime = null;
        $borcTutari = "0 ₺";
        $paymentLink = null;

        if (!empty($connectionData['data']['connection_start'])) {
            $connectionTime = $connectionData['data']['connection_start'];
        }
        if (!empty($connectionData['data']['connection_end'])) {
            $connectionTime = $connectionData['data']['connection_end'];
        }
        $connectionStatus = $connectionData['data']['connection_status'];
        if (!empty($paymentData['data']['borc']) && $paymentData['data']['borc'] > 0) {

            $borcTutari = number_format(
                    (float)$paymentData['data']['borc'],
                    2,      // ondalık
                    ',',    // ondalık ayırıcı
                    '.'     // binlik ayırıcı
                ) . ' ₺';
        }
        if (!empty($paymentLinkData['data']['link'])) {
            $paymentLink = $paymentLinkData['data']['link'];
        }

        return [
            'connection_durum' => $connectionStatus,
            'debt' => $borcTutari,
            'payment_linki' => $paymentLink,
            'connection_zaman' => $connectionTime,
            'aboneNumarasi' => $aboneNo,
            'isim' => $name,
            'tarife' => $tarife,

        ];
    }
}
