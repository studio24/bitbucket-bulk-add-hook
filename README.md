# slack-webhook-bitbucket

A simple CLI script to setup POST web hooks in all your Bitbucket repositories.

I use this for adding Slack POST web hook to all my Bitbucket repos, but it can
be used for other POST web hooks too.

## Usage

Usage:

    php bitbucket-bulk-add-hook.php

Script will ask for your Bitbucket login details and the URL of the
POST hook you want to add. If you wish you can add these to the BITBUCKET_* constants
in the script.

## Two-factor authentication and App passwords

If you have Two-factor authentication enabled (which is a very good idea) you will need to generate an App Password in 
 Bitbucket and use this as the password with this script.

Go to Bitbucket Settings > Access management > App passwords.  

Make sure your app password has the following permissions:

* Projects: Read
* Pull Requests: Read
* Issues: Read
* Webhooks: Read and Write

Simply use the app password in place of your normal password for this script.

## License

MIT License (MIT), copyright Studio 24 Ltd (www.studio24.net)
