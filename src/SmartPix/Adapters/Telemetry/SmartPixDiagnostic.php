<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry;

use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use MercadoPago\Woocommerce\Api\Http\TransportException;
use MercadoPago\Woocommerce\Api\Core\Exceptions\ApiException;
use Throwable;
use MercadoPago\Woocommerce\SmartPix\SmartPixDiagnosticException;

if (!defined('ABSPATH')) {
    exit;
}

/** Server-only diagnostics. Never serialize a request, payment proof or complete Order. */
final class SmartPixDiagnostic
{
    private const FIELDS = ['error', 'code', 'message', 'original_message', 'description', 'cause', 'errors', 'details', 'status', 'status_detail'];

    public static function response(HttpResponse $response, array $secrets = []): string
    {
        $data = $response->getData();
        $diagnostic = ['http_status' => $response->getStatus()];
        if (is_array($data)) {
            $diagnostic += self::fields($data);
        } elseif ($response->getStatus() >= 400 && $response->getBody() !== '') {
            $diagnostic['message'] = $response->getBody();
        }
        return self::message((string) json_encode($diagnostic, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), $secrets);
    }

    public static function exception(Throwable $error, array $secrets = []): string
    {
        if ($error instanceof SmartPixDiagnosticException) {
            return self::message($error->getMessage() . ': ' . $error->diagnosticMessage(), $secrets);
        }
        $message = $error instanceof TransportException ? $error->getDiagnosticMessage() : $error->getMessage();
        if ($error instanceof ApiException && $error->getOriginalMessage() !== null && $error->getOriginalMessage() !== '') {
            $message = $error->getOriginalMessage() . '; message=' . $message;
        }
        $code = $error instanceof TransportException ? $error->getTransportCode() : (string) $error->getCode();
        return self::message(get_class($error) . ' [' . $code . ']: ' . self::reason($message), $secrets);
    }

    /** Explain internal control-flow codes; preserve original external wording for other errors. */
    public static function reason(string $reason): string
    {
        $explanations = [
            'invalid_response' => 'response failed the Smart Pix contract checks',
            'operation_unresolved' => 'an earlier operation is not confirmed; a new dispatch is blocked',
            'operation_busy' => 'another worker holds the operation reservation',
            'operation_context_conflict' => 'the persisted operation belongs to different order/attempt facts',
            'operation_journal_invalid' => 'the persisted operation journal is malformed or exceeds its limit',
            'operation_ownership_lost' => 'the operation reservation changed before its result was saved',
            'operation_persistence_failed' => 'the operation journal could not be saved and verified',
            'operation_not_confirmed' => 'the provider result did not confirm the requested terminal state/refund total',
            'operation_limit' => 'the order reached the 1000-operation journal limit',
            'order_context_conflict' => 'WooCommerce order/attempt facts changed during the operation or could not be reloaded',
            'state_conflict' => 'the verified payment state does not permit the requested cancel/refund',
            'amount_conflict' => 'the requested refund exceeds the verified remaining refundable amount',
            'native_effect_not_confirmed' => 'WooCommerce did not confirm the expected native order status',
            'already_approved' => 'the payment was already approved; cancellation was not permitted',
            'refund_context_invalid' => 'the native WooCommerce refund could not be associated with a Smart Pix order',
        ];
        return isset($explanations[$reason]) ? $reason . ': ' . $explanations[$reason] : $reason;
    }

    /** Selected status tokens only; never include an Order, amount, reference or payer payload. */
    public static function statusFacts(array $data): string
    {
        $status = static function ($value): string {
            return is_string($value) && preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $value) === 1 ? $value : 'missing_or_invalid';
        };
        return 'order=' . $status($data['status'] ?? null) . '/' . $status($data['status_detail'] ?? null)
            . '; payment=' . $status($data['payments'][0]['status'] ?? null) . '/' . $status($data['payments'][0]['status_detail'] ?? null);
    }

    /** Preserve diagnostic wording; redact concrete credential/contact patterns before delivery. */
    public static function message(string $message, array $secrets = []): string
    {
        foreach ($secrets as $secret) {
            if (is_string($secret) && $secret !== '') {
                $message = str_replace($secret, '[REDACTED]', $message);
            }
        }
        // Bound redaction work as well as the final payload; error bodies are external input.
        $message = substr($message, 0, 16384);
        $patterns = [
            '/(?<![A-Z0-9._%+-])[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i' => '[REDACTED_EMAIL]',
            '/\b(?:APP_USR-|TEST-)[A-Za-z0-9_-]+/' => '[REDACTED]',
            '/\beyJ[A-Za-z0-9_-]*\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/' => '[REDACTED]',
            '/\b[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}\b/' => '[REDACTED]',
            '/\b[0-9]{11}\b/' => '[REDACTED_DOCUMENT]',
            '/\b((?:Proxy-)?Authorization\s*[:=]\s*)(?:\[REDACTED\]|[^\r\n]*)/i' => '$1[REDACTED]',
            '/\b((?:Set-)?Cookie\s*[:=]\s*)(?:\[REDACTED\]|[^\r\n]*)/i' => '$1[REDACTED]',
            '/\b(Bearer\s+)[A-Za-z0-9._~+\/-]+/i' => '$1[REDACTED]',
            '/(["\']?(?:access_token|token|pseudotoken|authorized_pseudotoken|open_finance_account_id|password|secret|api_key|cpf|document|email|payer|first_name|last_name|phone|address|card_number|security_code|cvv|session_id|order_key|authenticator)["\']?\s*[:=]\s*)(?:\[REDACTED(?:_[A-Z]+)?\]|"[^"\r\n]*"|\'[^\'\r\n]*\'|[^,;\s}\]&]+)/i' => '$1[REDACTED]',
        ];
        $message = (string) preg_replace(array_keys($patterns), array_values($patterns), $message);
        $message = (string) preg_replace('/[\x00-\x1f\x7f]/', ' ', $message);
        $message = (string) json_decode((string) json_encode($message, JSON_INVALID_UTF8_SUBSTITUTE), true);
        $message = substr($message, 0, 4096);
        while ($message !== '' && preg_match('//u', $message) !== 1) {
            $message = substr($message, 0, -1);
        }
        return $message;
    }

    /** @return array<string|int, mixed> */
    private static function fields(array $data, int $depth = 0): array
    {
        if ($depth >= 6) {
            return [];
        }
        $result = [];
        foreach (array_slice($data, 0, 30, true) as $key => $value) {
            if (!is_int($key) && !in_array($key, self::FIELDS, true)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $result[$key] = $value;
            } elseif (is_array($value)) {
                $result[$key] = self::fields($value, $depth + 1);
            }
        }
        return $result;
    }
}
