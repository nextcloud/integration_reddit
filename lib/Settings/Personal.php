<?php
namespace OCA\Reddit\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Util;
use OCP\IURLGenerator;
use OCP\IInitialStateService;

use OCA\Reddit\AppInfo\Application;

require_once __DIR__ . '/../constants.php';

class Personal implements ISettings {

    private $request;
    private $config;
    private $dataDirPath;
    private $urlGenerator;
    private $l;

    public function __construct(
                        string $appName,
                        IL10N $l,
                        IRequest $request,
                        IConfig $config,
                        IURLGenerator $urlGenerator,
                        IInitialStateService $initialStateService,
                        $userId) {
        $this->appName = $appName;
        $this->urlGenerator = $urlGenerator;
        $this->request = $request;
        $this->l = $l;
        $this->config = $config;
        $this->initialStateService = $initialStateService;
        $this->userId = $userId;
    }

    /**
     * @return TemplateResponse
     */
    public function getForm() {
        $token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token', '');

        // for OAuth
        $clientID = $this->config->getAppValue(Application::APP_ID, 'client_id', DEFAULT_REDDIT_CLIENT_ID);
        $clientID = $clientID ? $clientID : DEFAULT_REDDIT_CLIENT_ID;
        $clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret', '') !== '';

        $userConfig = [
            'token' => $token,
            'client_id' => $clientID,
            'client_secret' => $clientSecret,
        ];
        $this->initialStateService->provideInitialState($this->appName, 'user-config', $userConfig);
        $response = new TemplateResponse(Application::APP_ID, 'personalSettings');
        return $response;
    }

    public function getSection() {
        return 'linked-accounts';
    }

    public function getPriority() {
        return 10;
    }
}
