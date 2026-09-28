<?php

namespace Controllers;

use Framework\ControllerInterface;
use Framework\Exceptions\ForbiddenException;
use Framework\Responses\ResponseInterface;
use Framework\ServiceContainer;
use Jumbojett\OpenIDConnectClient;
use Models\Repository;
use function Framework\loginUser;
use function Framework\redirect;

class AuthOidcController implements ControllerInterface
{
	public function __invoke(array $input): ResponseInterface
	{
		if (!\Config::oidcEnabled()) {
			throw new ForbiddenException('SSO is not configured');
		}

		$issuer = rtrim(\Config::environment('OIDC_ISSUER'), '/');
		$redirect = \Config::environment('OIDC_REDIRECT_URI');
		$clientId = \Config::environment('OIDC_CLIENT_ID');
		$secretFile = \Config::environment('OIDC_CLIENT_SECRET_FILE');
		$clientSecret = is_file($secretFile) ? file_get_contents($secretFile) : false;
		$subjects = array_filter(array_map('trim', explode(',', \Config::environment('OIDC_ALLOWED_SUBS'))));
		if (!str_starts_with($issuer, 'https://') || !str_starts_with($redirect, 'https://') ||
			$clientId === '' || !is_string($clientSecret) || $clientSecret === '' || $subjects === []) {
			throw new ForbiddenException('SSO configuration is incomplete');
		}
		if (parse_url($redirect, PHP_URL_PATH) !== '/api/auth/oidc') {
			throw new ForbiddenException('SSO callback path is invalid');
		}

		$oidc = new OpenIDConnectClient($issuer, $clientId, $clientSecret);
		$oidc->setRedirectURL($redirect);
		$oidc->setCodeChallengeMethod('S256');
		$oidc->authenticate();
		$subject = $oidc->getVerifiedClaims('sub');
		if (!is_string($subject) || !in_array($subject, $subjects, true)) {
			throw new ForbiddenException('SSO account is not allowed');
		}

		$repository = ServiceContainer::get(Repository::class);
		$user = $repository->getFirstUser();
		if (!$user) {
			// Faved is a single-user application. The allowlisted identity owns the one account.
			$userId = (int)$repository->createUser('oidc_owner', password_hash(bin2hex(random_bytes(32)), \Config::getPasswordAlgo()));
		} else {
			$userId = (int)$user['id'];
		}
		loginUser($userId);
		$_SESSION['oidc_sub'] = $subject;
		$_SESSION['oidc_expires_at'] = time() + \Config::getSessionLifetime();
		return redirect('/');
	}
}
