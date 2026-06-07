<?php
/**
 * COBRAWA — Cliente da API WAHA
 * Cliente WAHA configurável pelo painel do sistema.
 */
class Waha {
    private string $server;
    private string $session;
    private string $apiKey;

    public function __construct(?array $override = null) {
        $cfg = $override ?: getWahaConfig();
        $server = trim((string)($cfg['servidor'] ?? ''));
        if ($server !== '' && !preg_match('~^https?://~i', $server)) {
            $server = 'http://' . $server;
        }
        $this->server  = rtrim($server ?: 'http://127.0.0.1:3000', '/');
        $this->session = trim((string)($cfg['sessao'] ?? 'default')) ?: 'default';
        $this->apiKey  = trim((string)($cfg['api_key'] ?? ''));
    }

    private function normalizePhone(string $chatId): string {
        if (str_contains($chatId, '@g.us')) return $chatId;
        $num = preg_replace('/\D/', '', $chatId);
        return $num . '@c.us';
    }

    private function request(string $method, string $endpoint, array $body = [], int $timeout = 20): array {
        if ($this->server === '') return ['ok' => false, 'error' => 'Servidor WAHA vazio'];
        if ($this->apiKey === '') return ['ok' => false, 'error' => 'API Key WAHA vazia'];

        $url = $this->server . $endpoint;
        $ch  = curl_init($url);
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Api-Key: ' . $this->apiKey,
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        ]);
        if (in_array(strtoupper($method), ['POST','PUT','PATCH','DELETE'], true) && !empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errNo = curl_errno($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($errNo) {
            return ['ok' => false, 'error' => "cURL {$errNo}: {$err}", '_url' => $url, '_http_code' => 0];
        }
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) $decoded = ['raw' => $raw];
        $decoded['_http_code'] = $code;
        $decoded['_url'] = $url;
        if ($code >= 400) {
            $msg = $decoded['message'] ?? $decoded['error'] ?? $raw ?? 'Erro HTTP';
            $decoded['ok'] = false;
            $decoded['error'] = "HTTP {$code}: {$msg}";
        }
        return $decoded;
    }

    /** Teste real da conexão: lista sessões e procura a sessão configurada. */
    public function test(): array {
        $sessions = $this->sessions();
        if (!empty($sessions['error'])) return $sessions;
        if (!is_array($sessions)) return ['ok' => false, 'error' => 'Resposta inválida da API WAHA'];

        $list = $sessions;
        if (isset($sessions['data']) && is_array($sessions['data'])) $list = $sessions['data'];
        foreach ($list as $s) {
            if (($s['name'] ?? $s['session'] ?? '') === $this->session) {
                return ['ok' => true, 'name' => $this->session, 'status' => $s['status'] ?? 'UNKNOWN', 'session' => $s];
            }
        }
        return ['ok' => false, 'error' => "Sessão '{$this->session}' não encontrada no WAHA", 'sessions' => $list];
    }

    /** Envia mensagem de texto */
    public function sendText(string $chatId, string $text): array {
        return $this->request('POST', '/api/sendText', [
            'session' => $this->session,
            'chatId'  => $this->normalizePhone($chatId),
            'text'    => $text,
        ]);
    }

    /** Envia arquivo/imagem via URL pública */
    public function sendFile(string $chatId, string $url, string $caption = '', string $filename = ''): array {
        return $this->request('POST', '/api/sendFile', [
            'session'  => $this->session,
            'chatId'   => $this->normalizePhone($chatId),
            'file'     => ['url' => $url, 'filename' => $filename ?: basename(parse_url($url, PHP_URL_PATH) ?: 'arquivo')],
            'caption'  => $caption,
        ]);
    }

    /** Envia áudio/voz via URL pública */
    public function sendAudio(string $chatId, string $url): array {
        return $this->request('POST', '/api/sendVoice', [
            'session' => $this->session,
            'chatId'  => $this->normalizePhone($chatId),
            'file'    => ['url' => $url],
        ]);
    }

    /** Obtém status da sessão */
    public function sessionStatus(): array {
        // Método mais compatível com sua versão WAHA: lista todas as sessões.
        $t = $this->test();
        if (!empty($t['ok'])) {
            return ['name' => $this->session, 'status' => $t['status'], 'session' => $t['session'], '_http_code' => 200];
        }

        // Fallback para versões que aceitam sessão individual.
        $r = $this->request('GET', '/api/sessions/' . rawurlencode($this->session));
        if (empty($r['error']) && (($r['name'] ?? $r['session'] ?? '') || ($r['status'] ?? ''))) return $r;
        return !empty($t['error']) ? $t : $r;
    }

    /** Lista todas as sessões */
    public function sessions(): array {
        return $this->request('GET', '/api/sessions');
    }

    /** Inicia sessão */
    public function startSession(): array {
        return $this->request('POST', '/api/sessions/' . rawurlencode($this->session) . '/start');
    }

    /** Para sessão */
    public function stopSession(): array {
        return $this->request('POST', '/api/sessions/' . rawurlencode($this->session) . '/stop');
    }

    /** Obtém QR Code da sessão */
    public function getQR(string $format = 'image'): array {
        return $this->request('GET', '/api/' . rawurlencode($this->session) . '/auth/qr?format=' . rawurlencode($format));
    }

    /** Marca mensagens como lidas */
    public function markSeen(string $chatId): array {
        return $this->request('POST', '/api/sendSeen', [
            'session' => $this->session,
            'chatId'  => $this->normalizePhone($chatId),
        ]);
    }

    /** Polling simples de mensagens novas, se disponível na versão WAHA instalada. */
    public function getRecentMessages(int $limit = 20): array {
        return $this->request('GET', "/api/messages?session=" . rawurlencode($this->session) . "&limit={$limit}");
    }
}
