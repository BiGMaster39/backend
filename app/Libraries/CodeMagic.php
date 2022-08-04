<?php namespace App\Libraries;

class CodeMagic
{
    private $settings;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->settings = new Settings();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Create new app project
     * @return array
     */
    public function create(): array
    {
        $data = [
            "repositoryUrl" => 'git@github.com:'.$this->settings
                    ->get_config("github_username").'/'.$this->settings
                    ->get_config("github_repo").'.git',
            "sha"           => [
                "data"       => base64_decode(
                    file_get_contents(WRITEPATH.'sha/codemagic_ssh_key')
                ),
                "passphrase" => null
            ],
            "projectType"   => "flutter-app"
        ];
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, 'https://api.codemagic.io/apps/new');
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_POST, 1 );
        curl_setopt($handle, CURLOPT_HTTPHEADER, [
            "x-auth-token: ".$this->settings->get_config("codemagic_key")
        ]);
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($data));
        $final = curl_exec($handle);
        $response = json_decode($final, true);
        curl_close($handle);
        return $response;
    }

    /**
     * Create new build
     * @param string $workflow_id
     * @param string $app_uid
     * @return array
     */
    public function build(string $workflow_id, string $app_uid): array
    {
        $data = [
            "appId"      => $this->settings->get_config("codemagic_id"),
            "workflowId" => $workflow_id,
            "branch"     => $app_uid
        ];
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, 'https://api.codemagic.io/builds');
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_POST, 1 );
        curl_setopt($handle, CURLOPT_HTTPHEADER, [
            "x-auth-token: ".$this->settings->get_config("codemagic_key"),
            "Content-Type: application/json"
        ]);
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($data));
        $final = curl_exec($handle);
        $response = json_decode($final, true);
        if (!empty($response['buildId'])) {
            return [
                "event" => true,
                "id"    => $response['buildId']
            ];
        } else {
            return [
               "event"    => false,
               "message" => [
                   'error'  => lang("Message.message_45"),
               ]
            ];
        }
    }

    /**
     * Detail status build
     * @param string $build_id
     * @return array
     */
    public function status(string $build_id): array
    {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, 'https://api.codemagic.io/builds/'.$build_id);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_HTTPHEADER, [
            "x-auth-token: ".$this->settings->get_config("codemagic_key"),
            "Content-Type: application/json"
        ]);
        $final = curl_exec($handle);
        $response = json_decode($final, true);
        if (!empty($response['build'])) {
            return [
                "event" => true,
                "build" => $response['build']
            ];
        } else {
            return [
                "event"    => false,
                "message" => [
                    'error'  => lang("Message.message_47"),
                ]
            ];
        }
    }
}