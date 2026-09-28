@extends('layouts.app')
<!-- Author:Arda Baran -->


    <div class="col-md-6 col-sm-12 mt-4">
      <div class="card shadow-sm">
        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
          <h4 class="mb-0">
            <i class="fa fa-robot"></i> ISS Chatbot
          </h4>
        </div>

        <div class="card-body p-0">

          <!-- general overview (at bottom , state independent) -->
          <div id="chatbot_overview" class="p-3 border-bottom">
            <!--  -->
          </div>

          <!--  chat message area  -->
          <div id="chatbot_messages"
               style="height: 320px; overflow-y: auto;"
               class="p-3">

            <div class="text-muted text-center mt-3">
              Chatbot hazır. Sorunuzu yazabilirsiniz.
            </div>

          </div>

        </div>


        <div class="card-footer">
          <div class="input-group">
            <input type="text" id="chatbot_input" class="form-control"
                   placeholder="ISS destek botuna mesaj yaz..." autocomplete="off">

            <div class="input-group-append">
              <button class="btn btn-primary" id="chatbot_send">
                Gönder
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>


  </div>

@endpush



@push('styles')
  <style>

    /* =========================
       DISABLED INPUT STATES
       ========================= */

    #chatbot_input:disabled {
      background: linear-gradient(135deg, #f1f3f5, #e9ecef);
      color: #6c757d;
      cursor: not-allowed;
      border-color: #dee2e6;
      box-shadow: none;
    }

    #chatbot_send:disabled {
      cursor: not-allowed;
      opacity: 0.45;
      filter: grayscale(0.4);
    }

    /* =========================
       INPUT WRAPPER (MODERN OVERLAY)
       ========================= */

    .chatbot-input-wrapper {
      position: relative;
    }

    .chatbot-input-wrapper.disabled {
      opacity: 0.85;
    }

    /* Hafif blur → "kilitli" hissi */
    .chatbot-input-wrapper.disabled input {
      filter: blur(0.8px);
    }

    /* Overlay mesaj (modern tooltip hissi) */
    .chatbot-input-wrapper.disabled::after {
      content: "Bir seçenek seçiniz";
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 0.8rem;
      color: #6c757d;
      background: rgba(255, 255, 255, 0.9);
      padding: 4px 10px;
      border-radius: 999px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      pointer-events: none;
      z-index: 10;
      animation: fadeInOverlay 0.2s ease;
    }

    /* =========================
       OVERLAY FADE IN
       ========================= */

    @keyframes fadeInOverlay {
      from {
        opacity: 0;
        transform: translateY(-50%) scale(0.95);
      }
      to {
        opacity: 1;
        transform: translateY(-50%) scale(1);
      }
    }


    /* =========================
       CHAT ROW
       ========================= */

    .chat-row {
      display: flex;
      margin-bottom: 10px;
      animation: slideFadeIn 0.25s ease forwards;
    }

    .chat-row.user {
      justify-content: flex-end;
    }

    .chat-row.bot {
      justify-content: flex-start;
    }

    /* =========================
       CHAT BUBBLES
       ========================= */

    .chat-bubble {
      max-width: 75%;
      padding: 12px 14px;
      border-radius: 18px;
      font-size: 0.9rem;
      line-height: 1.4;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
      word-wrap: break-word;
    }

    .chat-bubble.user {
      background: linear-gradient(135deg, #0d6efd, #0b5ed7);
      color: #fff;
      border-bottom-right-radius: 4px;
    }

    .chat-bubble.bot {
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: #fff;
      border-bottom-left-radius: 4px;
    }

    /* =========================
       MESSAGE ANIMATION
       ========================= */

    @keyframes slideFadeIn {
      from {
        opacity: 0;
        transform: translateY(8px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .badge-success {
      position: relative;
      animation: pulse-green 2s infinite;
    }

    @keyframes pulse-green {
      0% {
        box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.6);
      }
      70% {
        box-shadow: 0 0 0 8px rgba(40, 167, 69, 0);
      }
      100% {
        box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
      }
    }

    /* =========================
       BRANCH CHIPS
       ========================= */

    .branch-wrapper {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 10px;
    }

    .chatbot-branch-btn {
      border-radius: 999px;
      padding: 6px 14px;
      font-size: 0.85rem;
      border: 1px solid #0d6efd;
      background: #f8f9ff;
      color: #0d6efd;
      transition: all 0.2s ease;
    }

    .chatbot-branch-btn:hover {
      background: #0d6efd;
      color: #fff;
      box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
      transform: translateY(-1px);
    }

    .chatbot-branch-btn:active {
      transform: scale(0.96);
    }

    #chatbot_input {
      border-radius: 14px;
      padding: 10px 14px;
    }

    #chatbot_send {
      border-radius: 14px;
      padding: 8px 20px;
    }

  </style>
@endpush




@push('scripts')
  <script>


    $(document).ready(function () {
      loadChatbotOverview();
    });

    function loadChatbotOverview() {
      $.get("{{ route('internal_chatbot_overview') }}", function (res) {

        if (!res.success || !res.data) {
          $('#chatbot_overview').html(
            '<div class="text-muted small">Genel durum bilgisi alınamadı</div>'
          );
          return;
        }

        const d = res.data;

        //  CASE-INSENSITIVE ONLINE CHECK
        const isOnline = (d.connection_durum || '').toLowerCase() === 'online';
        const connectionLabel = d.connection_zaman
          ? (isOnline
            ? 'Oturum Başlangıç Zamanı: '
            : 'Bağlantı Kopma Zamanı: ')
          : '';

        // chech for debt 
        const numericDebt = parseFloat(
          (d.debt || '0').replace('₺', '').replace('.', '').replace(',', '.')
        );
        const hasDebt = numericDebt > 0;

        $('#chatbot_overview').html(`
      <div class="card shadow-sm">
        <div class="card-body p-2">
          <div class="row text-center align-items-center">

            <div class="col-3">
              <small class="text-muted">Bağlantı</small><br>
              <span class="badge ${isOnline ? 'badge-success' : 'badge-danger'}">
                ${d.connection_durum}
              </span>
            </div>

            <div class="col-3">
              <small class="text-muted">Borç</small><br>
              <strong class="${hasDebt ? 'text-danger' : 'text-success'}">
                ${d.debt}
              </strong>
            </div>

            <div class="col-3">
              <small class="text-muted">Tarife</small><br>
              <strong>${d.tarife}</strong>
            </div>

            <div class="col-3">
              ${d.payment_linki && hasDebt
          ? `<a href="${d.payment_linki}" target="_blank"
                     class="btn btn-sm btn-danger mt-1">
                     Ödeme Yap
                   </a>`
          : `<span class="text-success small">Borç Yok</span>`
        }
            </div>

          </div>

          <div class="row mt-2">
            <div class="col-12 text-center">
              <small class="text-muted">
                Abone: ${d.aboneNumarasi} •
               ${d.connection_zaman ? connectionLabel + d.connection_zaman : ''}
              </small>
            </div>
          </div>

        </div>
      </div>
    `);
      });
    }


    function setChatInputEnabled(enabled) {
      const input = $("#chatbot_input");
      const sendBtn = $("#chatbot_send");

      input.prop("disabled", !enabled);
      sendBtn.prop("disabled", !enabled);

      if (enabled) {
        input.focus();
      }
    }


    function addChatMessage(text, sender = "bot", branches = null) {
      const box = $("#chatbot_messages");

      const html = `
    <div class="chat-row ${sender}">
      <div class="chat-bubble ${sender}">
        ${text}
      </div>
    </div>
  `;

      box.append(html);

     
      if (branches) {
        setChatInputEnabled(false);

        const branchWrapper = $('<div class="branch-wrapper"></div>');

        branches.forEach(branch => {
          if (branch.match) {
            const label = branch.match[0];

            branchWrapper.append(`
          <button class="chatbot-branch-btn" data-value="${label}">
            ${label}
          </button>
        `);
          }
        });

        box.append(branchWrapper);
      } else {
        setChatInputEnabled(true);
      }

      box.scrollTop(box[0].scrollHeight);
    }

    
    $("#chatbot_send").on("click", function () {
      const input = $("#chatbot_input");
      const message = input.val().trim();
      if (!message) return;

      
      addChatMessage(message, "user");
      input.val("");

     
      $.post(
        "{{ route('internal_chatbot') }}",
        {
          message: message,
          _token: "{{ csrf_token() }}"
        },
        function (response) {
          addChatMessage(response.text, "bot", response.branches);

        }
      ).fail(function () {
        addChatMessage("Sunucuya ulaşılamadı.", "bot");
      });
    });

   
    $("#chatbot_input").on("keypress", function (e) {
      if (e.which === 13) {
        $("#chatbot_send").click();
      }
    });

    $(document).on("click", ".chatbot-branch-btn", function () {
      const value = $(this).data("value");

    
      addChatMessage(value, "user");

    
      $(".chatbot-branch-btn").remove();

     
      $.post(
        "{{ route('internal_chatbot') }}",
        {
          message: value,
          _token: "{{ csrf_token() }}"
        },
        function (response) {
          addChatMessage(response.text, "bot", response.branches);
        }
      );
    });


  </script>
@endpush
