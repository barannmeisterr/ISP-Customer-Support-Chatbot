<?php
/**
 * Author: Arda Baran
 *
 * Description:
 *
 * This class is the main orchestration service of the ISS Chatbot.
 *
 * It coordinates the conversation flow by combining the following
 * components:
 *
 * 1) DecisionTree
 *    Determines the appropriate category and manages the decision-tree
 *    nodes used during the conversation.
 *
 * 2) ConversationState
 *    Stores and manages the current state of the conversation,
 *    allowing the chatbot to continue multi-step conversations
 *    across multiple user messages.
 *
 * 3) DecisionActions
 *    Executes external operations such as checking internet sessions,
 *    payment information, connection status, available plans,
 *    plan change requests, and fault tickets.
 *
 * 4) GetCustomerInfoFromOIM
 *    Retrieves customer information from OIM and provides
 *    personalized customer greetings.
 *
 * The general conversation flow is:
 *
 * User Message
 *      ↓
 * ChatbotService
 *      ↓
 * Existing Conversation State?
 *      ↓
 * ┌───────────────┐
 * │      Yes      │──────→ handleNode()
 * └───────────────┘
 *      │
 *      No
 *      ↓
 * Intent / Category Detection
 *      ↓
 * Root Node
 *      ↓
 * ConversationState
 *      ↓
 * Decision Tree
 *      ↓
 * Question / Instruction / Action / Final Node
 *
 * This class therefore acts as the central coordinator between
 * user input, the decision tree, conversation state, external
 * API actions, and the final chatbot response.
 */
namespace App\Services\bot;

use Exception;

class ChatbotService
{
    public function __construct(
        protected DecisionTree           $tree,
        protected ConversationState      $state,
        protected DecisionActions        $actions,
        protected GetCustomerInfoFromOIM $oimClient,
    )
    {
    }

    public function reply(string $message): array
    {
        $message = trim($message);

        // 1) DEVAM EDEN AKIŞ VAR MI?
        $state = $this->state->get();
        if ($state) {
            return $this->handleNode($message, $state);
        }

        $name = $this->oimClient->makeTheBotPersonalized();


        // 3) INTENT BUL
        $category = $this->tree->matchCategory($message);

        if (!$category) {
            return [
                'text' => ($name ? $name . " " : "") .
                    "dilerseniz aşağıda belirtilen kategorilerin herhangi biriyle ilgili olarak size yardımcı olmaktan mutluluk duyarım.",
                'branches' => [
                    [
                        'match' => ['İnternet Yok / Bağlantı Sağlanamıyor Problemi']
                    ],
                    [
                        'match' => ['İnternet Sinyalinin Az Çekmesi Problemi']
                    ],
                    [
                        'match' => ['İnternetin Sık Sık Kopması / Bağlantıda Dalgalanma Olması Problemi']
                    ],
                    [
                        'match' => ['Hız Problemi (Düşük Hız / Yavaş İnternet)']
                    ],
                    [
                        'match' => ['Fatura / Borç / Ödeme Kaynaklı Kesinti Problemi']
                    ],
                    [
                        'match' => ['Kurulum / Reset / Modem Ayar Sorunları']
                    ],
                    [
                        'match' => ['Cihaz / Donanım Arızası (Modem, Adaptör, Kablo, Anten)']
                    ],
                    [
                        'match' => ['Diğer Operatörden Geçiş Sonrası IP / Bağlantı Sorunu']
                    ],
                    [
                        'match' => ['Web Sitesi / Online Ödeme Sistemi Hataları']
                    ],
                    [
                        'match' => ['Nakil / Adres Değişikliği Talebi']
                    ],
                    [
                        'match' => ['Paket Yetersizliği / Hız Yükseltme Talebi / Tarife Değişikliği']
                    ]
                ]
            ];
        }


        // 4) ROOT NODE BAŞLAT
        $rootNodeId = $this->tree->getRoot($category);
        $rootNode = $this->tree->getNode($category, $rootNodeId);

        $this->state->start($category['id'], $rootNodeId);

        return [
            'text' => ($name ? $name . " " : "") . $rootNode['text'],
            'branches' => $rootNode['branches'] ?? null
        ];
    }


    protected function handleNode(string $message, array $state): array
    {
        $categoryId = $state['category_id'] ?? null;
        $nodeId = $state['node_id'] ?? null;

        if (!$categoryId || !$nodeId) {
            return $this->error("Geçersiz state bilgisi");
        }

        $category = $this->tree->getCategoryById($categoryId);
        if (!$category) {
            return $this->error("Kategori bulunamadı: {$categoryId}");
        }

        $node = $this->tree->getNode($category, $nodeId);
        if (!$node) {
            return $this->error("Node bulunamadı: {$nodeId}");
        }

// 🔥 KARA DÜZEN – PAKET SEÇİMİ YAKALA (FSM BYPASS)
        if (isset($state['node_id']) && $state['node_id'] === 'node_plans_found_question') {

            $selected = trim($message);

            // Vazgeç
            if ($selected === 'Paketimi yükseltmekten vazgeçtim.') {
                $this->state->clear();

                return [
                    'text' => 'Değerli müşterimiz, talebiniz üzerine paket yükseltme işleminiz iptal edildi.',
                    'branches' => null
                ];
            }

            // 🔥 STATE'TEKİ PLANLARDAN SEÇİM YAKALA
            $plans = $state['available_plans'] ?? [];

            foreach ($plans as $plan) {
                if ($selected === $plan['label']) {

                    // 🔑 TARİFE ID (STRING GÖNDERİYORSUN)
                    $selectedTarifeId = (string)$plan['id'];

                    // 🔥 DİREKT ACTION ÇAĞRISI (FSM YOK)
                    $paketDegisiklikResult =
                        $this->actions->check_plan_change_request_session($selectedTarifeId);

                    // ✅ ACTION CONTRACT'A GÖRE MESAJ
                    if (($paketDegisiklikResult['success'] ?? false) === true) {
                        $changeRequestMessage =
                            $paketDegisiklikResult['message']
                            ?? 'Paket değişikliği talebiniz başarıyla alınmıştır.';
                    } else {
                        $changeRequestMessage =
                            $paketDegisiklikResult['message']
                            ?? 'Paket değişikliği sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.';
                    }

                    $this->state->clear();

                    return [
                        'text' => "Değerli müşterimiz, " . $changeRequestMessage,
                        'branches' => null
                    ];
                }
            }

            // Tanımsız giriş
            return [
                'text' => 'Değerli müşterimiz, lütfen listeden geçerli bir paket seçiniz.',
                'branches' => null
            ];
        }




        $msgNorm = strtolower($message);

        // ===============================
        // FINAL NODE
        // ===============================
        if ($node['type'] === 'final') {
            $this->state->clear();
            return [
                'text' => $node['text'],
                'branches' => null
            ];
        }


        // ===============================
// QUESTION NODE
// ===============================
        if ($node['type'] === 'question') {

            $branches = $node['branches'] ?? [];

            // 1) MATCH kontrolü
            foreach ($branches as $branch) {
                if (!empty($branch['match'])) {
                    foreach ($branch['match'] as $word) {
                        if ($word === "*" || str_contains($msgNorm, strtolower($word))) {

                            // FREE INPUT ise temp'e kaydet
                            if (!empty($node['allow_free_input'])) {
                                $this->state->setTemp($message);
                            }

                            $nextId = $branch['next'];
                            $nextNode = $this->tree->getNode($category, $nextId);

                            if (!$nextNode) {
                                return $this->error("Next node bulunamadı: {$nextId}");
                            }

                            // ===============================
                            // FINAL NODE
                            // ===============================
                            if ($nextNode['type'] === 'final') {
                                $this->state->clear();

                                return [
                                    'text' => $nextNode['text'],
                                    'branches' => null
                                ];
                            }

                            // ===============================
                            // 🔥 ACTION + AUTO_NEXT
                            // ===============================
                            if ($nextNode['type'] === 'action' && !empty($nextNode['auto_next'])) {
                                $this->state->moveTo($nextId);

                                // action'ı hemen çalıştır
                                return $this->handleNode('', $this->state->get());
                            }

                            // ===============================
                            // NORMAL NEXT
                            // ===============================
                            $this->state->moveTo($nextId);

                            return [
                                'text' => $nextNode['text'],
                                'branches' => $nextNode['branches'] ?? null
                            ];


                        }
                    }
                }
            }

            // 2) DEFAULT BRANCH
            foreach ($branches as $branch) {
                if (!empty($branch['default'])) {

                    if (!empty($node['allow_free_input'])) {
                        $this->state->setTemp($message);
                    }

                    $nextId = $branch['next'];
                    $nextNode = $this->tree->getNode($category, $nextId);

                    if (!$nextNode) {
                        return $this->error("Default next node bulunamadı: {$nextId}");
                    }

                    if ($nextNode['type'] === 'final') {
                        $this->state->clear();

                        return [
                            'text' => $nextNode['text'],
                            'branches' => null
                        ];
                    }

                    if ($nextNode['type'] === 'action' && !empty($nextNode['auto_next'])) {
                        $this->state->moveTo($nextId);
                        return $this->handleNode('', $this->state->get());
                    }

                    $this->state->moveTo($nextId);

                    return [
                        'text' => $nextNode['text'],
                        'branches' => $nextNode['branches'] ?? null
                    ];
                }
            }

            // 3) FREE INPUT → NEXT (branch yoksa)
            if (!empty($node['allow_free_input']) && !empty($node['next'])) {

                $this->state->setTemp($message);

                $nextId = $node['next'];
                $nextNode = $this->tree->getNode($category, $nextId);

                if (!$nextNode) {
                    return $this->error("allow_free_input -> next node bulunamadı: {$nextId}");
                }

                if ($nextNode['type'] === 'final') {
                    $this->state->clear();

                    return [
                        'text' => $nextNode['text'],
                        'branches' => null
                    ];
                }

                if ($nextNode['type'] === 'action' && !empty($nextNode['auto_next'])) {
                    $this->state->moveTo($nextId);
                    return $this->handleNode('', $this->state->get());
                }

                $this->state->moveTo($nextId);

                return [
                    'text' => $nextNode['text'],
                    'branches' => $nextNode['branches'] ?? null
                ];
            }

            // 4) fallback → soruyu tekrar et
            return [
                'text' => $node['text'] . " (Lütfen geçerli bir cevap yazın.)",
                'branches' => $node['branches'] ?? null
            ];
        }


// ===============================
        // INSTRUCTION NODE
        // ===============================
        if ($node['type'] === 'instruction') {

            if (empty($node['next'])) {
                return $this->error("Instruction node için 'next' tanımlı değil.");
            }

            $nextId = $node['next'];
            $nextNode = $this->tree->getNode($category, $nextId);

            if (!$nextNode) {
                return $this->error("Instruction next node bulunamadı: {$nextId}");
            }

            if ($nextNode['type'] === 'final') {
                $this->state->clear();
            } else {
                $this->state->moveTo($nextId);
            }

            return [
                'text' => $nextNode['text'],
                'branches' => $nextNode['branches'] ?? null
            ];
        }

// ===============================
// ACTION NODE
// ===============================
        if ($node['type'] === 'action') {

            $actionName = $node['action'];

            if (!method_exists($this->actions, $actionName)) {
                return $this->error("Action metodu bulunamadı: {$actionName}");
            }

            // 1️⃣ Free inputtan gelen abone no vs.
            $subscriberId = $this->state->getTemp();

            // 2️⃣ Action çalıştır
            $result = $this->actions->{$actionName}($subscriberId);
            $status = $result['status'];


// 🔥 KARA DÜZEN – SADECE PLAN LİSTESİ İÇİN
            if ($actionName === 'check_available_plans_session') {

                if ($status !== 'paket_plans_found') {
                    $this->state->clear();

                    return [
                        'text' => 'Uygun paket bulunamadı.',
                        'branches' => null
                    ];
                }
                $state = $this->state->get() ?? [];
                $state['available_plans'] = $result['plans'];
                session(['chatbot_state' => $state]);
                // 🔥 BRANCH'LERİ ELLE OLUŞTUR
                $branches = [];

                foreach ($result['plans'] as $plan) {
                    $branches[] = [
                        'match' => [$plan['label']],
                        'next' => 'final_upgrade_plan'
                    ];
                }

                // İptal seçeneği
                $branches[] = [
                    'match' => ['Paketimi yükseltmekten vazgeçtim.'],
                    'next' => 'final_user_yukseltmek_istemiyor'
                ];

                // FSM'yi burada sabitle
                $this->state->moveTo('node_plans_found_question');

                return [
                    'text' => 'Altyapınıza uygun paketler aşağıdadır. Hangisini seçmek istersiniz?',
                    'branches' => $branches
                ];
            }




            if (!isset($node['next_map'][$status])) {
                return $this->error("next_map bulunamadı: {$status}");
            }

            // 3️⃣ Next node
            $nextId = $node['next_map'][$status];
            $nextNode = $this->tree->getNode($category, $nextId);

            // 4️⃣ Temp temizle
            $this->state->clearTemp();

            // 5️⃣ Text'i hazırla
            $text = $this->actionTextParser(
                $actionName,
                $result,
                $nextNode['text']
            );

            // ===============================
            //  AUTO NEXT VAR + FINAL
            // ===============================
            if (!empty($node['auto_next']) && $nextNode['type'] === 'final') {
                $this->state->clear();

                return [
                    'text' => $text,
                    'branches' => null
                ];
            }
            // ===============================
            // 🔥 AUTO NEXT + QUESTION
            // ===============================
            if (!empty($node['auto_next']) && $nextNode['type'] === 'question') {

                $this->state->moveTo($nextId);

                $nextResponse = $this->handleNode('', $this->state->get());

                return [
                    'text' => $text . "\n\n" . $nextResponse['text'],
                    'branches' => $nextResponse['branches'] ?? null
                ];
            }
            // ===============================
            //  AUTO NEXT VAR + FINAL DEĞİL
            // ===============================
            if (!empty($node['auto_next'])) {
                $this->state->moveTo($nextId);

                // ️ Kullanıcıdan input bekleme → recursive devam
                return $this->handleNode('', $this->state->get());
            }

            // ===============================
            // NORMAL DAVRANIŞ (auto_next yok)
            // ===============================
            if ($nextNode['type'] === 'final') {
                $this->state->clear();
            } else {
                $this->state->moveTo($nextId);
            }

            return [
                'text' => $text,
                'branches' => $nextNode['branches'] ?? null
            ];
        }


        // ===============================
        // GEÇERSİZ NODE TYPE
        // ===============================
        return $this->error("Geçersiz node tipi: {$node['type']}");
    }


    /**
     * @param string $string
     * @return mixed
     * @throws Exception
     */
    private function error(string $string)
    {
        throw new Exception($string);
    }

    public function actionTextParser(string $actionName, array $result, string $text): string
    {
        switch ($actionName) {

            // ======================================
            // PPP SESSION PARSER
            // ======================================
            case "check_ppp_session":

                if (!empty($result['data'])) {

                    // Hitap
                    $isim = $result['data']['isim'] ?? null;
                    $isim = mb_convert_case($isim, MB_CASE_TITLE, "UTF-8");
                    if ($isim) {
                        $text = "Değerli müşterimiz " . $isim . ", " . $text;
                    }

                    // PPP bilgileri ekle
                    if (!empty($result['data']['username']) || !empty($result['data']['password'])) {

                        $dataText =
                            "\n\n➡️ Kullanıcı Adı: " . ($result['data']['username'] ?? 'yok') .
                            "\n➡️ Parola: " . ($result['data']['password'] ?? 'yok');

                        $text .= $dataText;
                    }
                }

                break;

            // ======================================
            // CONNECTION SESSION PARSER
            // ======================================
            case "check_connection_session":

                if (!empty($result['data'])) {

                    $isim = $result['data']['isim'] ?? null;
                    $isim = mb_convert_case($isim, MB_CASE_TITLE, "UTF-8");
                    if ($isim) {
                        $text = "Değerli müşterimiz " . $isim . ", " . $text;
                    }

                    // Online iken
                    if (!empty($result['data']['connection_start'])) {
                        $text .=
                            "\n\n➡️ Oturum Başlangıç: " . $result['data']['connection_start'] .
                            "\n➡️ Durum: " . $result['data']['connection_status'];
                    }

                    // Offline iken
                    if (!empty($result['data']['connection_end'])) {
                        $text .=
                            "\n\n➡️ Kopma Zamanı: " . $result['data']['connection_end'] .
                            "\n➡️ Durum: " . $result['data']['connection_status'];
                    }
                }

                break;
            // ===============================================
            // CONNECTION SESSION PARSER FOR INTERNET YOK PART
            // ===============================================

            case "check_connection_session_for_internet_yok":

                if (!empty($result['data'])) {
                    $hizKontrolText = "Değerli müşterimiz,";
                    $isim = $result['data']['isim'] ?? null;
                    $isim = mb_convert_case($isim, MB_CASE_TITLE, "UTF-8");
                    if ($isim) {
                        $hizKontrolText = $hizKontrolText . $isim . ", ";
                    }

                    // Online iken
                    if (!empty($result['data']['connection_start'])) {
                        $hizKontrolText .= "Yaptığımız kontrollerde internete bağlı(" . $result['data']['connection_status'] . ") gözüküyorsunuz.";

                    }

                    // Offline iken
                    if (!empty($result['data']['connection_end'])) {
                        $hizKontrolText .= "Yaptığımız kontrollerde internete bağlı değil(" . $result['data']['connection_status'] . ") gözüküyorsunuz.";

                    }
                    $text = $hizKontrolText . $text;
                }

                break;
            // ======================================
            // PAYMENT SESSION PARSER
            // ======================================
            case "check_payment_session":

                if (!empty($result['data'])) {

                    $isim = $result['data']['isim'] ?? null;
                    $isim = mb_convert_case($isim, MB_CASE_TITLE, "UTF-8");
                    if ($isim) {
                        $text = "Değerli müşterimiz " . $isim . ", " . $text;
                    }

                    // Borç
                    if (isset($result['data']['borc']) && $result['data']['borc'] > 0) {
                        $text .=
                            "\n\n➡️ Toplam Borcunuz: " . $result['data']['borc'] . " TL.";
                    }

                    // Fatura numarası
                    if (!empty($result['data']['faturano'])) {
                        $text .= "\n➡️ Ödenmemiş Fatura No: " . $result['data']['faturano'];
                    }

                    if (!empty($result['data']['link'])) {
                        $text .= "\n\n➡️ Ödeme Linkiniz: " . $result['data']['link'];
                    }
                }

                break;

            // ======================================
            // PAYMENT LINK PARSER
            // ======================================
            case "check_payment_link_session":

                if (!empty($result['data'])) {

                    $isim = $result['data']['isim'] ?? null;
                    $isim = mb_convert_case($isim, MB_CASE_TITLE, "UTF-8");
                    if ($isim) {
                        $text = "Değerli müşterimiz " . $isim . ", " . $text;
                    }

                    if (!empty($result['data']['link'])) {
                        $text .= "\n\n➡️ Ödeme Linkiniz: " . $result['data']['link'];
                    }
                }

                break;
            // ======================================
            // ARIZA TICKET PARSER
            // ======================================
            case "check_saha_ariza_session":
                if (!empty($result['data'])) {

                    if (!empty($result['data']['time'])) {
                        $text .=
                            "\n\n➡️ Oluşturulma Zamanı: " .
                            $result['data']['time'];
                    }

                    if (!empty($result['data']['message'])) {
                        $text .=
                            "\n➡️ Mesaj içeriği: " .
                            $result['data']['message'];
                    }
                }

                break;
            case "check_tarife_hiz_session":
                if (!empty($result['data'])) {

                    $isim = $result['data']['isim'] ?? null;
                    $isim = mb_convert_case($isim, MB_CASE_TITLE, "UTF-8");
                    $hizKontrolText = "Değerli müşterimiz,";
                    if ($isim) {
                        $hizKontrolText = $hizKontrolText . $isim . ", ";
                    }

                    // upload hızı
                    if (isset($result['data']['upload_speed']) && $result['data']['upload_speed'] > 0) {
                        $hizKontrolText .= "yapılan kontrollere göre paketinizin desteklediği Upload hız değeri " . $result['data']['upload_speed'] . " Mbps, ";
                    }

                    // download hızı
                    if (isset($result['data']['download_speed']) && $result['data']['download_speed'] > 0) {
                        $hizKontrolText .= " Download hız değeri ise " . $result['data']['download_speed'] . " Mbps olarak gözüküyor.";
                    }
                    $text = $hizKontrolText . $text;

                }
                break;
        }

        return $text;
    }


}
