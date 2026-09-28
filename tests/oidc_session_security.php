<?php

require __DIR__ . '/../vendor/autoload.php';

putenv('OIDC_ISSUER=https://id.example.org');
putenv('FAVED_PUBLIC_HTTPS=1');
if (!Config::oidcEnabled() || Config::getSessionLifetime() !== 28800 ||
    Config::getSessionCookieName() === 'faved-session') {
    throw new RuntimeException('SSO session configuration failed');
}

Framework\startSession();
if (!session_get_cookie_params()['secure']) {
    throw new RuntimeException('HTTPS session cookie was not enabled');
}
$before = session_id();
Framework\loginUser(1);
if (session_id() === $before) {
    throw new RuntimeException('Login did not rotate session ID');
}
$before = session_id();
Framework\logoutUser();
if (session_id() === $before || isset($_SESSION['user_id'])) {
    throw new RuntimeException('Logout did not clear and rotate session');
}
echo "OIDC session security: PASS\n";
