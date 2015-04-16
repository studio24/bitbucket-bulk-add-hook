<?php
/**
 * Bulk add a POST webhook to all your Bitbucket repositories
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Help
if (isset($argv[1]) && in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
    echo <<<EOD
Bulk add a POST hook to all repositories on your Bitbucket account

I use this to bulk add the Slack webhook to all our Bitbucket URLs

Usage: php bitbucket-bulk-add-hook.php

Script will ask for your Bitbucket login details and the URL of the
POST hook you want to add.

MIT License (MIT)
Copyright (c) 2014 Studio 24 Ltd (www.studio24.net)

EOD;
    exit();
}

echo PHP_EOL . "Bulk add a POST hook to all repositories on your Bitbucket account" . PHP_EOL;
echo "------------------------------------------------------------------" . PHP_EOL . PHP_EOL;

// Defaults (add values to auto-set these properties)
//define('BITBUCKET_ACCOUNT', '');
//define('BITBUCKET_POST_HOOK_URL', '');
//define('BITBUCKET_USERNAME', '');
//define('BITBUCKET_PASSWORD', '');

// Get arguments
if (defined('BITBUCKET_ACCOUNT') && BITBUCKET_ACCOUNT != '') {
    $account = BITBUCKET_ACCOUNT;
} else {
    echo "Enter the Bitbucket account name you want to apply the web hook to: ";
    $account = trim(fgets(STDIN));
}
if (defined('BITBUCKET_POST_HOOK_URL') && BITBUCKET_POST_HOOK_URL != '') {
    $hookUrl = BITBUCKET_POST_HOOK_URL;
} else {
    echo "Enter your new POST web hook to apply to all repositories: ";
    $hookUrl = trim(fgets(STDIN));
}
if (defined('BITBUCKET_USERNAME') && BITBUCKET_USERNAME != '') {
    $username = BITBUCKET_USERNAME;
} else {
    echo "Enter your Bitbucket username: ";
    $username = trim(fgets(STDIN));
}
if (defined('BITBUCKET_PASSWORD') && BITBUCKET_PASSWORD != '') {
    $password = BITBUCKET_PASSWORD;
} else {
    echo "Enter your Bitbucket password: ";
    $password = trim(fgets(STDIN));
}

// Confirm details
echo <<<EOD

About to set up the following POST web hook:
$hookUrl

On the Bitbucket account: $account
Using the login username '$username'

Do you want to continue (y/n)?
EOD;

$response = '';
while (!in_array(trim(strtolower($response)), array('y', 'n', 'yes', 'no'))) {
    $response = trim(fgets(STDIN));
}

if (in_array(trim(strtolower($response)), array('n','no'))) {
    echo "Quitting script" . PHP_EOL;
    exit(0);
}

echo "Reading in list of repositories from Bitbucket repositories/$account\n\n";

$api = new BitBucketAPI($username, $password);
$repos = $api->listRepositories($account);
echo "\n";

$repoPattern = '!https://bitbucket.org/api/2.0/repositories/' . $account . '/(.+)/watchers$!';
foreach ($repos->values as $repo) {
    if (preg_match($repoPattern, $repo->links->watchers->href, $m)) {
        $url = $m[1];
    } else {
        echo "Cannot match URL from " . $repo->links->watchers->href . PHP_EOL;
        continue;
    }

    echo "Testing repository: $url\n";

    $services = $api->getRepositoryServices($account, $url);
    $slackIntegrated = false;
    if (!empty($services)) {
        foreach ($services as $service) {
            if ($service->service->type == 'POST') {
                foreach ($service->service->fields as $data) {
                    if ($data->value == $hookUrl) {
                        $slackIntegrated = true;
                    }
                }
            }
        }
    }

    if (!$slackIntegrated) {
        echo "Adding web hook to $hookUrl\n";
        if (!$api->createService($account, $url, $hookUrl)) {
            echo "Failed to add web hook!\n";
        }

    } else {
        echo "Already integrated with the web hook\n";
    }
    echo "\n";
}

echo "All done!\n\n";

/**
 * Class to send/receive data from BitBucket API
 *
 * @see https://confluence.atlassian.com/display/BITBUCKET/Use+the+Bitbucket+REST+APIs
 */
class BitBucketAPI {

    /**
     * Bitbucket username
     *
     * @var string
     */
    protected $username;

    /**
     * Bitbucket password
     *
     * @var string
     */
    protected $password;

    /**
     * Constructor
     *
     * @param string $username Bitbucket username
     * @param string $password Bitbucket password
     */
    public function __construct($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Return all repositories under an account
     *
     * @param string $account
     * @return bool|mixed Array of response data, or false on failure
     */
    public function listRepositories($account)
    {
        $data = $this->get(sprintf('https://bitbucket.org/api/2.0/repositories/%s', $account));
        $nextPage = (isset($data->next)) ? $data->next : null;

        // Get paginated data & merge with original data
        while (!empty($nextPage)) {
            $next = $this->get($nextPage);

            foreach ($next->values as $item) {
                $data->values[] = $item;
            }
            $nextPage = (isset($next->next)) ? $next->next : null;
        }

        return $data;
    }

    /**
     * Return all services setup for a repository
     *
     * @param string $account
     * @param string $repository
     * @return bool|mixed Array of response data, or false on failure
     */
    public function getRepositoryServices($account, $repository)
    {
        return $this->get(sprintf('https://api.bitbucket.org/1.0/repositories/%s/%s/services', $account, $repository));
    }

    /**
     * Create a new POST web hook service for a repository
     *
     * @param string $account
     * @param string $repository
     * @param string $hookUrl
     * @return bool|mixed Array of response data, or false on failure
     */
    public function createService($account, $repository, $hookUrl)
    {
        $params = array(
            'type' => 'POST',
            'URL'  => $hookUrl
        );
        return $this->post(sprintf('https://api.bitbucket.org/1.0/repositories/%s/%s/services', $account, $repository), $params);
    }

    protected function get($url, $params = array())
    {
        // Build URL params
        if (!empty($params)) {
            if (strpos($url, '?') === false) {
                $url . '?';
            } else {
                $url . '&';
            }
            $url .= http_build_query($params);
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($curl, CURLOPT_URL, $url);

        // Run query
        echo "Querying $url ...\n";
        $response = curl_exec($curl);
        if ($response !== false) {
            return json_decode($response);
        }
        return false;
    }

    protected function post($url, $params = array())
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));

        // Run query
        echo "Posting to $url ...\n";
        $response = curl_exec($curl);
        if ($response !== false) {
            return json_decode($response);
        }
        return false;
    }

}
