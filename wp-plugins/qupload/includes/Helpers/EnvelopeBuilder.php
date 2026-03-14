<?php
/**
 * EnvelopeBuilder — Universal response envelope builder for QUpload.
 *
 * @package QUpload\Helpers
 * @since   1.0.0
 */

namespace QUpload\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use WP_REST_Response;

use QUpload\Enums\HttpStatusType;

class EnvelopeBuilder {
    private bool $isSuccess = true;
    private int $code = 200;
    private string $message = 'OK';
    private array $results = [];
    private string $requestedAt = '';
    private ?array $errors = null;

    public static function success(string $message = 'OK', int $code = HttpStatusType::Ok->value): static {
        $builder = new self();
        $builder->isSuccess = true;
        $builder->code = $code;
        $builder->message = $message;

        return $builder;
    }

    public static function error(
        string $message,
        int $code = HttpStatusType::ServerError->value,
        ?Throwable $exception = null,
    ): static {
        $builder = new self();
        $builder->isSuccess = false;
        $builder->code = $code;
        $builder->message = $message;

        $errors = ['BackendMessage' => $message, 'Backend' => []];

        if ($exception instanceof Throwable) {
            $errors['Backend'] = explode("\n", $exception->getTraceAsString());
        }

        $builder->errors = $errors;

        return $builder;
    }

    public function setSingleResult(array $item): static {
        $this->results = [$item];

        return $this;
    }

    public function setListResult(array $items): static {
        $this->results = $items;

        return $this;
    }

    public function setRequestedAt(string $path): static {
        $this->requestedAt = $path;

        return $this;
    }

    public function toResponse(): WP_REST_Response {
        $envelope = [
            'Status' => [
                'IsSuccess' => $this->isSuccess,
                'IsFailed'  => !$this->isSuccess,
                'Code'      => $this->code,
                'Message'   => $this->message,
                'Timestamp' => DateHelper::nowUtc(),
            ],
            'Attributes' => [
                'RequestedAt' => $this->requestedAt,
                'TotalRecords' => count($this->results),
            ],
            'Results' => $this->results,
        ];

        if ($this->errors !== null) {
            $envelope['Errors'] = $this->errors;
        }

        return new WP_REST_Response($envelope, $this->code);
    }
}
