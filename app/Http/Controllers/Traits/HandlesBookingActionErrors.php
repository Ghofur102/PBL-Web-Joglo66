<?php

namespace App\Http\Controllers\Traits;

use DomainException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

trait HandlesBookingActionErrors
{
    protected function respondToBookingAction(callable $action, string $failureMessage): JsonResponse
    {
        try {
            return $action();
        } catch (HttpException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], $exception->getStatusCode());
        } catch (DomainException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 409);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $failureMessage.$exception->getMessage()], 500);
        }
    }
}
