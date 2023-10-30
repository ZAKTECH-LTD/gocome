<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;

class RateLimitOtpRequests extends ThrottleRequests
{
    public function handle($request, Closure $next, $maxAttempts = 5, $decayMinutes = 0.5, $prefix = '')
    {
        return parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
    }


    protected function buildResponse($key, $maxAttempts)
    {
        $response = new \Illuminate\Http\JsonResponse([
            'message' => __('You have exceeded the OTP request limit. You can retry again after 10 minutes.'),
        ], 429);

        //create a log entry here
        try {
            logger('OTP request limit exceeded', [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'route' => request()->route()->getName(),
                'route_params' => request()->route()->parameters(),
            ]);
        } catch (\Exception $e) {
            logger("OTP request limit exceeded log error", [$e]);
        }

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts),
            //retry after 10mins, 10 * 60 = 600
            600,
        );
    }

    protected function resolveRequestSignature($request)
    {
        return sha1($request->method() . '|' . $request->route()->getDomain() . '|' . $request->ip());
    }
}
