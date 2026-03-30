<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->resolveToken($request);

        if (! is_string($token) || $token === '') {
            return $this->unauthorizedResponse();
        }

        $apiKey = ApiKey::findByPlainToken($token);

        if (! $apiKey instanceof ApiKey || ! $apiKey->ownerUser instanceof User) {
            return $this->unauthorizedResponse();
        }

        $request->setUserResolver(static fn (): User => $apiKey->ownerUser);
        Auth::setUser($apiKey->ownerUser);

        return $next($request);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Unauthorized.',
        ], 401);
    }

    private function resolveToken(Request $request): ?string
    {
        $token = $request->input('token');

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = $request->query('token');

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = $request->header('X-Api-Key');

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $authorizationHeader = $request->header('Authorization');

        if (is_string($authorizationHeader) && str_starts_with($authorizationHeader, 'Bearer ')) {
            $bearerToken = substr($authorizationHeader, 7);

            if ($bearerToken !== '') {
                return $bearerToken;
            }
        }

        $rawRequestBody = $request->getContent();

        if (is_string($rawRequestBody) && $rawRequestBody !== '') {
            $decodedRequestBody = json_decode($rawRequestBody, true);

            if (is_array($decodedRequestBody) && isset($decodedRequestBody['token']) && is_string($decodedRequestBody['token']) && $decodedRequestBody['token'] !== '') {
                return $decodedRequestBody['token'];
            }
        }

        return null;
    }
}
