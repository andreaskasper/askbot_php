<?php
/**
 * page_oauth.php - start and finish an external sign in.
 *
 * @param array $params provider, callback
 */
i18n::init(__FILE__);

$provider = (string)$params["provider"];

try {
    if (empty($params["callback"])) {
        PageEngine::goto(OAuth::authorizeUrl($provider));
    }
    if (!empty($_GET["error"])) throw new \RuntimeException(__("The sign in was cancelled."));

    $user = OAuth::complete($provider, (string)($_GET["code"] ?? ""), (string)($_GET["state"] ?? ""));
    MyUser::login($user, true);
    PageEngine::AddSuccessMessage(__("Signed in."));
    PageEngine::goto(Url::safeReturn($_SESSION["return_to"] ?? null));
} catch (\Throwable $e) {
    PageEngine::AddErrorMessage($e->getMessage());
    PageEngine::goto(url("account/signin"));
}
