<?php

namespace Controllers;

use Framework\ControllerInterface;
use Framework\Responses\ResponseInterface;
use function Framework\data;

class AuthOidcConfigController implements ControllerInterface
{
	public function __invoke(array $input): ResponseInterface
	{
		return data(['enabled' => \Config::oidcEnabled()]);
	}
}
