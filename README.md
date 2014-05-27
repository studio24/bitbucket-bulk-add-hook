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

MIT License (MIT)
Copyright (c) 2014 Studio 24 Ltd (www.studio24.net)
