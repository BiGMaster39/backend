<?php namespace App\Libraries;

class GitHub
{
    private $token;
    private $username;
    private $repo;
    private $branchName;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $settings = new Settings();
        $this->token = $settings->get_config("github_token");
        $this->username = $settings->get_config("github_username");
        $this->repo = $settings->get_config("github_repo");
        $this->branchName = "main";
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Create new branch for new app
     * @param string $name
     * @return array
     */
    public function create_branch(string $name): array
    {
        $target = $this->get_sha_repo();
        if (!$target["event"]) {
            return [
                'event'   => false,
                'message' => [
                    'error' => lang("Message.message_25")
                ]
            ];
        }
        $data = [
            "ref" => "refs/heads/".$name,
            "sha" => $target['sha']
        ];
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, 'https://api.github.com/repos/'.$this->username.'/'.$this->repo.'/git/refs');
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_POST, 1 );
        curl_setopt($handle, CURLOPT_HTTPHEADER, [
            "authorization: token $this->token",
            "User-Agent: SiteNative Server"
        ]);
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($data));
        $final = curl_exec($handle);
        $response = json_decode($final, true);
        curl_close($handle);
        if (!empty($response['ref'])) {
            return ['event' => true];
        } else {
            return [
                'event'   => false,
                'message' => [
                    'error' => lang("Message.message_26")
                ]
            ];
        }
    }

    /**
     * Create new commit
     * @param string $branch
     * @param string $path
     * @param $content
     * @return array
     */
    public function create_commit(string $branch, string $path, $content): array
    {
        $hash = $this->get_sha_file($path, $branch);
        if (!$hash["event"]) {
            return [
                'event'   => false,
                'message' => [
                    'error'  => lang("Message.message_28"),
                    'detail' => $hash
                ]
            ];
        }
        $data = [
            "message" => "update for file ".$path,
            "branch"  => $branch,
            "content" => base64_encode($content),
            "path"    => $path,
            "sha"     => $hash["sha"]
        ];
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, 'https://api.github.com/repos/'.$this->username.'/'.$this->repo.'/contents/'.$path);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_HTTPHEADER, [
            "authorization: token $this->token",
            "User-Agent: SiteNative Server"
        ]);
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($data));
        $final = curl_exec($handle);
        $response = json_decode($final, true);
        if (!empty($response['content'])) {
            return ['event' => true];
        } else {
            return [
                'event'   => false,
                'message' => [
                    'error'  => lang("Message.message_27"),
                    'detail' => $response,
                    'hash'   => $hash
                ]
            ];
        }
    }

    /**
     * Delete file
     * @param string $branch
     * @param string $path
     * @return array
     */
    public function delete_file(string $branch, string $path): array
    {
        $hash = $this->get_sha_file($path, $branch);
        if (!$hash["event"]) {
            return [
                'event'   => false,
                'message' => [
                    'error'  => lang("Message.message_28"),
                    'detail' => $hash
                ]
            ];
        }
        $data = [
            "message" => "delete file ".$path,
            "branch"  => $branch,
            "path"    => $path,
            "sha"     => $hash["sha"]
        ];
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, 'https://api.github.com/repos/'.$this->username.'/'.$this->repo.'/contents/'.$path);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_HTTPHEADER, [
            "authorization: token $this->token",
            "User-Agent: SiteNative Server"
        ]);
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($data));
        $final = curl_exec($handle);
        $response = json_decode($final, true);
        if (!empty($response['commit'])) {
            return ['event' => true];
        } else {
            return [
                'event'   => false,
                'message' => [
                    'error'  => lang("Message.message_33"),
                    'detail' => $response,
                    'hash'   => $hash
                ]
            ];
        }
    }

    /**
     * Upload new file
     * @param string $branch
     * @param string $path
     * @param $content
     * @return array
     */
    public function upload_commit(string $branch, string $path, $content): array
    {
        $data = [
            "message" => "update for file ".$path,
            "branch"  => $branch,
            "content" => base64_encode($content),
            "path"    => $path
        ];
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, 'https://api.github.com/repos/'.$this->username.'/'.$this->repo.'/contents/'.$path);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_HTTPHEADER, [
            "authorization: token $this->token",
            "User-Agent: SiteNative Server"
        ]);
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($data));
        $final = curl_exec($handle);
        $response = json_decode($final, true);
        if (!empty($response['commit'])) {
            return ['event' => true];
        } else {
            return [
                'event'   => false,
                'message' => [
                    'error'  => lang("Message.message_27"),
                    'detail' => $response,
                ]
            ];
        }
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get Sha for file
     * @param string $path
     * @param string $branch
     * @return array|false[]
     */
    private function get_sha_file(string $path, string $branch): array
    {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, 'https://api.github.com/repos/'.$this->username.'/'.$this->repo.'/contents/'.$path.'?ref='.$branch);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_HTTPHEADER, [
            "authorization: token $this->token",
            "User-Agent: SiteNative Server"
        ]);
        $final = curl_exec($handle);
        $response = json_decode($final, true);
        curl_close($handle);
        if (!empty($response)) {
            $result = [
                'event'  => true,
                'sha'    => $response['sha'],
                'detail' => $response
            ];
        } else {
            $result = [
                'event' => false
            ];
        }
        return $result;
    }

    /**
     * Get Sha hash for repo
     * @return array|false[]
     */
    private function get_sha_repo(): array
    {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, 'https://api.github.com/repos/'.$this->username.'/'.$this->repo.'/git/refs/heads/'.$this->branchName);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_HTTPHEADER, [
            "authorization: token $this->token",
            "User-Agent: SiteNative Server"
        ]);
        $final = curl_exec($handle);
        $response = json_decode($final, true);
        curl_close($handle);
        if (!empty($response['object'])) {
            $result = [
                'event' => true,
                'sha'   => $response['object']['sha']
            ];
        } else {
            $result = [
                'event' => false
            ];
        }
        return $result;
    }
}