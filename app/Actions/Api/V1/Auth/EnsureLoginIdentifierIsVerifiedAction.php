<?php

namespace App\Actions\Api\V1\Auth;

use App\Enums\ApiErrorCode;
use App\Enums\LoginIdentifierType;
use App\Models\User;
use App\Services\Auth\AuthLoginConfiguration;
use App\Services\Auth\LoginIdentifierDetector;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class EnsureLoginIdentifierIsVerifiedAction
{
    public function __construct(
        protected AuthLoginConfiguration $authLogin,
        protected LoginIdentifierDetector $loginIdentifierDetector,
    ) {}

    public function handle(Request $request, User $user): void
    {
        $type = $this->resolveIdentifierType($request);

        if ($type === LoginIdentifierType::Email && $user->email_verified_at !== null) {
            return;
        }

        if ($type === LoginIdentifierType::Phone && $user->phone_verified_at !== null) {
            return;
        }

        if ($type === LoginIdentifierType::Username && $this->usernameBackedContactIsVerified($user)) {
            return;
        }

        throw new HttpResponseException(sendResponse(
            status: false,
            message: __('api.identifier_not_verified'),
            data: [
                'identifier_type' => $type->value,
            ],
            statusCode: HttpStatus::HTTP_FORBIDDEN,
            additional: ['code' => ApiErrorCode::IdentifierNotVerified->value]
        ));
    }

    private function resolveIdentifierType(Request $request): LoginIdentifierType
    {
        $explicit = LoginIdentifierType::tryFrom($request->string('identifier_type')->toString());
        if ($explicit !== null) {
            return $explicit;
        }

        $allowed = $this->authLogin->loginIdentifierTypes();

        if (count($allowed) === 1) {
            return $allowed[0];
        }

        $rawIdentifier = LoginIdentifierDetector::rawCredentialStringFromRequest($request, true);
        [$type] = $this->loginIdentifierDetector->resolve(null, $rawIdentifier, $allowed);

        return $type;
    }

    private function usernameBackedContactIsVerified(User $user): bool
    {
        if ($user->email !== null && $user->email !== '') {
            return $user->email_verified_at !== null;
        }

        if ($user->phone !== null && $user->phone !== '') {
            return $user->phone_verified_at !== null;
        }

        return false;
    }
}
