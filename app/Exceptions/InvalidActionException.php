<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

class InvalidActionException extends Exception
{
    public function __construct(
        string $message = 'Invalid Action',
        public ?string $errorCode = 'invalid_action',
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
        logger()->debug("Invalid Action Exception: {$this->message}", [
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
