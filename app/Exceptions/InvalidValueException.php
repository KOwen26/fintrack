<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

class InvalidValueException extends Exception
{
    public function __construct(
        string $message = 'Invalid Value',
        public ?string $errorCode = 'invalid_value',
        public ?array $meta = null,
        int $httpStatus = 422
    ) {
        parent::__construct($message, $httpStatus);
    }

    /**
     * Report the exception.
     */
    public function report(): void
    {
        logger()->debug("Invalid Value Exception: {$this->message}", [
            'message' => $this->message,
            'errorCode' => $this->errorCode,
            'meta' => $this->meta,
        ]);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'code' => $this->errorCode,
                'message' => $this->message,
            ], 422);
        }

        return back()->with([
            'type' => 'warning',
            'code' => $this->errorCode,
            'message' => $this->message,
        ]);
    }
}
