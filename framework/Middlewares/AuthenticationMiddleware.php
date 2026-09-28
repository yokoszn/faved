<?php

namespace Framework\Middlewares;

use Controllers\AuthLoginController;
use Controllers\AuthOidcController;
use Controllers\AuthOidcConfigController;
use Controllers\SetupDatabaseController;
use Controllers\UserGetController;
use Framework\Exceptions\DatabaseNotFound;
use Framework\Exceptions\UnauthorizedException;
use Framework\ServiceContainer;
use Models\Repository;
use function Framework\getLoggedInUser;

class AuthenticationMiddleware extends MiddlewareAbstract
{
	protected array $skip_auth_routes = [
		// API Auth routes
		AuthLoginController::class,
		AuthOidcController::class,
		AuthOidcConfigController::class,
	];

	public function handle()
	{
		$controller_class = $this->controller_class;
		// Skip authentication for specific routes
		if (in_array($controller_class, $this->skip_auth_routes)) {
			return $this->next && $this->next->handle();
		}
		if (\Config::oidcEnabled() && $controller_class === SetupDatabaseController::class) {
			return $this->next && $this->next->handle();
		}
		if (\Config::oidcEnabled()) {
			try {
				ServiceContainer::get(Repository::class);
			} catch (DatabaseNotFound $e) {
				if ($controller_class === UserGetController::class) {
					return $this->next && $this->next->handle();
				}
				throw new UnauthorizedException();
			}
			$allowedSubjects = array_filter(array_map('trim', explode(',', \Config::environment('OIDC_ALLOWED_SUBS'))));
			$subject = $_SESSION['oidc_sub'] ?? null;
			$expiresAt = $_SESSION['oidc_expires_at'] ?? 0;
			if (is_string($subject) && in_array($subject, $allowedSubjects, true) &&
				is_int($expiresAt) && $expiresAt > time() && getLoggedInUser()) {
				return $this->next && $this->next->handle();
			}
			throw new UnauthorizedException();
		}

		// If the database is not set up yet, skip authentication checks
		try {
			$repository = ServiceContainer::get(Repository::class);
		} catch (DatabaseNotFound $e) {
			return $this->next && $this->next->handle();
		}

		if (!$repository->checkDatabaseExists()) {
			return $this->next && $this->next->handle();
		}

		$auth_enabled = $repository->userTableNotEmpty();

		// If auth is disabled, skip authentication check
		if (!$auth_enabled) {
			return $this->next && $this->next->handle();
		}

		$user = getLoggedInUser();

		if ($user) {
			return $this->next && $this->next->handle();
		}

		throw new UnauthorizedException();
	}
}
