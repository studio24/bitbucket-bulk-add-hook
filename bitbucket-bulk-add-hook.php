<?php
/**
 * Bulk add a POST webhook to all your Bitbucket repositories
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

$numRepos = 0;
$updatedRepos = 0;
$whitelistedRepos = 0;
$blacklistedRepos = 0;

// Help
if (isset($argv[1]) && in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
    echo <<<EOD
Bulk add a POST hook to all repositories on your Bitbucket account

I use this to bulk add the Slack webhook to all our Bitbucket URLs

Usage: php bitbucket-bulk-add-hook.php

Script will ask for your Bitbucket login details and the URL of the
POST hook you want to add.

MIT License (MIT)
Copyright (c) 2014-2016 Studio 24 Ltd (www.studio24.net)

EOD;
    exit();
}

echo PHP_EOL . "Bulk add a POST hook to all repositories on your Bitbucket account" . PHP_EOL;
echo "------------------------------------------------------------------" . PHP_EOL . PHP_EOL;

// Defaults (add values to auto-set these properties)
// define('BITBUCKET_ACCOUNT', '');
// define('BITBUCKET_POST_HOOK_URL', '');
// define('BITBUCKET_USERNAME', '');
// define('BITBUCKET_PASSWORD', '');
// define('BITBUCKET_WHITELIST', '');
// define('BITBUCKET_BLACKLIST', '');

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
if (defined('BITBUCKET_WHITELIST') && BITBUCKET_WHITELIST != '') {
    $whitelist = BITBUCKET_WHITELIST;
} else {
    echo "Enter pattern for whitelisting (Press enter to disable whitelisting): ";
    $whitelist = trim(fgets(STDIN));
}
if (defined('BITBUCKET_BLACKLIST') && BITBUCKET_BLACKLIST != '') {
    $blacklist = BITBUCKET_BLACKLIST;
} else {
    echo "Enter pattern for blacklisting (Press enter to disable blacklisting): ";
    $blacklist = trim(fgets(STDIN));
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

echo "Success! Found ".count($repos->values)." repositories inside $account account.\n\n";

$repoPattern = '!https://bitbucket.org/\!api/2.0/repositories/' . $account . '/(.+)/hooks$!';
foreach ($repos->values as $repo) {
    if (preg_match($repoPattern, $repo->links->hooks->href, $m)) {
        $url = $m[1];
    } else {
        echo "Cannot match URL from " . $repo->links->hooks->href . PHP_EOL;
        continue;
    }

    // Count this repo
    $numRepos++;

    if ( $blacklist != "" ) {
      if ( preg_match("/$blacklist/", $repo->slug )) {
        echo "Skipping repo ".$repo->slug." because is blacklisted\n";
        $blacklistedRepos++;
        continue;
      } 
    }
    
    if ( $whitelist != "" ) {
      if ( preg_match("/$whitelist/", $repo->slug )) {
        $whitelistedRepos++;
      } else {
        echo "Skipping repo ".$repo->slug." because does not match whitelist pattern\n";
        continue;
      }
    }
    
    echo "Testing repository: $url\n";

    $hooks = $api->getRepositoryWebHooks($account, $url);
    $slackIntegrated = false;
    if (!empty($hooks)) {
        foreach ($hooks->values as $hook) {
            if ($hook->url == $hookUrl) {
                $slackIntegrated = true;
            }
        }
    }

    if (!$slackIntegrated) {
        echo "Adding web hook to $hookUrl\n";

        $api_response = $api->createWebHook($account, $url, $hookUrl);

        if (is_null($api_response) || !$api_response || $api_response->url != $hookUrl) {
            echo "Failed to add web hook!\n";
        } else {
            // Count the repo update
            $updatedRepos++;

            echo "Added web hook\n";
        }
    } else {
        echo "Already integrated with the web hook\n";
    }
    echo "\n";
}

echo "All done!\n\n";

echo "Repo Report\n===========\n\nTotal Repos: ".$numRepos."\n-----------------\nUpdated: ".$updatedRepos."\n";
if ($blacklist != "") echo "Blacklisted: ".$blacklistedRepos."\n";
if ($whitelist != "") echo "Whitelisted: ".$whitelistedRepos."\n";
echo "\n";

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
     * Return all web hooks set up for a repository
     *
     * @param string $account
     * @param string $repository
     * @return bool|mixed Array of response data, or false on failure
     */
    public function getRepositoryWebHooks($account, $repository)
    {
        return $this->get(sprintf('https://api.bitbucket.org/2.0/repositories/%s/%s/hooks', $account, $repository));
    }

    /**
     * Create a new web hook for a repository
     *
     * @param string $account
     * @param string $repository
     * @param string $hookUrl
     * @return bool|mixed Array of response data, or false on failure
     */
    public function createWebHook($account, $repository, $hookUrl)
    {
        $params = array(
            'description' => 'Slack integration',
            'url'  => $hookUrl,
            'active' => true,
            'events' => [
                'repo:push',
                'repo:fork',
                'repo:commit_comment_created',
                'repo:commit_status_created',
                'repo:commit_status_updated',
                'issue:created',
                'issue:updated',
                'issue:comment_created',
                'pullrequest:created',
                'pullrequest:updated',
                'pullrequest:approved',
                'pullrequest:unapproved',
                'pullrequest:fulfilled',
                'pullrequest:rejected',
                'pullrequest:comment_created',
                'pullrequest:comment_updated',
                'pullrequest:comment_deleted'
            ]
        );
        return $this->post(sprintf('https://api.bitbucket.org/2.0/repositories/%s/%s/hooks', $account, $repository), $params);
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

        $httpResponseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpResponseCode !== 200) {
            throw new Exception("Cannot make request, HTTP status code " . $httpResponseCode);
        }

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
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));

        // Run query
        echo "Posting to $url ...\n";
        $response = curl_exec($curl);
        if ($response !== false) {
            return json_decode($response);
        }
        return false;
    }

}
