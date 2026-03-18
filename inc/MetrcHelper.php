<?php
/**
 * MetrcHelper — cURL wrapper for the Metrc Track-and-Trace API.
 *
 * Authentication:
 *   Metrc uses HTTP Basic Auth.  The username is the software/vendor API key
 *   (system-wide, stored in the METRC_VENDOR_KEY constant in _config.php).
 *   The password is the store-specific user API key (store.metrc_api_key).
 *   If METRC_VENDOR_KEY is not defined, the user key is used as the username
 *   and the password is left empty — works for integrations that issue a
 *   single combined key.
 *
 * Usage:
 *   $metrc = new MetrcHelper($apiKey, $licenseNumber);
 *   if (!$metrc->isConfigured()) { ... }
 *   $result = $metrc->getIncomingTransfers('2026-01-01T00:00:00Z', '2026-01-31T23:59:59Z');
 *   if ($result['success']) { $transfers = $result['data']; }
 *   else                    { $error = $result['error']; }
 */
class MetrcHelper {

    const BASE_URL   = 'https://api.metrc.com';
    const TIMEOUT    = 30;
    const MAX_RETRIES = 2;
    const RETRY_DELAY = 2; // seconds base for exponential back-off on 429

    private $userApiKey;
    private $licenseNumber;

    public function __construct($userApiKey, $licenseNumber) {
        $this->userApiKey    = trim((string)$userApiKey);
        $this->licenseNumber = trim((string)$licenseNumber);
    }

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Returns true when both credentials are present.
     */
    public function isConfigured() {
        return strlen($this->userApiKey) > 0 && strlen($this->licenseNumber) > 0;
    }

    /**
     * Fetch incoming (inbound) transfers for the configured license.
     *
     * @param string|null $lastModifiedStart  ISO-8601, e.g. "2026-01-01T00:00:00.000Z"
     * @param string|null $lastModifiedEnd    ISO-8601
     * @return array ['success'=>bool, 'data'=>array|null, 'error'=>string|null, 'http_code'=>int]
     */
    public function getIncomingTransfers($lastModifiedStart = null, $lastModifiedEnd = null) {
        $query = array('licenseNumber' => $this->licenseNumber);
        if ($lastModifiedStart) $query['lastModifiedStart'] = $lastModifiedStart;
        if ($lastModifiedEnd)   $query['lastModifiedEnd']   = $lastModifiedEnd;
        return $this->request('GET', '/transfers/v2/incoming', $query);
    }

    /**
     * Fetch the packages on a specific transfer.
     *
     * @param int $transferId  The Id from the incoming transfer list.
     * @return array
     */
    public function getTransferDeliveries($transferId) {
        return $this->request('GET', '/transfers/v2/' . (int)$transferId . '/deliveries');
    }

    /**
     * Fetch packages within a delivery.
     *
     * @param int $deliveryId
     * @return array
     */
    public function getDeliveryPackages($deliveryId) {
        $query = array('licenseNumber' => $this->licenseNumber);
        return $this->request('GET', '/transfers/v2/delivery/' . (int)$deliveryId . '/packages', $query);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Builds the HTTP Basic Auth header value.
     * Uses METRC_VENDOR_KEY as username if defined, otherwise uses the
     * user API key as username with an empty password.
     */
    private function buildAuthHeader() {
        $vendorKey = defined('METRC_VENDOR_KEY') ? METRC_VENDOR_KEY : '';
        if (strlen($vendorKey) > 0) {
            $credentials = $vendorKey . ':' . $this->userApiKey;
        } else {
            $credentials = $this->userApiKey . ':';
        }
        return 'Basic ' . base64_encode($credentials);
    }

    /**
     * Core cURL dispatcher.  Handles 429 rate-limit retries automatically.
     *
     * @param string $method   'GET' | 'POST'
     * @param string $endpoint e.g. '/transfers/v2/incoming'
     * @param array  $query    Query-string parameters (GET) or body (POST)
     * @return array ['success', 'data', 'error', 'http_code']
     */
    private function request($method, $endpoint, $query = array()) {
        $url = self::BASE_URL . $endpoint;
        if ($method === 'GET' && !empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => array(
                'Authorization: ' . $this->buildAuthHeader(),
                'Content-Type: application/json',
                'Accept: application/json',
            ),
        ));

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($query)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query));
            }
        }

        $attempt = 0;
        while (true) {
            $body     = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);

            // Hard cURL failure (network, TLS, timeout…)
            if ($body === false || $curlErr) {
                curl_close($ch);
                return array(
                    'success'   => false,
                    'http_code' => 0,
                    'error'     => 'Connection error: ' . ($curlErr ?: 'curl_exec returned false'),
                    'data'      => null,
                );
            }

            // 429 Rate Limit — back off and retry
            if ($httpCode === 429 && $attempt < self::MAX_RETRIES) {
                $attempt++;
                sleep(self::RETRY_DELAY * $attempt);
                continue;
            }

            curl_close($ch);
            return $this->parseResponse($httpCode, $body);
        }
    }

    /**
     * Converts a raw HTTP response into the standard result array.
     */
    private function parseResponse($httpCode, $body) {
        $decoded = json_decode($body, true);
        $success = ($httpCode >= 200 && $httpCode < 300);

        if ($success) {
            return array(
                'success'   => true,
                'http_code' => $httpCode,
                'error'     => null,
                'data'      => is_array($decoded) ? $decoded : array(),
            );
        }

        // Build a human-readable error message
        switch ($httpCode) {
            case 0:
                $error = 'No response received from Metrc. Check server connectivity.';
                break;
            case 401:
                $error = 'Metrc API key is invalid or expired. Please update Store Settings.';
                break;
            case 403:
                $error = 'Access forbidden. Verify the API key has permission for this license number.';
                break;
            case 404:
                $error = 'Metrc endpoint not found (HTTP 404). The license number may be incorrect.';
                break;
            case 429:
                $error = 'Metrc API rate limit exceeded. Please wait a moment and try again.';
                break;
            case 500:
            case 502:
            case 503:
                $error = 'Metrc API server error (HTTP ' . $httpCode . '). Try again shortly.';
                break;
            default:
                if (isset($decoded['Message']) && strlen($decoded['Message'])) {
                    $error = 'Metrc error: ' . $decoded['Message'];
                } elseif (isset($decoded['message']) && strlen($decoded['message'])) {
                    $error = 'Metrc error: ' . $decoded['message'];
                } else {
                    $error = 'Metrc API returned HTTP ' . $httpCode . '.';
                }
        }

        return array(
            'success'   => false,
            'http_code' => $httpCode,
            'error'     => $error,
            'data'      => null,
        );
    }
}
