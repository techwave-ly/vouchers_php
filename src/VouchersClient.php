<?php

namespace Commerce\Vouchers;

use Commerce\Vouchers\Exceptions\APIError;

class VouchersClient
{
    private string $apiKeyId;
    private string $apiSecret;
    private string $baseUrl;
    private bool $verifySsl;

    /**
     * Initialize the PHP client for B2B API integrations.
     * 
     * @param string $apiKeyId X-Api-Key-Id provided by Admin dashboard
     * @param string $apiSecret X-Api-Secret provided by Admin dashboard
     * @param string $baseUrl URL to the target environment
     * @param bool $verifySsl Set to false to bypass SSL checks (strictly for local dev)
     */
    public function __construct(string $apiKeyId, string $apiSecret, string $baseUrl, bool $verifySsl = true)
    {
        if (empty($apiKeyId)) throw new \InvalidArgumentException('apiKeyId is required');
        if (empty($apiSecret)) throw new \InvalidArgumentException('apiSecret is required');
        if (empty($baseUrl)) throw new \InvalidArgumentException('baseUrl is required');

        $this->apiKeyId = $apiKeyId;
        $this->apiSecret = $apiSecret;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->verifySsl = $verifySsl;
    }

    protected function generateIdempotencyKey(): string
    {
        if (function_exists('random_bytes')) {
            $data = random_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }
        return uniqid('vouch_', true);
    }

    protected function request(string $method, string $endpoint, ?array $payload = null, ?string $idempotencyKey = null): mixed
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $idemKey = $idempotencyKey ?? $this->generateIdempotencyKey();

        $headers = [
            'X-Api-Key-Id: ' . $this->apiKeyId,
            'X-Api-Secret: ' . $this->apiSecret,
            'X-Idempotency-Key: ' . $idemKey,
            'Accept: application/json',
        ];

        $ch = curl_init();
        if ($ch === false) {
            throw new APIError('Failed to initialize local cURL', 0);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if (!$this->verifySsl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        if ($payload !== null) {
            $jsonPayload = json_encode($payload);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false) {
            throw new APIError('Network error: ' . $error, 0);
        }

        $decodedBody = json_decode((string)$response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decodedBody = $response;
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $errorMessage = 'Unknown Error';
            if (is_array($decodedBody) && isset($decodedBody['error'])) {
                $errorMessage = $decodedBody['error'];
            } elseif (is_string($decodedBody) && !empty($decodedBody)) {
                $errorMessage = $decodedBody;
            }
            throw new APIError('API Request failed: ' . $errorMessage, $statusCode, $decodedBody);
        }

        return $decodedBody;
    }

    /**
     * Issue a single voucher.
     * 
     * @param float $amount The voucher value (e.g., 100 for 100 LYD).
     * @param array $options Optional keys: campaignId, expiresAt, idempotencyKey
     * @return mixed Response payload
     */
    public function issueVoucher(float $amount, array $options = []): mixed
    {
        $payload = [
            'amount' => $amount,
            'currency' => 'LYD',
        ];

        if (isset($options['campaignId'])) $payload['campaignId'] = $options['campaignId'];
        if (isset($options['expiresAt'])) $payload['expiresAt'] = $options['expiresAt'];

        return $this->request('POST', '/api/partner/v1/vouchers/issue', $payload, $options['idempotencyKey'] ?? null);
    }

    /**
     * Issue multiple vouchers at once (maximum 1000).
     * 
     * @param float $amount The voucher value (e.g., 100 for 100 LYD).
     * @param int $count Total number of vouchers to generate instantly. Max 1000.
     * @param array $options Optional keys: campaignId, expiresAt, idempotencyKey
     * @return mixed Response payload
     */
    public function bulkIssueVouchers(float $amount, int $count, array $options = []): mixed
    {
        $payload = [
            'amount' => $amount,
            'currency' => 'LYD',
            'count' => $count,
        ];

        if (isset($options['campaignId'])) $payload['campaignId'] = $options['campaignId'];
        if (isset($options['expiresAt'])) $payload['expiresAt'] = $options['expiresAt'];

        return $this->request('POST', '/api/partner/v1/vouchers/bulk-issue', $payload, $options['idempotencyKey'] ?? null);
    }

    /**
     * Void an existing active voucher. Once voided, it becomes completely unredeemable.
     * 
     * @param string $voucherId
     * @param string|null $idempotencyKey
     * @return mixed Response payload
     */
    public function voidVoucher(string $voucherId, ?string $idempotencyKey = null): mixed
    {
        $payload = ['voucherId' => $voucherId];
        return $this->request('POST', '/api/partner/v1/vouchers/void', $payload, $idempotencyKey);
    }

    /**
     * Get the current life-cycle status of a voucher.
     * 
     * @param string $voucherId
     * @return mixed Response payload
     */
    public function getVoucherStatus(string $voucherId): mixed
    {
        return $this->request('GET', '/api/partner/v1/vouchers/' . urlencode($voucherId) . '/status');
    }

    /**
     * Switch the behavior mode of your Partner App between 'test' and 'live'.
     * Vouchers generated in 'test' mode cannot manipulate real financial ledgers.
     * 
     * @param string $mode 'test' or 'live'
     * @param string|null $idempotencyKey
     * @return mixed Response payload
     */
    public function switchMode(string $mode, ?string $idempotencyKey = null): mixed
    {
        if ($mode !== 'test' && $mode !== 'live') {
            throw new \InvalidArgumentException("Mode must be strictly 'test' or 'live'");
        }
        $payload = ['mode' => $mode];
        return $this->request('POST', '/api/partner/v1/mode', $payload, $idempotencyKey);
    }
}
